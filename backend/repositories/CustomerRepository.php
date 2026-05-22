<?php

declare(strict_types=1);

final class CustomerRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function listByCompany(string $companyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, company_id, first_name, last_name, email, phone, notes, is_active, created_at
             FROM customers
             WHERE company_id = :company_id
             ORDER BY first_name ASC, last_name ASC'
        );
        $statement->execute(array(':company_id' => $companyId));

        return $statement->fetchAll();
    }

    public function create(string $companyId, array $data): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO customers (company_id, first_name, last_name, email, phone, notes, is_active)
             VALUES (:company_id, :first_name, :last_name, :email, :phone, :notes, :is_active)
             RETURNING *'
        );
        $statement->execute(array(
            ':company_id' => $companyId,
            ':first_name' => $data['first_name'],
            ':last_name' => $data['last_name'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':notes' => $data['notes'],
            ':is_active' => $data['is_active'],
        ));

        return $statement->fetch();
    }

    public function update(string $companyId, string $customerId, array $data): array
    {
        $statement = $this->pdo->prepare(
            'UPDATE customers
             SET first_name = :first_name,
                 last_name = :last_name,
                 email = :email,
                 phone = :phone,
                 notes = :notes,
                 is_active = :is_active,
                 updated_at = NOW()
             WHERE company_id = :company_id AND id = :id
             RETURNING id, company_id, first_name, last_name, email, phone, notes, is_active, created_at'
        );
        $statement->execute(array(
            ':company_id' => $companyId,
            ':id' => $customerId,
            ':first_name' => $data['first_name'],
            ':last_name' => $data['last_name'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':notes' => $data['notes'],
            ':is_active' => $data['is_active'],
        ));
        $customer = $statement->fetch();

        if (!$customer) {
            throw new RuntimeException('Cliente no encontrado.');
        }

        return $customer;
    }
}
