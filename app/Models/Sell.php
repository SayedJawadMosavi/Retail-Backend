<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
class Sell extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['sell_date', 'description','walkin_name','total_amount','total_paid', 'created_at', 'customer_id'];

    public function payments(): HasMany
    {
        return $this->hasMany(SellPayment::class);
    }
    public function items(): HasMany
    {
        return $this->hasMany(SellItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
