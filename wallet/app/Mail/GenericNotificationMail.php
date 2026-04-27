<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GenericNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{title:string,body:string,cta_label?:string,cta_url?:string}  $payload
     */
    public function __construct(
        public array $payload,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: (string) ($this->payload['title'] ?? 'Notification'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.generic_notification',
            with: [
                'title' => (string) ($this->payload['title'] ?? ''),
                'body' => (string) ($this->payload['body'] ?? ''),
                'cta_label' => $this->payload['cta_label'] ?? null,
                'cta_url' => $this->payload['cta_url'] ?? null,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

