<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f7fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; color: #4a5568;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f7fafc; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">

                    {{-- Header --}}
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 40px 32px; text-align: center;">
                            <p style="margin: 0 0 8px; font-size: 13px; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(255,255,255,0.8);">
                                {{ $booking->property->name }}
                            </p>
                            <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #ffffff; letter-spacing: -0.5px;">
                                Booking Confirmation
                            </h1>
                            <p style="margin: 12px 0 0; font-size: 14px; color: rgba(255,255,255,0.75);">
                                Reference: <strong style="color: #ffffff; font-family: 'Courier New', monospace; letter-spacing: 1px;">{{ strtoupper(substr($booking->id, 0, 8)) }}</strong>
                            </p>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding: 40px 40px 0;">
                            <p style="margin: 0 0 24px; font-size: 16px; color: #4a5568;">
                                Dear <strong>{{ $booking->customer->first_name }}</strong>,
                            </p>
                            <p style="margin: 0 0 32px; font-size: 15px; color: #718096; line-height: 1.6;">
                                Your booking has been confirmed. We look forward to welcoming you. Below are your reservation details.
                            </p>
                        </td>
                    </tr>

                    {{-- Stay Details Card --}}
                    <tr>
                        <td style="padding: 0 40px 24px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f7fafc; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden;">
                                <tr>
                                    <td style="padding: 20px 24px 4px;">
                                        <p style="margin: 0; font-size: 11px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #a0aec0;">Stay Details</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 16px 24px;">
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td width="50%" style="padding-bottom: 16px;">
                                                    <p style="margin: 0 0 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #a0aec0;">Check-in</p>
                                                    <p style="margin: 0; font-size: 15px; font-weight: 600; color: #2d3748;">
                                                        {{ \Carbon\Carbon::parse($booking->check_in_date)->format('D, M j, Y') }}
                                                    </p>
                                                </td>
                                                <td width="50%" style="padding-bottom: 16px;">
                                                    <p style="margin: 0 0 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #a0aec0;">Check-out</p>
                                                    <p style="margin: 0; font-size: 15px; font-weight: 600; color: #2d3748;">
                                                        {{ \Carbon\Carbon::parse($booking->check_out_date)->format('D, M j, Y') }}
                                                    </p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td width="50%">
                                                    <p style="margin: 0 0 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #a0aec0;">Nights</p>
                                                    <p style="margin: 0; font-size: 15px; font-weight: 600; color: #2d3748;">
                                                        {{ \Carbon\Carbon::parse($booking->check_in_date)->diffInDays(\Carbon\Carbon::parse($booking->check_out_date)) }}
                                                    </p>
                                                </td>
                                                <td width="50%">
                                                    <p style="margin: 0 0 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #a0aec0;">Guests</p>
                                                    <p style="margin: 0; font-size: 15px; font-weight: 600; color: #2d3748;">
                                                        {{ $booking->guests ?? 1 }}
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Property & Accommodation --}}
                    <tr>
                        <td style="padding: 0 40px 24px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden;">
                                <tr>
                                    <td style="padding: 20px 24px 4px;">
                                        <p style="margin: 0; font-size: 11px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #a0aec0;">Accommodation</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 24px 20px;">

                                        {{-- Property --}}
                                        <p style="margin: 0 0 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #a0aec0;">Property</p>
                                        <p style="margin: 0 0 16px; font-size: 15px; font-weight: 600; color: #2d3748;">{{ $booking->property->name }}</p>

                                        {{-- Units --}}
                                        @if($booking->units->isNotEmpty())
                                        <p style="margin: 0 0 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #a0aec0;">
                                            {{ $booking->units->count() > 1 ? 'Units' : 'Unit' }}
                                        </p>
                                        <p style="margin: 0 0 16px; font-size: 15px; color: #4a5568;">
                                            {{ $booking->units->pluck('name')->join(', ') }}
                                        </p>
                                        @endif

                                        {{-- Program --}}
                                        @if($booking->program)
                                        <p style="margin: 0 0 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #a0aec0;">Program</p>
                                        <p style="margin: 0; font-size: 15px; color: #4a5568;">{{ $booking->program->name }}</p>
                                        @endif

                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Add-ons --}}
                    @if($booking->addOns->isNotEmpty())
                    <tr>
                        <td style="padding: 0 40px 24px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden;">
                                <tr>
                                    <td style="padding: 20px 24px 4px;">
                                        <p style="margin: 0; font-size: 11px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #a0aec0;">Add-ons</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 24px 20px;">
                                        @foreach($booking->addOns as $addOn)
                                        <p style="margin: 0; padding: 8px 0; font-size: 14px; color: #4a5568; border-bottom: 1px solid #edf2f7;">
                                            {{ $addOn->name }}
                                            @if(isset($addOn->pivot->quantity) && $addOn->pivot->quantity > 1)
                                                <span style="color: #a0aec0;">× {{ $addOn->pivot->quantity }}</span>
                                            @endif
                                        </p>
                                        @endforeach
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @endif

                    {{-- Price Summary --}}
                    <tr>
                        <td style="padding: 0 40px 24px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f7fafc; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden;">
                                <tr>
                                    <td style="padding: 20px 24px 4px;">
                                        <p style="margin: 0; font-size: 11px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #a0aec0;">Price Summary</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 24px;">

                                        @if(!empty($booking->tax_amount))
                                        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 8px;">
                                            <tr>
                                                <td style="font-size: 14px; color: #718096;">Subtotal</td>
                                                <td align="right" style="font-size: 14px; color: #718096;">
                                                    {{ number_format($booking->total_price - $booking->tax_amount, 2) }} {{ $booking->currency }}
                                                </td>
                                            </tr>
                                        </table>
                                        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 12px;">
                                            <tr>
                                                <td style="font-size: 14px; color: #718096;">Tax</td>
                                                <td align="right" style="font-size: 14px; color: #718096;">
                                                    {{ number_format($booking->tax_amount, 2) }} {{ $booking->currency }}
                                                </td>
                                            </tr>
                                        </table>
                                        <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 0 0 12px;">
                                        @endif

                                        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 16px;">
                                            <tr>
                                                <td style="font-size: 16px; font-weight: 700; color: #2d3748;">Total</td>
                                                <td align="right" style="font-size: 18px; font-weight: 700; color: #667eea;">
                                                    {{ number_format($booking->total_price, 2) }} {{ $booking->currency }}
                                                </td>
                                            </tr>
                                        </table>

                                        {{-- Payment status badge --}}
                                        @php
                                            $statusColors = [
                                                'paid'     => ['bg' => '#c6f6d5', 'text' => '#276749'],
                                                'partial'  => ['bg' => '#fefcbf', 'text' => '#744210'],
                                                'unpaid'   => ['bg' => '#fed7d7', 'text' => '#9b2c2c'],
                                                'refunded' => ['bg' => '#e9d8fd', 'text' => '#553c9a'],
                                            ];
                                            $statusKey = $booking->payment_status ?? 'unpaid';
                                            $colors = $statusColors[$statusKey] ?? ['bg' => '#e2e8f0', 'text' => '#4a5568'];
                                        @endphp
                                        <span style="display: inline-block; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; background-color: {{ $colors['bg'] }}; color: {{ $colors['text'] }};">
                                            {{ ucfirst(str_replace('_', ' ', $statusKey)) }}
                                        </span>

                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding: 32px 40px 40px; text-align: center; border-top: 1px solid #e2e8f0;">
                            <p style="margin: 0 0 8px; font-size: 15px; font-weight: 600; color: #4a5568;">
                                Thank you for choosing {{ $booking->property->name }}
                            </p>
                            <p style="margin: 0; font-size: 13px; color: #a0aec0;">
                                If you have any questions about your booking, please don't hesitate to contact us.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
