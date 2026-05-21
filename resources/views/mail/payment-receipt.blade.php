<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f7fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; color: #4a5568;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f7fafc; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">

                    {{-- Header --}}
                    <tr>
                        <td style="background: linear-gradient(135deg, #48bb78 0%, #2f855a 100%); padding: 40px 40px 32px; text-align: center;">
                            <p style="margin: 0 0 8px; font-size: 13px; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(255,255,255,0.8);">
                                {{ $booking->property->name }}
                            </p>
                            <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #ffffff; letter-spacing: -0.5px;">
                                Payment Receipt
                            </h1>
                            <p style="margin: 12px 0 0; font-size: 14px; color: rgba(255,255,255,0.75);">
                                Booking reference: <strong style="color: #ffffff; font-family: 'Courier New', monospace; letter-spacing: 1px;">{{ strtoupper(substr($booking->id, 0, 8)) }}</strong>
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
                                We have received your payment. Please keep this receipt for your records.
                            </p>
                        </td>
                    </tr>

                    {{-- Payment Details Card --}}
                    <tr>
                        <td style="padding: 0 40px 24px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f0fff4; border-radius: 8px; border: 1px solid #c6f6d5; overflow: hidden;">
                                <tr>
                                    <td style="padding: 20px 24px 4px;">
                                        <p style="margin: 0; font-size: 11px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #68d391;">Payment Details</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 24px 24px;">

                                        {{-- Amount --}}
                                        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #c6f6d5;">
                                            <tr>
                                                <td>
                                                    <p style="margin: 0 0 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #68d391;">Amount Paid</p>
                                                    <p style="margin: 0; font-size: 28px; font-weight: 700; color: #276749;">
                                                        {{ number_format($payment->amount, 2) }} {{ $payment->currency }}
                                                    </p>
                                                </td>
                                                <td align="right" valign="top">
                                                    @php
                                                        $statusColors = [
                                                            'completed' => ['bg' => '#c6f6d5', 'text' => '#276749'],
                                                            'pending'   => ['bg' => '#fefcbf', 'text' => '#744210'],
                                                            'failed'    => ['bg' => '#fed7d7', 'text' => '#9b2c2c'],
                                                            'refunded'  => ['bg' => '#e9d8fd', 'text' => '#553c9a'],
                                                        ];
                                                        $statusKey = $payment->status ?? 'completed';
                                                        $colors = $statusColors[$statusKey] ?? ['bg' => '#e2e8f0', 'text' => '#4a5568'];
                                                    @endphp
                                                    <span style="display: inline-block; padding: 6px 16px; border-radius: 9999px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; background-color: {{ $colors['bg'] }}; color: {{ $colors['text'] }};">
                                                        {{ ucfirst($statusKey) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>

                                        {{-- Payment meta --}}
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td width="50%" style="padding-bottom: 16px;">
                                                    <p style="margin: 0 0 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #68d391;">Payment Method</p>
                                                    <p style="margin: 0; font-size: 15px; font-weight: 600; color: #2d3748;">
                                                        {{ ucfirst(str_replace('_', ' ', $payment->method)) }}
                                                    </p>
                                                </td>
                                                <td width="50%" style="padding-bottom: 16px;">
                                                    <p style="margin: 0 0 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #68d391;">Date Paid</p>
                                                    <p style="margin: 0; font-size: 15px; font-weight: 600; color: #2d3748;">
                                                        @if($payment->paid_at)
                                                            {{ \Carbon\Carbon::parse($payment->paid_at)->format('M j, Y') }}
                                                        @else
                                                            —
                                                        @endif
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>

                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Booking Reference --}}
                    <tr>
                        <td style="padding: 0 40px 24px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden;">
                                <tr>
                                    <td style="padding: 20px 24px 4px;">
                                        <p style="margin: 0; font-size: 11px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #a0aec0;">Booking Information</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 24px 20px;">
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td width="50%">
                                                    <p style="margin: 0 0 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #a0aec0;">Reference</p>
                                                    <p style="margin: 0; font-size: 15px; font-weight: 600; color: #2d3748; font-family: 'Courier New', monospace; letter-spacing: 1px;">
                                                        {{ strtoupper(substr($booking->id, 0, 8)) }}
                                                    </p>
                                                </td>
                                                <td width="50%">
                                                    <p style="margin: 0 0 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #a0aec0;">Property</p>
                                                    <p style="margin: 0; font-size: 15px; font-weight: 600; color: #2d3748;">
                                                        {{ $booking->property->name }}
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
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
                                If you have any questions about this payment, please don't hesitate to contact us.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
