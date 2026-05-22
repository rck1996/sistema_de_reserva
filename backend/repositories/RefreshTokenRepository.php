<?php

declare(strict_types=1);

final class RefreshTokenRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(string $userId, ?string $companyId, string $refreshToken, int $ttlSeconds): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO refresh_tokens (user_id, company_id, token_hash, expires_at)
             VALUES (:user_id, :company_id, :token_hash, NOW() + (:ttl || \' seconds\')::interval)'
        );
        $statement->execute(array(
            ':user_id' => $userId,
            ':company_id' => $companyId,
            ':token_hash' => hash('sha256', $refreshToken),
            ':ttl' => (string) $ttlSeconds,
        ));
    }

    public function findValid(string $refreshToken): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM refresh_tokens
             WHERE token_hash = :token_hash AND revoked_at IS NULL AND expires_at > NOW()
             LIMIT 1'
        );
        $statement->execute(array(':token_hash' => hash('sha256', $refreshToken)));
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function revoke(string $refreshToken): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE refresh_tokens SET revoked_at = NOW()
             WHERE token_hash = :token_hash AND revoked_at IS NULL'
        );
        $statement->execute(array(':token_hash' => hash('sha256', $refreshToken)));
    }
}
