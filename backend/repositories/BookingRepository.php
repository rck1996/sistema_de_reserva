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
            $duration = max(15, (int) $service['duration_minutes']);
            $endsAt = (clone $startsAt)->modify('+' . $duration . ' minutes');

            $this->assertNoOverlap($companyId, $data['professional_id'], $startsAt, $endsAt);

            $status = in_array($data['status'], self::VALID_STATUSES, true) ? $data['status'] : 'pending';
            $statement = $this->pdo->prepare(
                'INSERT INTO bookings (company_id, customer_id, professional_id, service_id, starts_at, ends_at, status, notes)
                 VALUES (:company_id, :customer_id, :professional_id, :service_id, :starts_at, :ends_at, :status, :notes)
                 RETURNING id'
            );
            $statement->execute(array(
                ':company_id' => $companyId,
                ':customer_id' => $data['customer_id'],
                ':professional_id' => $data['professional_id'],
                ':service_id' => $data['service_id'],
                ':starts_at' => $startsAt->format(DateTimeInterface::ATOM),
                ':ends_at' => $endsAt->format(DateTimeInterface::ATOM),
                ':status' => $status,
                ':notes' => $data['notes'],
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
             LIMIT 1'
        );
        $statement->execute(array(
            ':company_id' => $companyId,
            ':professional_id' => $professionalId,
            ':service_id' => $serviceId,
        ));

        return (bool) $statement->fetchColumn();
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
