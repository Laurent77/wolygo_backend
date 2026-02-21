<?php

namespace Modules\UserManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class AdminBroadcastNotification extends Model
{
    use HasUuid;

    protected $table = 'admin_broadcast_notifications';

    protected $fillable = [
        'title',
        'message',
        'image_path',
        'target_type',
        'target_user_id',
        'channels',
        'status',
        'sent_at',
        'sent_by',
        'total_recipients',
        'success_count',
        'failure_count',
    ];

    protected $casts = [
        'channels' => 'array',
        'sent_at'  => 'datetime',
    ];

    public function sentBy()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
