<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'admins';

    protected $fillable = [
        'username',
        'password',
        'name',
        'email',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function lockerLoansProcessed(): HasMany
    {
        return $this->hasMany(LockerLoan::class, 'processed_by_user_id');
    }

    public function loansCreated(): HasMany
    {
        return $this->hasMany(Loan::class, 'created_by_user_id');
    }

    public function loansReturned(): HasMany
    {
        return $this->hasMany(Loan::class, 'returned_by_user_id');
    }

    public function loanExtensionsProcessed(): HasMany
    {
        return $this->hasMany(LoanExtension::class, 'processed_by_user_id');
    }
}
