<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['status', 'purchase_date', 'description', 'created_at', 'vendor_id', 'container_id'];

    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class);
    }
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseDetail::class);
    }
    public function extraExpense(): HasMany
    {
        return $this->hasMany(PurchaseExtraExpense::class);
    }
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }
}
