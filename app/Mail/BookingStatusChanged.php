<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingStatusChanged extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Booking $booking,
        public string $previousStatus,
        public ?string $rejectionReason = null,
    ) {
        //
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        $label = match ($this->booking->status) {
            'Approved' => 'Approved',
            'Rejected' => 'Rejected',
            default => 'Update',
        };

        return $this->subject("Smart Dalaw Booking {$label} - ".$this->booking->reference_code)
            ->markdown('emails.booking-status-changed', [
                'booking' => $this->booking,
                'previousStatus' => $this->previousStatus,
                'rejectionReason' => $this->rejectionReason,
            ]);
    }
}
