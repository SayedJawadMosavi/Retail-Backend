<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductBack extends Model
{
    use HasFactory,SoftDeletes;
    protected $fillable = ['bill_id','stock_id','price', 'quantity','description','product_id','item_id','carton_amount','item_id','customer_id'];
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
