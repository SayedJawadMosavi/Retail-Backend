<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

class IncomingOutgoing extends Model

{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'type', 'amount', 'created_by', 'created_at', 'table', 'table_id'];
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    use HasFactory;
}
