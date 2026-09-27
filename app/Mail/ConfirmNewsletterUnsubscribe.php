<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ConfirmNewsletterUnsubscribe extends Mailable
{
    public function __construct(public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Konfirmasi berhenti berlangganan Besofton Insights');
    }

    public function content(): Content
    {
        return new Content(text: 'emails.newsletter-unsubscribe');
    }
}
