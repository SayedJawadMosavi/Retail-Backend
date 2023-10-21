<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Stock;
use App\Models\StockProductTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductStockController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = new StockProductTransfer();
            $searchCol = ['quantity', 'description', 'created_at'];
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
        
            return response()->json(["data" => $results,'total' => $total,  "extraTotal" => ['product_stocks_transfer' => $allTotal, 'trash' => $trashTotal]]);
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
            $searchCol = ['quantity', 'description', 'created_at'];
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
        
            return response()->json(["data" => $results,'total' => $total,  "extraTotal" => ['product_stocks' => $allTotal, 'trash' => $trashTotal]]);
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
          $check=  ProductStock::where('product_id',$request->product['id'])->where('stock_id',$request->stock['id'])->first();
          
            if (!$check) {

                $product =  $product->create($attributes);
            }else{
              
               $product= ProductStock::where('product_id',$request->product['id'])->where('stock_id',$request->stock['id'])->increment('quantity',$request->amount);
                $product=ProductStock::where('product_id',$request->product['id'])->where('stock_id',$request->stock['id'])->first();
            }
            if ($product) {
                Product::where('id',$request->product['id'])->decrement('quantity',$request->amount);
                $transfer = new StockProductTransfer();
                $attributes = $request->only($transfer->getFillable());
                $attributes['product_id'] = $request->product['id'];
                $attributes['stock_id'] = $request->stock['id'];
                $attributes['quantity'] = $request->amount;
                $attributes['stock_product_id'] = $product->id;
                $transfer =  $transfer->create($attributes);

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
            
            $product->update($attributes);
            if ($product) {
             
                Product::where('id',$product->product_id)->increment('quantity',$product->quantity);
                Product::where('id',$product->product_id)->decrement('quantity',$request->amount);
                ProductStock::where('id',$product->stock_product_id)->decrement('quantity',$product->quantity);
                ProductStock::where('id',$product->stock_product_id)->increment('quantity',$request->amount);
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
            $product = Product::select(['id', 'product_name','quantity'])->where('status', 1)->get();
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
               
                'amount.required' => " مقدار میباشد",
                'product_name.required' => "اسم محصول میباشد",
                'stock.required' => "اسم گدام ضروری میباشد",
              

            ]

        );
    }
}
