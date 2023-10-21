<?php

namespace App\Http\Controllers;

use App\Models\ProductStock;
use App\Models\Stock;
use App\Models\StockToStockTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockToStockTransferController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = new StockToStockTransfer();
            $searchCol = ['quantity', 'description', 'created_at'];
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
                $product_stock =  $product_stock->create($attributess);
                              
                $from_product = ProductStock::where('id', $request->product['id'])->decrement('quantity', $request->amount);

            }else{
              
                $from_product = ProductStock::where('id', $request->product['id'])->decrement('quantity', $request->amount);
                ProductStock::where('product_id',$product_stock_id->product_id)->where('stock_id',$request->receiver['id'])->increment('quantity', $request->amount);
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
                $attributess['quantity'] = $request->amount;
                $product_stock =  $product_stock->create($attributess);
                $receiver_stock_product_id = $product_stock->id;
                $from_product = ProductStock::where('id', $product_stock_id->id)->increment('quantity', $product->quantity);
                $from_product = ProductStock::where('id', $product_stock_id->id)->decrement('quantity', $request->amount);
            }else{

                $receiver_stock_product_id = ProductStock::where('product_id',$product_stock_id->product_id)->where('stock_id',$request->receiver['id'])->first();
                if (isset($request->product['id'])) {
                    return 'yes';
                    if ($request->product['product_id'] == $product->sender_stock_product_id) {
                        $from_product = ProductStock::where('id', $product_stock_id->id)->increment('quantity', $product->quantity);
                        $sender_stock_product_id =  ProductStock::find($product->sender_stock_product_id);
                        
                        $sender_stock_product_id->decrement('quantity', $request->amount);
                        
                        
                        $receiver_stock_product_id->decrement('quantity', $product->quantity);
                        $receiver_stock_product_id->increment('quantity', $request->amount);
                    }else{
                        ProductStock::find($product->sender_stock_product_id)->increment('quantity', $product->quantity);
                        ProductStock::find($product->receiver_stock_product_id)->decrement('quantity', $product->quantity);
                        ProductStock::find($request->product['product_id'])->decrement('quantity', $request->amount);
                        $receiver_stock_product_id->increment('quantity', $request->amount);
                    }
                }else{
                    return 'no';
                    if ($request->product['product_id'] == $product->sender_stock_product_id) {
                        $from_product = ProductStock::where('id', $product_stock_id->id)->increment('quantity', $product->quantity);
                        $sender_stock_product_id =  ProductStock::find($product->sender_stock_product_id);
                        
                        $sender_stock_product_id->decrement('quantity', $request->amount);
                        
                        
                        $receiver_stock_product_id->decrement('quantity', $product->quantity);
                        $receiver_stock_product_id->increment('quantity', $request->amount);
                    }else{
                        ProductStock::find($product->sender_stock_product_id)->increment('quantity', $product->quantity);
                        ProductStock::find($product->receiver_stock_product_id)->decrement('quantity', $product->quantity);
                        ProductStock::find($request->product_stock['product_id'])->decrement('quantity', $request->amount);
                        $receiver_stock_product_id->increment('quantity', $request->amount);
                    }
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

                'amount.required' => " مقدار میباشد",
                'product.required' => "اسم محصول میباشد",
                'sender.required' => "از گدام ضروری میباشد",
                'receiver.required' => "به گدام ضروری میباشد",


            ]

        );
    }
}