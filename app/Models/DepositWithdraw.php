<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DepositWithdraw extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['type', 'amount','description',  'customer_id','table','table_id', 'created_at', 'created_by'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
