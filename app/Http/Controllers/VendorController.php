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
        // $this->middleware('permissions:vendor_view')->only('index');
        // $this->middleware('permissions:vendor_create')->only(['store', 'update']);
        // $this->middleware('permissions:vendor_delete')->only(['destroy']);
        // $this->middleware('permissions:vendor_restore')->only(['restore']);
        // $this->middleware('permissions:vendor_force_delete')->only(['forceDelete']);
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
                $result->total_price = $result->items_sum_total + $result->extra_expense_sum_price;
                $result->remainder = $result->total_price - $result->payments_sum_amount;
                return $result;
            });

            return response()->json(["data" => $results,'total' => $total,  "extraTotal" => ['vendors' => $allTotal, 'trash' => $trashTotal]]);
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

            $query = new Purchase();
            $query =  $query->whereVendorId($id)->with('vendor')->withSum('payments', 'amount')->withSum('extraExpense', 'price')->withSum('items', 'total');
            $purchases = $query->latest()->get();
            $purchases = collect($purchases);
            $purchases = $purchases->map(function ($result) {
                $result->total_price = $result->items_sum_total + $result->extra_expense_sum_price;
                $result->remainder = $result->total_price - $result->payments_sum_amount;
                $result->paid_amount = $result->total_price - $result->remainder;
                return $result;
            });
            return response()->json(['purchases' => $purchases]);
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
    public function changeStatus(Request $request)
    {
        try {
            $status = $request->status;
            if ($status == false) {
                $vendor = Vendor::where('id', $request->id)->update(['status'  => true]);
            } else {
                $vendor = Vendor::where('id', $request->id)->update(['status'  => false]);
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
                'phone_number' => 'required',
                'address' => 'required',

            ],
            [
                'organization_name.required' => "اسم کمپنی ضروری میباشد",
                'name.required' => "اسم ضروری میباشد",
                'address.required' => "آدرس ضروری میباشد",
                'phone_number.required' => "شماره تماس اجباری میباشد",
                'phone_number.unique' => "شماره تیلفون ذیل موجود می باشد",

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
