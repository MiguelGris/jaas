<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedCode;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, HasGeneratedCode, Notifiable;

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function meterReadings()
    {
        return $this->hasMany(MeterReading::class);
    }

    public function incomes()
    {
        return $this->hasMany(Income::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function cashClosings()
    {
        return $this->hasMany(CashClosing::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    protected function generatedCodeDefinition(): array
    {
        return ['field' => 'user_code', 'series' => 'users', 'prefix' => 'USR', 'padding' => 4];
    }
}
