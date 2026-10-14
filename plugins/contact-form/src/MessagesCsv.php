<?php

namespace PnPress\ContactForm;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export of the inbox. Cells that a spreadsheet would read as a formula are prefixed with a
 * quote, so a message such as "=HYPERLINK(...)" stays text.
 */
class MessagesCsv
{
    public const HEADER = ['id', 'received_at', 'name', 'email', 'message', 'page_url', 'read_at', 'handled_at'];

    /**
     * @param  iterable<ContactMessage>  $messages
     */
    public static function download(iterable $messages, ?string $filename = null): StreamedResponse
    {
        $filename ??= 'contact-messages-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($messages) {
            echo static::toString($messages);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  iterable<ContactMessage>  $messages
     */
    public static function toString(iterable $messages): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, self::HEADER, escape: '');

        foreach ($messages as $message) {
            fputcsv($out, array_map(self::cell(...), [
                $message->id,
                $message->created_at?->toDateTimeString(),
                $message->name,
                $message->email,
                $message->message,
                $message->page_url,
                $message->read_at?->toDateTimeString(),
                $message->handled_at?->toDateTimeString(),
            ]), escape: '');
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    public static function cell(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
