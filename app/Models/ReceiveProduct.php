<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReceiveProduct extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['product_id', 'quantity','description', 'purchase_item_id', 'created_at'];
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
