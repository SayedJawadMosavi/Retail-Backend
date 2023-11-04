<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SellPayment extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['sell_id', 'amount','description', 'customer_id', 'created_by'];
}
