<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Models\Expenses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ExpensesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $company = Auth::user()->company_id;
        $query = Expenses::where('company_id', '=', $company);

        if ($request->has('query')){
            $search = $request->input('query');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'LIKE', "%{$search}%")
                    ->orWhere('amount', 'LIKE', "%{$search}%");
            });
        }

        $expenses = $query->paginate(10);

        return response()->json($expenses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $company = Auth::user()->company_id;

        $validator = Validator::make($request->all(), [
            'date' => 'required',
            'description' => 'required|min:3',
            'amount' => 'required',
            'category_id' => 'required|integer',
            'tax_id' => 'required|integer',
            'contact_id' => 'required|integer',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $expense = new Expenses();
            $expense->company_id = $company;
            $expense->user_id = Auth::id();
            $expense->expense_date = $request->date;
            $expense->description = $request->description;
            $expense->amount = $request->amount;
            $expense->category_id = $request->category_id;
            $expense->contact_id = $request->contact_id;
            $expense->save();
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
        
        $expense->tax()->sync($request->tax_id);

        return response()->json($expense);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $company = Auth::user()->company_id;

        $expense = Expenses::where('company_id', '=', $company)->where('id', '=', $id)->first();

        return response()->json($expense);
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
        //
    }
}
