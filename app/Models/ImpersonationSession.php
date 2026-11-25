<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ImpersonationSession extends Model
{
    use HasFactory;

    protected $table = 'impersonation_sessions';

    public const STATUS_ACTIVE  = 'ACTIVE';
    public const STATUS_ENDED   = 'ENDED';
    public const STATUS_EXPIRED = 'EXPIRED';

    protected $fillable = [
        'session_id',
        'super_admin_id',
        'super_admin_name',
        'super_admin_username',
        'target_admin_id',
        'target_name',
        'target_username',
        'target_role',
        'reason',
        'token_id',
        'ip_address',
        'user_agent',
        'status',
        'started_at',
        'expires_at',
        'ended_at',
        'end_reason',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'ended_at'   => 'datetime',
        ];
    }

    public static function generateSessionId(): string
    {
        do {
            $candidate = 'IMP-' . now()->format('Ymd') . '-' . Str::upper(Str::random(8));
        } while (static::where('session_id', $candidate)->exists());

        return $candidate;
    }

    public function superAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'super_admin_id');
    }

    public function targetAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'target_admin_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
