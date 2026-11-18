<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminPasswordReset extends Model
{
    /** Masa berlaku link reset password (menit). */
    public const LIFETIME_MINUTES = 30;

    protected $table = 'admin_password_resets';

    protected $fillable = [
        'admin_id',
        'token_hash',
        'created_by',
        'expires_at',
        'used_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at'    => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /** Link yang belum dipakai, belum dicabut, dan belum kedaluwarsa. */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Alasan link tidak dapat dipakai, atau null bila masih valid.
     */
    public function invalidReason(): ?string
    {
        if ($this->used_at) {
            return 'Link reset password ini sudah pernah digunakan.';
        }
        if ($this->revoked_at) {
            return 'Link reset password ini sudah dibatalkan oleh Super Admin. Silakan minta link baru bila masih diperlukan.';
        }
        if ($this->expires_at->isPast()) {
            return 'Link reset password ini sudah kedaluwarsa. Silakan minta link baru kepada Super Admin.';
        }
        if (! $this->admin) {
            return 'Akun petugas untuk link ini tidak ditemukan.';
        }
        if (! $this->admin->is_active) {
            return 'Akun petugas ini sedang nonaktif. Hubungi Super Admin.';
        }

        return null;
    }
}
