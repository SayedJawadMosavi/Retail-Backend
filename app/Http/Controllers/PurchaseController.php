<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\TreasuryLog;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\PurchaseExtraExpense;
use App\Models\PurchasePayment;
use App\Models\Vendor;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    public function __construct()
    {
        $this->middleware('permissions:purchase_view')->only('index');
        $this->middleware('permissions:purchase_create')->only(['store', 'update']);
        $this->middleware('permissions:purchase_delete')->only(['destroy']);
        $this->middleware('permissions:purchase_restore')->only(['restore']);
        $this->middleware('permissions:purchase_force_delete')->only(['forceDelete']);
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = new Purchase();
            $searchCol = ['purchase_date','vendor.name','container.name', 'description', 'created_at'];

            $query = $this->search($query, $request, $searchCol);
            $query = $query->with('vendor','container')->withSum('payments', 'amount')->withSum('extraExpense', 'price')->withSum('items', 'total')->withSum('items', 'yen_cost');
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
                $result->total_price = round($result->items_sum_total + $result->extra_expense_sum_price,2);
                $result->remainder = round($result->total_price - $result->payments_sum_amount,2);
                return $result;
            });
            return response()->json(["data" => $results, 'total' => $total, "extraTotal" => ['purchases' => $allTotal, 'trash' => $trashTotal]]);
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

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {



       try {
        DB::beginTransaction();
        $this->storeValidation($request);
        $purchase = new Purchase();
        $user_id = Auth::user()->id;
        $attributes = $request->only($purchase->getFillable());
        $date1  = $attributes['purchase_date'];

        $dates = new DateTime($date1);
        $attributes['created_by'] = $user_id;
        $attributes['purchase_date'] = $dates->format("Y-m-d");
        $attributes['container_id'] = $request->container_id['id'];
        $attributes['vendor_id'] = $request->vendor_id['id'];
        // $attributes['id'] = 2;
        $purchase =  $purchase->create($attributes);
        $amount=0;
        $total=0;
        $totals=0;
        foreach ($request->items as $item) {

            $item['created_by'] = $user_id;
            $item['purchase_id'] = $purchase->id;
            $item['product_id'] = $item['product_id']['id'];
            $item['vendor_id'] = $request->vendor_id['id'];
            $item['created_at'] = $dates->format("Y-m-d");
            $item['yen_cost'] = $item['cost'];
            $item['quantity'] = $item['quantity'];
            $item['rate'] = $item['rate'];
            $item['expense'] = $item['expense'];
            $item['carton_amount'] = $item['carton_amount'];
            $item['per_carton_cost'] = $item['per_carton_cost'];
            $item['sell_price'] = $item['sell_price'];
            $item['carton'] = $item['carton'];
            $amount=$item['cost']/ $item['rate'];
            $total= $amount* $item['carton_amount'];
            $totals+= ($total*1+1* $item['expense']) * ($item['carton']);
            $item['total']= round($totals,2);
            PurchaseDetail::create($item);
        }

        foreach ($request->extra_expense as $exp) {
            $exp['created_by'] = $user_id;
            $exp['purchase_id'] = $purchase->id;
            $exp['vendor_id'] = $request->vendor_id['id'];
            $item['created_at'] = $dates->format("Y-m-d");
            PurchaseExtraExpense::create($exp);
        }

        if ($request->paid_amount > 0) {
            $payment = PurchasePayment::create(['purchase_id' => $purchase->id, 'amount' => $request->paid_amount, 'created_by' => $user_id, 'created_at' => $request->date, 'vendor_id' => $request->vendor_id['id']]);
            TreasuryLog::create(['table' => "purchase", 'table_id' => $payment->id, 'type' => 'withdraw',  'name' => 'بنام'. ' ( بیل نمبر  ' . $purchase->id .'   سوداګر'. '   '.$request->vendor_id['name'].  '  '.'کانتینر'. $request->container_id['name']. ')', 'amount' => $request->paid_amount, 'created_by' => $user_id, 'created_at' => $payment->created_at,]);
        }

        DB::commit();
        return response()->json($purchase, 201);
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
            $purchase = new Purchase();
            $purchase = $purchase->with('vendor','container')->with(['payments' => fn ($q) => $q->withTrashed(), 'items' => fn ($q) => $q->withTrashed(), 'items.product' => fn ($q) => $q->withTrashed(), 'extraExpense' => fn ($q) => $q->withTrashed()])->withTrashed()->withSum('payments', 'amount')->withSum('extraExpense', 'price')->withSum('items', 'total')->withSum('items', 'yen_cost')->find($id);
            $purchase->total_price = round($purchase->items_sum_total+$purchase->extra_expense_sum_price,2);
            $purchase->remainder  = round($purchase->total_price - $purchase->payments_sum_amount,2);

            return response()->json($purchase);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Purchase $purchase)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $this->updateValidation($request);
            DB::beginTransaction();
            $purchase = Purchase::find($id);
            $attributes = $request->only($purchase->getFillable());
            $date1  = $attributes['purchase_date'];

            $dates = new DateTime($date1);
            if (isset($request->vendor_id['id'])) {

                $purchase->vendor_id=$request->vendor_id['id'];
            }
            $purchase->vendor_id=$request->vendor_id;
            if (isset($request->container_id['id'])) {

                $purchase->container_id=$request->container_id['id'];
            }
            $purchase->container_id=$request->container_id;
            $purchase->purchase_date=$dates->format("Y-m-d");
            $purchase->update($attributes);

            DB::commit();
            return response()->json($purchase, 202);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function restore(string $type, string $id)
    {
        try {
            DB::beginTransaction();
            $ids = explode(",", $id);

            if ($type == 'purchase') {
                $model = new Purchase();
                $payment_ids =  PurchasePayment::withTrashed()->whereIn('purchase_id', $ids)->get()->pluck('id');
                PurchasePayment::withTrashed()->whereIn('purchase_id', $ids)->restore();
                PurchaseDetail::withTrashed()->whereIn('purchase_id', $ids)->restore();
                PurchaseExtraExpense::withTrashed()->whereIn('purchase_id', $ids)->restore();
                TreasuryLog::withTrashed()->where(['table' => 'purchase'])->whereIn('table_id', $payment_ids)->restore();
                $expense = PurchaseExtraExpense::withTrashed()->whereIn('purchase_id', $ids)->get()->pluck('id');
                $income = TreasuryLog::withTrashed()->where(['table' => 'purchase_extra_expense'])->whereIn('table_id', $expense)->restore();
            }
            if ($type == 'payments') {
                $model = new PurchasePayment();
                TreasuryLog::withTrashed()->where(['table' => 'orders'])->whereIn('table_id', $ids)->restore();
            }
            if ($type == 'items')
                $model = new PurchaseDetail();
            if ($type == 'expenses'){
                $model = new PurchaseExtraExpense();
                $expense = PurchaseExtraExpense::withTrashed()->find($id);
                $income = TreasuryLog::withTrashed()->where(['table' => 'purchase_extra_expense', 'table_id' => $expense->id])->first();
                if ($income) {
                    $income->restore();

                }
            }

            $model->whereIn('id', $ids)->withTrashed()->restore();
            DB::commit();
            return response()->json(true, 203);
        } catch (\Throwable $th) {

            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }

    public function destroy(string $type, string $id)
    {

        try {
            DB::beginTransaction();
            $ids = explode(",", $id);


            if ($type == 'purchase') {
                $model = new Purchase();
                $payment_ids =  PurchasePayment::whereIn('purchase_id', $ids)->get()->pluck('id');
                PurchasePayment::whereIn('purchase_id', $ids)->delete();
                PurchaseDetail::whereIn('purchase_id', $ids)->delete();
                PurchaseExtraExpense::whereIn('purchase_id', $ids)->delete();
                TreasuryLog::withTrashed()->where(['table' => 'purchase'])->whereIn('table_id', $payment_ids)->delete();
                $expense = PurchaseExtraExpense::withTrashed()->whereIn('purchase_id', $ids)->get()->pluck('id');
                $income = TreasuryLog::withTrashed()->where(['table' => 'purchase_extra_expense'])->whereIn('table_id', $expense)->delete();
            }


            if ($type == 'payments') {
                $model = new PurchasePayment();
                TreasuryLog::withTrashed()->where(['table' => 'purchase'])->whereIn('table_id', $ids)->delete();
            }
            if ($type == 'items')

                $model = new PurchaseDetail();
            if ($type == 'expenses'){

                $model = new PurchaseExtraExpense();
                $expense = PurchaseExtraExpense::find($id);
                $income = TreasuryLog::withTrashed()->where(['table' => 'purchase_extra_expense', 'table_id' => $expense->id])->first();
                if ($income) {
                    $income->delete();

                }
            }


            $result =  $model->whereIn('id', $ids)->delete();
            DB::commit();
            return response()->json($result, 206);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }

    public function forceDelete(string $type, string $id)
    {
        try {
            DB::beginTransaction();
            $ids = explode(",", $id);

            if ($type == 'purchase') {
                $model = new Purchase();
                $payment_ids =  PurchasePayment::withTrashed()->whereIn('purchase_id', $ids)->get()->pluck('id');
                TreasuryLog::withTrashed()->where(['table' => 'purchase'])->whereIn('table_id', $payment_ids)->forceDelete();
                $expense = PurchaseExtraExpense::withTrashed()->whereIn('purchase_id', $ids)->get()->pluck('id');
                $income = TreasuryLog::withTrashed()->where(['table' => 'purchase_extra_expense'])->whereIn('table_id', $expense)->forceDelete();

            }


            if ($type == 'payments') {
                $model = new PurchasePayment();
                TreasuryLog::withTrashed()->where(['table' => 'purchase'])->whereIn('table_id', $ids)->forceDelete();
            }
            if ($type == 'items') {
                $model = new PurchaseDetail();
            }
            if ($type == 'expenses') {
                $model = new PurchaseExtraExpense();
                $expense = PurchaseExtraExpense::withTrashed()->find($id);
                $income = TreasuryLog::withTrashed()->where(['table' => 'purchase_extra_expense', 'table_id' => $expense->id])->first();
                if ($income) {
                    $income->forceDelete();

                }
            }

            $result =  $model->withTrashed()->whereIn('id', $ids)->forceDelete();
            DB::commit();
            return response()->json($result, 206);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }
    public function getContainer(Request $request)
    {
        try {
            $branch = Container::select(['id', 'name'])->where('status', 1)->get();
            return response()->json($branch);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
    public function getVendor(Request $request)
    {
        try {
            $branch = Vendor::select(['id', 'name'])->where('status', 1)->get();
            return response()->json($branch);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
    public function getProduct(Request $request)
    {
        try {
            $branch = Product::select(['id', 'product_name'])->where('status', 1)->get();
            return response()->json($branch);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function storeValidation($request)
    {
        return $request->validate(
            [

                'container_id' => [
                    'required',
                ],
                'vendor_id' => 'required',
                'paid_amount' => 'numeric:min:0',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required',
                'items.*.cost' => 'required|numeric',
                'items.*.quantity' => 'required',
                'extra_expense' => 'sometimes|array',
                'extra_expense.*.name' => 'required_with:extra_expense|string',
                'extra_expense.*.price' => 'required_with:extra_expense|numeric|min:0',
            ],
            [
                'vendor_id.required' => ' د معامله لرونکی نوم اړین ده!',
                'container_id.required' => 'د کانتینر شمیره اړینه ده!',
                "purchase_date.required" => "د ثبت نیټه اړینه ده",
                "purchase_date.date" => "نیټه سمه نده",
                "purchase_date.before_or_equal" => " د ثبت نیټه د فعلی نیتي څخه لوړ نشی کیدای",
                'item.*.rate.required' => 'بیه اړین ده ',
                'item.*.rate.numeric' => 'بیه باید په عدد وی',
                'item.*.rate.min' => 'بیه له یو څخه کم نشی کیدای',
                'paid_amount.numeric' => 'د وصول مقدار باید عدد وی',
                'paid_amount.min' => ' د وصول مقدار له یو څخه لږ نشی کیدای',
                'items.required' => 'توکی اړینی دی',
                'items.array' => 'توکی باید لیست شی',
                'items.min' => 'د توکی لیست اوږدوالی له یو څخه لږ کیدای نشی',
                'items.*.name.required' => 'نوم په توکو کی اړین وی',
                'items.*.cost.required' => 'بیه په توکو کی اړین وی',
                'items.*.cost.numeric' => 'بیه په توکو کی باید په عدد وی',


                'items.*.qunantity.required' => 'اندازه په توکو کی اړین وی',
                'extra_expense.sometimes' => 'اضافی لګښت',
                'extra_expense.array' => 'اضافه لګښت باید لست وی',
                'extra_expense.*.name.required_with' => 'نوم اضافه لګښت کی اړین وی',
                'extra_expense.*.name.string' => 'نوم اضافه لګښت کی باید کلمه وی',
                'extra_expense.*.price.required_with' => 'بیه اضافه مصرف کی اړین وی',
                'extra_expense.*.price.numeric' => 'بیه اضافه مصرف کی باید عدد وی',
                'extra_expense.*.price.min' => 'بیه اضافه مصرف کی له یو څخه لږ کیدای نشی',
            ]
        );
    }
    public function updateValidation($request)
    {
        return $request->validate(
            [

                'purchase_date' => ['required', 'date', 'before_or_equal:' . now()],
                'container_id' => 'required',
                'vendor_id' => 'required',

            ],
            [

                "purchase_date.required" => "د ثیت نیټه اړین ده",
                "purchase_date.date" => "د ثبت نیټه سمه نده",
                "purchase_date.before_or_equal" => "د ثبت نیټه د فعلی نیتي څخه لوړ نشی کیدای!",
                'container_id.required' => 'د کانتینر نوم اړین ده',
                'vendor_id.required' => '  د ممعامله کوونکی نوم ضروری ده',

            ]
        );
    }

    public function addItem(Request $request)
    {
        try {
            $request->validate(
                [

                    'purchase_id' => ['required', 'exists:purchases,id'],
                    'created_at' => ['required', 'date', 'before_or_equal:' . now()],
                    'product_id' => 'required',
                    'cost' => 'required|numeric|min:0',
                    'quantity' => 'required',
                    'carton_amount' => 'required',
                    'carton' => 'required',
                    'expense' => 'required|numeric',
                ],
                [

                    'purchase_id.required' => 'د محصول نوم ضروری ده!',
                    'purchase_id.exists' => 'د محصول نوم په یستم کی موجود نده!',
                    "created_at.required" => "د ثبت تاریخ ضروری ده",
                    "created_at.date" => "د ثبت تاریخ ضروری ده",
                    "created_at.before_or_equal" => "د ثبت نیټه د فعلی نیتي څخه لوړ نشی کیدای!",
                    'product_id.required' => 'د محصول نوم ضروری ده',
                    'quantity.required' => 'د پیسو اندازه ضروری ده',
                    'carton_amount.required' => 'اندازه په کارتن کښی ضروری ده',
                    'carton.required' => 'تعداد په کارتن کی ْضروری ده',
                    'cost.required' => 'قیمت اړین ده ',
                    'cost.numeric' => 'قیمت باید عدد وی',
                    'cost.min' => 'قیمت د یوه نه کم نشی کیدلای',
                    'expense.required' => 'مصرف ضروری ده ',
                    'expense.numeric' => 'مصرف باید عدد وی',


                ],

            );


            DB::beginTransaction();
            $purchase = Purchase::find($request->purchase_id);
            $user_id = Auth::user()->id;

            $attributes = $request->all();
            $date1  = $attributes['created_at'];

            $dates = new DateTime($date1);
            $attributes['created_by'] = $user_id;
            $attributes['created_at'] = $dates->format("Y-m-d");
            $attributes['purchase_id'] = $purchase->id;
            $attributes['product_id'] = $request->product_id['id'];
            $attributes['vendor_id'] = $purchase->vendor_id;
            $attributes['vendor_id'] = $purchase->vendor_id;
            $attributes['carton_amount'] = $request->carton_amount;
            $attributes['carton'] = $request->carton;
            $attributes['per_carton_cost'] = $request->per_carton_cost;
            $attributes['sell_price'] = $request->sell_price;

            $attributes['rate'] = $request->rate;
            $attributes['yen_cost'] = $request->cost;

            $item =  PurchaseDetail::create($attributes);

            DB::commit();
            return response()->json($item, 201);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }


    public function updateItem(Request $request)
    {
        try {

            $request->validate(
                [

                    'purchase_id' => ['required', 'exists:purchases,id'],
                    'created_at' => ['required', 'date', 'before_or_equal:' . now()],
                    'product_id' => 'required',
                    'cost' => 'required|numeric|min:0',
                    'quantity' => 'required',
                    'expense' => 'required|numeric',
                ],
                [

                    'purchase_id.required' => 'د محصول نوم ضروری ده!',
                    'purchase_id.exists' => 'د محصول نوم په یستم کی موجود نده!',
                    "created_at.required" => "د ثبت تاریخ ضروری ده",
                    "created_at.date" => "د ثبت تاریخ ضروری ده",
                    "created_at.before_or_equal" => "د ثبت نیټه د فعلی نیتي څخه لوړ نشی کیدای!",
                    'product_id.required' => 'د محصول نوم ضروری ده',
                    'quantity.required' => 'د پیسو اندازه ضروری ده',

                    'cost.required' => 'قیمت اړین ده ',
                    'cost.numeric' => 'قیمت باید عدد وی',
                    'cost.min' => 'قیمت د یوه نه کم نشی کیدلای',
                    'expense.required' => 'مصرف ضروری ده ',
                    'expense.numeric' => 'مصرف باید عدد وی',
                    'expense.min' => 'مصرف د یوه نم لږ نشی کیدلای',

                ],

            );
            DB::beginTransaction();

            $detail = PurchaseDetail::find($request->id);
            $date1  = $request->created_at;

            $dates = new DateTime($date1);
            $detail->created_at = $dates->format("Y-m-d");

            if(isset($request->product_id['id'])){
                $detail->product_id = $request->product_id['id'];

            }else{

                $detail->product_id = $request->product['id'];
            }

            $detail->carton = $request->carton;
            $detail->yen_cost = $request->cost;
            $detail->quantity = $request->quantity;
            $detail->rate = $request->rate;
            $detail->expense = $request->expense;
            $detail->per_carton_cost = $request->per_carton_cost;
            $detail->sell_price = $request->sell_price;
            $detail->total = $request->total;
            $detail->save();

            DB::commit();
            return response()->json($detail, 202);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }


    public function addPayment(Request $request)
    {
        try {
            $request->validate(
                [

                    'purchase_id' => ['required', 'exists:purchases,id'],
                    'created_at' => ['required', 'date', 'before_or_equal:' . now()],
                    'amount' => 'required|numeric|min:1',
                ],
                [


                    'purchase_id.required' => 'د محصول نوم ضروری ده!',
                    'purchase_id.exists' => 'د محصول نوم په یستم کی موجود نده!',
                    "created_at.required" => "د ثبت تاریخ ضروری ده",
                    "created_at.date" => "د ثبت تاریخ ضروری ده",
                    "created_at.before_or_equal" => "د ثبت نیټه د فعلی نیتي څخه لوړ نشی کیدای!",

                    'amount.min' => 'وصول شوی پیسی باید د صفر نه لوی وی',
                    'amount.required' => 'وصول شوی مقدار ضروری وی',
                    'amount.numeric' => 'وصول شوی مقدار باید عدد وی',





                ]
            );
            DB::beginTransaction();
            $purchase = Purchase::find($request->purchase_id);
            $user_id = Auth::user()->id;

            $attributes = $request->all();

            $attributes['created_by'] = $user_id;
            $attributes['created_at'] = $attributes['created_at'];
            $attributes['purchase_id'] = $purchase->id;
            $attributes['vendor_id'] = $purchase->vendor_id;
            $payment =  PurchasePayment::create($attributes);
            TreasuryLog::create(['table' => "purchase", 'table_id' => $payment->id, 'type' => 'withdraw', 'name' => 'بنام'. ' ( بیل نمبر  ' . $purchase->id .'  سوداګر  '  .'    '. $request->vendor_name .'     ' .  'کانتینر'   .'   ' . $request->container_name. ')', 'amount' => $request->amount, 'created_by' => $user_id, 'created_at' => $payment->created_at,]);


            DB::commit();
            return response()->json($payment, 201);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }

    public function updatePayment(Request $request)
    {
        try {
            $request->validate(
                [
                    'id' => ['required', 'exists:purchase_payments,id'],
                    'amount' => 'required|numeric|min:1',
                ],
                [
                    'id.required' => 'آی ډی ضروری ه',
                    'id.exists' => 'آی ډی په سیستم کی موجود نده',
                    'amount.min' => 'وصول شوی پیسی باید د صفر نه لوی وی',
                    'amount.required' => 'وصول شوی مقدار ضروری وی',
                    'amount.numeric' => 'وصول شوی باید عدد وی',



                ]
            );
            DB::beginTransaction();

            $payment = PurchasePayment::find($request->id);
            if (!$payment)
                return response()->json('آی دی شتون نلري', 422);

            $order              = Purchase::withSum('payments', 'amount')->withSum('extraExpense', 'price')->withSum('items', 'total')->find($payment->purchase_id);
            $total = $order->items_sum_total+ $order->extra_expense_sum_price;
            $paid = $order->payments_sum_amount - $payment->amount + $request->amount;

            if ($paid > $total) {
                return response()->json('دا نشي کولی د مجموعې څخه ډیر وي', 422);
            }
            $payment->amount = $request->amount;
            $payment->description = $request->description;
            $payment->save();
            $income = TreasuryLog::withTrashed()->where(['table' => 'purchase', 'table_id' => $request->id])->first();
            if ($income) {
                $income->amount = $request->amount;
                $income->save();
            }

            DB::commit();
            return response()->json($payment, 202);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }




    public function addExpense(Request $request)
    {
        try {
            $request->validate(
                [

                    'purchase_id' => ['required', 'exists:purchases,id'],
                    'created_at' => ['required', 'date', 'before_or_equal:' . now()],
                    'name' => 'required',
                    'price' => 'required|numeric|min:1',
                ],
                [

                    'purchase_id.required' => 'د محصول نوم ضروری ده!',
                    'purchase_id.exists' => 'د محصول نوم په یستم کی موجود نده!',
                    "created_at.required" => "د ثبت تاریخ ضروری ده",
                    "created_at.date" => "د ثبت تاریخ ضروری ده",
                    "created_at.before_or_equal" => "د ثبت نیټه د فعلی نیتي څخه لوړ نشی کیدای!",
                    'name.required' => 'نوم ضروری ده',
                    'price.required' => 'قیمت ضروری ده',
                    'price.numeric' => 'قیمت عدد وی',
                    'price.min' => 'قیمت د یوه نه کم نشی کیدلای',

                ]
            );
            DB::beginTransaction();
            $purchase = Purchase::find($request->purchase_id);
            $user_id = Auth::user()->id;

            $attributes = $request->all();

            $attributes['created_by'] = $user_id;
            $attributes['created_at'] = $attributes['created_at'];
            $attributes['purchase_id'] = $purchase->id;
            $attributes['vendor_id'] = $purchase->vendor_id;
            $expense =  PurchaseExtraExpense::create($attributes);
            TreasuryLog::create(['table' => "purchase_extra_expense", 'table_id' => $expense->id, 'type' => 'withdraw', 'name' => 'بابت مصارف اضافی ازخرید' . $request->anme   . ' ( بیل نمبر  ' . $purchase->id .'  معامله دار'  .'    '. $request->vendor_name .'     ' .  'کانتینر'   .'   ' . $request->container_name. ')', 'amount' => $request->price, 'created_by' => $user_id, 'created_at' => $expense->created_at,]);
            DB::commit();
            return response()->json($expense, 201);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }
    public function updateExpense(Request $request)
    {
        try {
            $request->validate(
                [
                    'id' => ['required', 'exists:purchase_extra_expenses,id'],
                    'created_at' => ['required', 'date', 'before_or_equal:' . now()],
                    'name' => 'required',
                    'price' => 'required|numeric|min:1',
                ],
                $this->validationTranslation()
            );
            DB::beginTransaction();
            $expense = PurchaseExtraExpense::find($request->id);
            $income = TreasuryLog::withTrashed()->where(['table' => 'purchase_extra_expense', 'table_id' => $request->id])->first();
            if ($income) {
                $income->amount = $request->price;
                $income->save();
            }
            $expense->created_at = $request->created_at;
            $expense->name = $request->name;
            $expense->price = $request->price;
            $expense->save();

            DB::commit();
            return response()->json($expense, 202);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }
    public function checkStatus($id)
    {
        try {


            $purchase = PurchaseDetail::where('purchase_id', $id)->get();
            $total=0;
            foreach ($purchase as $key ) {
                $total+=$key->quantity;
                $p=  Product::find($key->product_id);
                $product= Product::find($key->product_id)->update([
                'quantity'    =>$p->quantity+$key->quantity,

               ]);
            }
            Purchase::find($id)->update(['status'   =>'recieved']);
            return response()->json($product, 202);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }


}
