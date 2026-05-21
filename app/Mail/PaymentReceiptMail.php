<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Payment $payment, public Booking $booking)
    {
        $this->booking->loadMissing(['customer', 'property']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Payment Receipt — ' . $this->booking->property->name);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.payment-receipt');
    }
}
