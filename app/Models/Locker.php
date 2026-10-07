<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Locker extends Model
{
    use HasFactory;

    protected $table = 'lockers';

    protected $fillable = [
        'locker_code',
        'status',
    ];

    public function lockerLoans(): HasMany
    {
        return $this->hasMany(LockerLoan::class, 'locker_id');
    }
}
