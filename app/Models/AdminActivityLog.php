<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'admin_activity_logs';

    protected $fillable = [
        'actor_id',
        'actor_name',
        'actor_username',
        'actor_role',
        'action',
        'target_id',
        'target_name',
        'target_username',
        'target_role',
        'changes',
        'description',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'changes'    => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** Label aktivitas yang ditampilkan di UI. */
    public const ACTION_LABELS = [
        'account_created'     => 'Akun Dibuat',
        'account_updated'     => 'Data Akun Diubah',
        'role_changed'        => 'Peran Diubah',
        'password_changed'    => 'Password Diganti',
        'account_activated'   => 'Akun Diaktifkan',
        'account_deactivated' => 'Akun Dinonaktifkan',
        'account_deleted'     => 'Akun Dihapus',
        'reset_link_created'  => 'Link Reset Password Dibuat',
        'reset_link_revoked'  => 'Link Reset Password Dibatalkan',
        'password_reset_via_link' => 'Password Direset via Link',
    ];
}
