<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    public const CHANNEL_ONLINE = 'online';

    public const CHANNEL_WALK_IN = 'walk_in';

    protected $fillable = [
        'reference_code',
        'facility_id',
        'dormitory_id',
        'booking_channel',
        'pdl_name',
        'visitor_full_name',
        'visitor_sex',
        'visitor_age',
        'visitor_email',
        'visitor_contact',
        'visitor_id_type',
        'visitor_id_number',
        'visitor_id_photo_path',
        'relationship_to_pdl',
        'visit_date',
        'time_slot',
        'status',
        'admin_remarks',
        'terms_accepted',
        'terms_accepted_at',
        'privacy_consent',
        'privacy_consent_at',
        'privacy_notice_version',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'visitor_age' => 'integer',
        'terms_accepted' => 'boolean',
        'terms_accepted_at' => 'datetime',
        'privacy_consent' => 'boolean',
        'privacy_consent_at' => 'datetime',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function dormitory(): BelongsTo
    {
        return $this->belongsTo(Dormitory::class);
    }

    public function accompanyingVisitors(): HasMany
    {
        return $this->hasMany(AccompanyingVisitor::class);
    }

    public function getPdlFullNameAttribute(): string
    {
        return $this->pdl_name ?? 'N/A';
    }

    /**
     * Resolve a photo for "primary" or an accompanying visitor id that
     * belongs to this booking. Unknown visitors resolve to null.
     */
    public function idPhotoPathFor(string $visitor): ?string
    {
        if ($visitor === 'primary') {
            return $this->visitor_id_photo_path;
        }

        return $this->accompanyingVisitors()
            ->whereKey($visitor)
            ->value('id_photo_path');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function scopeVisibleToUser(Builder $query, ?User $user = null): Builder
    {
        if ($user === null || $user->isSuperAdmin()) {
            return $query;
        }

        return $query->where('facility_id', $user->facility_id ?? 0);
    }
}
