<?php

namespace App\Http\Controllers;

use App\Models\TreasuryLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Morilog\Jalali\Jalalian;

class TreasuryLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {

            $query = new TreasuryLog();
     
            $total_amount_income_usd = clone $query;
            $total_amount_income_usd = $total_amount_income_usd->whereDate('created_at', \DB::raw('CURDATE()'))->where('type','deposit')->sum('amount');
        
     
            $total_expense_usd = clone $query;
            $total_expense_usd = $total_expense_usd->whereDate('created_at', \DB::raw('CURDATE()'))->where('type','withdraw')->sum('amount');
                                                           
            $searchCol = ['name', 'type', 'amount', 'created_by'];
            $query = $this->search($query, $request, $searchCol);

            $trashTotal = clone $query;
            $trashTotal = $trashTotal->onlyTrashed()->count();

            $allLog = clone $query;
            $allLog = $allLog->whereDate('created_at', \DB::raw('CURDATE()'))->count();

            $allOutgoing = clone $query;
            $allOutgoing = $allOutgoing->whereDate('created_at', \DB::raw('CURDATE()'))->count();

            if ($request->tab == 'trash') {
                $query = $query->onlyTrashed();
            }
            else {
                
                $query = $query->whereDate('created_at', \DB::raw('CURDATE()'));
            
            }

            $query = $query->with(['user:id,name'])->latest()->paginate($request->itemPerPage);
            $results = collect($query->items());
          
            $total = $query->total();

            $result = [
                "data" => $results,
                "total" => $total,
                "extraTotal" => ['expense' => $allOutgoing, 'treasuryLog' => $allLog, 'trash' => $trashTotal,],
                'extra' => ['total_amount_income_usd'  =>$total_amount_income_usd,'total_expense_usd'  =>$total_expense_usd]

            ];
            return response()->json($result);
        } catch (Exception $th) {
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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(TreasuryLog $treasuryLog)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TreasuryLog $treasuryLog)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TreasuryLog $treasuryLog)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TreasuryLog $treasuryLog)
    {
        //
    }

    public function reports(Request $request)
    {
       
        try {

            $query = new TreasuryLog();
        
            $now= Carbon::now();
            $date_now = jdate($now)->format('Y-m-d');
          
            $startDate = $request->start_date;
            $endDate = $request->end_date;
           
          
            $total_amount_income_usd = clone $query;
            $total_amount_income_usd = $total_amount_income_usd->whereBetween('register_date', [$startDate, $endDate])->where('type','income')->where('transaction_type','deposit')->where('currency','USD')->sum('usd_amount');
        

            $total_expense_usd = clone $query;
            $total_expense_usd = $total_expense_usd->whereBetween('register_date', [$startDate, $endDate])->where('type','expense')->where('transaction_type','withdraw')->where('currency','USD')->sum('usd_amount');
           
            
            $total_fees = clone $query;
            $total_fees = $total_fees->whereBetween('register_date', [$startDate, $endDate])->where('currency','USD')->sum('fees');
        
            $searchCol = ['name', 'type','transaction_type', 'amount', 'created_by'];
            $query = $this->search($query, $request, $searchCol);

            $trashTotal = clone $query;
            $trashTotal = $trashTotal->onlyTrashed()->count();

            $allLog = clone $query;
            $allLog = $allLog->whereBetween('register_date', [$startDate, $endDate])->count();

            $allOutgoing = clone $query;
            $allOutgoing = $allOutgoing->whereBetween('register_date', [$startDate, $endDate])->count();

            if ($request->tab == 'trash') {
                $query = $query->onlyTrashed();
            }
            else {
                
                $query = $query->whereBetween('register_date', [$startDate, $endDate]);
            
            }

            $query = $query->with(['user:id,name'])->latest()->paginate($request->itemPerPage);
            $results = collect($query->items());
          
            $total = $query->total();

            $result = [
                "data" => $results,
                "total" => $total,
                "extraTotal" => ['expense' => $allOutgoing, 'treasuryLog' => $allLog, 'trash' => $trashTotal,],
                'extra' => ['total_deposit' => $total_deposit,'total_amount_income_usd'  =>$total_amount_income_usd,'total_expense_usd'  =>$total_expense_usd,'total_amount_expense_afn'  =>$total_amount_expense_afn,'total_amount_income_afn' =>$total_amount_income_afn, 'total_withdraw' => $total_withdraw,'total_deposit_requested'  =>$total_deposit_requested,'total_withdraw_requested'  =>$total_withdraw_requested,'total_fees'  =>$total_fees,'total_amount_d_afn'  =>$total_amount_d_afn,'total_amount_d_usd'   =>$total_amount_d_usd,'total_amount_w_afn'  =>$total_amount_w_afn,'total_amount_w_usd'  =>$total_amount_w_usd]
            ];
            return response()->json($result);
        } catch (Exception $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
}
