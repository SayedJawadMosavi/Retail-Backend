<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DepositWithdraw;
use App\Models\Sell;
use App\Models\TreasuryLog;
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
            $searchCol = ['first_name', 'last_name', 'email', 'phone_number', 'created_at', 'tazkira_number', 'address', 'total_amount'];
            $query = $this->search($query, $request, $searchCol);
            $total_sell = clone $query;
            $total_amount = $total_sell->sum('total_amount');
            $total_paid = $total_sell->sum('total_paid');
            $query = $query->withSum('payments', 'amount')->withSum('items', 'cost')->withSum('items', 'total');



            $trashTotal = clone $query;
            $trashTotal = $trashTotal->onlyTrashed()->count();

            $allTotal = clone $query;
            $allTotal = $allTotal->count();
            if ($request->tab == 'trash') {
                $query = $query->onlyTrashed();
            }
            $query = $query->orderByRaw('GREATEST(total_amount - total_paid, 0) DESC');
            $query = $query->latest()->paginate($request->itemPerPage);

            $results = collect($query->items());
            $total = $query->total();
            $results = $results->map(function ($result) {
                $result->total_price = $result->items_sum_total;
                $result->remainder = $result->total_price - $result->payments_sum_amount;
                return $result;
            });

            return response()->json(["data" => $results, 'total' => $total, "extraTotal" => ['customers' => $allTotal, 'trash' => $trashTotal], 'customer_info' => ['total_amount' => $total_amount, 'total_paid' => $total_paid, 'total_reminder'  => $total_amount - $total_paid]]);
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
            return response()->json($customer, 201);
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
            $customer = new Customer();
            $customer = $customer->with(['payments' => fn ($q) => $q->withTrashed(), 'sell' => fn ($q) => $q->withTrashed(), 'deposit_withdraw' => fn ($q) => $q->withTrashed(), 'items' => fn ($q) => $q->withTrashed(), 'items.product_stock.product' => fn ($q) => $q->withTrashed(), 'items.product_stock.stock' => fn ($q) => $q->withTrashed()])->withTrashed()->withSum('payments', 'amount')->withSum('sell', 'total_amount')->withSum('sell', 'total_paid')->find($id);
            // $customer->total_price = round($customer->sell_sum_total_amount,2);
            // $customer->total_paid = round($customer->sell_sum_total_paid,2);
            $customer->remainder  = round($customer->total_amount - $customer->total_paid, 2);

            return response()->json($customer);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json($th->getMessage(), 500);
        }
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
    public function changeStatus($id, $value)
    {


        try {

            if ($id == 1) {
                $pacakge = Customer::where('id', $value)->update(['status'  => 0]);
            } else if ($id == 0) {
                $pacakge = Customer::where('id', $value)->update(['status'  => 1]);
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



            ],
            [
                'first_name.required' => "نوم ضروری ده",

                'phone_number.unique' => "شماره تلفن موجود ده",
                'tazkira_number.unique' => 'دا تذکره شمیره مخکې کارول کیږي!',


            ]

        );
    }

    public function reports(Request $request)
    {
        try {
            $query = new Customer();
            $searchCol = ['first_name', 'last_name', 'email', 'phone_number', 'created_at', 'tazkira_number'];
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
    public function addIDepositWithdraw(Request $request)
    {


        try {
            $request->validate(
                [
                    'customer_id' => ['required'],
                    'created_at' => ['required', 'date', 'before_or_equal:' . now()],
                    'amount' => 'required|numeric|min:1',
                    'type' => 'required',

                ],
                [
                    'customer_id.required' => 'customer id is required!',
                    "created_at.required" => "date is required",
                    "created_at.date" => "date is not correct",
                    "created_at.before_or_equal" => "register date can not be bigger than now!",
                    'type.required' => 'type is required',
                    'amount.required' => 'amount is required ',
                    'amount.numeric' => 'amount must be numeric',
                    'amount.min' => 'amount can not be less than one',

                ],

            );
            DB::beginTransaction();
            $user_id = Auth::user()->id;
            $attributes = $request->all();
            $attributes['created_by'] = $user_id;

            $attributes['created_at'] = $attributes['created_at'];
            $attributes['customer_id'] = $request->customer_id;
            $item =  DepositWithdraw::create($attributes);

            $customer = Customer::find($request->customer_id);

            if ($attributes['type'] == "deposit") {
                if ($customer->total_paid == null) {
                    $customer->total_paid = 0;
                    $customer->save();

                }
                $customer->increment('total_paid', $request->amount);

                TreasuryLog::create(['table' => "customer_deposit_withdraw", 'table_id' => $item->id, 'type' => 'deposit', 'name' => ' راکړی لپاره' . ' (  پیرودونکي'  . '    ' . $customer->first_name . ' )', 'amount' => $request->amount, 'created_by' => $user_id, 'created_at' => $request->created_at,]);
            } else if ($attributes['type'] == "withdraw") {
                if ($customer->total_amount == null) {
                    $customer->total_amount = 0;
                    $customer->save();

                }
                $customer->increment('total_amount', $request->amount);

                TreasuryLog::create(['table' => "customer_deposit_withdraw", 'table_id' => $item->id, 'type' => 'withdraw', 'name' => ' ورکړی لپاره' . ' (  پیرودونکي'  . '    ' . $customer->first_name . ' )', 'amount' => $request->amount, 'created_by' => $user_id, 'created_at' => $request->created_at,]);
            }
            DB::commit();
            return response()->json($item, 201);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }
}
