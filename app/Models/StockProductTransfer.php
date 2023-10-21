<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockProductTransfer extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['product_id','stock_id', 'quantity','description','stock_product_id', 'created_by'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(product::class);
    }
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
}
