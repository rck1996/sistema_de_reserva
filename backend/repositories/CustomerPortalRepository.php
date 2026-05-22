<?php

declare(strict_types=1);

final class CustomerPortalRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function profileForUser(string $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT customer_profiles.id, customer_profiles.user_id, customer_profiles.first_name,
                    customer_profiles.last_name, customer_profiles.email, customer_profiles.phone,
                    customer_profiles.avatar_url, customer_profiles.notes, customer_profiles.is_active,
                    customer_profiles.created_at
             FROM customer_profiles
             WHERE customer_profiles.user_id = :user_id
                AND customer_profiles.is_active = TRUE
             LIMIT 1'
        );
        $statement->execute(array(':user_id' => $userId));
        $profile = $statement->fetch();

        if (!$profile) {
            throw new RuntimeException('Perfil de cliente no encontrado.');
        }

        return $profile;
    }

    public function bookingsForUser(string $userId): array
    {
        $profile = $this->profileForUser($userId);
        $statement = $this->pdo->prepare(
            'SELECT bookings.id, bookings.company_id, bookings.customer_profile_id, bookings.professional_id,
                    bookings.service_id, bookings.starts_at, bookings.ends_at, bookings.status,
                    bookings.notes, bookings.created_at,
                    customer_profiles.first_name AS customer_first_name, customer_profiles.last_name AS customer_last_name,
                    customer_profiles.email AS customer_email,
                    companies.name AS company_name, companies.slug AS company_slug,
                    professionals.name AS professional_name, professionals.calendar_color AS professional_color,
                    services.name AS service_name, COALESCE(bookings.final_price, services.price) AS service_price,
                    services.duration_minutes AS service_duration_minutes
             FROM bookings
             JOIN customer_profiles ON customer_profiles.id = bookings.customer_profile_id
             JOIN companies ON companies.id = bookings.company_id
             JOIN professionals ON professionals.id = bookings.professional_id AND professionals.company_id = bookings.company_id
             JOIN services ON services.id = bookings.service_id AND services.company_id = bookings.company_id
             WHERE bookings.customer_profile_id = :customer_profile_id
             ORDER BY bookings.starts_at DESC'
        );
        $statement->execute(array(
            ':customer_profile_id' => $profile['id'],
        ));

        return $statement->fetchAll();
    }
}
