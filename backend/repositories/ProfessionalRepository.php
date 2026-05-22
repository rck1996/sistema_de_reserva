<?php

declare(strict_types=1);

final class ProfessionalRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function listByCompany(string $companyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT professionals.id, professionals.company_id, professionals.user_id, professionals.name,
                    professionals.email, professionals.phone, professionals.bio, professionals.calendar_color,
                    professionals.booking_capacity, professionals.accepts_waitlist, professionals.is_active,
                    professionals.created_at,
                    COALESCE(
                        json_agg(
                            json_build_object(
                                \'id\', services.id,
                                \'name\', services.name,
                                \'discipline_id\', services.discipline_id
                            )
                        ) FILTER (WHERE services.id IS NOT NULL),
                        \'[]\'::json
                    ) AS services
             FROM professionals
             LEFT JOIN professional_services ON professional_services.professional_id = professionals.id
                AND professional_services.company_id = professionals.company_id
             LEFT JOIN services ON services.id = professional_services.service_id
                AND services.company_id = professionals.company_id
             WHERE professionals.company_id = :company_id
             GROUP BY professionals.id
             ORDER BY professionals.name ASC'
        );
        $statement->execute(array(':company_id' => $companyId));

        return array_map(array($this, 'decodeServices'), $statement->fetchAll());
    }

    public function create(string $companyId, array $data): array
    {
        $serviceIds = $this->validServiceIds($companyId, $data['service_ids']);
        $this->pdo->beginTransaction();

        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO professionals (company_id, name, email, phone, bio, calendar_color, booking_capacity, accepts_waitlist, is_active)
                 VALUES (:company_id, :name, :email, :phone, :bio, :calendar_color, :booking_capacity, :accepts_waitlist, :is_active)
                 RETURNING *'
            );
            $statement->execute(array(
                ':company_id' => $companyId,
                ':name' => $data['name'],
                ':email' => $data['email'],
                ':phone' => $data['phone'],
                ':bio' => $data['bio'],
                ':calendar_color' => $data['calendar_color'],
                ':booking_capacity' => $data['booking_capacity'],
                ':accepts_waitlist' => $data['accepts_waitlist'],
                ':is_active' => $data['is_active'],
            ));
            $professional = $statement->fetch();

            $this->syncServices($companyId, (string) $professional['id'], $serviceIds);
            $this->pdo->commit();

            return $this->findByCompany($companyId, (string) $professional['id']);
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function findByCompany(string $companyId, string $professionalId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT professionals.id, professionals.company_id, professionals.user_id, professionals.name,
                    professionals.email, professionals.phone, professionals.bio, professionals.calendar_color,
                    professionals.booking_capacity, professionals.accepts_waitlist, professionals.is_active,
                    professionals.created_at,
                    COALESCE(
                        json_agg(json_build_object(\'id\', services.id, \'name\', services.name, \'discipline_id\', services.discipline_id))
                        FILTER (WHERE services.id IS NOT NULL),
                        \'[]\'::json
                    ) AS services
             FROM professionals
             LEFT JOIN professional_services ON professional_services.professional_id = professionals.id
                AND professional_services.company_id = professionals.company_id
             LEFT JOIN services ON services.id = professional_services.service_id
                AND services.company_id = professionals.company_id
             WHERE professionals.company_id = :company_id AND professionals.id = :id
             GROUP BY professionals.id
             LIMIT 1'
        );
        $statement->execute(array(':company_id' => $companyId, ':id' => $professionalId));
        $professional = $statement->fetch();

        if (!$professional) {
            throw new RuntimeException('Profesional no encontrado');
        }

        return $this->decodeServices($professional);
    }

    private function syncServices(string $companyId, string $professionalId, array $serviceIds): void
    {
        if ($serviceIds === array()) {
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO professional_services (company_id, professional_id, service_id)
             VALUES (:company_id, :professional_id, :service_id)
             ON CONFLICT (professional_id, service_id) DO NOTHING'
        );

        foreach ($serviceIds as $serviceId) {
            $statement->execute(array(
                ':company_id' => $companyId,
                ':professional_id' => $professionalId,
                ':service_id' => $serviceId,
            ));
        }
    }

    private function validServiceIds(string $companyId, array $serviceIds): array
    {
        $serviceIds = array_values(array_unique(array_filter(array_map('strval', $serviceIds))));
        if ($serviceIds === array()) {
            return array();
        }

        $placeholders = array();
        $params = array(':company_id' => $companyId);
        foreach ($serviceIds as $index => $serviceId) {
            $key = ':service_' . $index;
            $placeholders[] = $key;
            $params[$key] = $serviceId;
        }

        $statement = $this->pdo->prepare(
            'SELECT id FROM services WHERE company_id = :company_id AND id IN (' . implode(', ', $placeholders) . ')'
        );
        $statement->execute($params);
        $validIds = array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));

        if (count($validIds) !== count($serviceIds)) {
            throw new InvalidArgumentException('Uno o mas servicios no pertenecen a la empresa autenticada.');
        }

        return $validIds;
    }

    private function decodeServices(array $professional): array
    {
        $professional['services'] = json_decode((string) $professional['services'], true) ?: array();

        return $professional;
    }
}
