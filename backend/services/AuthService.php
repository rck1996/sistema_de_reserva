<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../support/jwt.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/RefreshTokenRepository.php';

final class AuthService
{
    private array $config;

    public function __construct(
        private UserRepository $users,
        private RefreshTokenRepository $refreshTokens
    ) {
        $this->config = require __DIR__ . '/../config/app.php';
    }

    public function login(string $companySlug, string $email, string $password): array
    {
        $user = $this->users->findByCompanySlugAndEmail($companySlug, $email);
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            throw new RuntimeException('Credenciales invalidas');
        }

        return $this->issueTokenPair($user);
    }

    public function loginCustomer(string $email, string $password): array
    {
        $user = $this->users->findGlobalCustomerByEmail($email);
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            throw new RuntimeException('Credenciales invalidas');
        }

        return $this->issueTokenPair($user);
    }

    public function registerCompany(string $companyName, string $companySlug, string $email, string $password, string $username = ''): array
    {
        $user = $this->users->createCompanyAdmin($companyName, $companySlug, $email, $password, $username);

        return $this->issueTokenPair($user);
    }

    public function registerCustomer(string $companySlug, string $email, string $password, string $firstName, string $lastName, string $phone = '', string $notes = ''): array
    {
        $user = $this->users->createCompanyCustomer($companySlug, $email, $password, $firstName, $lastName, $phone, $notes);

        return $this->issueTokenPair($user);
    }

    public function registerGlobalCustomer(string $email, string $password, string $firstName, string $lastName, string $phone = '', string $notes = ''): array
    {
        $user = $this->users->createGlobalCustomer($email, $password, $firstName, $lastName, $phone, $notes);

        return $this->issueTokenPair($user);
    }

    public function refresh(string $refreshToken): array
    {
        $record = $this->refreshTokens->findValid($refreshToken);
        if ($record === null) {
            throw new RuntimeException('Refresh token invalido');
        }

        $user = $this->users->findById((string) $record['user_id']);
        if ($user === null) {
            throw new RuntimeException('Usuario no encontrado');
        }

        $this->refreshTokens->revoke($refreshToken);

        return $this->issueTokenPair($user);
    }

    public function logout(string $refreshToken): void
    {
        if ($refreshToken !== '') {
            $this->refreshTokens->revoke($refreshToken);
        }
    }

    public function me(string $accessToken): array
    {
        $claims = jwt_decode($accessToken, $this->accessSecret());
        $user = $this->users->findById((string) $claims['user_id']);
        if ($user === null) {
            throw new RuntimeException('Usuario no encontrado');
        }

        return $this->userPayload($user);
    }

    private function issueTokenPair(array $user): array
    {
        $claims = array(
            'user_id' => (string) $user['id'],
            'company_id' => (string) ($user['company_id'] ?? ''),
            'role' => (string) $user['role'],
        );
        $accessToken = jwt_encode($claims, $this->accessSecret(), $this->accessTtl());
        $refreshToken = bin2hex(random_bytes(48));
        $this->refreshTokens->create((string) $user['id'], $user['company_id'] ?? null, $refreshToken, $this->refreshTtl());

        return array(
            'ok' => true,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->accessTtl(),
            'user' => $this->userPayload($user),
        );
    }

    private function userPayload(array $user): array
    {
        return array(
            'id' => (string) $user['id'],
            'company_id' => (string) ($user['company_id'] ?? ''),
            'company_slug' => (string) ($user['company_slug'] ?? ''),
            'company_name' => (string) ($user['company_name'] ?? ''),
            'email' => (string) $user['email'],
            'username' => (string) ($user['username'] ?? ''),
            'role' => (string) $user['role'],
        );
    }

    private function accessSecret(): string
    {
        $secret = (string) ($this->config['jwt']['secret'] ?? '');
        if ($secret === '') {
            throw new RuntimeException('JWT_SECRET no configurado');
        }

        return $secret;
    }

    private function accessTtl(): int
    {
        return max(60, (int) ($this->config['jwt']['access_ttl'] ?? 900));
    }

    private function refreshTtl(): int
    {
        return max(3600, (int) ($this->config['jwt']['refresh_ttl'] ?? 2592000));
    }
}
