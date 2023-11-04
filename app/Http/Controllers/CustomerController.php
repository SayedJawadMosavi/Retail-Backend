<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LDAP\Result;
use PhpParser\Node\Stmt\Return_;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function __construct()
    {
        $this->middleware('permissions:customer_view')->only('index');
        $this->middleware('permissions:customer_create')->only(['store', 'update']);
        $this->middleware('permissions:customer_delete')->only(['destroy']);
        $this->middleware('permissions:customer_restore')->only(['restore']);
        $this->middleware('permissions:customer_force_delete')->only(['forceDelete']);
    }
    public $path = "images/customers";



    public function index(Request $request)
    {



        try {
            $query = new Customer();
            $searchCol = ['first_name', 'last_name', 'email', 'phone_number', 'created_at','tazkira_number','address'];
            $query = $this->search($query, $request, $searchCol);
            $query = $query->withSum('payments', 'amount')->withSum('items', 'cost')->withSum('items', 'total');



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
                $result->total_price = $result->items_sum_total;
                $result->remainder = $result->total_price - $result->payments_sum_amount;
                return $result;
            });

            return response()->json(["data" => $results, 'total' => $total, "extraTotal" => ['customers' => $allTotal, 'trash' => $trashTotal]]);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function getRelations()
    {
        return   [
            'items' => function ($query) {
                $query->select('customer_id', DB::raw('SUM(available_cost) as total_price'), DB::raw('MAX(created_at) as start_date'), DB::raw('MIN(created_at) as end_date'))->groupBy('customer_id');
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
            $customer = new Customer();
            $attributes = $request->only($customer->getFillable());
            $attributes['created_at'] = $request->date;
            $attributes['status'] = 1;
            if ($request->hasFile('profile')) {
                $attributes['profile']  = $this->storeFile($request->file('profile'), $this->path);
            }
            $customer =  $customer->create($attributes);

            DB::commit();
            return response()->json($customer, 200);
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
        #######
    }


    public function getEmployees(Request $request)
    {
        try {
            $employee = Employee::select(['id', 'salary', 'first_name', 'last_name'])->latest()->get();
            return response()->json($employee);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {

        $this->storeValidation($request);
        try {
            DB::beginTransaction();
            $customer = Customer::find($request->id);
            $attributes = $request->only($customer->getFillable());
            $customer->update($attributes);
            DB::commit();
            return response()->json($customer, 202);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }
    public function changeStatus($id,$value)
    {


        try {

            if ($id==1) {
                $pacakge=Customer::where('id',$value)->update(['status'  =>0]);
            }else if ($id==0) {
                $pacakge=Customer::where('id',$value)->update(['status'  =>1]);

            }
            return response()->json($pacakge, 202);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
    public function restore(string $id)
    {
        try {
            $ids = explode(",", $id);
            Customer::whereIn('id', $ids)->withTrashed()->restore();
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
            Customer::whereIn('id', $ids)->withTrashed()->forceDelete();
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
            $result = Customer::whereIn("id", $ids)->delete();
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
                'first_name' => 'required',
                'last_name' => 'required',
                'phone_number' => 'required',
                'tazkira_number' => 'required'

            ],
            [
                'first_name.required' => "The name is required",
                'last_name.required' => "The last name is required",
                'phone_number.unique' => "phone number is exist",
                'tazkira_number.unique' => 'this tazkira_number is used before!',
                'phone_number.required' => "Phone number is required",
                'phone_number.unique' => "phone number should be unique",
                'tazkira_number.required' => "site id  is required",

            ]

        );
    }

    public function reports(Request $request)
    {
        try {
            $query = new Customer();
            $searchCol = ['first_name', 'last_name', 'email', 'phone_number', 'created_at','tazkira_number'];
            $query = $this->search($query, $request, $searchCol);
            $date1 = new DateTime($request->start_date);
            $startDate = $date1->format('Y-m-d');
            $date1 = new DateTime($request->end_date);
            $endDate = $date1->format('Y-m-d');
            $query =     $query->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate]);
            $query = $query->withSum('payments', 'amount');


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

            return response()->json(["data" => $results, 'total' => $total, "extraTotal" => ['customers' => $allTotal, 'trash' => $trashTotal]]);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
}
