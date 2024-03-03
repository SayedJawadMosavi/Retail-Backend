<?php

namespace App\Http\Controllers;

use App\Models\ProductStock;
use App\Models\Stock;
use App\Models\StockToStockTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockToStockTransferController extends Controller
{
    public function __construct()
    {
        $this->middleware('permissions:stock_to_stock_transfer_view')->only('index');
        $this->middleware('permissions:stock_to_stock_transfer_create')->only(['store', 'update']);
        $this->middleware('permissions:stock_to_stock_transfer_delete')->only(['destroy']);
        $this->middleware('permissions:stock_to_stock_transfer_restore')->only(['restore']);
        $this->middleware('permissions:stock_to_stock_transfer_force_delete')->only(['forceDelete']);
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = new StockToStockTransfer();
            $searchCol = ['quantity', 'description', 'created_at','carton_quantity','sender.name','receiver.name'];
            $query = $this->search($query, $request, $searchCol);
           $query=$query->with('product_stock.product','sender','receiver');
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

            return response()->json(["data" => $results,'total' => $total,  "extraTotal" => ['stock_receivers_transfer' => $allTotal, 'trash' => $trashTotal]]);
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
        $this->storeValidation($request);


        try {

            DB::beginTransaction();
            $product = new StockToStockTransfer();
            $attributes = $request->only($product->getFillable());
            $attributes['sender_stock_product_id'] = $request->product['id'];
            $attributes['sender_stock_id'] = $request->sender['id'];
            $attributes['receiver_stock_id'] = $request->receiver['id'];
            $attributes['quantity'] = $request->amount;
            $product_stock_id=  ProductStock::where('id',$request->product['id'])->first();

            $check=  ProductStock::where('product_id',$product_stock_id->product_id)->where('stock_id',$request->receiver['id'])->count();


            if ($check==0) {
                $product_stock = new ProductStock();
                $attributess = $request->only($product_stock->getFillable());
                $attributess['product_id'] = $product_stock_id->product_id;
                $attributess['stock_id'] = $request->receiver['id'];
                $attributess['quantity'] = $request->amount;
                $attributess['alarm_amount'] = $product_stock_id->alarm_amount;
                $product_stock =  $product_stock->create($attributess);

                ProductStock::where('id', $request->product['id'])->decrement('quantity', $request->amount);
                ProductStock::where('id', $request->product['id'])->decrement('carton_quantity', $request->carton_quantity);

            }else{

                ProductStock::where('id', $request->product['id'])->decrement('quantity', $request->amount);
                ProductStock::where('id', $request->product['id'])->decrement('carton_quantity', $request->carton_quantity);
                ProductStock::where('product_id',$product_stock_id->product_id)->where('stock_id',$request->receiver['id'])->increment('quantity', $request->amount);
                ProductStock::where('product_id',$product_stock_id->product_id)->where('stock_id',$request->receiver['id'])->increment('carton_quantity', $request->carton_quantity);
                $product_stock =ProductStock::where('product_id',$product_stock_id->product_id)->where('stock_id',$request->receiver['id'])->first();
            }
                $attributes['receiver_stock_product_id'] = $product_stock->id;

                $product =  $product->create($attributes);




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
    public function show(StockToStockTransfer $stockToStockTransfer)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(StockToStockTransfer $stockToStockTransfer)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {

            DB::beginTransaction();

            $product = StockToStockTransfer::find($request->id);

            if (isset($request->product['id'])) {

                $attributes['sender_stock_product_id'] = $request->product['id'];
                $product_stock_id=  ProductStock::where('id',$request->product['id'])->first();
            }else{


                $attributes['sender_stock_product_id'] = $product->sender_stock_product_id;
                $product_stock_id=  ProductStock::where('id',$product->sender_stock_product_id)->first();
            }


            $attributes = $request->only($product->getFillable());
            $attributes['sender_stock_id'] = $request->sender['id'];
            $attributes['receiver_stock_id'] = $request->receiver['id'];
            $attributes['sender_stock_product_id'] = $product_stock_id->id;
            $attributes['quantity'] = $request->amount;



            // $sender_stock_product_id = ProductStock::find($product->sender_stock_product_id);

            // $receiver_product_stock_id = ProductStock::where('stock_id',$request->receiver['id'])->where('product_id',$product_stock_id->product_id)->first();
            $check=  ProductStock::where('product_id',$product_stock_id->product_id)->where('stock_id',$request->receiver['id'])->count();
            if ($check==0) {

                $product_stock = new ProductStock();
                $attributess = $request->only($product_stock->getFillable());
                $attributess['product_id'] = $product_stock_id->product_id;
                $attributess['stock_id'] = $request->receiver['id'];
                $attributess['quantity'] = 0;
                $product_stock =  $product_stock->create($attributess);
                $receiver_stock_product_id = $product_stock->id;
                $from_product = ProductStock::where('id', $product_stock_id->id)->increment('quantity', $product->quantity);
                $from_product = ProductStock::where('id', $product_stock_id->id)->decrement('quantity', $request->amount);

                $from_product = ProductStock::where('id', $product_stock_id->id)->increment('carton_quantity', $product->carton_quantity);
                $from_product = ProductStock::where('id', $product_stock_id->id)->decrement('carton_quantity', $request->carton_quantity);
            }

                $receiver_stock_product_id = ProductStock::where('product_id',$product_stock_id->product_id)->where('stock_id',$request->receiver['id'])->first();
                if (isset($request->product['id'])) {


                    if ($request->product['id'] == $product->sender_stock_product_id) {

                        $from_product = ProductStock::where('id', $product_stock_id->id)->increment('quantity', $product->quantity);
                        $from_product = ProductStock::where('id', $product_stock_id->id)->increment('carton_quantity', $product->carton_quantity);
                        $sender_stock_product_id =  ProductStock::find($product->sender_stock_product_id);

                        $sender_stock_product_id->decrement('quantity', $request->amount);
                        $receiver_stock_product_id->decrement('quantity', $product->quantity);
                        $receiver_stock_product_id->increment('quantity', $request->amount);

                        $sender_stock_product_id->decrement('carton_quantity', $request->carton_quantity);
                        $receiver_stock_product_id->decrement('carton_quantity', $product->carton_quantity);
                        $receiver_stock_product_id->increment('carton_quantity', $request->carton_quantity);
                    }else{

                        ProductStock::find($product->sender_stock_product_id)->increment('quantity', $product->quantity);
                        ProductStock::find($product->receiver_stock_product_id)->decrement('quantity', $product->quantity);
                        ProductStock::find($request->product['id'])->decrement('quantity', $request->amount);
                        $receiver_stock_product_id->increment('quantity', $request->amount);

                        ProductStock::find($product->sender_stock_product_id)->increment('carton_quantity', $product->carton_quantity);
                        ProductStock::find($product->receiver_stock_product_id)->decrement('carton_quantity', $product->carton_quantity);
                        ProductStock::find($request->product['id'])->decrement('carton_quantity', $request->carton_quantity);
                        $receiver_stock_product_id->increment('carton_quantity', $request->carton_quantity);
                    }
                }else{

                    if ($request->product_stock['id'] == $product->sender_stock_product_id) {
                        $from_product = ProductStock::where('id', $product_stock_id->id)->increment('quantity', $product->quantity);
                        $from_product = ProductStock::where('id', $product_stock_id->id)->increment('carton_quantity', $product->carton_quantity);
                        $sender_stock_product_id =  ProductStock::find($product->sender_stock_product_id);

                        $sender_stock_product_id->decrement('quantity', $request->amount);
                        $receiver_stock_product_id->decrement('quantity', $product->quantity);
                        $receiver_stock_product_id->increment('quantity', $request->amount);

                        $sender_stock_product_id->decrement('carton_quantity', $request->carton_quantity);
                        $receiver_stock_product_id->decrement('carton_quantity', $product->carton_quantity);
                        $receiver_stock_product_id->increment('carton_quantity', $request->carton_quantity);
                    }else{
                        ProductStock::find($product->sender_stock_product_id)->increment('quantity', $product->quantity);
                        ProductStock::find($product->receiver_stock_product_id)->decrement('quantity', $product->quantity);

                        ProductStock::find($request->product['id'])->decrement('quantity', $request->amount);
                        $product_stock->increment('quantity', $request->amount);

                        ProductStock::find($product->sender_stock_product_id)->increment('carton_quantity', $product->carton_quantity);
                        ProductStock::find($product->receiver_stock_product_id)->decrement('carton_quantity', $product->carton_quantity);

                        ProductStock::find($request->product['id'])->decrement('carton_quantity', $request->carton_quantity);
                        $product_stock->increment('carton_quantity', $request->carton_quantity);
                    }
                }



            $attributes['receiver_stock_product_id'] = $receiver_stock_product_id->id;

            $product =  $product->update($attributes);

            DB::commit();
            return response()->json($product, 201);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(StockToStockTransfer $stockToStockTransfer)
    {

    }

    public function getProduct($id)
    {

        try {
            $product = ProductStock::with('product')->where('stock_id', $id)->get();
            return response()->json($product);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
    public function getStock($id)
    {

        try {
            $product = Stock::where('id', '!=', $id)->get();
            return response()->json($product);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
    public function storeValidation($request)
    {
        return $request->validate(
            [
                'sender' => 'required',
                'product' => 'required',
                'amount' => 'required',
                'receiver' => 'required',


            ],
            [

                'amount.required' => " د پیسو اندازه ضروری وی",
                'product.required' => "د محصول نوم ضروري وی",
                'sender.required' => "ګدام ته لیږل ضروری وی",
                'receiver.required' => "ګدام نه اخیستل ضروري وی",


            ]

        );
    }
}
