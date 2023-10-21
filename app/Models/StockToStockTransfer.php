<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockToStockTransfer extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['sender_stock_id','receiver_stock_id', 'quantity','description','sender_stock_product_id','receiver_stock_product_id', 'created_by'];
    public function product_stock(): BelongsTo
    {
        return $this->belongsTo(ProductStock::class,'sender_stock_product_id');
    }
    public function sender(): BelongsTo
    {
        return $this->belongsTo(Stock::class,'sender_stock_id');
    }
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(Stock::class,'receiver_stock_id');
    }
}
