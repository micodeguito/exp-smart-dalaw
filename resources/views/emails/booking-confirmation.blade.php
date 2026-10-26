@component('mail::message')
# Booking Received - Smart Dalaw

Hello {{ $booking->visitor_full_name }},

Thank you for submitting your visitation request. Your booking has been received and is awaiting review by the facility.

@component('mail::panel')
**Booking reference:** {{ $booking->reference_code }}
@endcomponent

## Booking Details

- **Facility:** {{ $booking->facility->name }}
- **Dormitory:** {{ $booking->dormitory?->name ?? 'Not assigned' }}
- **Person deprived of liberty (PDL):** {{ $booking->getPdlFullNameAttribute() }}
- **Visitor:** {{ $booking->visitor_full_name }}
- **Visit date:** {{ $booking->visit_date->format('M d, Y') }}
- **Time slot:** {{ $booking->time_slot }}
- **Status:** {{ $booking->status }}

## Check your booking status

Visit the Smart Dalaw tracking page and enter reference number **{{ $booking->reference_code }}** to see the latest status and visit details. The facility administrator must approve your visit before check-in.

@component('mail::button', ['url' => route('bookings.track')])
Track my booking
@endcomponent

@if ($booking->visitor_email)
You received this confirmation because an email address was provided with your booking. ID photos are never attached to emails and are only viewable by authorized facility staff.
@endif
@endcomponent
