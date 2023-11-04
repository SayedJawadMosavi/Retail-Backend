<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\IncomingOutgoing;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\SalaryPayment;
use App\Models\Sell;
use App\Models\TreasuryLog;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class DashboardController extends Controller
{
    //
    public function index()
    {
        $transactions = $this->transactions();

        // $expired_contract = $this->getContractExpired();

        $accountMoney_usd = $this->AccountPaymentUSD();
        return response()->json([
            'transactions' => $transactions, 'account_money_usd' => $accountMoney_usd,
            'allIncomeExpense' => $this->allExpenseIncome(),

            // 'expired_contract' =>$expired_contract,

        ]);
    }

    public function transactions()
    {
        return [User::count(), Employee::count(), Vendor::count(), Purchase::count(), Sell::count(), Customer::where('status', 1)->count(), Customer::where('status', 0)->count()];
    }
    public function allExpenseIncome()
    {
        return [TreasuryLog::whereType('deposit')->whereDate('created_at', \DB::raw('CURDATE()'))->sum('amount'), TreasuryLog::whereType('withdraw')->whereDate('created_at', \DB::raw('CURDATE()'))->sum('amount'), TreasuryLog::whereType('deposit')->whereDate('created_at', \DB::raw('CURDATE()'))->sum('amount') - TreasuryLog::whereType('withdraw')->whereDate('created_at', \DB::raw('CURDATE()'))->sum('amount')];
    }



    public function AccountPaymentUSD()
    {
        $query = new TreasuryLog();

        $totalIncomes_usd = clone $query;
        $totalIncomes_usd = $totalIncomes_usd->whereType('deposit')->sum('amount');
        $totalOutGoing_usd = clone $query;
        $totalOutGoing_usd = $totalOutGoing_usd->whereType('withdraw')->sum('amount');
        return $totalIncomes_usd - $totalOutGoing_usd;
    }

    public function reports(Request $request)
    {


        $request->validate(
            [
                'start_date' => ['required', 'date', 'before_or_equal:' . $request->end_date],
            ],
            [
                "start_date.date" => "Start Date is not correct",
                "start_date.before_or_equal" => "start date can not be bigger than end date!",
            ]

        );
        try {
            $type = $request->type;

            $date1 = new DateTime($request->start_date);
            $startDate = $date1->format('Y-m-d');

            $date1 = new DateTime($request->end_date);
            $endDate = $date1->format('Y-m-d');

            if ($type == 'income') {
                $data = IncomingOutgoing::whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])->where('type', 'incoming')->get();
                return response()->json($data);
            }
            if ($type == 'expense') {
                $data = IncomingOutgoing::whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])->where('type', 'outgoing')->get();
                return response()->json($data);
            }
            if ($type == 'salaries') {
                $data = SalaryPayment::with('employee:id,first_name,last_name,salary,job_title')->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])->get();
                return $data;
                return response()->json($data);
                // ExchangeMoney::where(DB::raw('Date(created_at)'), '>=', $request->start_date)->where(DB::raw('Date(created_at)'), '<=', $request->end)->get();
            }

            if ($type == 'employee') {
                $data = Employee::whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])->get();
                return response()->json($data);
                // ExchangeMoney::where(DB::raw('Date(created_at)'), '>=', $request->start_date)->where(DB::raw('Date(created_at)'), '<=', $request->end)->get();
            }
            if ($type == 'detail') {
                $data = PurchaseDetail::where('product_id', $request->detail_id)->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])->get();
                return response()->json($data);
                // ExchangeMoney::where(DB::raw('Date(created_at)'), '>=', $request->start_date)->where(DB::raw('Date(created_at)'), '<=', $request->end)->get();
            }
            if ($type == 'stock_detail') {
                $data = ProductStock::with('product')->where('stock_id', $request->detail_id)->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])->get();
                return response()->json($data);
                // ExchangeMoney::where(DB::raw('Date(created_at)'), '>=', $request->start_date)->where(DB::raw('Date(created_at)'), '<=', $request->end)->get();
            }
            if ($type == 'loan_payment') {
                $data =         EmployeeLoan::with('employee')->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])->where('employee_id', $request->employee_id)->get();

                return response()->json($data);
            }
            if ($type == 'product') {
                $data =         Product::with('category')->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])->get();

                return response()->json($data);
            }
            if ($type == 'customers') {
                $query = new Customer();
                $query = $query->withSum('payments', 'amount')->withSum('items', 'cost')->withSum('items', 'total');
                $query =     $query->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate]);
                $query = $query->latest()->get();

                $results = $query->map(function ($result) {
                    $result->total_price = $result->items_sum_total;
                    $result->remainder = $result->total_price - $result->payments_sum_amount;
                    return $result;
                });
                return response()->json($results);
            }
            if ($type == 'purchase') {
                $query = new Purchase();
                $query = $query->with('vendor', 'container')->withSum('payments', 'amount')->withSum('extraExpense', 'price')->withSum('items', 'total')->withSum('items', 'yen_cost');
                $query =     $query->whereBetween(DB::raw('DATE(purchase_date)'), [$startDate, $endDate]);
                $query = $query->latest()->get();

                $results = $query->map(function ($result) {
                    $result->total_price = $result->items_sum_total + $result->extra_expense_sum_price;
                    $result->remainder = $result->total_price - $result->payments_sum_amount;
                    return $result;
                });
                return response()->json($results);
            }
            if ($type == 'sell') {
                $query = new Sell();
                $searchCol = ['sell_date', 'city', 'address', 'sell_date'];

                $query = $this->search($query, $request, $searchCol);
                $query = $query->with('customer')->withSum('payments', 'amount')->withSum('items', 'total')->withSum('items', 'cost');
                $query =     $query->whereBetween(DB::raw('DATE(sell_date)'), [$startDate, $endDate]);
                $query = $query->latest()->get();

                $results = $query->map(function ($result) {
                    $result->total_price = $result->items_sum_total;
                    $result->remainder = $result->total_price - $result->payments_sum_amount;
                    return $result;
                });
                return response()->json($results);
            }
            if ($type == 'journal') {
                $query = new TreasuryLog();
                $searchCol = ['name', 'type', 'amount', 'created_by'];
                $query = $this->search($query, $request, $searchCol);
                $query = $query->with(['user:id,name']);
                $query =     $query->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate]);
                $query = $query->latest()->get();


                return response()->json($query);
            }
            //code...
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function getReports(Request $request)
    {


        if ($request->type == "salaries") {
            try {
                $query = new SalaryPayment();

                $searchCol = ['employee_id', 'created_at', 'employee.first_name', 'employee.last_name', "paid", "salary", 'present', 'absent'];
                $query = $this->search($query, $request, $searchCol);
                $query = $query->with('employee:id,first_name,last_name,salary,job_title');
                $date1 = new DateTime($request->start_date);
                $startDate = $date1->format('Y-m-d');
                $date1 = new DateTime($request->end_date);
                $endDate = $date1->format('Y-m-d');
                $query =     $query->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate]);
                $totalPaid = clone $query;
                $totalPaid = $totalPaid->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])->sum('paid');
                $totalSalary = clone $query;
                $totalSalary = $totalSalary->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])->sum('salary');
                $totalRemainder = $totalSalary - $totalPaid;
                $trashTotal = clone $query;
                $trashTotal = $trashTotal->onlyTrashed()->count();
                $allTotal = clone $query;
                $allTotal = $allTotal->count();
                if ($request->tab == 'trash') {
                    $query = $query->onlyTrashed();
                }
                $query = $query->latest()->paginate($request->itemPerPage);
                $results = $query->items();
                $total = $query->total();
                return response()->json(["data" => $results, 'total' => $total, "extraTotal" => ['reports' => $allTotal, 'trash' => $trashTotal], 'extra_value' => ['total_paid' => $totalPaid, 'total_remainder' => $totalRemainder, 'total_salary'  => $totalSalary]]);
            } catch (\Throwable $th) {
                return response()->json($th->getMessage(), 500);
            }
        } else if ($request->type == "employee") {
            try {
                $query = new Employee();
                $searchCol = ['first_name', 'last_name', 'email', 'phone_number', 'current_address', 'permenent_address', 'created_at', 'employee_id_number', 'employment_start_date', 'employment_end_date', "job_title"];
                $query = $this->search($query, $request, $searchCol);
                $date1 = new DateTime($request->start_date);
                $startDate = $date1->format('Y-m-d');
                $date1 = new DateTime($request->end_date);
                $endDate = $date1->format('Y-m-d');
                $query =     $query->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate]);

                $trashTotal = clone $query;
                $trashTotal = $trashTotal->onlyTrashed()->count();


                $allTotal = clone $query;
                $allTotal = $allTotal->count();
                if ($request->tab == 'trash') {
                    $query = $query->onlyTrashed();
                }
                $query = $query->latest()->paginate($request->itemPerPage);
                $results = $query->items();
                $total = $query->total();
                return response()->json(["data" => $results, 'total' => $total, "extraTotal" => ['reports' => $allTotal, 'trash' => $trashTotal]]);
            } catch (\Throwable $th) {
                return response()->json($th->getMessage(), 500);
            }
        } else if ($request->type == "purchase") {
            try {
                $query = new Purchase();
                $searchCol = ['rate', 'country', 'city', 'address', 'purchase_date'];

                $query = $this->search($query, $request, $searchCol);
                $date1 = new DateTime($request->start_date);
                $startDate = $date1->format('Y-m-d');
                $date1 = new DateTime($request->end_date);
                $endDate = $date1->format('Y-m-d');
                $query = $query->with('vendor', 'container')->withSum('payments', 'amount')->withSum('extraExpense', 'price')->withSum('items', 'total')->withSum('items', 'yen_cost');
                $query =     $query->whereBetween(DB::raw('DATE(purchase_date)'), [$startDate, $endDate]);
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
                return response()->json(["data" => $results, 'total' => $total, "extraTotal" => ['reports' => $allTotal, 'trash' => $trashTotal]]);
            } catch (\Throwable $th) {
                return response()->json($th->getMessage(), 500);
            }
        } else if ($request->type == "sell") {
            try {
                $query = new Sell();
                $searchCol = ['sell_date', 'city', 'address', 'sell_date'];

                $query = $this->search($query, $request, $searchCol);

                $query = $this->search($query, $request, $searchCol);
                $date1 = new DateTime($request->start_date);
                $startDate = $date1->format('Y-m-d');
                $date1 = new DateTime($request->end_date);
                $endDate = $date1->format('Y-m-d');
                $query = $query->with('customer')->withSum('payments', 'amount')->withSum('items', 'total')->withSum('items', 'cost');
                $query =     $query->whereBetween(DB::raw('DATE(sell_date)'), [$startDate, $endDate]);
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
                return response()->json(["data" => $results, 'total' => $total, "extraTotal" => ['reports' => $allTotal, 'trash' => $trashTotal]]);
            } catch (\Throwable $th) {
                return response()->json($th->getMessage(), 500);
            }
        } else if ($request->type == "income") {
            try {
                $query = new IncomingOutgoing();
                $searchCol = ['name', 'type', 'amount', 'created_by', 'expense_category'];
                $query = $this->search($query, $request, $searchCol);

                $date1 = new DateTime($request->start_date);
                $startDate = $date1->format('Y-m-d');
                $date1 = new DateTime($request->end_date);
                $endDate = $date1->format('Y-m-d');

                $query =     $query->where('type', 'income')->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate]);

                $trashTotal = clone $query;
                $trashTotal = $trashTotal->onlyTrashed()->count();


                $allTotal = clone $query;
                $allTotal = $allTotal->count();
                if ($request->tab == 'trash') {
                    $query = $query->onlyTrashed();
                }
                $query = $query->latest()->paginate($request->itemPerPage);
                $results = $query->items();
                $total = $query->total();

                return response()->json(["data" => $results, 'total' => $total, "extraTotal" => ['reports' => $allTotal, 'trash' => $trashTotal]]);
            } catch (Exception $th) {
                return response()->json($th->getMessage(), 500);
            }
        } else if ($request->type == "expense") {
            try {
                $query = new IncomingOutgoing();
                $searchCol = ['name', 'type', 'amount', 'created_by', 'expense_category'];
                $query = $this->search($query, $request, $searchCol);

                $date1 = new DateTime($request->start_date);
                $startDate = $date1->format('Y-m-d');
                $date1 = new DateTime($request->end_date);
                $endDate = $date1->format('Y-m-d');
                $query =     $query->where('type', 'expense')->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate]);
                $trashTotal = clone $query;
                $trashTotal = $trashTotal->onlyTrashed()->count();
                $allTotal = clone $query;
                $allTotal = $allTotal->count();
                if ($request->tab == 'trash') {
                    $query = $query->onlyTrashed();
                }
                $query = $query->latest()->paginate($request->itemPerPage);
                $results = $query->items();
                $total = $query->total();
                return response()->json(["data" => $results, 'total' => $total, "extraTotal" => ['reports' => $allTotal, 'trash' => $trashTotal]]);
            } catch (Exception $th) {
                return response()->json($th->getMessage(), 500);
            }
        } else if ($request->type == "customers") {
            try {


                $query = new Customer();
                $searchCol = ['first_name', 'last_name', 'email', 'phone_number', 'created_at', 'tazkira_number'];
                $query = $this->search($query, $request, $searchCol);
                $date1 = new DateTime($request->start_date);
                $startDate = $date1->format('Y-m-d');
                $date1 = new DateTime($request->end_date);
                $endDate = $date1->format('Y-m-d');
                $query =     $query->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate]);
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
                    $result->total_price = $result->items_sum_total + $result->extra_expense_sum_price;
                    $result->remainders = $result->total_price - $result->payments_sum_amount;
                    return $result;
                });

                return response()->json(["data" => $results, 'total' => $total, "extraTotal" => ['reports' => $allTotal, 'trash' => $trashTotal]]);
            } catch (\Throwable $th) {
                return response()->json($th->getMessage(), 500);
            }
        } else if ($request->type == "product") {
            try {
                $query = new Product();
                $searchCol = ['company_name', 'product_name', 'code', 'size', 'color', 'created_at'];
                $query = $this->search($query, $request, $searchCol);
                $date1 = new DateTime($request->start_date);
                $startDate = $date1->format('Y-m-d');
                $date1 = new DateTime($request->end_date);
                $endDate = $date1->format('Y-m-d');
                $query =     $query->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate]);
                $query = $query->with('category');
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

                return response()->json(["data" => $results, 'total' => $total,  "extraTotal" => ['reports' => $allTotal, 'trash' => $trashTotal]]);
            } catch (\Throwable $th) {
                return response()->json($th->getMessage(), 500);
            }
        } else if ($request->type == "journal") {
            try {

                $date1 = new DateTime($request->start_date);
                $startDate = $date1->format('Y-m-d');
                $date1 = new DateTime($request->end_date);
                $endDate = $date1->format('Y-m-d');
                $query = new TreasuryLog();

                $total_amount_income_usd = clone $query;
                $total_amount_income_usd = $total_amount_income_usd->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])->where('type','deposit')->sum('amount');


                $total_expense_usd = clone $query;
                $total_expense_usd = $total_expense_usd->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])->where('type','withdraw')->sum('amount');

                $searchCol = ['name', 'type', 'amount', 'created_by'];
                $query = $this->search($query, $request, $searchCol);

                $trashTotal = clone $query;
                $trashTotal = $trashTotal->onlyTrashed()->count();

                $allLog = clone $query;
                $allLog = $allLog->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])->count();

                $allOutgoing = clone $query;
                $allOutgoing = $allOutgoing->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])->count();

                if ($request->tab == 'trash') {
                    $query = $query->onlyTrashed();
                }
                else {

                    $query = $query->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate]);

                }

                $query = $query->with(['user:id,name'])->latest()->paginate($request->itemPerPage);
                $results = collect($query->items());

                $total = $query->total();

                $result = [
                    "data" => $results,
                    "total" => $total,
                    "extraTotal" => ['expense' => $allOutgoing, 'reports' => $allLog, 'trash' => $trashTotal,],
                    'extra' => ['total_amount_income_usd'  =>$total_amount_income_usd,'total_expense_usd'  =>$total_expense_usd]

                ];
                return response()->json($result);
            } catch (Exception $th) {
                return response()->json($th->getMessage(), 500);
            }
        }
    }
}
