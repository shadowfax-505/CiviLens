<?php

namespace App\Mail;

use App\Models\CitizenReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CitizenReportAcknowledgement extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public CitizenReport $report) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "CivicLens report acknowledgement: {$this->report->public_uuid}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.citizen-reports.acknowledgement',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
