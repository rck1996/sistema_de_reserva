<?php

declare(strict_types=1);

final class UserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByCompanySlugAndEmail(string $companySlug, string $email): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT users.*, companies.slug AS company_slug, companies.name AS company_name
             FROM users
             JOIN companies ON companies.id = users.company_id
             WHERE companies.slug = :slug AND users.email = :email AND users.is_active = TRUE
             LIMIT 1'
        );
        $statement->execute(array(':slug' => $companySlug, ':email' => $email));
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function findById(string $userId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT users.*, companies.slug AS company_slug, companies.name AS company_name
             FROM users
             LEFT JOIN companies ON companies.id = users.company_id
             WHERE users.id = :id AND users.is_active = TRUE
             LIMIT 1'
        );
        $statement->execute(array(':id' => $userId));
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function createCompanyAdmin(string $companyName, string $companySlug, string $email, string $password, string $username = ''): array
    {
        $this->pdo->beginTransaction();
        try {
            $company = $this->createCompany($companyName, $companySlug);
            $statement = $this->pdo->prepare(
                'INSERT INTO users (company_id, email, username, password_hash, role)
                 VALUES (:company_id, :email, :username, :password_hash, :role)
                 RETURNING *'
            );
            $statement->execute(array(
                ':company_id' => $company['id'],
                ':email' => $email,
                ':username' => $username !== '' ? $username : null,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':role' => 'admin_empresa',
            ));
            $user = $statement->fetch();
            $this->pdo->commit();

            return array_merge($user, array('company_slug' => $company['slug'], 'company_name' => $company['name']));
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function createCompany(string $name, string $slug): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO companies (name, slug)
             VALUES (:name, :slug)
             RETURNING *'
        );
        $statement->execute(array(':name' => $name, ':slug' => $slug));

        return $statement->fetch();
    }
}
