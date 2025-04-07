<?php

namespace App\Services\Contacts;

use App\Interfaces\ContactRepositoryInterface;
use App\Models\Contact;
use Illuminate\Support\Facades\Auth;

class ContactService
{
    protected ContactRepositoryInterface $contactRepository;

    public function __construct(ContactRepositoryInterface $contactRepository)
    {
        $this->contactRepository = $contactRepository;
    }

    /**
     * Get contacts with filtering and pagination
     */
    public function getContacts(array $params): array
    {
        $companyId = Auth::user()->current_company_id;
        $contacts = $this->contactRepository->getFilteredContacts($companyId, $params);
        $lastUpdate = $this->contactRepository->getLastUpdate($companyId);
        $recentlyAdded = $this->contactRepository->getRecentlyAddedCount($companyId, 3);

        return [
            'data' => $contacts->toArray(),
            'recently_added' => $recentlyAdded,
            'last_update' => $lastUpdate,
        ];
    }

    /**
     * Create a new contact
     */
    public function createContact(array $data): Contact
    {
        $data['company_id'] = Auth::user()->current_company_id;
        $data['user_id'] = Auth::user()->id;

        return $this->contactRepository->create($data);
    }

    /**
     * Get contact by ID
     */
    public function getContactById(string $id): ?Contact
    {
        return $this->contactRepository->findById($id);
    }

    /**
     * Update contact
     */
    public function updateContact(string $id, array $data): ?Contact
    {
        return $this->contactRepository->update($id, $data);
    }

    /**
     * Delete contact
     */
    public function deleteContact(string $id): bool
    {
        return $this->contactRepository->delete($id);
    }
}
