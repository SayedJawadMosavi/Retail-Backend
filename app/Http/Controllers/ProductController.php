<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('permissions:product_view')->only('index');
        $this->middleware('permissions:product_create')->only(['store', 'update']);
        $this->middleware('permissions:product_delete')->only(['destroy']);
        $this->middleware('permissions:product_restore')->only(['restore']);
        $this->middleware('permissions:product_force_delete')->only(['forceDelete']);
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = new Product();
            $searchCol = ['company_name', 'product_name', 'code', 'size','color', 'created_at'];
            $query = $this->search($query, $request, $searchCol);
           $query=$query->with('category');
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

            return response()->json(["data" => $results,'total' => $total,  "extraTotal" => ['products' => $allTotal, 'trash' => $trashTotal]]);
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
            $product = new Product();
            $attributes = $request->only($product->getFillable());
            $attributes['created_at'] = $request->date;
            $attributes['category_id'] = $request->category_id['id'];
            $attributes['status'] = 1;
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
    public function show(string $id)
    {

        try {
            $detail = new Product();
            $detail = $detail->with(['detail' => fn ($q) => $q->withTrashed()])->find($id);
            return response()->json($detail);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
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

            $product = Product::find($request->id);
            $attributes = $request->only($product->getFillable());
            if (!isset($request->category_id['id'])) {
                $attributes['category_id']=$request->category['id'];
            }else {
                $attributes['category_id']=$request->category_id['id'];

            }
            $product->update($attributes);
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
            Product::whereIn('id', $ids)->withTrashed()->restore();
            return response()->json(true, 203);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function forceDelete(string $id)
    {
        try {
            DB::beginTransaction();
            $ids = explode(",", $id);
            Product::whereIn('id', $ids)->withTrashed()->forceDelete();
            DB::commit();
            return response()->json(true, 203);
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
            $result = Product::whereIn("id", $ids)->delete();
            DB::commit();
            return response()->json($result, 206);
        } catch (\Exception $th) {
            //throw $th;
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }

    public function storeValidation($request)
    {
        return $request->validate(
            [
                'company_name' => 'required',
                'product_name' => 'required',
                'category_id' => 'required',


            ],
            [
                'company_name.required' => "اسم کمپنی ضروری میباشد",
                'product_name.required' => "اسم محصول میباشد",
                'categor_id.required' => "کتگوری ضروری میباشد",


            ]

        );
    }
    public function getCategory(Request $request)
    {
        try {
            $branch = ProductCategory::select(['id', 'name'])->where('status', 1)->get();
            return response()->json($branch);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function changeStatus($id,$value)
    {
        try {
            if ($id==1) {
                $product=Product::where('id',$value)->update(['status'  =>0]);
            }else if ($id==0) {
                $product=Product::where('id',$value)->update(['status'  =>1]);
            }
            return response()->json($product, 202);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
}
