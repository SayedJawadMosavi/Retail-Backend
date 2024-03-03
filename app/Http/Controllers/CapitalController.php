<?php

namespace App\Http\Controllers;

use App\Models\Capital;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CapitalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = new Capital();
            $searchCol = ['amount', 'created_at'];
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

            return response()->json(["data" => $results,'total' => $total,  "extraTotal" => ['capitals' => $allTotal, 'trash' => $trashTotal]]);
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
        try {
            DB::beginTransaction();
            $capital = new Capital();
            $attributes = $request->only($capital->getFillable());

            $capital =  $capital->create($attributes);
            DB::commit();
            return response()->json($capital, 201);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Capital $capital)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Capital $capital)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request,string $id)
    {
        $this->storeValidation($request);
        try {
            DB::beginTransaction();
            $capital =  Capital::find($request->id);
            $attributes = $request->only($capital->getFillable());
            $capital =  $capital->update($attributes);
            DB::commit();
            return response()->json($capital, 202);
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
            $result = Capital::whereIn("id", $ids)->delete();
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
            Capital::whereIn('id', $ids)->withTrashed()->restore();
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
            Capital::whereIn('id', $ids)->withTrashed()->forceDelete();
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
                'amount' => 'required',
            ],
            [
                'amount.required' => "د  مقدار ضروری ده",

            ]

        );
    }
}
