<?php

namespace App\Http\Controllers;

use App\Models\Sell;
use App\Models\SellItem;
use App\Models\TreasuryLog;
use App\Models\ProductStock;
use App\Models\Customer;
use App\Models\DepositWithdraw;
use App\Models\Product;
use App\Models\PurchaseDetail;
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
            $searchCol = ['sell_date', 'customer.first_name', 'total_amount', 'total_paid', 'description', 'created_at'];

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
                $result->total_price = round($result->items_sum_total, 2);
                $result->remainder = round($result->total_price - $result->payments_sum_amount, 2);
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
            $sumsQuantity = [];
            $total_cost = 0;
            $total_amount = 0;

            foreach ($request->items as $item) {
                $product_id = ProductStock::where('id', $item['product_id']['id'])->first();

                // $income_price =   PurchaseDetail::where('product_id', $product_id->product_id)->orderBy('id', 'desc')->latest()->first();
                // $income_prices = ($income_price->yen_cost / $income_price->rate) * $income_price->carton_amount;

                // $income_prices = ($income_prices + $income_price->expense) * $item['quantity'];

                $item['created_by'] = $user_id;
                $item['sell_id'] = $sell->id;
                $item['product_stock_id'] = $item['product_id']['id'];
                $item['per_carton_price'] = $item['income_price'];
                $item['customer_id'] = $request->customer_id['id'];
                $item['created_at'] = $dates->format("Y-m-d");
                $item['income_price'] = $item['income_price'] * $item['quantity'];
                $item['total'] = round(($item['quantity'] * $item['cost']), 2);
                $total_cost += round(($item['quantity'] * $item['cost']), 2);
                $quantity = $item['quantity'];
                $item['cost'] = $item['cost'];
                $item['quantity'] = $quantity * $item['carton_amount'];
                $item['carton_quantity'] = $quantity;
                $item['carton_amount'] = $item['carton_amount'];


                $total_amount += $quantity * $item['carton_amount'];
                $sell_item = SellItem::create($item);
                $productId = $item['product_id']['id'];
                $p_id = $item['product_id']['id'];

                if (!isset($sums[$productId])) {
                    $sums[$productId] = 0;
                }
                if (!isset($sumsQuantity[$p_id])) {
                    $sumsQuantity[$p_id] = 0;
                }
                $sums[$productId] += $quantity;
                $sumsQuantity[$p_id] += $quantity * $item['carton_amount'];
            }
            $customer = $sell->customer_id;
            $sell->increment('total_amount', $total_cost);
            $customers = Customer::find($request->customer_id['id']);
            Customer::where('id', $request->customer_id['id'])->update(['total_amount'   => $customers->total_amount + round($total_cost, 2)]);

            foreach ($sums as $productId => $sum) {
                $productStockAmount = ProductStock::where('id', $productId)->first();
                if ($sum > $productStockAmount->carton_quantity) {
                    return response()->json('د محصول مجموعه د محصول له مجموعې څخه زیاته ده', 422);
                } else {
                    $productStockAmount = ProductStock::where('id', $productId)->decrement('carton_quantity', $sum);
                }
            }
            foreach ($sumsQuantity as $p_id  =>  $value) {
                $productStockAmount = ProductStock::where('id', $p_id)->decrement('quantity', $value);
            }

            if ($request->paid_amount > 0) {
                $sell->update(['total_paid'   => $sell->total_paid + $request->paid_amount]);
                Customer::where('id', $request->customer_id['id'])->update(['total_paid'   => $customers->total_paid + $request->paid_amount]);
                $payment = SellPayment::create(['sell_id' => $sell->id, 'amount' => $request->paid_amount, 'created_by' => $user_id, 'created_at' => $request->date, 'customer_id' => $request->customer_id['id']]);

                TreasuryLog::create(['table' => "sell", 'table_id' => $payment->id, 'type' => 'deposit',  'name' => 'وصول' . ' (د بیل نمبر  ' . $sell->id . '   پیرودونکي' . '   ' . $request->customer_id['first_name'] .  ' )', 'amount' => $request->paid_amount, 'created_by' => $user_id, 'created_at' => $payment->created_at,]);
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
            $sell = $sell->with('customer')->with(['payments' => fn ($q) => $q->withTrashed(), 'items' => fn ($q) => $q->withTrashed(), 'items.product_stock.product' => fn ($q) => $q->withTrashed(), 'items.product_stock.stock' => fn ($q) => $q->withTrashed()])->withTrashed()->withSum('payments', 'amount')->withSum('items', 'total')->withSum('items', 'cost')->find($id);
            $sell->total_price = round($sell->items_sum_total, 2);
            $sell->remainder  = round($sell->total_price - $sell->payments_sum_amount, 2);

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
                $sell->customer_id = $request->customer['id'];
            }
            $sell->customer_id = $request->customer_id;
            $sell->sell_date = $dates->format("Y-m-d");
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
                $data =  SellItem::withTrashed()->whereIn('sell_id', $ids)->get();
                $data2 =  Sell::withTrashed()->whereIn('id', $ids)->get();
                foreach ($data as $key) {
                    ProductStock::where('id', $key->product_stock_id)->decrement('quantity', $key->quantity);
                    ProductStock::where('id', $key->product_stock_id)->decrement('carton_quantity', $key->carton_quantity);
                }
                foreach ($data2 as $key2) {
                    Customer::where('id', $key2->customer_id)->increment('total_amount', $key2->total_amount);
                    Customer::where('id', $key2->customer_id)->increment('total_paid', $key2->total_paid);
                }
            }
            if ($type == 'payments') {
                $model = new SellPayment();
                $payment = SellPayment::withTrashed()->where('id', $id)->first();
                Customer::where('id', $payment->customer_id)->increment('total_paid', $payment->amount);

                TreasuryLog::withTrashed()->where(['table' => 'sell'])->whereIn('table_id', $ids)->restore();
            }
            if ($type == 'deposit_witdraw') {
                $model = new DepositWithdraw();
                $payment = DepositWithdraw::withTrashed()->where('id', $id)->first();
                if ($payment->type == "deposit") {
                    Customer::where('id', $payment->customer_id)->increment('total_paid', $payment->amount);
                } else {

                    Customer::where('id', $payment->customer_id)->increment('total_amount', $payment->amount);
                }

                TreasuryLog::withTrashed()->where(['table' => 'customer_deposit_withdraw'])->whereIn('table_id', $ids)->restore();
            }
            if ($type == 'items') {

                $product = SellItem::withTrashed()->where('id', $id)->first();
                ProductStock::where('id', $product->product_stock_id)->decrement('quantity', $product->quantity);
                ProductStock::where('id', $product->product_stock_id)->decrement('carton_quantity', $product->carton_quantity);
                Customer::where('id', $product->customer_id)->increment('total_amount', $product->total);
                Sell::where('id', $product->sell_id)->increment('total_amount', $product->total);

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
                $data =  SellItem::whereIn('sell_id', $ids)->get();
                $data2 =  Sell::whereIn('id', $ids)->get();
                foreach ($data as $key) {
                    ProductStock::where('id', $key->product_stock_id)->increment('quantity', $key->quantity);
                    ProductStock::where('id', $key->product_stock_id)->increment('carton_quantity', $key->carton_quantity);
                }
                foreach ($data2 as $key2) {
                    Customer::where('id', $key2->customer_id)->decrement('total_amount', $key2->total_amount);
                    Customer::where('id', $key2->customer_id)->decrement('total_paid', $key2->total_paid);
                }
                $model = new Sell();
                $payment_ids =  SellPayment::whereIn('sell_id', $ids)->get()->pluck('id');
                SellPayment::whereIn('sell_id', $ids)->delete();
                SellItem::whereIn('sell_id', $ids)->delete();
                TreasuryLog::where(['table' => 'sell'])->whereIn('table_id', $payment_ids)->delete();
            }
            if ($type == 'payments') {
                $payment = SellPayment::where('id', $id)->first();

                Customer::where('id', $payment->customer_id)->decrement('total_paid', $payment->amount);

                $model = new SellPayment();
                TreasuryLog::withTrashed()->where(['table' => 'sell'])->whereIn('table_id', $ids)->delete();
            }
            if ($type == 'deposit_witdraw') {
                $payment = DepositWithdraw::where('id', $id)->first();
                if ($payment->type == "deposit") {
                    Customer::where('id', $payment->customer_id)->decrement('total_paid', $payment->amount);
                } else {
                    Customer::where('id', $payment->customer_id)->decrement('total_amount', $payment->amount);
                }

                $model = new DepositWithdraw();
                TreasuryLog::withTrashed()->where(['table' => 'customer_deposit_withdraw'])->whereIn('table_id', $ids)->delete();
            }
            if ($type == 'items') {

                $product = SellItem::where('id', $id)->first();

                ProductStock::where('id', $product->product_stock_id)->increment('quantity', $product->quantity);
                ProductStock::where('id', $product->product_stock_id)->increment('carton_quantity', $product->carton_quantity);

                Customer::where('id', $product->customer_id)->decrement('total_amount', $product->total);
                Sell::where('id', $product->sell_id)->decrement('total_amount', $product->total);

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
            if ($type == 'deposit_witdraw') {
                $model = new DepositWithdraw();
                TreasuryLog::withTrashed()->where(['table' => 'customer_deposit_withdraw'])->whereIn('table_id', $ids)->forceDelete();
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

                    'total' => 'required',

                ],
                [

                    'sell_id.required' => 'د محصول نمبر ضروری ده!',
                    'sell_id.exists' => 'د محصول نمبر په سیستم کي ضروري ده',
                    "created_at.required" => "د ثبت تاریخ  ضروری ده",
                    "created_at.date" => "د ثبت تاریخ سمه نده",
                    "created_at.before_or_equal" => "د ثبت تاریخ ده نن ورځي تاریخ نه لوی نشی کیدلای",
                    'product_id.required' => 'د محصول نوم ضروری ده',

                    'cost.required' => 'قیمت ضروری ده ',
                    'total.required' => 'مجموعه ضروری ده',
                    'cost.numeric' => 'قیمت باید حسابی عدد وی',
                    'cost.min' => 'قیمت د یوه نه کم نشی کیدلای',


                ],

            );

            DB::beginTransaction();
            $sell = Sell::find($request->sell_id);
            $customer = Customer::find($sell->customer_id);
            $user_id = Auth::user()->id;

            $attributes = $request->all();
            $date1  = $attributes['created_at'];

            $dates = new DateTime($date1);
            $attributes['created_by'] = $user_id;
            $attributes['created_at'] = $dates->format("Y-m-d");
            $attributes['sell_id'] = $sell->id;
            $attributes['product_stock_id'] = $request->product_id['id'];
            $attributes['customer_id'] = $sell->customer_id;
            $attributes['income_price'] = $request->carton_quantity * $request->income_price;
            $attributes['cost'] = $request->cost;
            $attributes['carton_amount'] = $request->carton_amount;
            $attributes['carton_quantity'] = $request->carton_quantity;
            $attributes['quantity'] = $request->carton_quantity * $request->carton_amount;
            $exist = ProductStock::where('id', $request->product_id['id'])->first();

            if ($request->carton_quantity > $exist->carton_quantity) {
                return response()->json('دا نشي کولی د مجموعې کارتن څخه ډیر وي', 422);
            } else {
                $item =  SellItem::create($attributes);
                if ($item) {
                    ProductStock::where('id', $request->product_id['id'])->decrement('quantity',  $request->carton_quantity * $request->carton_amount);
                    ProductStock::where('id', $request->product_id['id'])->decrement('carton_quantity', $request->carton_quantity);
                    $sell->increment('total_amount', $request->total);
                    $customer->increment('total_amount', $request->total);
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

                    'total' => 'required',

                ],
                [

                    'sell_id.required' => 'د محصول شمیره اړینه ده!',
                    'sell_id.exists' => 'د محصول شمیره په سیسټم کې شتون نلري!',
                    "created_at.required" => "د ثبت نیټه اړینه ده",
                    "created_at.date" => "نیټه سمه نده",
                    "created_at.before_or_equal" =>  " د ثبت نیټه د فعلی نیتي څخه لوړ نشی کیدای",
                    'product_id.required' => "د محصول نوم ضروری ده",

                    'total.required' => 'مجموعه اړینه ده',
                    'cost.required' => 'قیمت اړین دی ',
                    'cost.numeric' => 'قیمت باید یو شمیر وي',
                    'cost.min' => 'نرخ نشي کولی له یو څخه کم وي',


                ],

            );

            DB::beginTransaction();
            $product = SellItem::find($request->id);
            $attributes = $request->only($product->getFillable());

            if (isset($request->product_id['id'])) {
                $exist = ProductStock::where('id', $request->product_id['id'])->first();
                if ($request->carton_quantity > $exist->carton_quantity) {
                    return response()->json('دا نشي کولی د مجموعې کارتن څخه ډیر وي', 422);
                } else {

                    $attributes['product_stock_id'] = $request->product_id['id'];
                    $attributes['quantity'] = $request->carton_quantity * $request->carton_amount;
                    if ($request->product_id['id'] != $product->product_stock_id) {
                        ProductStock::where('id', $product->product_stock_id)->increment('quantity', $product->carton_amount * $product->carton_quantity);
                        ProductStock::where('id', $request->product_id['id'])->decrement('quantity', $request->carton_quantity * $request->carton_amount);
                        ProductStock::where('id', $product->product_stock_id)->increment('carton_quantity', $product->carton_quantity);
                        ProductStock::where('id', $request->product_id['id'])->decrement('carton_quantity', $request->carton_quantity);
                        $sell_id = $product->sell_id;
                        $customer_id = $product->customer_id;
                        Sell::where('id', $sell_id)->decrement('total_amount', $product->total);
                        Sell::where('id', $sell_id)->increment('total_amount', $request->total);
                        Customer::where('id', $customer_id)->decrement('total_amount', $product->total);
                        Customer::where('id', $customer_id)->increment('total_amount', $request->total);
                    }
                }
            } else {

                $exist = ProductStock::where('id', $request->product_stock_id)->first();

                if ($request->carton_quantity > $exist->carton_quantity) {
                    return response()->json('دا نشي کولی د مجموعې کارتن څخه ډیر وي', 422);
                } else {
                    $attributes['product_stock_id'] = $request->product_stock_id;
                    ProductStock::where('id', $product->product_stock_id)->increment('quantity', $product->carton_amount * $product->carton_quantity);
                    ProductStock::where('id', $product->product_stock_id)->decrement('quantity', $request->carton_quantity * $request->carton_amount);

                    ProductStock::where('id', $product->product_stock_id)->increment('carton_quantity', $product->carton_quantity);
                    ProductStock::where('id', $product->product_stock_id)->decrement('carton_quantity', $request->carton_quantity);
                    $sell_id = $product->sell_id;
                    $customer_id = $product->customer_id;
                    Sell::where('id', $sell_id)->decrement('total_amount', $product->total);
                    Sell::where('id', $sell_id)->increment('total_amount', $request->total);
                    Customer::where('id', $customer_id)->decrement('total_amount', $product->total);
                    Customer::where('id', $customer_id)->increment('total_amount', $request->total);
                }
            }

            $item =  $product->update($attributes);


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


                    'created_at' => ['required', 'date', 'before_or_equal:' . now()],
                    'amount' => 'required|numeric|min:1',
                ],
                [


                    "created_at.required" => "د ثبت نیټه اړینه ده",
                    "created_at.date" => "نیټه سمه نده",
                    "created_at.before_or_equal" =>  " د ثبت نیټه د فعلی نیتي څخه لوړ نشی کیدای",
                    'cost.required' => 'قیمت اړین دی ',
                    'cost.numeric' => 'قیمت باید یو شمیر وي',
                    'cost.min' => 'نرخ نشي کولی له یو څخه کم وي',

                ]
            );
            DB::beginTransaction();
            $customer = Customer::find($request->customer_id);

            $user_id = Auth::user()->id;

            $attributes = $request->all();

            $attributes['created_by'] = $user_id;
            $attributes['created_at'] = $attributes['created_at'];
            // $attributes['cus$customer_id'] = $customer->id;
            $attributes['customer_id'] = $customer->id;
            $payment =  SellPayment::create($attributes);
            $customer->update(['total_paid'  => $customer->total_paid + $request->amount]);

            TreasuryLog::create(['table' => "sell_payment", 'table_id' => $payment->id, 'type' => 'deposit', 'name' => 'وصول' . ' (  پیرودونکي'  . '    ' . $customer->first_name . ' )', 'amount' => $request->amount, 'created_by' => $user_id, 'created_at' => $payment->created_at,]);

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
                    'id' => ['required', 'exists:sell_payments,id'],
                    'amount' => 'required|numeric|min:1',
                ],
                [
                    'id.required' => 'ای دی ضروری ده',
                    'id.exists' => 'آی دی  په سیستم کی  شتون نلري',
                    'amount.required' => 'قیمت اړین دی ',
                    'amount.numeric' => 'قیمت باید یو شمیر وي',
                    'amount.min' => 'نرخ نشي کولی له یو څخه کم وي',
                ]
            );
            DB::beginTransaction();

            $payment = SellPayment::find($request->id);
            if (!$payment)
                return response()->json('آی دی  شتون نلري', 422);

            $order              = Customer::withSum('payments', 'amount')->find($payment->customer_id);

            $total = $order->total_amount + $order->extra_expense_sum_price;
            $paid = $order->total_paid - $payment->amount + $request->amount;
            $order->decrement('total_paid', $payment->amount);
            $order->increment('total_paid', $request->amount);

            if ($paid > $total) {
                return response()->json('دا نشي کولی د مجموعې څخه ډیر وي', 422);
            }
            $payment->amount = $request->amount;
            $payment->description = $request->description;
            $payment->save();
            $income = TreasuryLog::withTrashed()->where(['table' => 'sell_payment', 'table_id' => $request->id])->first();
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
            $customer = Customer::select(['id', 'first_name', 'type'])->where('status', 1)->get();

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
    public function getProduct($id)
    {
        try {
            $product = ProductStock::find($id);
            $product = Product::where('id', $product->product_id)->first();

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



                "sell_date.required" => "د ثبت نیټه اړینه ده",
                "sell_date.date" => "نیټه سمه نده",
                "sell_date.before_or_equal" => " د ثبت نیټه د فعلی نیتي څخه لوړ نشی کیدای",

                'paid_amount.numeric' => 'د وصول مقدار باید عدد وی',
                'paid_amount.min' => ' د وصول مقدار له یو څخه لږ نشی کیدای',
                'items.required' => 'توکی اړینی دی',
                'items.array' => 'توکی باید لیست شی',
                'items.min' => 'د توکی لیست اوږدوالی له یو څخه لږ کیدای نشی',
                'items.*.product_id.required' => 'د محصول نوم اړین دی',
                'items.*.stock_id.required' => 'د ګدام نوم اړین دی',
                'items.*.cost.required' => 'بیه په توکو کی اړین وی',
                'items.*.cost.numeric' => 'بیه په توکو کی باید په عدد وی',
                'items.*.cost.min' => 'بیه په توکو کی له یو لږ کیدای نشی',
                'items.*.qunantity.required' => 'اندازه په توکو کی اړین وی',


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

                "sell_date.required" => "د ثبت نیټه اړینه ده",
                "sell_date.date" => "نیټه سمه نده",
                "sell_date.before_or_equal" => " د ثبت نیټه د فعلی نیتي څخه لوړ نشی کیدای",

                'customer_id.required' => '  د پیرودونکي نوم ضروری ده',

            ]
        );
    }
}
