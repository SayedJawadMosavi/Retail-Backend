<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = new Stock();
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

            return response()->json(["data" => $results,'total' => $total,  "extraTotal" => ['stocks' => $allTotal, 'trash' => $trashTotal]]);
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
            $category = new Stock();
            // $attributes = $request->only($category->getFillable());
            // $attributes['status'] = 'false';
            $category->create([
                'name' => $request->name,
                'address' => $request->address,
                'status' => true,
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
    public function show(string $id)
    {

        try {
            $stock = new Stock();
            $stock = $stock->with(['items' => fn ($q) => $q->withTrashed(), 'items.product' => fn ($q) => $q->withTrashed()])->withTrashed()->find($id);
            // $stock->total_price = $stock->items_sum_total+$stock->extra_expense_sum_price;
            // $stock->remainder  = $stock->total_price - $stock->payments_sum_amount;

            return response()->json($stock);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Stock $stock)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $this->storeValidation($request);
        try {
           
            DB::beginTransaction();
        
            $stock = Stock::find($request->id);
            $attributes = $request->only($stock->getFillable());
            $stock->update($attributes);
            DB::commit();
            return response()->json($stock, 202);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function restore(string $id)
    {
        try {
            $ids = explode(",", $id);
            Stock::whereIn('id', $ids)->withTrashed()->restore();
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
            Stock::whereIn('id', $ids)->withTrashed()->forceDelete();
            DB::commit();
            return response()->json(true, 203);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 400);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {

        try {
            DB::beginTransaction();
            $ids  = explode(",", $id);
            $result = Stock::whereIn("id", $ids)->delete();
            DB::commit();
            return response()->json($result, 206);
        } catch (\Exception $th) {
            //throw $th;
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }

    public function storeValidation($request)
    {
        return $request->validate(
            [
                'name' => 'required',
            ],
            [
                'name.required' => "اسم گدام ضروری میباشد",

            ]

        );
    }

    public function changeStatus(Request $request)
    {
        try {
            $status = $request->status;
            if ($status == false) {
                $product = Stock::where('id', $request->id)->update(['status'  => true]);
            } else {
                $product = Stock::where('id', $request->id)->update(['status'  => false]);
            }
            return response()->json($product, 202);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
}
