<?php

namespace App\Http\Controllers;

use App\Models\ExpenseIncomeCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseIncomeCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = new ExpenseIncomeCategory();
            $searchCol = ['name', 'created_at'];
            $query = $this->search($query, $request, $searchCol);
            $trashTotal = clone $query;
            $trashTotal = $trashTotal->onlyTrashed()->count();

            $allTotal = clone $query;
            $allTotal = $allTotal->count();
            if ($request->tab == 'trash') {
                $query = $query->onlyTrashed();
            }
            $query = $query->latest()->paginate($request->itemPerPage);
            $results = collect($query->items());
            $total = $query->total();

            return response()->json(["data" => $results,'total' => $total,  "extraTotal" => ['categories' => $allTotal, 'trash' => $trashTotal]]);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->storeValidation($request);

        // return $request->all();
        try {
            DB::beginTransaction();
            $category = new ExpenseIncomeCategory();
            // $attributes = $request->only($category->getFillable());
            // $attributes['status'] = 'false';
            $category->create([
                'name' => $request->name,
                'type' => $request->type,
                'status' => 1,
            ]);
            DB::commit();
            return response()->json($category, 201);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(ExpenseIncomeCategory $expenseIncomeCategory)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ExpenseIncomeCategory $expenseIncomeCategory)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {

        try {
            DB::beginTransaction();
            $category =  ExpenseIncomeCategory::find($request->id);
            // $attributes = $request->only($category->getFillable());
            // $attributes['status'] = 'false';
            $category->update([
                'name' => $request->name,
                'type' => $request->type,

            ]);
            DB::commit();
            return response()->json($category, 202);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Int $id)
    {

        try {
            DB::beginTransaction();
            $ids  = explode(",", $id);
            $result = ExpenseIncomeCategory::whereIn("id", $ids)->delete();
            DB::commit();
            return response()->json($result, 206);
        } catch (\Exception $th) {
            //throw $th;
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }

    public function restore(string $id)
    {
        try {
            $ids = explode(",", $id);
            ExpenseIncomeCategory::whereIn('id', $ids)->withTrashed()->restore();
            return response()->json(true, 203);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function forceDelete(string $id)
    {
        try {
            DB::beginTransaction();
            $ids = explode(",", $id);
            ExpenseIncomeCategory::whereIn('id', $ids)->withTrashed()->forceDelete();
            DB::commit();
            return response()->json(true, 203);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 400);
        }
    }

    public function storeValidation($request)
    {
        return $request->validate(
            [
                'name' => 'required',
            ],
            [
                'name.required' => "کتګوری نوم ضروری ده",

            ]

        );
    }
    public function changeStatus($id,$value)
    {


        try {

            if ($id==1) {
                $category=ExpenseIncomeCategory::where('id',$value)->update(['status'  =>0]);
            }else if ($id==0) {
                $category=ExpenseIncomeCategory::where('id',$value)->update(['status'  =>1]);

            }
            return response()->json($category, 202);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
    public function getCategory(Request $request)
    {
        try {
            $category = ExpenseIncomeCategory::select(['id', 'name'])->where('status', 1)->get();
            return response()->json($category);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
    public function getExpenseIncomeCategory($type)
    {
        // $type=$type=="expense" ? 'outgoing'  :'incoming';
        try {
            $category = ExpenseIncomeCategory::select(['id', 'name'])->where('status', 1)->where('type',$type)->get();
            return response()->json($category);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
}
