<?php

declare(strict_types=1);

final class ServiceRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function listByCompany(string $companyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT services.id, services.company_id, services.discipline_id, services.name, services.description,
                    services.price, services.duration_minutes, services.modality, services.color, services.is_active,
                    disciplines.name AS discipline_name
             FROM services
             LEFT JOIN disciplines ON disciplines.id = services.discipline_id AND disciplines.company_id = services.company_id
             WHERE services.company_id = :company_id
             ORDER BY services.name ASC'
        );
        $statement->execute(array(':company_id' => $companyId));

        return $statement->fetchAll();
    }

    public function create(string $companyId, array $data): array
    {
        $disciplineId = $data['discipline_id'] ?: null;

        if ($disciplineId !== null && !$this->disciplineBelongsToCompany($companyId, $disciplineId)) {
            throw new InvalidArgumentException('La disciplina no pertenece a la empresa autenticada.');
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO services (company_id, discipline_id, name, description, price, duration_minutes, modality, color, is_active)
             VALUES (:company_id, :discipline_id, :name, :description, :price, :duration_minutes, :modality, :color, :is_active)
             RETURNING *'
        );
        $statement->execute(array(
            ':company_id' => $companyId,
            ':discipline_id' => $disciplineId,
            ':name' => $data['name'],
            ':description' => $data['description'],
            ':price' => $data['price'],
            ':duration_minutes' => $data['duration_minutes'],
            ':modality' => $data['modality'],
            ':color' => $data['color'],
            ':is_active' => $data['is_active'],
        ));

        return $statement->fetch();
    }

    private function disciplineBelongsToCompany(string $companyId, string $disciplineId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM disciplines WHERE id = :id AND company_id = :company_id LIMIT 1'
        );
        $statement->execute(array(
            ':id' => $disciplineId,
            ':company_id' => $companyId,
        ));

        return (bool) $statement->fetchColumn();
    }
}
