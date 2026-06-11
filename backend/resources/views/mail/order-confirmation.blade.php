<x-mail::message>
# {{ __('mail.greeting', ['name' => $order->customer_name]) }}

{{ __('mail.intro', ['number' => $order->number]) }}

@foreach ($order->bookings as $booking)
<x-mail::panel>
**{{ $booking->vehicle?->name }}**

- {{ __('mail.pickup') }}: {{ $booking->pickupLocation?->name }} — {{ $booking->date_start->format('d/m/Y') }} {{ substr($booking->time_start ?? '', 0, 5) }}
- {{ __('mail.dropoff') }}: {{ $booking->dropoffLocation?->name ?? $booking->pickupLocation?->name }} — {{ $booking->date_end->format('d/m/Y') }} {{ substr($booking->time_end ?? '', 0, 5) }}
- {{ __('mail.days') }}: {{ $booking->days }}
@if ($booking->extras)
- {{ __('mail.extras') }}: {{ collect($booking->extras)->map(fn ($e) => $e['name'].' x '.$e['qty'])->join(', ') }}
@endif
</x-mail::panel>
@endforeach

| | |
|---:|---:|
| {{ __('mail.total') }} | **€{{ number_format((float) $order->total, 2, ',', '.') }}** |
@if ($order->discount_total > 0)
| {{ __('mail.discount') }} ({{ $order->coupon_code }}) | −€{{ number_format((float) $order->discount_total, 2, ',', '.') }} |
@endif
| {{ __('mail.paid') }} | €{{ number_format((float) $order->paid_total, 2, ',', '.') }} |
@if ($order->paid_total < $order->total)
| {{ __('mail.balance') }} | €{{ number_format((float) $order->total - (float) $order->paid_total, 2, ',', '.') }} |
@endif

{{ __('mail.outro') }}

{{ __('mail.signature') }}<br>
easyRent24h
</x-mail::message>
