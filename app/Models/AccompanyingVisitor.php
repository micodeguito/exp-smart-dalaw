<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccompanyingVisitor extends Model
{
    /** @use HasFactory<\Database\Factories\AccompanyingVisitorFactory> */
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'full_name',
        'age',
        'sex',
        'id_type',
        'id_number',
        'id_photo_path',
        'relationship_to_pdl',
    ];

    protected $casts = [
        'age' => 'integer',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
