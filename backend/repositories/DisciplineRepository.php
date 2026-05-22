<?php

declare(strict_types=1);

final class DisciplineRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function listByCompany(string $companyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, company_id, name, description, color, is_active, created_at
             FROM disciplines
             WHERE company_id = :company_id
             ORDER BY name ASC'
        );
        $statement->execute(array(':company_id' => $companyId));

        return $statement->fetchAll();
    }

    public function create(string $companyId, array $data): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO disciplines (company_id, name, description, color, is_active)
             VALUES (:company_id, :name, :description, :color, :is_active)
             RETURNING *'
        );
        $statement->execute(array(
            ':company_id' => $companyId,
            ':name' => $data['name'],
            ':description' => $data['description'],
            ':color' => $data['color'],
            ':is_active' => $data['is_active'],
        ));

        return $statement->fetch();
    }
}
