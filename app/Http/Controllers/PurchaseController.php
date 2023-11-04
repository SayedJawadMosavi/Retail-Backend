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
            $searchCol = ['rate', 'country', 'city', 'address', 'created_at'];

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
                $result->total_price = $result->items_sum_total + $result->extra_expense_sum_price;
                $result->remainder = $result->total_price - $result->payments_sum_amount;
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
       //
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
            $item['total'] = ((($item['cost']/ $item['rate']) + $item['expense']) * $item['quantity']);
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
            TreasuryLog::create(['table' => "purchase", 'table_id' => $payment->id, 'type' => 'withdraw', 'name' => ' بابت خرید   ', 'amount' => $request->paid_amount, 'created_by' => $user_id, 'created_at' => $payment->created_at,]);
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
            $purchase->total_price = $purchase->items_sum_total+$purchase->extra_expense_sum_price;
            $purchase->remainder  = $purchase->total_price - $purchase->payments_sum_amount;

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
            }
            if ($type == 'payments') {
                $model = new PurchasePayment();
                TreasuryLog::withTrashed()->where(['table' => 'orders'])->whereIn('table_id', $ids)->restore();
            }
            if ($type == 'items')
                $model = new PurchaseDetail();
            if ($type == 'expenses')
                $model = new PurchaseExtraExpense();

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
            }


            if ($type == 'payments') {
                $model = new PurchasePayment();
                TreasuryLog::withTrashed()->where(['table' => 'purchase'])->whereIn('table_id', $ids)->delete();
            }
            if ($type == 'items')
                $model = new PurchaseDetail();
            if ($type == 'expenses')
                $model = new PurchaseExtraExpense();

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
                'items.*.cost' => 'required|numeric|min:1',
                'items.*.quantity' => 'required',
                'extra_expense' => 'sometimes|array',
                'extra_expense.*.name' => 'required_with:extra_expense|string',
                'extra_expense.*.price' => 'required_with:extra_expense|numeric|min:0',
            ],
            [
                'vendor_id.required' => ' اسم معامله دار ضروری میباشد!',
                'container_id.required' => 'نمبر کانتینر ضروری میباشد!',

                "purchase_date.required" => "تاریخ ثبت ضروری میباشد",
                "purchase_date.date" => "تاریخ درست نمی باشد",
                "purchase_date.before_or_equal" => "تاریخ ثبت بزرگتر از تاریخ فعلی شده نمیتواند!",

                'item.*.rate.required' => 'نرخ ضروری میباشد ',
                'item.*.rate.numeric' => 'نرخ باید عدد باشد',
                'item.*.rate.min' => 'نرخ کمتر از یک شده نمیتواند',
                'paid_amount.numeric' => 'مقدار پرداختی باید عدد باشد',
                'paid_amount.min' => 'مقدار پرداختی کمتر از یک شده نمی تواند',
                'items.required' => 'موارد ضروری می باشد',
                'items.array' => 'موارد باید لیست باشد',
                'items.min' => 'طول لیست موارد کمتر از یک شده نمی تواند',
                'items.*.name.required' => 'نام در موارید ضرور می باشد',
                'items.*.cost.required' => 'قیمت در موارید ضرور می باشد',
                'items.*.cost.numeric' => 'قیمت در موارید باید عدد باشد',
                'items.*.cost.min' => 'قیمت در موارید از یک کمتر بوده نمی تواند',
                'items.*.qunantity.required' => 'مقدار در موارید ضروری می باشد',

                'extra_expense.sometimes' => 'مصرف اضافه',
                'extra_expense.array' => 'مصرف اضافه باید لیست باشد',
                'extra_expense.*.name.required_with' => 'نام در مصرف اضافه ضرور می باشد',
                'extra_expense.*.name.string' => 'نام در مصرف اضافه باید کلمه باشد',
                'extra_expense.*.price.required_with' => 'قمیت در مصرف اضافه ضرور می باشد',
                'extra_expense.*.price.numeric' => 'قیمت در مصرف اضافه باید عدد باشد',
                'extra_expense.*.price.min' => 'قیمت در مصرف اضافه کمتر از یک شده نمی تواند',
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

                "purchase_date.required" => "تاریخ ثبت ضروری میباشد",
                "purchase_date.date" => "تاریخ ثبت درست نمی باشد",
                "purchase_date.before_or_equal" => "تاریخ ثبت بزرگتر از تاریخ فعلی شده نمیتواند!",
                'container_id.required' => 'اسم کانتینر ضروری میباشد',
                'vendor_id.required' => '  اسم معامله گر ضروری میباشد',

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
                    'cost' => 'required|numeric|min:1',
                    'quantity' => 'required',
                    'expense' => 'required|numeric|min:0.01',
                ],
                [

                    'purchase_id.required' => 'نمبر محصول ضروری میباشد!',
                    'purchase_id.exists' => 'نمبر محصول در سیستم موجود نیست!',
                    "created_at.required" => "تاریخ ثبت ضروری میباشد",
                    "created_at.date" => "تاریخ ثبت درست نمی باشد",
                    "created_at.before_or_equal" => "تاریخ ثبت بزرگتر از تاریخ فعلی شده نمیتواند!",
                    'product_id.required' => 'نام محصول ضروری میباشد',
                    'quantity.required' => 'مقدار ضروری میباشد',
                    'cost.required' => 'قیمت ضروری میباشد ',
                    'cost.numeric' => 'قیمت باید عدد باشد',
                    'cost.min' => 'قیمت کمتر از یک شده نیتواند',
                    'expense.required' => 'مصرف ضروری میباشد ',
                    'expense.numeric' => 'مصرف باید عدد باشد',
                    'expense.min' => 'مصرف کمتر از یک شده نیتواند',

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
                    'cost' => 'required|numeric|min:1',
                    'quantity' => 'required',
                    'expense' => 'required|numeric|min:0.01',
                ],
                [

                    'purchase_id.required' => 'نمبر محصول ضروری میباشد!',
                    'purchase_id.exists' => 'نمبر محصول در سیستم موجود نیست!',
                    "created_at.required" => "تاریخ ثبت ضروری میباشد",
                    "created_at.date" => "تاریخ ثبت درست نمی باشد",
                    "created_at.before_or_equal" => "تاریخ ثبت بزرگتر از تاریخ فعلی شده نمیتواند!",
                    'product_id.required' => 'نام محصول ضروری میباشد',
                    'quantity.required' => 'مقدار ضروری میباشد',
                    'cost.required' => 'قیمت ضروری میباشد ',
                    'cost.numeric' => 'قیمت باید عدد باشد',
                    'cost.min' => 'قیمت کمتر از یک شده نیتواند',
                    'expense.required' => 'مصرف ضروری میباشد ',
                    'expense.numeric' => 'مصرف باید عدد باشد',
                    'expense.min' => 'مصرف کمتر از یک شده نیتواند',

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

            $detail->yen_cost = $request->cost;
            $detail->quantity = $request->quantity;
            $detail->rate = $request->rate;
            $detail->expense = $request->expense;
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

                    'purchase_id.required' => 'نمبر خرید ضروری میباشد!',
                    'purchase_id.exists' => 'نمبر خرید در سیستم موجود نیست!',
                    "created_at.required" => "تاریخ ثبت ضروری میباشد",
                    "created_at.date" => "تاریخ ثبت درست نمی باشد",
                    "created_at.before_or_equal" => "تاریخ ثبت بزرگتر از تاریخ فعلی شده نمیتواند!",
                    'amount.min' => 'مقدار پرداختی باید بزرگ از صفر باشد',
                    'amount.required' => 'مقدار پرداختی ضروری می باشد',
                    'amount.numeric' => 'مقدار پرداختی باید عدد باشد',

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
            TreasuryLog::create(['table' => "purchase_payment", 'table_id' => $payment->id, 'type' => 'withdraw', 'name' => 'پرداختی بابت خرید محصول', 'amount' => $request->amount, 'created_by' => $user_id, 'created_at' => $payment->created_at,]);


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
                    'id.required' => 'ای دی ضروری میباشد',
                    'id.exists' => 'آی دی در سیستم موجود نیست',
                    'amount.min' => 'مقدار پرداختی باید بزرگ از صفر باشد',
                    'amount.required' => 'مقدار پرداختی ضروری می باشد',
                    'amount.numeric' => 'مقدار پرداختی باید عدد باشد',
                ]
            );
            DB::beginTransaction();

            $payment = PurchasePayment::find($request->id);
            if (!$payment)
                return response()->json('آی دی موجود نیست', 422);

            $order              = Purchase::withSum('payments', 'amount')->withSum('extraExpense', 'price')->withSum('items', 'total')->find($payment->purchase_id);
            $total = $order->items_sum_total+ $order->extra_expense_sum_price;
            $paid = $order->payments_sum_amount - $payment->amount + $request->amount;

            if ($paid > $total) {
                return response()->json('نمیتواند بزرگتر از مجموع باشد', 422);
            }
            $payment->amount = $request->amount;
            $payment->save();
            $income = TreasuryLog::withTrashed()->where(['table' => 'purchase_payment', 'table_id' => $request->id])->first();
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

                    'purchase_id.required' => 'نمبر خرید ضروری میباشد!',
                    'purchase_id.exists' => 'نمبر خرید در سیستم موجود نیست!',
                    "created_at.required" => "تاریخ ثبت ضروری میباشد",
                    "created_at.date" => "تاریخ ثبت درست نمی باشد",
                    "created_at.before_or_equal" => "تاریخ ثبت بزرگتر از تاریخ فعلی شده نمیتواند!",
                    'name.required' => 'نام ضروری میباشد',
                    'price.required' => 'قیمت ضروری میباشد ',
                    'price.numeric' => 'قیمت باید عدد باشد',
                    'price.min' => 'قیمت کمتر از یک شده نیتواند',

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
