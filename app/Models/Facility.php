<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    protected $fillable = [
        'name',
        'max_visitors_per_hour',
        'regional_office',
        'jail_unit_name',
        'badge_image_path',
    ];

    public function pdls(): HasMany
    {
        return $this->hasMany(Pdl::class);
    }

    public function dormitories(): HasMany
    {
        return $this->hasMany(Dormitory::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function admins(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopeVisibleToUser(Builder $query, ?User $user = null): Builder
    {
        if ($user === null || $user->isSuperAdmin()) {
            return $query;
        }

        return $query->whereKey($user->facility_id ?? 0);
    }
}
