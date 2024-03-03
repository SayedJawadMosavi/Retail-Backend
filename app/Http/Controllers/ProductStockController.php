<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PurchaseDetail;
use App\Models\Stock;
use App\Models\StockProductTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductStockController extends Controller
{
    public function __construct()
    {
        $this->middleware('permissions:stock_product_transfer_view')->only('index');
        $this->middleware('permissions:stock_product_transfer_create')->only(['store', 'update']);
        $this->middleware('permissions:stock_product_transfer_delete')->only(['destroy']);
        $this->middleware('permissions:stock_product_transfer_restore')->only(['restore']);
        $this->middleware('permissions:stock_product_transfer_force_delete')->only(['forceDelete']);
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {

            $query = new StockProductTransfer();
            $searchCol = ['quantity', 'description', 'created_at','product.product_name','stock.name'];
            $query = $this->search($query, $request, $searchCol);
           $query=$query->with('product','stock');

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
            $result = [
                "data" => $results,
                "total" => $total,
                "extraTotal" => ['product_stocks_transfer' => $allTotal, 'trash' => $trashTotal],

            ];
            return response()->json($result);
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
    public function getStockProduct(Request $request)
    {
        try {

            $query = new ProductStock();
            $searchCol = ['quantity', 'description', 'created_at','product.product_name','stock.name'];
            $query = $this->search($query, $request, $searchCol);
            $query=$query->with('product','stock');
            $total_carton = clone $query;
           $trashTotal = clone $query;
           $total_carton = $total_carton->sum('carton_quantity');

            $trashTotal = $trashTotal->onlyTrashed()->count();

            $allTotal = clone $query;
            $allTotal = $allTotal->count();
            $allTotalalarmAmount = clone $query;
            // $allTotalalarmAmount = ProductStock::whereRaw("carton_quantity", '<',"quantity")->count();
           $allTotalalarmAmount= ProductStock::whereRaw('carton_quantity < alarm_amount')->count();
            if ($request->tab == 'trash') {
                $query = $query->onlyTrashed();
            }else if ($request->tab == 'product_stocks') {
                $query = $query;
            } else if($request->tab == 'product_stocks_alarm') {

                $query = $query->whereRaw('carton_quantity < alarm_amount');
            }
            $query = $query->latest()->paginate($request->itemPerPage);
            $results = collect($query->items());
            $total = $query->total();
            $result = [
                "data" => $results,
                "total" => $total,
                "extraTotal" => ['product_stocks' => $allTotal,'product_stocks_alarm'  =>$allTotalalarmAmount, 'trash' => $trashTotal],
                'extra_data' => ['total_income' => $total_carton]
            ];
            return response()->json($result);

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
            $product = new ProductStock();
            $attributes = $request->only($product->getFillable());
            $attributes['product_id'] = $request->product['id'];
            $attributes['stock_id'] = $request->stock['id'];
            $attributes['quantity'] = $request->amount;
            $attributes['carton_quantity'] = $request->carton_quantity;

            $check=  ProductStock::where('product_id',$request->product['id'])->where('stock_id',$request->stock['id'])->first();

            if (!$check) {

                $product =  $product->create($attributes);
            }else{

               $product= ProductStock::where('product_id',$request->product['id'])->where('stock_id',$request->stock['id'])->increment('quantity',$request->amount);
               $product= ProductStock::where('product_id',$request->product['id'])->where('stock_id',$request->stock['id'])->increment('carton_quantity',$request->carton_quantity);
                $product=ProductStock::where('product_id',$request->product['id'])->where('stock_id',$request->stock['id'])->first();
            }
            if ($product) {
               $check= Product::where('id',$request->product['id'])->first();
                if ($check->carton_amount < $request->carton_quantity ) {
                    return response()->json('د مجموعی نه لوی نشی کیدلای', 422);
                }
                Product::where('id',$request->product['id'])->decrement('quantity',$request->amount);
                Product::where('id',$request->product['id'])->decrement('carton_amount',$request->carton_quantity);
                $transfer = new StockProductTransfer();
                $attributes = $request->only($transfer->getFillable());
                $attributes['product_id'] = $request->product['id'];
                $attributes['stock_id'] = $request->stock['id'];
                $attributes['quantity'] = $request->amount;
                $attributes['carton_quantity'] = $request->carton_quantity;
                $attributes['carton_amount'] = $request->carton_amount;
                $attributes['stock_product_id'] = $product->id;
                $transfer =  $transfer->create($attributes);

                $product=ProductStock::where('product_id',$request->product['id'])->where('stock_id',$request->stock['id'])->update(['alarm_amount'  =>$request->alarm_amount]);
            }
            DB::commit();
            return response()->json($product, 201);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ProductStock $productStock)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProductStock $productStock)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $this->storeValidation($request);
        try {
            DB::beginTransaction();

            $product = StockProductTransfer::find($request->id);
            $attributes = $request->only($product->getFillable());
            if (!isset($request->product['id'])) {
                $attributes['product_id']=$request->product['id'];
            }else {
                $attributes['product_id']=$request->product['id'];

            }
            if (!isset($request->stock['id'])) {
                $attributes['stock_id']=$request->stock['id'];
            }else {
                $attributes['stock_id']=$request->stock['id'];

            }
            Product::where('id',$product->product_id)->increment('carton_amount',$product->carton_quantity);
            Product::where('id',$product->product_id)->decrement('carton_amount',$request->carton_quantity);

            $product->update($attributes);
            if ($product) {


                Product::where('id',$product->product_id)->increment('quantity',$product->quantity);
                Product::where('id',$product->product_id)->decrement('quantity',$request->amount);

                $diff = $request->carton_quantity - $product->carton_quantity;

                if ($diff > 0) {

                    ProductStock::where('id', $product->stock_product_id)->increment('carton_quantity', $diff);
                    ProductStock::where('id', $product->stock_product_id)->increment('quantity', $diff * $request->carton_amount);
                } else {
                    // Use the absolute value of $diff in the decrement and adjust the quantity accordingly
                    $stock_product =   ProductStock::where('id', $product->stock_product_id)->first();
                    // Use the absolute value of $diff in the decrement and adjust the quantity accordingly

                    if ($stock_product->carton_quantity < abs($diff)) {

                        return response()->json('د مجموعی نه لوی نشی کیدلای', 422);
                    }

                    ProductStock::where('id', $product->stock_product_id)->decrement('carton_quantity', abs($diff));
                    ProductStock::where('id', $product->stock_product_id)->decrement('quantity', abs($diff) * $request->carton_amount);
                }
                $product->update($attributes);
                ProductStock::where('id',$product->stock_product_id)->update(['alarm_amount'  =>$request->alarm_amount]);
                $product->quantity = $request->amount;
                $product->save();
            }
            DB::commit();
            return response()->json($product, 202);
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
            $data= StockProductTransfer::withTrashed()->whereIn('id',$ids)->get();
            foreach ($data as $key ) {

                ProductStock::where('product_id',$key->product_id)->where('stock_id',$key->stock_id)->increment('quantity',$key->quantity);
                Product::where('id',$key->product_id)->decrement('quantity',$key->quantity);

            }
           $result= StockProductTransfer::whereIn('id', $ids)->withTrashed()->restore();
            return response()->json($result, 203);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function forceDelete(string $id)
    {
        try {
            DB::beginTransaction();
            $ids = explode(",", $id);

           $result= StockProductTransfer::whereIn('id', $ids)->withTrashed()->forceDelete();
            DB::commit();
            return response()->json($result, 203);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 400);
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
           $data= StockProductTransfer::whereIn('id',$ids)->get();
           foreach ($data as $key ) {

               ProductStock::where('product_id',$key->product_id)->where('stock_id',$key->stock_id)->decrement('quantity',$key->quantity);
               Product::where('id',$key->product_id)->increment('quantity',$key->quantity);

           }
            $result = StockProductTransfer::whereIn("id", $ids)->delete();
            DB::commit();
            return response()->json($result, 206);
        } catch (\Exception $th) {
            //throw $th;
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }

    public function getProduct(Request $request)
    {
        try {
            $product = Product::select(['id', 'product_name','quantity','carton_amount'])->where('status', 1)->get();
            return response()->json($product);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
    public function getProductAlarmAmount($id)
    {

        try {
            $product = Product::find($id);
         if($product!=null){
           $purchase_detail= PurchaseDetail::where('product_id',$product->id)->orderBy('id','desc')->latest()->first();
            return response()->json(['product'   =>$product,'purchase_detail'   =>$purchase_detail]);
        }
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
    public function getProdutAmount($id,$product_id)
    {

        try {
            $product = ProductStock::where('product_id', $product_id)->where('stock_id',$id)->first();
            return response()->json($product);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
    public function getStock(Request $request)
    {
        try {
            $stock = Stock::select(['id', 'name'])->where('status', 1)->get();
            return response()->json($stock);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
    public function storeValidation($request)
    {
        return $request->validate(
            [
                'product' => 'required',
                'amount' => 'required',
                'stock' => 'required',


            ],
            [

                'amount.required' => " د پیسو اندازه ضروری ده",
                'product_name.required' => "د محصول نوم ضروری ده",
                'stock.required' => "د ګدام نوم ضروری ده",
            ]

        );
    }

    public function getProductPrice($id)
    {
        try {
            $product = ProductStock::find($id);
            $product=Product::where('id',$product->product_id)->first();
            if($product!=null){
                $purchase_detail= PurchaseDetail::where('product_id',$product->id)->orderBy('id','desc')->latest()->first();
                 return response()->json(['product'   =>$product,'purchase_detail'   =>$purchase_detail]);
             }


        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
}
