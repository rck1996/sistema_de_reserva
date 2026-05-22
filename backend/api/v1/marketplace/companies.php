<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../database/connection.php';
require_once __DIR__ . '/../../../support/http.php';
require_once __DIR__ . '/../../../repositories/MarketplaceRepository.php';

try {
    $repository = new MarketplaceRepository(backend_pdo());
    $slug = input_string($_GET, 'slug', false);
    if ($slug !== '') {
        json_response(array('ok' => true, 'data' => $repository->companyBySlug($slug)));
    }

    json_response(array('ok' => true, 'data' => $repository->publicCompanies()));
} catch (Throwable $exception) {
    json_response(array('ok' => false, 'error' => $exception->getMessage()), 400);
}
