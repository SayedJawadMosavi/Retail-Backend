<?php

namespace App\Http\Controllers;

use App\Models\TreasuryLog;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

            $query = $query->with(['user:id,name'])->orderBy('id')->paginate($request->itemPerPage);
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
