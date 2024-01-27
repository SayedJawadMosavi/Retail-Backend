<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\IncomingOutgoing;
use App\Models\SalaryPayment;
use App\Models\TreasuryLog;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LDAP\Result;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function __construct()
    {
        $this->middleware('permissions:employee_view')->only('index');
        $this->middleware('permissions:employee_create')->only(['store', 'update']);
        $this->middleware('permissions:employee_delete')->only(['destroy']);
        $this->middleware('permissions:employee_restore')->only(['restore']);
        $this->middleware('permissions:employee_force_delete')->only(['forceDelete']);
    }
    public $path = "images/employees";



    public function index(Request $request)
    {
        try {
            $query = new Employee();
            $searchCol = ['first_name', 'last_name', 'email', 'phone_number', 'current_address', 'permenent_address', 'created_at', 'employee_id_number', 'employment_start_date', 'employment_end_date', "job_title"];
            $query = $this->search($query, $request, $searchCol);
            $query = $query->with('payments');
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
            return response()->json(["data" => $results, 'total' => $total, "extraTotal" => ['employees' => $allTotal, 'trash' => $trashTotal]]);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->storeValidation($request);
        try {
            DB::beginTransaction();
            $employee = new Employee();
            $attributes = $request->only($employee->getFillable());
            $attributes['created_at'] = $request->date;
            $employmentStartDate  = $attributes['employment_start_date'];
            $employmentEndDate  = $attributes['employment_end_date'];
            $date1 = new DateTime($employmentStartDate);
            $date2 = new DateTime($employmentEndDate);
            $attributes['employment_start_date'] = $date1->format("Y-m-d");
            $attributes['employment_end_date'] = $date2->format("Y-m-d");
            if ($request->hasFile('profile')) {
                $attributes['profile']  = $this->storeFile($request->file('profile'), $this->path);
            }
            $employee =  $employee->create($attributes);

            DB::commit();
            return response()->json($employee, 200);
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
            $employee = new Employee();
            $employee = $employee->with(['payments' => fn ($q) => $q->withTrashed(), 'loans' => fn ($q) => $q->withTrashed()])->withTrashed()->withSum('loans', 'amount')->find($id);
            return response()->json($employee);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json($th->getMessage(), 500);
        }
    }


    public function getEmployees(Request $request)
    {
        try {
            $employee = Employee::select(['id', 'salary', 'first_name', 'last_name','loan'])->latest()->get();
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
            $employee = Employee::find($id);
            $attributes = $request->only($employee->getFillable());
            $employmentStartDate  = $attributes['employment_start_date'];
            $employmentEndDate  = $attributes['employment_end_date'];
            $date1 = new DateTime($employmentStartDate);
            $date2 = new DateTime($employmentEndDate);
            $attributes['employment_start_date'] = $date1->format("Y-m-d");
            $attributes['employment_end_date']   = $date2->format("Y-m-d");
            $employee->update($attributes);
            DB::commit();
            return response()->json($employee, 202);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }

    public function restore(string $id)
    {
        try {
            $ids = explode(",", $id);
            Employee::whereIn('id', $ids)->withTrashed()->restore();
            $salary_ids =  SalaryPayment::withTrashed()->whereIn('employee_id', $ids)->get()->pluck('id');
            $loan_ids =  EmployeeLoan::withTrashed()->whereIn('employee_id', $ids)->get()->pluck('id');
            SalaryPayment::withTrashed()->whereIn("employee_id", $ids)->restore();
            EmployeeLoan::withTrashed()->whereIn("employee_id", $ids)->restore();
            TreasuryLog::withTrashed()->where(['table' => 'employee_salary'])->whereIn('table_id', $salary_ids)->restore();
            TreasuryLog::withTrashed()->where(['table' => 'employee_loan'])->whereIn('table_id', $loan_ids)->restore();
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
            $salary_ids =  SalaryPayment::withTrashed()->whereIn('employee_id', $ids)->get()->pluck('id');
            Employee::whereIn('id', $ids)->withTrashed()->forceDelete();
            SalaryPayment::withTrashed()->whereIn("employee_id", $ids)->forceDelete();
            EmployeeLoan::withTrashed()->whereIn("employee_id", $ids)->forceDelete();
            // TreasuryLog::withTrashed()->where(['table' => 'salary'])->whereIn('table_id', $salary_ids)->forceDelete();
            DB::commit();
            return response()->json(true, 203);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
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
            $result = Employee::whereIn("id", $ids)->delete();
            $salary_ids =  SalaryPayment::whereIn('employee_id', $ids)->get()->pluck('id');
            $loan_ids =  EmployeeLoan::whereIn('employee_id', $ids)->get()->pluck('id');
            SalaryPayment::whereIn("employee_id", $ids)->delete();
            EmployeeLoan::whereIn("employee_id", $ids)->delete();
            TreasuryLog::withTrashed()->where(['table' => 'employee_salary'])->whereIn('table_id', $salary_ids)->delete();
            TreasuryLog::withTrashed()->where(['table' => 'employee_loan'])->whereIn('table_id', $loan_ids)->delete();
            DB::commit();
            return response()->json($result, 206);
        } catch (\Exception $th) {
            //throw $th;
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }
    public function addILoan(Request $request)
    {


        try {
            $request->validate(
                [
                    'employee_id' => ['required'],
                    'created_at' => ['required', 'date', 'before_or_equal:' . now()],
                    'amount' => 'required|numeric|min:1',
                    'type' => 'required',

                ],
                [
                    'employee_id.required' => 'employee id is required!',
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
            $attributes['employee_id'] = $request->employee_id;
            $item =  EmployeeLoan::create($attributes);

            $employee = Employee::find($request->employee_id);

            if ($attributes['type'] == "deposit") {
                $employee->loan = $employee->loan - $request->amount;
                $employee->save();
                TreasuryLog::create(['table' => "loan", 'table_id' => $item->id, 'type' => 'deposit', 'name' => '(  گرفتن قرضه کارمند'. '   '.$request->employee_name.   ')', 'amount' => $request->amount, 'created_by' => $user_id, 'created_at' => $attributes['created_at']]);
            } else if ($attributes['type'] == "withdraw") {

                $employee->loan = $employee->loan + $request->amount;
                $employee->save();
                TreasuryLog::create(['table' => "loan", 'table_id' => $item->id, 'type' => 'withdraw', 'name' => '(  گرفتن قرضه کارمند'. '   '.$request->employee_name.   ')', 'amount' => $request->amount, 'created_by' => $user_id, 'create_at' => $attributes['created_at']]);
            }
            DB::commit();
            return response()->json($item, 201);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }


    public function updateLoan(Request $request)
    {
        try {

            $request->validate(
                [
                    'employee_id' => ['required'],
                    'created_at' => ['required', 'date', 'before_or_equal:' . now()],
                    'amount' => 'required|numeric|min:1',
                    'type' => 'required',
                ],
                [
                    'employee_id.required' => 'آی دی کارمند ضروری میباشد',
                    "created_at.required" => "تاریخ ضروری میباشد",
                    "created_at.date" => "تاریخ درست نمی باشد",
                    "created_at.before_or_equal" => "تاریخ ثبت بزرگتر از امروز شده نمی تواند",
                    'type.required' => 'نوعیت ضروری میباشد',
                    'amount.required' => 'مقدار ضروری میباشد ',
                    'amount.numeric' => 'مقدار باید عدد باشد',
                    'amount.min' => 'مقدار کمتر از یک شده نمی تواند',

                ]

            );
            DB::beginTransaction();

            $loan = EmployeeLoan::find($request->id);
            $employee              = Employee::find($loan->employee_id);
            $loan->created_at = $request->created_at;
            if ($request->type == "withdraw") {

                $employee->loan =   $employee->loan - $loan->amount + $request->amount;
                $employee->save();
            } else if ($request->type == "deposit") {
                $employee->loan =   $employee->loan + $loan->amount - $request->amount;
                $employee->save();
            }
            if ($request->type != $loan->type) {
                if ($request->type == "withdraw") {
                    $employee->loan =   $employee->loan + $loan->amount;
                    $employee->save();
                } else if ($request->type == "deposit") {
                    $employee->loan =   $employee->loan - $loan->amount;
                    $employee->save();
                }
                if ($request->type == "deposit") {
                    $employee->loan = $employee->loan - $request->amount;
                    $employee->save();
                } else if ($request->type == "withdraw") {
                    $employee->loan = $employee->loan + $request->amount;
                    $employee->save();
                }
            }
            $loan->created_at = $request->created_at;
            $loan->amount = $request->amount;
            $loan->type = $request->type;
            $loan->save();
            $income = TreasuryLog::withTrashed()->where(['table' => 'loan', 'table_id' => $request->id])->first();
            if ($income) {
                $income->amount = $request->amount;
                $income->save();
            }
            DB::commit();
            return response()->json($loan, 202);
        } catch (\Exception $th) {
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

                'employment_start_date' => 'required:date',
                'job_title' => 'required',
            ],
            [
                'first_name.required' => "نام ضروری می باشد",
                'last_name.required' => "تخلص ضروری می باشد",
                'employee_start_date.required' => "شروع کارمند ضروری می باشد",
                'job_title.required' => "عنوان وظیفه ضروری می باشد",
            ]

        );
    }
    public function reports(Request $request)
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
                return response()->json(["data" => $results, 'total' => $total, "extraTotal" => ['salaries' => $allTotal, 'trash' => $trashTotal], 'extra_value' => ['total_paid' => $totalPaid, 'total_remainder' => $totalRemainder, 'total_salary'  => $totalSalary]]);
            } catch (\Throwable $th) {
                return response()->json($th->getMessage(), 500);
            }
        } else {
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
                return response()->json(["data" => $results, 'total' => $total, "extraTotal" => ['employees' => $allTotal, 'trash' => $trashTotal]]);
            } catch (\Throwable $th) {
                return response()->json($th->getMessage(), 500);
            }
        }
    }
}
