<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryPayment extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['paid', 'salary','loan','present','absent','deduction','description','year_month', 'employee_id', 'created_at', 'created_by'];
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
