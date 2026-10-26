<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dormitory extends Model
{
    protected $fillable = [
        'facility_id',
        'name',
        'max_online_bookings_per_hour',
        'max_bookings_per_hour',
        'badge_image_path',
    ];

    protected $casts = [
        'max_online_bookings_per_hour' => 'integer',
        'max_bookings_per_hour' => 'integer',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function scopeVisibleToUser(Builder $query, ?User $user = null): Builder
    {
        if ($user === null || $user->isSuperAdmin()) {
            return $query;
        }

        return $query->where('facility_id', $user->facility_id ?? 0);
    }

    public function slotBookings(string $visitDate, string $timeSlot, ?int $excludeBookingId = null): HasMany
    {
        return $this->bookings()
            ->whereDate('visit_date', $visitDate)
            ->where('time_slot', $timeSlot)
            ->where('status', '!=', 'Rejected')
            ->when($excludeBookingId, fn (Builder $query) => $query->whereKeyNot($excludeBookingId));
    }

    /**
     * @return array{online: int, combined: int}
     */
    public function slotUsage(string $visitDate, string $timeSlot, ?int $excludeBookingId = null): array
    {
        $bookings = $this->slotBookings($visitDate, $timeSlot, $excludeBookingId);

        return [
            'online' => (clone $bookings)->where('booking_channel', Booking::CHANNEL_ONLINE)->count(),
            'combined' => (clone $bookings)->count(),
        ];
    }

    /**
     * @param  array{online: int, combined: int}  $usage
     */
    public function acceptsChannel(string $channel, array $usage): bool
    {
        if ($usage['combined'] >= $this->max_bookings_per_hour) {
            return false;
        }

        if ($channel === Booking::CHANNEL_ONLINE) {
            return $usage['online'] < $this->max_online_bookings_per_hour;
        }

        return true;
    }

    /**
     * @param  array{online: int, combined: int}  $usage
     */
    public function capacityErrorMessage(string $channel, array $usage): string
    {
        if ($channel === Booking::CHANNEL_ONLINE && $usage['online'] >= $this->max_online_bookings_per_hour) {
            return 'This time slot has reached its online booking limit. Please choose another time.';
        }

        return 'This time slot has reached its maximum combined capacity. Please choose another time.';
    }
}
