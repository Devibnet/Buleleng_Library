<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Member extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'members';

    protected $fillable = [
        'member_no',
        'name',
        'residential_address',
        'ktp_address',
        'place_of_birth',
        'date_of_birth',
        'job_category',
        'institution_name',
        'phone_number',
        'email',
        'password',
        'photo_path',
        'google_id',
        'avatar',
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
            'date_of_birth' => 'date',
            'password' => 'hashed',
        ];
    }

    /**
     * Generate unique member number with format MEM-YYYYMM-XXXX
     * Example: MEM-202609-0001
     */
    public static function generateMemberNo(): string
    {
        $prefix = 'MEM-' . now()->format('Ym') . '-';

        $lastMember = static::where('member_no', 'like', $prefix . '%')
            ->orderBy('member_no', 'desc')
            ->first();

        $sequence = 1;
        if ($lastMember) {
            $lastSequence = (int) substr($lastMember->member_no, strlen($prefix));
            $sequence = $lastSequence + 1;
        }

        do {
            $candidateNo = $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $exists = static::where('member_no', $candidateNo)->exists();
            if ($exists) {
                $sequence++;
            }
        } while ($exists);

        return $candidateNo;
    }

    public function memberCarts(): HasMany
    {
        return $this->hasMany(MemberCart::class, 'member_id');
    }

    public function guestbooks(): HasMany
    {
        return $this->hasMany(Guestbook::class, 'member_id');
    }

    public function lockerLoans(): HasMany
    {
        return $this->hasMany(LockerLoan::class, 'member_id');
    }

    public function onsiteReadings(): HasMany
    {
        return $this->hasMany(OnsiteReading::class, 'member_id');
    }

    public function reservedCollections(): HasMany
    {
        return $this->hasMany(ReservedCollection::class, 'member_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'member_id');
    }
}
