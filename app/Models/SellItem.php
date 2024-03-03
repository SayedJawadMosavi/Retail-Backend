<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
class SellItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['cost', 'quantity', 'description','total','per_carton_price','income_price', 'created_at', 'sell_id', 'product_stock_id','customer_id','carton_amount','carton_quantity'];
    public function product_stock(): BelongsTo
    {
        return $this->belongsTo(ProductStock::class);
    }
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
