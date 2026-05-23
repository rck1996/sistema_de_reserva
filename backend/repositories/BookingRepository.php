<?php

declare(strict_types=1);

final class BookingRepository
{
    private const ACTIVE_STATUSES = array('pending', 'confirmed', 'in_progress');
    private const VALID_STATUSES = array('pending', 'confirmed', 'in_progress', 'completed', 'no_show', 'cancelled');

    public function __construct(private PDO $pdo)
    {
    }

    public function listByCompany(string $companyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT bookings.id, bookings.company_id, bookings.customer_id, bookings.professional_id,
                    bookings.service_id, bookings.starts_at, bookings.ends_at, bookings.status,
                    bookings.notes, bookings.created_at,
                    customers.first_name AS customer_first_name, customers.last_name AS customer_last_name,
                    customers.email AS customer_email,
                    professionals.name AS professional_name, professionals.calendar_color AS professional_color,
                    services.name AS service_name, services.price AS service_price,
                    services.duration_minutes AS service_duration_minutes
             FROM bookings
             JOIN customers ON customers.id = bookings.customer_id AND customers.company_id = bookings.company_id
             JOIN professionals ON professionals.id = bookings.professional_id AND professionals.company_id = bookings.company_id
             JOIN services ON services.id = bookings.service_id AND services.company_id = bookings.company_id
             WHERE bookings.company_id = :company_id
             ORDER BY bookings.starts_at ASC'
        );
        $statement->execute(array(':company_id' => $companyId));

        return $statement->fetchAll();
    }

    public function create(string $companyId, array $data): array
    {
        $this->pdo->beginTransaction();

        try {
            $customer = $this->tenantRow('customers', $companyId, $data['customer_id'], 'Cliente no pertenece a la empresa.');
            $professional = $this->tenantRow('professionals', $companyId, $data['professional_id'], 'Profesional no pertenece a la empresa.');
            $service = $this->tenantRow('services', $companyId, $data['service_id'], 'Servicio no pertenece a la empresa.');

            if (!(bool) $customer['is_active']) {
                throw new InvalidArgumentException('Cliente inactivo.');
            }
            if (!(bool) $professional['is_active']) {
                throw new InvalidArgumentException('Profesional inactivo.');
            }
            if (!(bool) $service['is_active']) {
                throw new InvalidArgumentException('Servicio inactivo.');
            }
            if (!$this->professionalCanProvideService($companyId, $data['professional_id'], $data['service_id'])) {
                throw new InvalidArgumentException('El profesional no realiza este servicio.');
            }

            $startsAt = $this->parseStartDate($data['starts_at']);
            $duration = $this->effectiveDuration($companyId, $data['professional_id'], $data['service_id'], (int) $service['duration_minutes']);
            $endsAt = (clone $startsAt)->modify('+' . $duration . ' minutes');

            $this->assertProfessionalAvailability($companyId, $data['professional_id'], $startsAt, $endsAt);
            $this->assertNoOverlap($companyId, $data['professional_id'], $startsAt, $endsAt);

            $status = in_array($data['status'], self::VALID_STATUSES, true) ? $data['status'] : 'pending';
            $price = $this->effectivePrice($companyId, $data['professional_id'], $data['service_id'], (string) $service['price']);
            $statement = $this->pdo->prepare(
                'INSERT INTO bookings (company_id, customer_id, customer_profile_id, company_customer_id, professional_id, service_id, starts_at, ends_at, status, notes, final_price, price_source)
                 VALUES (:company_id, :customer_id, :customer_profile_id, :company_customer_id, :professional_id, :service_id, :starts_at, :ends_at, :status, :notes, :final_price, :price_source)
                 RETURNING id'
            );
            $statement->execute(array(
                ':company_id' => $companyId,
                ':customer_id' => $data['customer_id'],
                ':customer_profile_id' => $data['customer_profile_id'] ?? null,
                ':company_customer_id' => $data['company_customer_id'] ?? null,
                ':professional_id' => $data['professional_id'],
                ':service_id' => $data['service_id'],
                ':starts_at' => $startsAt->format(DateTimeInterface::ATOM),
                ':ends_at' => $endsAt->format(DateTimeInterface::ATOM),
                ':status' => $status,
                ':notes' => $data['notes'],
                ':final_price' => $price['amount'],
                ':price_source' => $price['source'],
            ));

            $bookingId = (string) $statement->fetchColumn();
            $this->pdo->commit();

            return $this->findByCompany($companyId, $bookingId);
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function createFromMarketplaceCustomer(string $userId, array $data): array
    {
        $company = $this->companyBySlug($data['company_slug']);
        $profile = $this->customerProfileForUser($userId);
        $membership = $this->activeCompanyCustomer((string) $company['id'], (string) $profile['id']);
        $localCustomer = $this->ensureLocalCustomer((string) $company['id'], $userId, $profile);

        return $this->create((string) $company['id'], array(
            'customer_id' => $localCustomer['id'],
            'customer_profile_id' => $profile['id'],
            'company_customer_id' => $membership['id'],
            'professional_id' => $data['professional_id'],
            'service_id' => $data['service_id'],
            'starts_at' => $data['starts_at'],
            'status' => 'pending',
            'notes' => $data['notes'] ?? 'Reserva creada desde marketplace.',
        ));
    }

    public function updateStatus(string $companyId, string $bookingId, string $status): array
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException('Estado de reserva no valido.');
        }

        $statement = $this->pdo->prepare(
            'UPDATE bookings
             SET status = :status, updated_at = NOW()
             WHERE company_id = :company_id AND id = :id
             RETURNING id'
        );
        $statement->execute(array(
            ':company_id' => $companyId,
            ':id' => $bookingId,
            ':status' => $status,
        ));

        if (!$statement->fetchColumn()) {
            throw new RuntimeException('Reserva no encontrada.');
        }

        return $this->findByCompany($companyId, $bookingId);
    }

    public function reschedule(string $companyId, string $bookingId, string $startsAt): array
    {
        $booking = $this->findByCompany($companyId, $bookingId);
        if ((string) $booking['status'] === 'cancelled') {
            throw new InvalidArgumentException('No se puede reprogramar una reserva cancelada.');
        }

        $start = $this->parseStartDate($startsAt);
        $end = (clone $start)->modify('+' . (int) $booking['service_duration_minutes'] . ' minutes');
        $this->assertNoOverlap($companyId, (string) $booking['professional_id'], $start, $end, $bookingId);
        $this->assertProfessionalAvailability($companyId, (string) $booking['professional_id'], $start, $end);

        $statement = $this->pdo->prepare(
            'UPDATE bookings
             SET starts_at = :starts_at, ends_at = :ends_at, updated_at = NOW()
             WHERE company_id = :company_id AND id = :id'
        );
        $statement->execute(array(
            ':company_id' => $companyId,
            ':id' => $bookingId,
            ':starts_at' => $start->format(DateTimeInterface::ATOM),
            ':ends_at' => $end->format(DateTimeInterface::ATOM),
        ));

        return $this->findByCompany($companyId, $bookingId);
    }

    private function findByCompany(string $companyId, string $bookingId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT bookings.id, bookings.company_id, bookings.customer_id, bookings.professional_id,
                    bookings.service_id, bookings.starts_at, bookings.ends_at, bookings.status,
                    bookings.notes, bookings.created_at,
                    customers.first_name AS customer_first_name, customers.last_name AS customer_last_name,
                    customers.email AS customer_email,
                    professionals.name AS professional_name, professionals.calendar_color AS professional_color,
                    services.name AS service_name, services.price AS service_price,
                    services.duration_minutes AS service_duration_minutes
             FROM bookings
             JOIN customers ON customers.id = bookings.customer_id AND customers.company_id = bookings.company_id
             JOIN professionals ON professionals.id = bookings.professional_id AND professionals.company_id = bookings.company_id
             JOIN services ON services.id = bookings.service_id AND services.company_id = bookings.company_id
             WHERE bookings.company_id = :company_id AND bookings.id = :id
             LIMIT 1'
        );
        $statement->execute(array(':company_id' => $companyId, ':id' => $bookingId));
        $booking = $statement->fetch();

        if (!$booking) {
            throw new RuntimeException('Reserva no encontrada.');
        }

        return $booking;
    }

    private function tenantRow(string $table, string $companyId, string $id, string $error): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM ' . $table . ' WHERE company_id = :company_id AND id = :id LIMIT 1'
        );
        $statement->execute(array(':company_id' => $companyId, ':id' => $id));
        $row = $statement->fetch();

        if (!$row) {
            throw new InvalidArgumentException($error);
        }

        return $row;
    }

    private function professionalCanProvideService(string $companyId, string $professionalId, string $serviceId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1
             FROM professional_services
             WHERE company_id = :company_id
                AND professional_id = :professional_id
                AND service_id = :service_id
                AND is_active = TRUE
             LIMIT 1'
        );
        $statement->execute(array(
            ':company_id' => $companyId,
            ':professional_id' => $professionalId,
            ':service_id' => $serviceId,
        ));

        return (bool) $statement->fetchColumn();
    }

    private function effectiveDuration(string $companyId, string $professionalId, string $serviceId, int $fallback): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(custom_duration_minutes, :fallback) AS duration_minutes
             FROM professional_services
             WHERE company_id = :company_id AND professional_id = :professional_id AND service_id = :service_id
             LIMIT 1'
        );
        $statement->execute(array(
            ':company_id' => $companyId,
            ':professional_id' => $professionalId,
            ':service_id' => $serviceId,
            ':fallback' => $fallback,
        ));

        return max(15, (int) ($statement->fetchColumn() ?: $fallback));
    }

    private function effectivePrice(string $companyId, string $professionalId, string $serviceId, string $fallback): array
    {
        $statement = $this->pdo->prepare(
            'SELECT custom_price
             FROM professional_services
             WHERE company_id = :company_id AND professional_id = :professional_id AND service_id = :service_id
             LIMIT 1'
        );
        $statement->execute(array(
            ':company_id' => $companyId,
            ':professional_id' => $professionalId,
            ':service_id' => $serviceId,
        ));
        $customPrice = $statement->fetchColumn();

        return array(
            'amount' => $customPrice !== false && $customPrice !== null ? $customPrice : $fallback,
            'source' => $customPrice !== false && $customPrice !== null ? 'professional_custom' : 'service_base',
        );
    }

    private function assertNoOverlap(string $companyId, string $professionalId, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt, string $ignoreBookingId = ''): void
    {
        $params = array(
            ':company_id' => $companyId,
            ':professional_id' => $professionalId,
            ':starts_at' => $startsAt->format(DateTimeInterface::ATOM),
            ':ends_at' => $endsAt->format(DateTimeInterface::ATOM),
        );
        $ignoreSql = '';
        if ($ignoreBookingId !== '') {
            $ignoreSql = ' AND id <> :ignore_id';
            $params[':ignore_id'] = $ignoreBookingId;
        }

        $statement = $this->pdo->prepare(
            'SELECT 1
             FROM bookings
             WHERE company_id = :company_id
                AND professional_id = :professional_id
                AND status IN (\'' . implode('\', \'', self::ACTIVE_STATUSES) . '\')
                AND starts_at < :ends_at
                AND ends_at > :starts_at'
                . $ignoreSql .
             ' LIMIT 1'
        );
        $statement->execute($params);

        if ($statement->fetchColumn()) {
            throw new InvalidArgumentException('El profesional ya tiene una reserva en ese horario.');
        }
    }

    private function assertProfessionalAvailability(string $companyId, string $professionalId, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): void
    {
        $weekday = (int) $startsAt->format('w');
        $statement = $this->pdo->prepare(
            'SELECT 1
             FROM professional_availability
             WHERE company_id = :company_id
                AND professional_id = :professional_id
                AND weekday = :weekday
                AND is_active = TRUE
                AND start_time <= CAST(:start_time AS time)
                AND end_time >= CAST(:end_time AS time)
             LIMIT 1'
        );
        $statement->execute(array(
            ':company_id' => $companyId,
            ':professional_id' => $professionalId,
            ':weekday' => $weekday,
            ':start_time' => $startsAt->format('H:i:s'),
            ':end_time' => $endsAt->format('H:i:s'),
        ));

        if (!$statement->fetchColumn()) {
            throw new InvalidArgumentException('El profesional no trabaja en ese horario.');
        }

        $blocks = $this->pdo->prepare(
            'SELECT 1
             FROM professional_time_blocks
             WHERE company_id = :company_id
                AND professional_id = :professional_id
                AND is_available = FALSE
                AND starts_at < :ends_at
                AND ends_at > :starts_at
             LIMIT 1'
        );
        $blocks->execute(array(
            ':company_id' => $companyId,
            ':professional_id' => $professionalId,
            ':starts_at' => $startsAt->format(DateTimeInterface::ATOM),
            ':ends_at' => $endsAt->format(DateTimeInterface::ATOM),
        ));

        if ($blocks->fetchColumn()) {
            throw new InvalidArgumentException('El profesional tiene un bloqueo en ese horario.');
        }
    }

    private function companyBySlug(string $slug): array
    {
        $statement = $this->pdo->prepare(
            'SELECT companies.*
             FROM companies
             JOIN company_profiles ON company_profiles.company_id = companies.id
             WHERE companies.slug = :slug AND company_profiles.is_public = TRUE
             LIMIT 1'
        );
        $statement->execute(array(':slug' => $slug));
        $company = $statement->fetch();
        if (!$company) {
            throw new InvalidArgumentException('Empresa no encontrada.');
        }

        return $company;
    }

    private function customerProfileForUser(string $userId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM customer_profiles WHERE user_id = :user_id AND is_active = TRUE LIMIT 1');
        $statement->execute(array(':user_id' => $userId));
        $profile = $statement->fetch();
        if (!$profile) {
            throw new InvalidArgumentException('Perfil de cliente no encontrado.');
        }

        return $profile;
    }

    private function activeCompanyCustomer(string $companyId, string $customerProfileId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM company_customers
             WHERE company_id = :company_id
                AND customer_profile_id = :customer_profile_id
                AND status = \'active\'
             LIMIT 1'
        );
        $statement->execute(array(
            ':company_id' => $companyId,
            ':customer_profile_id' => $customerProfileId,
        ));
        $membership = $statement->fetch();
        if (!$membership) {
            throw new InvalidArgumentException('Debes inscribirte en esta empresa antes de reservar.');
        }

        return $membership;
    }

    private function ensureLocalCustomer(string $companyId, string $userId, array $profile): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO customers (company_id, user_id, first_name, last_name, email, phone, notes, is_active)
             VALUES (:company_id, :user_id, :first_name, :last_name, :email, :phone, :notes, TRUE)
             ON CONFLICT (company_id, email) DO UPDATE
             SET user_id = EXCLUDED.user_id,
                 first_name = EXCLUDED.first_name,
                 last_name = EXCLUDED.last_name,
                 phone = EXCLUDED.phone,
                 is_active = TRUE,
                 updated_at = NOW()
             RETURNING *'
        );
        $statement->execute(array(
            ':company_id' => $companyId,
            ':user_id' => $userId,
            ':first_name' => $profile['first_name'],
            ':last_name' => $profile['last_name'],
            ':email' => $profile['email'],
            ':phone' => $profile['phone'],
            ':notes' => $profile['notes'],
        ));

        return $statement->fetch();
    }

    private function parseStartDate(string $startsAt): DateTimeImmutable
    {
        try {
            $date = new DateTimeImmutable($startsAt);
        } catch (Throwable) {
            throw new InvalidArgumentException('Fecha de inicio no valida.');
        }

        if ($date <= new DateTimeImmutable('-1 minute')) {
            throw new InvalidArgumentException('La reserva debe iniciar en el futuro.');
        }

        return $date;
    }
}
