<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\IncomingOutgoing;

use App\Models\SalaryPayment;
use App\Models\TreasuryLog;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class SalaryPaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = new SalaryPayment();
            $searchCol = ['employee_id', 'created_at', 'employee.first_name', 'employee.last_name', "paid", "salary"];
            $query = $this->search($query, $request, $searchCol);
            $query = $query->with('employee:id,first_name,last_name,salary,job_title');
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
            return response()->json(["data" => $results, 'total' => $total, "extraTotal" => ['salaries' => $allTotal, 'trash' => $trashTotal]]);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        try {
            DB::beginTransaction();
 
            $this->storeValidation($request);
            $salary = new SalaryPayment();
            $user_id = Auth::user()->id;
            $attributes = $request->only($salary->getFillable());
            $date1  = $request->created_at;
            $dateString = $request->created_at;
            $date = Carbon::parse($dateString);
            $year = $date->year;
            $month = $date->month;
            $check =  SalaryPayment::whereYear('created_at', $year)
                ->whereMonth('created_at', $month)->where('employee_id', $request->employee_id)
                ->count();
         
            if ($check > 0) {
                return response()->json(' in one month you can not pay again', 500);
            }
            $dates = new DateTime($date1);
            $attributes['created_at'] = $dates->format("Y-m-d");
            $attributes['year_month'] = $request->year_month['year'].'-'.$request->year_month['month']+1;
            $attributes['created_by'] = $user_id;
            $attributes['salary'] = $request->employee['salary']-$request->deduction-$request->employee['salary']/30*$request->absent;
            $salary =  $salary->create($attributes);
            $employee = Employee::find($request->employee_id);
            if ($request->employee['salary']-$request->paid>0) {
              
                $absent=$request->employee['salary']/30*$request->absent;
                $payable = $request->employee['salary'] - $absent-$request->deduction;
                $diff = $payable - $request->paid;
                if($employee->loan > 0){
                    $loan = $employee->loan-$request->deduction - $diff;
                    $loan_remainder= abs($loan);
                }else{
                    $loan = $employee->loan-$request->deducation - $diff;
                    $loan_remainder= $diff;
                   
                }
                
                $employee->loan= $loan;

                $employee->save();
              
                if ($diff>0) {
                    $employee_loan=  EmployeeLoan::create([
                        'type'    =>"deposit",
                        'table'    =>"salary",
                        'table_id'    =>  $salary->id,
                        'amount'     =>$diff,
                        'employee_id'      =>$request->employee['id'],
                        'created_at'      =>$dates->format("Y-m-d")
                       ]);
                    
                     
                       TreasuryLog::create(['table' => "employee_loan",'client_id' =>$request->employee['id'], 'table_id' => $employee_loan->id, 'type' => 'deposit', 'name' => 'آمد بابت  باقی از معاش  ', 'amount' => $diff, 'created_by' => $user_id, 'created_at' => $request->created_at]);
                
                    }

                if (isset($request->deduction)  && $request->deduction>0) {
                    $employee_loan=  EmployeeLoan::create([
                        'type'    =>"deposit",
                        'table'    =>"salary",
                        'currency'  =>  $salary->id,
                        'amount'     =>$request->deduction,
                        'employee_id'      =>$request->employee['id'],
                        'created_at'      =>$dates->format("Y-m-d")
                       ]);
                 
                    TreasuryLog::create(['table' => "employee_loan",'client_id' =>$request->employee['id'], 'table_id' => $employee_loan->id, 'type' => 'deposit', 'name' => 'قرضه کارمند ', 'amount' => $request->deduction, 'created_by' => $user_id, 'created_at' => $request->created_at]);

                }

            }
         
            $name = $employee->first_name . ' ' . $employee->last_name;
          
            TreasuryLog::create(['table' => "employee_salary",'client_id' =>$request->employee_id, 'table_id' => $salary->id, 'type' => 'withdraw', 'name' => 'پرداخت معاش ', 'amount' => $request->paid, 'created_by' => $user_id, 'created_at' => $request->created_at]);

            DB::commit();
            return response()->json(true, 201);
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
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $this->updateValidation($request);
        try {
            $date1  = $request->created_at;
            $dateString = $request->created_at;
            $date = Carbon::parse($dateString);
            $year = $date->year;
            $month = $date->month;
            $dates = new DateTime($date1);
            
            DB::beginTransaction();
            $salary = SalaryPayment::find($request->id);
            $employee              = Employee::find($salary->employee_id);
            $absent=$request->employee['salary']/30*$request->absent;
            $payable = $request->employee['salary'] - $absent-$request->deduction;
            $loan_toal=$payable-$request->paid;
         
            $paid=$salary->salary -$salary->paid;
            $employee->loan=   $employee->loan+$paid-$loan_toal;
            $employee->save();
            $loan_amount= EmployeeLoan::withTrashed()->where(['table' => 'salary', 'table_id' => $salary->id])->first();
            if ($loan_amount) {
                $loan_amount->amount = $loan_toal;
                $loan_amount->save();
            }
            $salary->paid = $request->paid;
            $created_at  = $dates->format("Y-m-d");
            
            $salary->created_at = $dates->format("Y-m-d");
            
            $salary->present=$request->present;
            $salary->absent=$request->absent;
            $salary->description=$request->description;
            $salary->deduction=$request->deduction;
            $salary->save();
            $income = TreasuryLog::withTrashed()->where(['table' => 'employee_salary', 'table_id' => $request->id])->first();
       
            if ($income) {
                $income->amount = $request->paid;
                $income->save();
            }
            if ($loan_amount!=null) {
                $loan = TreasuryLog::withTrashed()->where(['table' => 'employee_loan', 'table_id' => $loan_amount->id])->first();
                if ($loan) {
    
                    $loan->amount =$loan_toal;
                    $loan->save();
                }
               
            }
     
            DB::commit();
            return response()->json($salary, 202);
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
            $model = new  SalaryPayment();
            TreasuryLog::withTrashed()->where(['table' => 'employee_salary'])->whereIn('table_id', $ids)->restore();
            $model->whereIn('id', $ids)->withTrashed()->restore();
            return response()->json(true, 203);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function forceDelete(string $id)
    {
        try {
            $ids = explode(",", $id);
            $model = new  SalaryPayment();
            TreasuryLog::withTrashed()->where(['table' => 'employee_salary'])->whereIn('table_id', $ids)->forceDelete();
            $model->whereIn('id', $ids)->withTrashed()->forceDelete();
            return response()->json(true, 203);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }


    public function destroy(string $id)
    {
        try {
            DB::beginTransaction();
            $ids = explode(",", $id);
            $model = new  SalaryPayment();
            $result =  $model->whereIn('id', $ids)->delete();
            TreasuryLog::withTrashed()->where(['table' => 'employee_salary'])->whereIn('table_id', $ids)->delete();
            DB::commit();
            return response()->json($result, 206);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }

    public function storeValidation($request)
    {
        return $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'created_at' => ['required', 'date'],
            'paid' => 'numeric:min:0',
        ], $this->validationTranslation());
    }

    public function updateValidation($request)
    {
        return $request->validate(
            [
                'created_at' => ['required', 'date'],
                'paid' => 'numeric:min:0|max:' . $request->salary,
            ],
            $this->validationTranslation(),
        );
    }
}
