<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking)
    {
        $this->booking->loadMissing(['customer', 'property', 'units', 'program', 'addOns']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Booking Confirmation — ' . $this->booking->property->name);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.booking-confirmation');
    }
}
