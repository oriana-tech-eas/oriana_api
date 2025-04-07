<?php

namespace App\Http\Controllers;

use App\Http\Requests\Company\StoreCompanyRequest;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $user = User::find(Auth::id());

        // Si es super-admin, ver todas las empresas
        if ($user->hasRole('super-admin')) {
            $companies = Company::with('owner')->paginate(10);
        } else {
            // Para usuarios normales, ver sólo sus empresas
            $companies = Company::whereHas('users', function ($query) use ($user) {
                $query->where('users.id', $user->id);
            })->paginate(10);
        }

        return response()->json(['data' => $companies]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $user = User::find(Auth::id());
        $data = $request->validated();

        try {
            DB::beginTransaction();

            $company = new Company;
            $company->name = $data['name'];
            $company->address = $data['address'] ?? null;
            $company->phone = $data['phone'] ?? null;

            // Si es super-admin y eligió un owner
            if ($user->hasRole('super-admin') && isset($data['owner_id'])) {
                $ownerId = $data['owner_id'];
                // Verificar que el usuario existe
                $owner = User::findOrFail($ownerId);
                $company->owner_id = $owner->id;
            } else {
                // Si no es super-admin o no eligió owner, el creador es el owner
                $company->owner_id = $user->id;
            }

            $company->save();

            if (!$user->hasRole('super-admin')) {
                // Si no es super-admin, asignar la empresa al creador
                $user->current_company_id = $company->id;
                $user->save();
            }

            // Crear relación en la tabla pivot company_user
            $companyUser = new CompanyUser;
            $companyUser->company_id = $company->id;
            $companyUser->user_id = $company->owner_id;
            $companyUser->save();

            DB::commit();

            return response()->json(['message' => 'Empresa creada con éxito', 'data' => $company], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $user = User::find(Auth::id());

        // Obtener la empresa con relaciones
        $company = Company::with('owner')->findOrFail($id);

        // Verificar permisos
        if (
            ! $user->hasRole('super-admin') &&
            ! CompanyUser::where('company_id', $id)->where('user_id', $user->id)->exists()
        ) {
            return response()->json(['error' => 'No tienes permiso para ver esta empresa'], 403);
        }

        return response()->json(['data' => $company]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreCompanyRequest $request, string $id): JsonResponse
    {
        $user = User::find(Auth::id());
        $company = Company::findOrFail($id);
        $data = $request->validated();

        // Verificar permisos
        if (! $user->hasRole('super-admin') && $company->owner_id !== $user->id) {
            return response()->json(['error' => 'No tienes permiso para actualizar esta empresa'], 403);
        }

        try {
            DB::beginTransaction();

            $company->name = $data['name'];
            $company->description = $data['description'] ?? $company->description;
            $company->address = $data['address'] ?? $company->address;
            $company->phone = $data['phone'] ?? $company->phone;
            $company->email = $data['email'] ?? $company->email;

            // Solo super-admin puede cambiar el owner
            if ($user->hasRole('super-admin') && isset($data['owner_id'])) {
                $newOwnerId = $data['owner_id'];
                // Verificar que el usuario existe
                $newOwner = User::findOrFail($newOwnerId);

                // Si cambia el owner, asegurar relación en tabla pivot
                if ($company->owner_id !== $newOwnerId) {
                    $company->owner_id = $newOwnerId;

                    // Asegurar que el nuevo owner está en la tabla pivot
                    if (! CompanyUser::where('company_id', $id)->where('user_id', $newOwnerId)->exists()) {
                        $companyUser = new CompanyUser;
                        $companyUser->company_id = $company->id;
                        $companyUser->user_id = $newOwnerId;
                        $companyUser->save();
                    }
                }
            }

            $company->save();
            DB::commit();

            return response()->json(['message' => 'Empresa actualizada con éxito', 'data' => $company], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = User::find(Auth::id());
        $company = Company::findOrFail($id);

        // Verificar permisos
        if (! $user->hasRole('super-admin') && $company->owner_id !== $user->id) {
            return response()->json(['error' => 'No tienes permiso para eliminar esta empresa'], 403);
        }

        try {
            // Eliminar relaciones en la tabla pivot primero
            CompanyUser::where('company_id', $id)->delete();

            // Eliminar la empresa
            $company->delete();

            return response()->json(['message' => 'Empresa eliminada con éxito'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Listar usuarios disponibles para asignar como owner (solo super-admin)
     */
    public function availableOwners(): JsonResponse
    {
        $user = User::find(Auth::id());

        if (! $user->hasRole('super-admin')) {
            return response()->json(['error' => 'No tienes permiso para ver esta información'], 403);
        }

        $users = User::select('id', 'name', 'email')->get();

        return response()->json(['data' => $users]);
    }
}
