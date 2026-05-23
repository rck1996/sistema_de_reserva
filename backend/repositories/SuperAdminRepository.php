<?php

declare(strict_types=1);

final class SuperAdminRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function overview(): array
    {
        return array(
            'metrics' => $this->metrics(),
            'companies' => $this->companies(),
            'recent_bookings' => $this->recentBookings(),
        );
    }

    public function updateCompanyProfile(string $companyId, array $data): array
    {
        $this->pdo->beginTransaction();
        try {
            $company = $this->pdo->prepare(
                'UPDATE companies
                 SET name = :name, updated_at = NOW()
                 WHERE id = :id
                 RETURNING id'
            );
            $company->execute(array(
                ':id' => $companyId,
                ':name' => $data['name'],
            ));
            if (!$company->fetchColumn()) {
                throw new RuntimeException('Empresa no encontrada.');
            }

            $profile = $this->pdo->prepare(
                'UPDATE company_profiles
                 SET display_name = :display_name,
                     tagline = :tagline,
                     description = :description,
                     city = :city,
                     contact_email = :contact_email,
                     contact_phone = :contact_phone,
                     primary_color = :primary_color,
                     accent_color = :accent_color,
                     is_public = :is_public,
                     updated_at = NOW()
                 WHERE company_id = :company_id'
            );
            $profile->execute(array(
                ':company_id' => $companyId,
                ':display_name' => $data['display_name'],
                ':tagline' => $data['tagline'],
                ':description' => $data['description'],
                ':city' => $data['city'],
                ':contact_email' => $data['contact_email'],
                ':contact_phone' => $data['contact_phone'],
                ':primary_color' => $data['primary_color'],
                ':accent_color' => $data['accent_color'],
                ':is_public' => $data['is_public'] ? 'true' : 'false',
            ));

            $this->pdo->commit();

            return $this->company($companyId);
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function metrics(): array
    {
        $statement = $this->pdo->query(
            'SELECT
                (SELECT COUNT(*) FROM companies) AS companies,
                (SELECT COUNT(*) FROM users WHERE is_active = TRUE) AS active_users,
                (SELECT COUNT(*) FROM users WHERE role = \'customer\' AND is_active = TRUE) AS customers,
                (SELECT COUNT(*) FROM professionals WHERE is_active = TRUE) AS professionals,
                (SELECT COUNT(*) FROM services WHERE is_active = TRUE) AS services,
                (SELECT COUNT(*) FROM bookings) AS bookings,
                (SELECT COUNT(*) FROM company_customers WHERE status = \'active\') AS memberships'
        );

        return $statement->fetch() ?: array();
    }

    private function companies(): array
    {
        $statement = $this->pdo->query(
            'SELECT companies.id, companies.slug, companies.name,
                    company_profiles.display_name, company_profiles.tagline, company_profiles.description,
                    company_profiles.city, company_profiles.contact_email, company_profiles.contact_phone,
                    company_profiles.primary_color, company_profiles.accent_color, company_profiles.is_public,
                    COUNT(DISTINCT users.id) AS users_count,
                    COUNT(DISTINCT professionals.id) AS professionals_count,
                    COUNT(DISTINCT services.id) AS services_count,
                    COUNT(DISTINCT bookings.id) AS bookings_count
             FROM companies
             LEFT JOIN company_profiles ON company_profiles.company_id = companies.id
             LEFT JOIN users ON users.company_id = companies.id
             LEFT JOIN professionals ON professionals.company_id = companies.id
             LEFT JOIN services ON services.company_id = companies.id
             LEFT JOIN bookings ON bookings.company_id = companies.id
             GROUP BY companies.id, company_profiles.id
             ORDER BY companies.created_at DESC'
        );

        return $statement->fetchAll();
    }

    private function company(string $companyId): array
    {
        foreach ($this->companies() as $company) {
            if ((string) $company['id'] === $companyId) {
                return $company;
            }
        }

        throw new RuntimeException('Empresa no encontrada.');
    }

    private function recentBookings(): array
    {
        $statement = $this->pdo->query(
            'SELECT bookings.id, bookings.starts_at, bookings.status,
                    companies.name AS company_name,
                    services.name AS service_name,
                    professionals.name AS professional_name,
                    customers.first_name AS customer_first_name,
                    customers.last_name AS customer_last_name
             FROM bookings
             JOIN companies ON companies.id = bookings.company_id
             JOIN services ON services.id = bookings.service_id
             JOIN professionals ON professionals.id = bookings.professional_id
             JOIN customers ON customers.id = bookings.customer_id
             ORDER BY bookings.created_at DESC
             LIMIT 10'
        );

        return $statement->fetchAll();
    }
}
