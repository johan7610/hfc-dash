<?php

namespace App\Models\Payroll;

use App\Models\Branch;
use App\Models\Concerns\BelongsToAgency;
use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollEmployee extends Model
{
    use HasFactory, SoftDeletes, BelongsToAgency, BelongsToBranch;

    protected $fillable = [
        'agency_id',
        'branch_id',
        'user_id',
        'employment_date',
        'termination_date',
        'designation_snapshot',
        'pay_frequency',
        'pay_day_of_month',
        'is_active',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'employment_date'  => 'date',
        'termination_date' => 'date',
        'is_active'        => 'boolean',
        'pay_day_of_month' => 'integer',
    ];

    // ── Relationships ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(PayrollEmployeeEarning::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(PayrollEmployeeDeduction::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(PayrollPayslip::class);
    }

    public function currentEarnings(): HasMany
    {
        return $this->hasMany(PayrollEmployeeEarning::class)
            ->where(function ($q) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', now()->toDateString());
            });
    }

    public function currentDeductions(): HasMany
    {
        return $this->hasMany(PayrollEmployeeDeduction::class)
            ->where(function ($q) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', now()->toDateString());
            });
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->whereNull('termination_date');
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false)->whereNull('termination_date');
    }

    public function scopeTerminated($query)
    {
        return $query->whereNotNull('termination_date');
    }
}
