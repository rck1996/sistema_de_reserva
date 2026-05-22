<?php

declare(strict_types=1);

final class CustomerPortalRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function profileForUser(string $companyId, string $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT customers.id, customers.company_id, customers.user_id, customers.first_name,
                    customers.last_name, customers.email, customers.phone, customers.notes,
                    customers.is_active, customers.created_at,
                    companies.slug AS company_slug, companies.name AS company_name
             FROM customers
             JOIN companies ON companies.id = customers.company_id
             WHERE customers.company_id = :company_id
                AND customers.user_id = :user_id
                AND customers.is_active = TRUE
             LIMIT 1'
        );
        $statement->execute(array(':company_id' => $companyId, ':user_id' => $userId));
        $profile = $statement->fetch();

        if (!$profile) {
            throw new RuntimeException('Perfil de cliente no encontrado.');
        }

        return $profile;
    }

    public function bookingsForUser(string $companyId, string $userId): array
    {
        $profile = $this->profileForUser($companyId, $userId);
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
                AND bookings.customer_id = :customer_id
             ORDER BY bookings.starts_at DESC'
        );
        $statement->execute(array(
            ':company_id' => $companyId,
            ':customer_id' => $profile['id'],
        ));

        return $statement->fetchAll();
    }
}
