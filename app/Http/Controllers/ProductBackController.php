<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ProductBack;
use App\Models\ProductStock;
use App\Models\Sell;
use App\Models\SellItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductBackController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = new ProductBack();
            $searchCol = ['price','carton_amount','quantity', 'description', 'created_at','product.product_name','stock.name','customer.first_name'];
            $query = $this->search($query, $request, $searchCol);
           $query=$query->with('product','stock','customer');
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

            return response()->json(["data" => $results,'total' => $total,  "extraTotal" => ['product_backs' => $allTotal, 'trash' => $trashTotal]]);
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
        // $this->storeValidation($request);
        try {

            DB::beginTransaction();
            $product = new ProductBack();
            $attributes = $request->only($product->getFillable());
            $attributes['bill_id'] = $request->product['id'];
            $attributes['product_id'] = $request->sell_product_id['product_stock']['product']['id'];
            $attributes['customer_id'] = $request->product['customer']['id'];
            $attributes['item_id'] = $request->sell_product_id['id'];
            $attributes['quantity'] = $request->amount;
            $attributes['carton_amount'] =  $request->sell_product_id['carton_amount'] * $request->amount;
            $attributes['stock_id'] = $request->stock['id'];
            $attributes['price'] = $request->total;
            $product_stock_id =  ProductStock::where('product_id', $request->sell_product_id['product_stock']['product']['id'])->first();
            $check =  ProductStock::where('product_id', $product_stock_id->product_id)->where('stock_id', $request->stock['id'])->count();
          $total=  SellItem::where('id',$request->sell_product_id['id'])->first();
          $customer=  Customer::where('id',$request->product['customer']['id'])->first();
            if ($total->carton_quantity < $request->amount) {
                return response()->json('د محصول مجموعه د محصول له مجموعې څخه زیاته ده', 422);
            }else{
                if ($check == 0) {
                    $product_stock = new ProductStock();
                    $attributess = $request->only($product_stock->getFillable());
                    $attributess['product_id'] = $request->sell_product_id['product_stock']['product']['id'];
                    $attributess['stock_id'] = $request->stock['id'];
                    $attributess['quantity'] = $request->sell_product_id['carton_amount'] * $request->amount;
                    $attributess['carton_quantity'] = $request->amount;
                    $attributess['carton_amount'] = $request->sell_product_id['carton_amount'];
                    $product_stock =  $product_stock->create($attributess);
                } else {

                    ProductStock::where('product_id',$request->sell_product_id['product_stock']['product']['id'])->where('stock_id', $request->stock['id'])->increment('carton_quantity', $request->amount);
                    ProductStock::where('product_id',$request->sell_product_id['product_stock']['product']['id'])->where('stock_id', $request->stock['id'])->increment('quantity', $request->sell_product_id['carton_amount'] * $request->amount);
                }
                Sell::where('id',$request->product['id'])->decrement('total_amount',$request->total);
                Customer::where('id',$request->product['customer']['id'])->decrement('total_amount',$request->total);
                SellItem::where('id',$request->sell_product_id['id'])->decrement('carton_quantity',$request->amount);
                SellItem::where('id',$request->sell_product_id['id'])->decrement('quantity',$request->sell_product_id['carton_amount'] * $request->amount);
                SellItem::where('id',$request->sell_product_id['id'])->decrement('total',$request->total);
                SellItem::where('id',$request->sell_product_id['id'])->decrement('income_price',$request->income_price * $request->amount);
                $product =  $product->create($attributes);

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
    public function show(ProductBack $productBack)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProductBack $productBack)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProductBack $productBack)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductBack $productBack)
    {
        //
    }

    public function getSellProductList(Request $request)
    {
        try {
            $sell = Sell::with('customer')->get();
            return response()->json($sell);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
    public function getSellItem(string $id)
    {
        try {
            $item = SellItem::with('product_stock.product')->where('sell_id', $id)->get();

            return response()->json($item);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
    public function getSellItempPrice(string $id)
    {
        try {
            $item = SellItem::with('product_stock.product')->where('id', $id)->first();

            return response()->json($item);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
}
