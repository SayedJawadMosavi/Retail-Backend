<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TreasuryLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'amount','type', 'created_by', 'created_at', 'table', 'table_id'];
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}