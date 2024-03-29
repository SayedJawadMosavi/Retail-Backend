<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'first_name',
        'tazkira_number',
        'last_name',
        'profile',
        'email',
        'phone_number',
        'address',
        'status',
        'type',
        'description',
        'total_amount',
        'total_paid',

    ];
    public function payments(): HasMany
    {
        return $this->hasMany(SellPayment::class, 'customer_id');
    }
    public function items()
    {
        return $this->hasMany(SellItem::class, 'customer_id');
    }

    public function sell()
    {
        return $this->hasMany(Sell::class, 'customer_id');
    }
    public function deposit_withdraw()
    {
        return $this->hasMany(DepositWithdraw::class, 'customer_id');
    }

}
