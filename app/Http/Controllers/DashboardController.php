<?php

namespace App\Http\Controllers;


use App\Models\User;

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
        $expired_contract_alarm = $this->getContractExpirationAlarm();
        // $expired_contract = $this->getContractExpired();
        $accountMoney_afg = $this->AccountPaymentAfg();
        $accountMoney_usd = $this->AccountPaymentUSD();
        return response()->json([
            'transactions' => $transactions, 'account_money_afg' => $accountMoney_afg, 'account_money_usd' => $accountMoney_usd,
            'allIncomeExpense' => $this->allExpenseIncome(),
            'expired_contract_alarm' =>$expired_contract_alarm,
            // 'expired_contract' =>$expired_contract,
         
        ]);
    }

    public function transactions()
    {
        return [User::count(), Customer::count(), Employee::count(), Vendor::count(), Purchase::count(), Contract::count(), ExpenseIncomeLog::count(),];
    }
    public function allExpenseIncome()
    {
        return [ExpenseIncomeLog::whereType('income')->whereDate('created_at', \DB::raw('CURDATE()'))->where('currency', 'Afg')->sum('amount'), ExpenseIncomeLog::whereType('income')->whereDate('created_at', \DB::raw('CURDATE()'))->where('currency', 'USD')->sum('amount'), ExpenseIncomeLog::whereType('expense')->whereDate('created_at', \DB::raw('CURDATE()'))->where('currency', 'Afg')->sum('amount'), ExpenseIncomeLog::whereType('expense')->whereDate('created_at', \DB::raw('CURDATE()'))->where('currency', 'USD')->sum('amount')];
    }



    public function AccountPaymentUSD()
    {
        $query = new ExpenseIncomeLog();

        $totalIncomes_usd = clone $query;
        $totalIncomes_usd = $totalIncomes_usd->whereType('income')->where('currency', 'USD')->sum('amount');
        $totalOutGoing_usd = clone $query;
        $totalOutGoing_usd = $totalOutGoing_usd->whereType('expense')->where('currency', 'USD')->sum('amount');
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


            //code...
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
   
}