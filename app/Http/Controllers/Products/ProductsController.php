<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Models\Products;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $company = Auth::user()->company_id;
        $query = Products::where('company_id', '=', $company);

        if ($request->has('query')) {
            $search = $request->input('query');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('document', 'LIKE', "%{$search}%");
            });
        }

        $products = $query->paginate(10);
        $lastUpdate = Products::where('company_id', '=', $company)->max('updated_at');
        $recentlyAdded = Products::where('company_id', '=', $company)->where('created_at', '>=', now()->subDays(3))->count();

        return response()->json([
            'data' => $products->toArray(),
            'recently_added' => $recentlyAdded,
            'last_update' => $lastUpdate,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $company = Auth::user()->company_id;

        $request->validate([
            'name' => 'required|min:3',
            'description' => 'min:3',
            'price' => 'required',
            'category_id' => 'required',
        ]);
        
        $product = new Products();
        $product->company_id = $company;
        $product->name = $request->name;
        $product->description = $request->description;
        $product->price = $request->price;
        $product->category_id = $request->category_id;
        $product->save();

        $product->taxes()->sync($request->tax_ids);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Products::find($id);

        return response()->json($product);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|min:3',
            'description' => 'min:3',
            'price' => 'required',
            'category_id' => 'required',
        ]);

        $product = Products::find($id);
        $product->name = $request->name;
        $product->description = $request->description;
        $product->price = $request->price;
        $product->category_id = $request->category_id;
        $product->save();

        $product->taxes()->sync($request->tax_ids);

        return response()->json(['message' => 'Product updated successfully']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $product = Products::find($id);
        $product->delete();

        return response()->json(['message' => 'Product deleted']);
    }
}
