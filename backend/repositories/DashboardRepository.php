<?php

declare(strict_types=1);

final class DashboardRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function summary(string $companyId): array
    {
        return array(
            'metrics' => $this->metrics($companyId),
            'bookings_by_status' => $this->bookingsByStatus($companyId),
            'upcoming_bookings' => $this->upcomingBookings($companyId),
            'top_services' => $this->topServices($companyId),
            'staff_load' => $this->staffLoad($companyId),
        );
    }

    private function metrics(string $companyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                (SELECT COUNT(*) FROM bookings WHERE company_id = :company_id) AS total_bookings,
                (SELECT COUNT(*) FROM bookings WHERE company_id = :company_id AND starts_at::date = CURRENT_DATE) AS today_bookings,
                (SELECT COUNT(*) FROM bookings WHERE company_id = :company_id AND starts_at >= NOW() AND status IN (\'pending\', \'confirmed\', \'in_progress\')) AS upcoming_bookings,
                (SELECT COUNT(*) FROM bookings WHERE company_id = :company_id AND status = \'cancelled\') AS cancelled_bookings,
                (SELECT COUNT(*) FROM customers WHERE company_id = :company_id AND is_active = TRUE) AS active_customers,
                (SELECT COUNT(*) FROM professionals WHERE company_id = :company_id AND is_active = TRUE) AS active_professionals,
                (SELECT COUNT(*) FROM services WHERE company_id = :company_id AND is_active = TRUE) AS active_services,
                COALESCE((
                    SELECT SUM(services.price)
                    FROM bookings
                    JOIN services ON services.id = bookings.service_id AND services.company_id = bookings.company_id
                    WHERE bookings.company_id = :company_id
                      AND bookings.status IN (\'confirmed\', \'in_progress\', \'completed\')
                ), 0) AS estimated_revenue'
        );
        $statement->execute(array(':company_id' => $companyId));

        return $statement->fetch() ?: array();
    }

    private function bookingsByStatus(string $companyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT status, COUNT(*) AS total
             FROM bookings
             WHERE company_id = :company_id
             GROUP BY status
             ORDER BY status ASC'
        );
        $statement->execute(array(':company_id' => $companyId));

        return $statement->fetchAll();
    }

    private function upcomingBookings(string $companyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT bookings.id, bookings.starts_at, bookings.ends_at, bookings.status,
                    customers.first_name AS customer_first_name, customers.last_name AS customer_last_name,
                    professionals.name AS professional_name, professionals.calendar_color AS professional_color,
                    services.name AS service_name
             FROM bookings
             JOIN customers ON customers.id = bookings.customer_id AND customers.company_id = bookings.company_id
             JOIN professionals ON professionals.id = bookings.professional_id AND professionals.company_id = bookings.company_id
             JOIN services ON services.id = bookings.service_id AND services.company_id = bookings.company_id
             WHERE bookings.company_id = :company_id
               AND bookings.starts_at >= NOW()
               AND bookings.status IN (\'pending\', \'confirmed\', \'in_progress\')
             ORDER BY bookings.starts_at ASC
             LIMIT 8'
        );
        $statement->execute(array(':company_id' => $companyId));

        return $statement->fetchAll();
    }

    private function topServices(string $companyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT services.id, services.name, services.color, COUNT(bookings.id) AS total
             FROM services
             LEFT JOIN bookings ON bookings.service_id = services.id
                AND bookings.company_id = services.company_id
                AND bookings.status <> \'cancelled\'
             WHERE services.company_id = :company_id
             GROUP BY services.id
             ORDER BY total DESC, services.name ASC
             LIMIT 5'
        );
        $statement->execute(array(':company_id' => $companyId));

        return $statement->fetchAll();
    }

    private function staffLoad(string $companyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT professionals.id, professionals.name, professionals.calendar_color,
                    COUNT(bookings.id) AS upcoming_total
             FROM professionals
             LEFT JOIN bookings ON bookings.professional_id = professionals.id
                AND bookings.company_id = professionals.company_id
                AND bookings.starts_at >= NOW()
                AND bookings.status IN (\'pending\', \'confirmed\', \'in_progress\')
             WHERE professionals.company_id = :company_id
                AND professionals.is_active = TRUE
             GROUP BY professionals.id
             ORDER BY upcoming_total DESC, professionals.name ASC'
        );
        $statement->execute(array(':company_id' => $companyId));

        return $statement->fetchAll();
    }
}
