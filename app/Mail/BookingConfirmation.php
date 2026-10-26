<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Booking $booking,
    ) {
        //
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this->subject('Your Smart Dalaw Booking Reference '.$this->booking->reference_code)
            ->markdown('emails.booking-confirmation', [
                'booking' => $this->booking,
            ]);
    }
}
