<?php

namespace Modules\UserManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class DriverDocument extends Model
{
    use HasUuid;

    protected $table = 'driver_documents';

    protected $fillable = [
        'driver_id',
        'document_type',
        'document_number',
        'issued_at',
        'expires_at',
        'front_image_path',
        'back_image_path',
        'pdf_path',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'is_mandatory',
        'notes',
    ];

    protected $casts = [
        'issued_at'   => 'date',
        'expires_at'  => 'date',
        'reviewed_at' => 'datetime',
        'is_mandatory' => 'boolean',
    ];

    // ---- Relationships ----

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ---- Helpers ----

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired'
            || ($this->expires_at && $this->expires_at->isPast());
    }

    public function daysUntilExpiry(): ?int
    {
        if (!$this->expires_at) return null;
        return max(0, now()->diffInDays($this->expires_at, false));
    }
}
