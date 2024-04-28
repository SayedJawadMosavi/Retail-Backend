<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VendorController extends Controller
{
    public function __construct()
    {
        $this->middleware('permissions:vendor_view')->only('index');
        $this->middleware('permissions:vendor_create')->only(['store', 'update']);
        $this->middleware('permissions:vendor_delete')->only(['destroy']);
        $this->middleware('permissions:vendor_restore')->only(['restore']);
        $this->middleware('permissions:vendor_force_delete')->only(['forceDelete']);
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = new Vendor();
            $searchCol = ['organization_name', 'name', 'email', 'phone_number', 'created_at'];
            $query = $this->search($query, $request, $searchCol);
            $query = $query->withSum('payments', 'amount')->withSum('extraExpense', 'price')->withSum('items', 'yen_cost')->withSum('items', 'total');
            $trashTotal = clone $query;
            $total_purchase = clone $query;
            $total_amount = $total_purchase->sum('total_amount');
            $total_paid = $total_purchase->sum('total_paid');
            $trashTotal = $trashTotal->onlyTrashed()->count();

            $allTotal = clone $query;
            $allTotal = $allTotal->count();
            if ($request->tab == 'trash') {
                $query = $query->onlyTrashed();
            }
            $query = $query->latest()->paginate($request->itemPerPage);
            $results = collect($query->items());
            $total = $query->total();
            $results = $results->map(function ($result) {
                $result->total_price = round($result->items_sum_total + $result->extra_expense_sum_price,2);
                $result->remainder = round($result->total_price - $result->payments_sum_amount,2);
                return $result;
            });

            return response()->json(["data" => $results,'total' => $total,  "extraTotal" => ['vendors' => $allTotal, 'trash' => $trashTotal], 'vendor_info' => ['total_amount' => $total_amount, 'total_paid' => $total_paid, 'total_reminder'  => $total_amount - $total_paid]]);
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
    public function getRelations()
    {
        return   [
            'items' => function ($query) {
                $query->select('vendor_id', DB::raw('SUM(cost) as total_price'), DB::raw('MAX(created_at) as start_date'), DB::raw('MIN(created_at) as end_date'))->groupBy('vendor_id');
            },

        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->storeValidation($request);
        try {
            DB::beginTransaction();
            $vendor = new Vendor();
            $attributes = $request->only($vendor->getFillable());
            $attributes['created_at'] = $request->date;
            $attributes['status'] = 1;
            $vendor =  $vendor->create($attributes);

            DB::commit();
            return response()->json($vendor, 201);
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
        //
        try {

            $vendor = new Vendor();
            $vendor = $vendor->with(['payments' => fn ($q) => $q->withTrashed(), 'purchases' => fn ($q) => $q->withTrashed(), 'items' => fn ($q) => $q->withTrashed(), 'items.product' => fn ($q) => $q->withTrashed()])->withTrashed()->withSum('payments', 'amount')->withSum('purchases', 'total_amount')->withSum('purchases', 'total_paid')->find($id);
            $vendor->remainder  = round($vendor->total_amount - $vendor->total_paid, 2);

            return response()->json($vendor);
        } catch (\Exception $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Vendor $vendor)
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

            $vendor = Vendor::find($request->id);
            $attributes = $request->only($vendor->getFillable());
            $vendor->update($attributes);
            DB::commit();
            return response()->json($vendor, 202);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }

    public function changeStatus($id,$value)
    {
        try {
            if ($id==1) {
                $vendor=Vendor::where('id',$value)->update(['status'  =>0]);
            }else if ($id==0) {
                $vendor=Vendor::where('id',$value)->update(['status'  =>1]);
            }
            return response()->json($vendor, 202);
        } catch (\Throwable $th) {
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
            Vendor::whereIn('id', $ids)->withTrashed()->restore();
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
            Vendor::whereIn('id', $ids)->withTrashed()->forceDelete();
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
            $result = Vendor::whereIn("id", $ids)->delete();
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
                'organization_name' => 'required',
                'name' => 'required',


            ],
            [
                'organization_name.required' => "د کمپني نوم ضروری ده",
                'name.required' => "نوم ضروری ده",


            ]

        );
    }


    public function vendorPurchase(Request $request)
    {
        try {
            $query = new Purchase();
            $query = $query->whereVendorId($request->id)->with('vendor')->withSum('payments', 'amount')->withSum('extraExpense', 'price')->withSum('items', 'total');

            $results = $query->latest()->get();
            $results = collect($results);
            $results = $results->map(function ($result) {
                $result->total_price = $result->items_sum_total + $result->extra_expense_sum_price;
                $result->remainder = $result->total_price - $result->payments_sum_amount;
                return $result;
            });
            return response()->json($results);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
}
