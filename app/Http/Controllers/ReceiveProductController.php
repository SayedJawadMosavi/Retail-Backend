<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseDetail;
use App\Models\ReceiveProduct;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceiveProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
            $receiveProduct = new ReceiveProduct();
            $attributes = $request->only($receiveProduct->getFillable());
            $date1  = $attributes['created_at'];

            $dates = new DateTime($date1);
            $attributes['created_at'] = $dates->format("Y-m-d");
            $attributes['purchase_item_id'] = $request->product_item_id;


            $receiveProduct =  $receiveProduct->create($attributes);
            if ($receiveProduct) {
                $p=  Product::find($request->product_id);
                $product= Product::find($request->product_id)->update([
                'quantity'    =>$p->quantity+$request->quantity,

               ]);
               $pr=  PurchaseDetail::find($request->product_item_id);
               if ($request->quantity+$pr->received > $pr->quantity ) {
                   return response()->json('نمیتواند بزرگتر از مجموع باشد', 422);
               }
                $product= PurchaseDetail::where('id',$request->product_item_id)->update([
                'received'    =>$pr->received+$request->quantity,

               ]);

            }
            DB::commit();
            return response()->json($receiveProduct, 201);
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
            $purchase =  ReceiveProduct::with('product')->where('purchase_item_id',$id)->get();


            return response()->json($purchase);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ReceiveProduct $receiveProduct)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ReceiveProduct $receiveProduct)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ReceiveProduct $receiveProduct)
    {
        //
    }
}
