<?php

declare(strict_types=1);

final class MarketplaceRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function publicCompanies(): array
    {
        $statement = $this->pdo->query(
            'SELECT companies.id, companies.slug, companies.name,
                    company_profiles.display_name, company_profiles.tagline, company_profiles.description,
                    company_profiles.logo_url, company_profiles.cover_url, company_profiles.primary_color,
                    company_profiles.accent_color, company_profiles.city, company_profiles.contact_email,
                    company_profiles.contact_phone
             FROM companies
             JOIN company_profiles ON company_profiles.company_id = companies.id
             WHERE company_profiles.is_public = TRUE
             ORDER BY company_profiles.display_name ASC, companies.name ASC'
        );

        return $statement->fetchAll();
    }

    public function companyBySlug(string $slug): array
    {
        $statement = $this->pdo->prepare(
            'SELECT companies.id, companies.slug, companies.name,
                    company_profiles.display_name, company_profiles.tagline, company_profiles.description,
                    company_profiles.logo_url, company_profiles.cover_url, company_profiles.primary_color,
                    company_profiles.accent_color, company_profiles.city, company_profiles.address,
                    company_profiles.contact_email, company_profiles.contact_phone, company_profiles.website_url
             FROM companies
             JOIN company_profiles ON company_profiles.company_id = companies.id
             WHERE companies.slug = :slug AND company_profiles.is_public = TRUE
             LIMIT 1'
        );
        $statement->execute(array(':slug' => $slug));
        $company = $statement->fetch();
        if (!$company) {
            throw new RuntimeException('Empresa no encontrada.');
        }

        $company['disciplines'] = $this->disciplinesForCompany((string) $company['id']);
        $company['services'] = $this->servicesForCompany((string) $company['id']);
        $company['professionals'] = $this->professionalsForCompany((string) $company['id']);

        return $company;
    }

    public function enrollCustomer(string $userId, string $companySlug): array
    {
        $company = $this->companyBySlug($companySlug);
        $profile = $this->customerProfileForUser($userId);
        $statement = $this->pdo->prepare(
            'INSERT INTO company_customers (company_id, customer_profile_id, status)
             VALUES (:company_id, :customer_profile_id, \'active\')
             ON CONFLICT (company_id, customer_profile_id)
             DO UPDATE SET status = \'active\', updated_at = NOW()
             RETURNING *'
        );
        $statement->execute(array(
            ':company_id' => $company['id'],
            ':customer_profile_id' => $profile['id'],
        ));

        return array(
            'membership' => $statement->fetch(),
            'company' => $company,
            'customer_profile' => $profile,
        );
    }

    private function disciplinesForCompany(string $companyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, description, color
             FROM disciplines
             WHERE company_id = :company_id AND is_active = TRUE
             ORDER BY name ASC'
        );
        $statement->execute(array(':company_id' => $companyId));

        return $statement->fetchAll();
    }

    private function servicesForCompany(string $companyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT services.id, services.discipline_id, services.name, services.description,
                    services.price, services.duration_minutes, services.modality, services.color,
                    disciplines.name AS discipline_name
             FROM services
             LEFT JOIN disciplines ON disciplines.id = services.discipline_id AND disciplines.company_id = services.company_id
             WHERE services.company_id = :company_id AND services.is_active = TRUE
             ORDER BY services.name ASC'
        );
        $statement->execute(array(':company_id' => $companyId));

        return $statement->fetchAll();
    }

    private function professionalsForCompany(string $companyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT professionals.id, professionals.name, professionals.bio, professionals.calendar_color,
                    COALESCE(
                        json_agg(
                            json_build_object(
                                \'service_id\', services.id,
                                \'service_name\', services.name,
                                \'base_price\', services.price,
                                \'custom_price\', professional_services.custom_price,
                                \'effective_price\', COALESCE(professional_services.custom_price, services.price)
                            )
                        ) FILTER (WHERE services.id IS NOT NULL),
                        \'[]\'::json
                    ) AS services
             FROM professionals
             LEFT JOIN professional_services ON professional_services.professional_id = professionals.id
                AND professional_services.company_id = professionals.company_id
                AND professional_services.is_active = TRUE
             LEFT JOIN services ON services.id = professional_services.service_id
                AND services.company_id = professionals.company_id
             WHERE professionals.company_id = :company_id AND professionals.is_active = TRUE
             GROUP BY professionals.id
             ORDER BY professionals.name ASC'
        );
        $statement->execute(array(':company_id' => $companyId));

        return array_map(static function (array $professional): array {
            $professional['services'] = json_decode((string) $professional['services'], true) ?: array();
            return $professional;
        }, $statement->fetchAll());
    }

    private function customerProfileForUser(string $userId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM customer_profiles WHERE user_id = :user_id AND is_active = TRUE LIMIT 1');
        $statement->execute(array(':user_id' => $userId));
        $profile = $statement->fetch();
        if (!$profile) {
            throw new RuntimeException('Perfil global de cliente no encontrado.');
        }

        return $profile;
    }
}
