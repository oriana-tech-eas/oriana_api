<?php

namespace App\Interfaces;

use App\Models\Contact;
use Illuminate\Pagination\LengthAwarePaginator;

interface ContactRepositoryInterface
{
    public function getFilteredContacts(int $companyId, array $params): LengthAwarePaginator;

    public function getLastUpdate(int $companyId): ?string;

    public function getRecentlyAddedCount(int $companyId, int $days): int;

    public function create(array $data): Contact;

    public function findById(string $id): ?Contact;

    public function update(string $id, array $data): ?Contact;

    public function delete(string $id): bool;
}
