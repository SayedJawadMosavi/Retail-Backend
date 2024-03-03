<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory,SoftDeletes;
    protected $fillable = [
        'company_name',
        'product_name',
        'category_id',
        'code',
        'size',
        'color',
        'quantity',
        'carton_amount',
        'unit_name',
        'carton_quantity',
        'status',
        'description'

    ];
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }
    public function detail()
    {
        return $this->hasMany(PurchaseDetail::class, 'product_id');
    }
    public function sell()
    {
        return $this->hasMany(SellItem::class, 'product_stock_id');
    }
}
