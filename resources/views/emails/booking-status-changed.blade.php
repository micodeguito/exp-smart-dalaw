@component('mail::message')
@if ($booking->status === 'Approved')
# Your Booking Has Been Approved - Smart Dalaw

Hello {{ $booking->visitor_full_name }},

Your visitation request has been approved. Please review the visit details below.
@elseif ($booking->status === 'Rejected')
# Your Booking Has Been Rejected - Smart Dalaw

Hello {{ $booking->visitor_full_name }},

Your visitation request was not approved. The reason recorded by the facility is shown below.
@else
# Booking Status Update - Smart Dalaw

Hello {{ $booking->visitor_full_name }},

The status of your visitation request has been updated.
@endif

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
- **Previous status:** {{ $previousStatus }}
- **Current status:** {{ $booking->status }}

@if ($rejectionReason)
## Reason for rejection

{{ $rejectionReason }}
@endif

@if ($booking->status === 'Approved')
## Before your visit

- Bring the valid ID you used in this booking.
- Arrive 15 minutes before your scheduled time slot.
- Present your booking reference or QR code at the facility entrance.
@endif

## Check your booking status

Visit the Smart Dalaw tracking page and enter reference number **{{ $booking->reference_code }}** to see the latest status and visit details.

@component('mail::button', ['url' => route('bookings.track')])
Track my booking
@endcomponent

@if ($booking->visitor_email)
You received this notification because an email address was provided with your booking. ID photos are never attached to emails and are only viewable by authorized facility staff.
@endif
@endcomponent
