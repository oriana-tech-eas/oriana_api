<?php

namespace App\Http\Controllers\Categories;

use App\Http\Controllers\Controller;
use App\Models\Categories;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategoriesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $categoryType = $request->query('category_type', null);

        $categoriesQuery = Categories::query();

        if ($categoryType) {
            $categoriesQuery->where('category_type', $categoryType);
        }

        $categories = $categoriesQuery->where(
            function ($query) {
                $query->where('company_id', Auth::user()->company_id)
                    ->orWhere('is_shared', true);
            }
        )->get(['id', 'name', 'is_shared', 'category_type']);

        return response()->json($categories);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|min:3',
            'category_type' => 'required',
        ]);

        $category = new Categories;
        $category->name = $request->name;
        $category->category_type = $request->category_type;
        $category->company_id = Auth::user()->company_id;
        $category->is_shared = false;
        $category->save();

        return response()->json($category);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $category = Categories::where('id', $id)->first();

        if (! $category) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        if ($category->is_shared) {
            return response()->json(['message' => 'No se puede eliminar una categoría predeterminada'], 403);
        }

        try {
            $category->delete();
        } catch (\Exception $e) {
            return response()->json(['message' => 'No se pudo eliminar la categoría'], 500);
        }

        return response()->json(['message' => 'Categoria eliminada'], 200);
    }
}
