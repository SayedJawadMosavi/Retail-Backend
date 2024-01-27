<?php

namespace App\Http\Controllers;

use App\Models\Sell;
use App\Models\SellItem;
use App\Models\TreasuryLog;
use App\Models\ProductStock;
use App\Models\Customer;
use App\Models\SellPayment;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SellController extends Controller
{
    public function __construct()
    {
        // $this->middleware('permissions:sell_view')->only('index');
        // $this->middleware('permissions:sell_create')->only(['store', 'update']);
        // $this->middleware('permissions:sell_delete')->only(['destroy']);
        // $this->middleware('permissions:sell_restore')->only(['restore']);
        // $this->middleware('permissions:sell_force_delete')->only(['forceDelete']);
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = new Sell();
            $searchCol = ['sell_date', 'city', 'address', 'created_at'];

            $query = $this->search($query, $request, $searchCol);
            $query = $query->with('customer')->withSum('payments', 'amount')->withSum('items', 'total')->withSum('items', 'cost');
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
                $result->total_price = round($result->items_sum_total,2 );
                $result->remainder = round($result->total_price - $result->payments_sum_amount,2);
                return $result;
            });
            return response()->json(["data" => $results, 'total' => $total, "extraTotal" => ['sells' => $allTotal, 'trash' => $trashTotal]]);
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

           $sell = new Sell();

        $user_id = Auth::user()->id;
        $attributes = $request->only($sell->getFillable());
        $date1  = $attributes['sell_date'];
        $dates = new DateTime($date1);
        $attributes['created_by'] = $user_id;
        $attributes['sell_date'] = $dates->format("Y-m-d");
        $attributes['customer_id'] = $request->customer_id['id'];
        $sell =  $sell->create($attributes);
        $sums = [];
        foreach ($request->items as $item) {

            $item['created_by'] = $user_id;
            $item['sell_id'] = $sell->id;
            $item['product_stock_id'] = $item['product_id']['id'];
            $item['customer_id'] = $request->customer_id['id'];
            $item['created_at'] = $dates->format("Y-m-d");
            $item['cost'] = $item['cost'];
            $item['quantity'] = $item['quantity'];
            $item['total'] = ($item['cost'] * $item['quantity']);
            SellItem::create($item);
            $productId = $item['product_id']['id'];
            $quantity = $item['quantity'];
            if (!isset($sums[$productId])) {
                $sums[$productId] = 0;
            }
            $sums[$productId] += $quantity;
        }
        foreach ($sums as $productId => $sum) {
            $productStockAmount = ProductStock::where('id',$productId)->first();
            if ($sum > $productStockAmount->quantity) {
                return response()->json('مجموع محصول  بزرگتر از گدام محصول است', 422);
            }else{
                $productStockAmount = ProductStock::where('id',$productId)->decrement('quantity',$sum);
            }
        }

        if ($request->paid_amount > 0) {
            $payment = SellPayment::create(['sell_id' => $sell->id, 'amount' => $request->paid_amount, 'created_by' => $user_id, 'created_at' => $request->date, 'customer_id' => $request->customer_id['id']]);

            TreasuryLog::create(['table' => "sell", 'table_id' => $payment->id, 'type' => 'deposit',  'name' => 'بابت پرداختی فروش'. ' ( بیل نمبر  ' . $sell->id .'   مشتری'. '   '.$request->customer_id['first_name'].  ' )', 'amount' => $request->paid_amount, 'created_by' => $user_id, 'created_at' => $payment->created_at,]);

        }

        DB::commit();
        return response()->json($sell, 201);
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
            $sell = new Sell();
            $sell = $sell->with('customer')->with(['payments' => fn ($q) => $q->withTrashed(), 'items' => fn ($q) => $q->withTrashed(),'items.product_stock.product' => fn ($q) => $q->withTrashed(),'items.product_stock.stock' => fn ($q) => $q->withTrashed()])->withTrashed()->withSum('payments', 'amount')->withSum('items', 'total')->withSum('items', 'cost')->find($id);
            $sell->total_price = round($sell->items_sum_total,2);
            $sell->remainder  = round($sell->total_price - $sell->payments_sum_amount,2);

            return response()->json($sell);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Sell $sell)
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
            $sell = Sell::find($id);
            $attributes = $request->only($sell->getFillable());
            $date1  = $attributes['sell_date'];
            $dates = new DateTime($date1);
            if (isset($request->customer['id'])) {
                $sell->customer_id=$request->customer['id'];
            }
            $sell->customer_id=$request->customer_id;
            $sell->sell_date=$dates->format("Y-m-d");
            $sell->update($attributes);

            DB::commit();
            return response()->json($sell, 202);
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

            if ($type == 'sell') {
                $model = new Sell();
                $payment_ids =  SellPayment::withTrashed()->whereIn('sell_id', $ids)->get()->pluck('id');
                SellPayment::withTrashed()->whereIn('sell_id', $ids)->restore();
                SellItem::withTrashed()->whereIn('sell_id', $ids)->restore();

                TreasuryLog::withTrashed()->where(['table' => 'sell'])->whereIn('table_id', $payment_ids)->restore();
                $data=  SellItem::withTrashed()->whereIn('sell_id', $ids)->get();
                foreach ($data as $key ) {
                    ProductStock::where('id',$key->product_stock_id)->decrement('quantity',$key->quantity);
                }
            }
            if ($type == 'payments') {
                $model = new SellPayment();
                TreasuryLog::withTrashed()->where(['table' => 'sell'])->whereIn('table_id', $ids)->restore();
            }
            if ($type == 'items'){

                $product=SellItem::withTrashed()->where('id', $id)->first();
                ProductStock::where('id',$product->product_stock_id)->decrement('quantity',$product->quantity);
                $model = new SellItem();
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
            if ($type == 'sell') {
                $data=  SellItem::whereIn('sell_id', $ids)->get();
                foreach ($data as $key ) {
                    ProductStock::where('id',$key->product_stock_id)->increment('quantity',$key->quantity);
                }
                $model = new Sell();
                $payment_ids =  SellPayment::whereIn('sell_id', $ids)->get()->pluck('id');
                SellPayment::whereIn('sell_id', $ids)->delete();
                SellItem::whereIn('sell_id', $ids)->delete();
                TreasuryLog::where(['table' => 'sell'])->whereIn('table_id', $payment_ids)->delete();
            }
            if ($type == 'payments') {
                $model = new SellPayment();
                TreasuryLog::withTrashed()->where(['table' => 'sell'])->whereIn('table_id', $ids)->delete();
            }
            if ($type == 'items'){

                $product=SellItem::where('id', $id)->first();

                ProductStock::where('id',$product->product_stock_id)->increment('quantity',$product->quantity);
                $model = new SellItem();
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

            if ($type == 'sell') {
                $model = new Sell();
                $payment_ids =  SellPayment::withTrashed()->whereIn('sell_id', $ids)->get()->pluck('id');
                $item =  SellItem::withTrashed()->whereIn('sell_id', $ids)->forceDelete();
                TreasuryLog::withTrashed()->where(['table' => 'sell'])->whereIn('table_id', $payment_ids)->forceDelete();
            }


            if ($type == 'payments') {
                $model = new SellPayment();
                TreasuryLog::withTrashed()->where(['table' => 'sell'])->whereIn('table_id', $ids)->forceDelete();
            }
            if ($type == 'items') {
                $model = new SellItem();

            }

            $result =  $model->withTrashed()->whereIn('id', $ids)->forceDelete();
            DB::commit();
            return response()->json($result, 206);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }
    public function addItem(Request $request)
    {
        try {


            $request->validate(
                [

                    'sell_id' => ['required', 'exists:sells,id'],
                    'created_at' => ['required', 'date', 'before_or_equal:' . now()],
                    'product_id' => 'required',
                    'cost' => 'required|numeric|min:1',
                    'quantity' => 'required',
                    'total' => 'required',

                ],
                [

                    'sell_id.required' => 'نمبر محصول ضروری میباشد!',
                    'sell_id.exists' => 'نمبر محصول در سیستم موجود نیست!',
                    "created_at.required" => "تاریخ ثبت ضروری میباشد",
                    "created_at.date" => "تاریخ ثبت درست نمی باشد",
                    "created_at.before_or_equal" => "تاریخ ثبت بزرگتر از تاریخ فعلی شده نمیتواند!",
                    'product_id.required' => 'نام محصول ضروری میباشد',
                    'quantity.required' => 'مقدار ضروری میباشد',
                    'cost.required' => 'قیمت ضروری میباشد ',
                    'total.required' => 'مجموع ضروری میباشد ',
                    'cost.numeric' => 'قیمت باید عدد باشد',
                    'cost.min' => 'قیمت کمتر از یک شده نیتواند',


                ],

            );

            DB::beginTransaction();
            $sell = Sell::find($request->sell_id);
            $user_id = Auth::user()->id;

            $attributes = $request->all();
            $date1  = $attributes['created_at'];

            $dates = new DateTime($date1);
            $attributes['created_by'] = $user_id;
            $attributes['created_at'] = $dates->format("Y-m-d");
            $attributes['sell_id'] = $sell->id;
            $attributes['product_stock_id'] = $request->product_id['id'];
            $attributes['customer_id'] = $sell->customer_id;

            $attributes['cost'] = $request->cost;
           $exist= ProductStock::where('id',$request->product_id['id'])->first();

            if ($request->quantity>$exist->quantity) {
                return response()->json('نمیتواند بزرگتر از مجموع باشد', 422);

            }else{
                $item =  SellItem::create($attributes);
                if ($item) {
                    ProductStock::where('id',$request->product_id['id'])->decrement('quantity',$request->quantity);
                }

            }
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

                    'sell_id' => ['required', 'exists:sells,id'],
                    'created_at' => ['required', 'date', 'before_or_equal:' . now()],
                    'product_id' => 'required',
                    'cost' => 'required|numeric|min:1',
                    'quantity' => 'required',
                    'total' => 'required',

                ],
                [

                    'sell_id.required' => 'نمبر محصول ضروری میباشد!',
                    'sell_id.exists' => 'نمبر محصول در سیستم موجود نیست!',
                    "created_at.required" => "تاریخ ثبت ضروری میباشد",
                    "created_at.date" => "تاریخ ثبت درست نمی باشد",
                    "created_at.before_or_equal" => "تاریخ ثبت بزرگتر از تاریخ فعلی شده نمیتواند!",
                    'product_id.required' => 'نام محصول ضروری میباشد',
                    'quantity.required' => 'مقدار ضروری میباشد',
                    'cost.required' => 'قیمت ضروری میباشد ',
                    'total.required' => 'مجموع ضروری میباشد ',
                    'cost.numeric' => 'قیمت باید عدد باشد',
                    'cost.min' => 'قیمت کمتر از یک شده نیتواند',


                ],

            );

            DB::beginTransaction();
            $product = SellItem::find($request->id);
            $attributes = $request->only($product->getFillable());
            if (isset($request->product_id['id'])) {
                $attributes['product_stock_id']=$request->product_id['id'];
                if ($request->product_id['id']!=$product->product_stock_id) {
                    ProductStock::where('id',$product->product_stock_id)->increment('quantity',$product->quantity);
                    ProductStock::where('id',$request->product_id['id'])->decrement('quantity',$request->quantity);
                }
            }else {

                $attributes['product_stock_id']=$request->product_stock_id;
                ProductStock::where('id',$product->product_stock_id)->increment('quantity',$product->quantity);
                ProductStock::where('id',$product->product_stock_id)->decrement('quantity',$request->quantity);

            }

          $item=  $product->update($attributes);

            DB::commit();
            return response()->json($product, 202);
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

                    'sell_id' => ['required', 'exists:sells,id'],
                    'created_at' => ['required', 'date', 'before_or_equal:' . now()],
                    'amount' => 'required|numeric|min:1',
                ],
                [

                    'sell_id.required' => 'نمبر فروش ضروری میباشد!',
                    'sell_id.exists' => 'نمبر فروش در سیستم موجود نیست!',
                    "created_at.required" => "تاریخ ثبت ضروری میباشد",
                    "created_at.date" => "تاریخ ثبت درست نمی باشد",
                    "created_at.before_or_equal" => "تاریخ ثبت بزرگتر از تاریخ فعلی شده نمیتواند!",
                    'amount.min' => 'مقدار پرداختی باید بزرگ از صفر باشد',
                    'amount.required' => 'مقدار پرداختی ضروری می باشد',
                    'amount.numeric' => 'مقدار پرداختی باید عدد باشد',

                ]
            );
            DB::beginTransaction();
            $sell = Sell::find($request->sell_id);
            $user_id = Auth::user()->id;

            $attributes = $request->all();

            $attributes['created_by'] = $user_id;
            $attributes['created_at'] = $attributes['created_at'];
            $attributes['sell_id'] = $sell->id;
            $attributes['customer_id'] = $sell->customer_id;
            $payment =  SellPayment::create($attributes);


            TreasuryLog::create(['table' => "sell_payment", 'table_id' => $payment->id, 'type' => 'deposit', 'name' => 'بابت پرداختی فروش'. ' ( بیل نمبر  ' . $sell->id .'   مشتری'  .'    '. $request->customer_name .' )', 'amount' => $request->amount, 'created_by' => $user_id, 'created_at' => $payment->created_at,]);

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

            $order              = Purchase::withSum('payments', 'amount')->withSum('extraExpense', 'price')->withSum('items', 'total')->find($payment->sell_id);
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


    public function getCustomer(Request $request)
    {
        try {
            $customer = Customer::select(['id', 'first_name'])->where('status', 1)->get();

            return response()->json($customer);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
    public function getProductStock(Request $request)
    {
        try {
            $product = ProductStock::with('product')->get();

            return response()->json($product);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
    public function storeValidation($request)
    {
        return $request->validate(
            [





                'customer_id' => 'required',

                'paid_amount' => 'numeric:min:0',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required',
                'items.*.stock_id' => 'required',
                'items.*.cost' => 'required|numeric|min:1',
                'items.*.quantity' => 'required',

            ],
            [



                "sell_date.required" => "تاریخ ثبت ضروری میباشد",
                "sell_date.date" => "تاریخ درست نمی باشد",
                "sell_date.before_or_equal" => "تاریخ ثبت بزرگتر از تاریخ فعلی شده نمیتواند!",


                'paid_amount.numeric' => 'مقدار پرداختی باید عدد باشد',
                'paid_amount.min' => 'مقدار پرداختی کمتر از یک شده نمی تواند',
                'items.required' => 'موارد ضروری می باشد',
                'items.array' => 'موارد باید لیست باشد',
                'items.min' => 'طول لیست موارد کمتر از یک شده نمی تواند',
                'items.*.product_id.required' => 'نام محصول ضرور می باشد',
                'items.*.stock_id.required' => 'نام گدام ضرور می باشد',
                'items.*.cost.required' => 'قیمت در موارید ضرور می باشد',
                'items.*.cost.numeric' => 'قیمت در موارید باید عدد باشد',
                'items.*.cost.min' => 'قیمت در موارید از یک کمتر بوده نمی تواند',
                'items.*.qunantity.required' => 'مقدار در موارید ضروری می باشد',


            ]
        );
    }
    public function updateValidation($request)
    {
        return $request->validate(
            [

                'sell_date' => ['required', 'date', 'before_or_equal:' . now()],

                'customer_id' => 'required',

            ],
            [

                "sell_date.required" => "تاریخ ثبت ضروری میباشد",
                "sell_date.date" => "تاریخ ثبت درست نمی باشد",
                "sell_date.before_or_equal" => "تاریخ ثبت بزرگتر از تاریخ فعلی شده نمیتواند!",

                'customer_id.required' => '  اسم مشتری میباشد',

            ]
        );
    }
}
