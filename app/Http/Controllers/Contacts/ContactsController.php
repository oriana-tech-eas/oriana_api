<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contacts\StoreContactRequest;
use App\Services\Contacts\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactsController extends Controller
{
    protected ContactService $contactService;

    public function __construct(ContactService $contactService)
    {
        $this->contactService = $contactService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->contactService->getContacts($request->all());

        return response()->json($result);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreContactRequest $request): JsonResponse
    {
        try {
            $contact = $this->contactService->createContact($request->validated());

            return response()->json(['message' => 'Customer created', 'data' => $contact], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $contact = $this->contactService->getContactById($id);

        return response()->json($contact);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreContactRequest $request, string $id): JsonResponse
    {
        try {
            $contact = $this->contactService->updateContact($id, $request->validated());

            return response()->json(['message' => 'Contact updated', 'data' => $contact], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->contactService->deleteContact($id);

            return response()->json(['message' => 'Contact deleted'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
