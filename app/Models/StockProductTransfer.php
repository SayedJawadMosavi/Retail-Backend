<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockProductTransfer extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['product_id','stock_id', 'quantity','description','stock_product_id','carton_amount','alarm_amount','carton_quantity', 'created_by'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
}
