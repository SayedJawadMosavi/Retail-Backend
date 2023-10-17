<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['yen_cost', 'quantity', 'description','total','currency','expense','rate', 'created_at', 'purchase_id', 'product_id','vendor_id'];
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
