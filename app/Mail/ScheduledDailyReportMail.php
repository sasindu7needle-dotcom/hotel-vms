<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;

class ScheduledDailyReportMail extends Mailable
{
    use Queueable;

    /** @param array<int, array{type:string,label:string,columns:array<int,string>,rows:array<int,array<int,string>>,summary:string}> $reports */
    public function __construct(public string $scheduleName, public string $reportDate, public array $reports) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Daily report — {$this->scheduleName} — {$this->reportDate}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.daily-report');
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return array_map(fn (array $report) => Attachment::fromData(fn () => $this->csv($report), str($report['label'])->slug('_').'-'.$this->reportDate.'.csv')->withMime('text/csv'), $this->reports);
    }

    private function csv(array $report): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $report['columns']);
        foreach ($report['rows'] as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);
        return stream_get_contents($stream) ?: '';
    }
}
