<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLoan extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['type', 'amount',  'employee_id','table','table_id', 'created_at', 'created_by'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
