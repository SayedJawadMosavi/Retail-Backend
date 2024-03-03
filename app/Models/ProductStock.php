<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductStock extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['product_id','stock_id', 'quantity','description', 'created_by','carton_amount','carton_quantity','alarm_amount'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

}
