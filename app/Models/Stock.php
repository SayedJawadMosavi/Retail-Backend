<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Stock extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        'name',
        'address',
        'status'
    ];
    public function items()
    {
        return $this->hasMany(ProductStock::class, 'stock_id');
    }
}
