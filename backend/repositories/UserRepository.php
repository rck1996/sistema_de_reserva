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

    public function findGlobalCustomerByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT users.*, NULL AS company_slug, NULL AS company_name
             FROM users
             WHERE users.email = :email
                AND users.role = \'customer\'
                AND users.is_active = TRUE
             LIMIT 1'
        );
        $statement->execute(array(':email' => $email));
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

    public function createCompanyCustomer(string $companySlug, string $email, string $password, string $firstName, string $lastName, string $phone = '', string $notes = ''): array
    {
        $this->pdo->beginTransaction();
        try {
            $company = $this->findCompanyBySlug($companySlug);
            if ($company === null) {
                throw new InvalidArgumentException('Empresa no encontrada.');
            }

            $statement = $this->pdo->prepare(
                'INSERT INTO users (company_id, email, username, password_hash, role)
                 VALUES (:company_id, :email, :username, :password_hash, :role)
                 RETURNING *'
            );
            $statement->execute(array(
                ':company_id' => $company['id'],
                ':email' => $email,
                ':username' => null,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':role' => 'customer',
            ));
            $user = $statement->fetch();

            $customerStatement = $this->pdo->prepare(
                'INSERT INTO customers (company_id, user_id, first_name, last_name, email, phone, notes, is_active)
                 VALUES (:company_id, :user_id, :first_name, :last_name, :email, :phone, :notes, TRUE)'
            );
            $customerStatement->execute(array(
                ':company_id' => $company['id'],
                ':user_id' => $user['id'],
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':email' => $email,
                ':phone' => $phone,
                ':notes' => $notes,
            ));

            $this->pdo->commit();

            return array_merge($user, array('company_slug' => $company['slug'], 'company_name' => $company['name']));
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function createGlobalCustomer(string $email, string $password, string $firstName, string $lastName, string $phone = '', string $notes = ''): array
    {
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO users (company_id, email, username, password_hash, role)
                 VALUES (NULL, :email, NULL, :password_hash, :role)
                 RETURNING *'
            );
            $statement->execute(array(
                ':email' => $email,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':role' => 'customer',
            ));
            $user = $statement->fetch();

            $profileStatement = $this->pdo->prepare(
                'INSERT INTO customer_profiles (user_id, first_name, last_name, email, phone, notes, is_active)
                 VALUES (:user_id, :first_name, :last_name, :email, :phone, :notes, TRUE)'
            );
            $profileStatement->execute(array(
                ':user_id' => $user['id'],
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':email' => $email,
                ':phone' => $phone,
                ':notes' => $notes,
            ));

            $this->pdo->commit();

            return array_merge($user, array('company_slug' => '', 'company_name' => 'Marketplace'));
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
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

    private function findCompanyBySlug(string $slug): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM companies WHERE slug = :slug LIMIT 1');
        $statement->execute(array(':slug' => $slug));
        $company = $statement->fetch();

        return $company === false ? null : $company;
    }
}
