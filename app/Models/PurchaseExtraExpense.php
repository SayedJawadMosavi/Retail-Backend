<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseExtraExpense extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['purchase_id', 'name',  'price', 'vendor_id', 'created_by'];
}
