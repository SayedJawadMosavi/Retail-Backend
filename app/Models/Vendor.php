<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use HasFactory,SoftDeletes;
    protected $fillable = [
        'organization_name',
        'name',
        'address',
        'email',
        'phone_number',
        'status',
        'description'
       
    ];
    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class, 'vendor_id');
    }

    public function extraExpense(): HasMany
    {
        return $this->hasMany(PurchaseExtraExpense::class, 'vendor_id');
    }
    public function items()
    {
        return $this->hasMany(PurchaseDetail::class, 'vendor_id');
    }
    public function purchases()
    {
        return $this->hasMany(Purchase::class, 'vendor_id');
    }
}
