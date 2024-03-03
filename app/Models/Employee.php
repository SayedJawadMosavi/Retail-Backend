<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'first_name',
        'last_name',
        'profile',
        'email',
        'phone_number',
        'current_address',
        'permenent_address',
        'employment_start_date',
        'employment_end_date',
        'job_title',
        'salary',
        'employee_id_number',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class);
    }
    public function loans(): HasMany
    {
        return $this->hasMany(EmployeeLoan::class);
    }
}
