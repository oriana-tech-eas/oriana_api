<?php

namespace App\Repositories\Contacts;

use App\Interfaces\ContactRepositoryInterface;
use App\Models\Contact;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class ContactRepository implements ContactRepositoryInterface
{
    /**
     * Get filtered  with pagination
     */
    public function getFilteredContacts(int $companyId, array $params): LengthAwarePaginator
    {
        $query = Contact::where('company_id', '=', $companyId);

        if (isset($params['contacts_type'])) {
            $query->where('contacts_type', $params['contacts_type']);
        }

        if (isset($params['query'])) {
            $search = $params['query'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('document', 'LIKE', "%{$search}%");
            });
        }

        return $query->paginate(10);
    }

    /**
     * Get last update timestamp
     */
    public function getLastUpdate(int $companyId): ?string
    {
        return Contact::where('company_id', '=', $companyId)->max('updated_at');
    }

    /**
     * Get count of recently added contacts
     */
    public function getRecentlyAddedCount(int $companyId, int $days): int
    {
        return Contact::where('company_id', '=', $companyId)
            ->where('created_at', '>=', Carbon::now()->subDays($days))
            ->count();
    }

    /**
     * Create a new contact
     */
    public function create(array $data): Contact
    {
        $contact = new Contact;
        $contact->fill($data);
        $contact->save();

        return $contact;
    }

    /**
     * Find contact by ID
     */
    public function findById(string $id): ?Contact
    {
        return Contact::find($id);
    }

    /**
     * Update contact
     */
    public function update(string $id, array $data): ?Contact
    {
        $contact = $this->findById($id);
        if ($contact) {
            $contact->fill($data);
            $contact->save();
        }

        return $contact;
    }

    /**
     * Delete contact
     */
    public function delete(string $id): bool
    {
        $contact = $this->findById($id);
        if ($contact) {
            return $contact->delete();
        }

        return false;
    }
}
