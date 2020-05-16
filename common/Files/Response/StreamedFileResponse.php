<?php

namespace Common\Files\Response;

use Common\Files\FileEntry;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamedFileResponse implements FileResponse
{
    /**
     * @param FileEntry $entry
     * @param array $options
     * @return mixed
     */
    public function make(FileEntry $entry, $options)
    {
        $response = new StreamedResponse;
        $disposition = $response->headers->makeDisposition(
            $options['disposition'], $entry->getNameWithExtension(), str_replace('%', '', Str::ascii($entry->getNameWithExtension()))
        );
        $response->headers->replace([
            'Content-Type' => $entry->mime,
            'Content-Length' => $entry->file_size,
            'Content-Disposition' => $disposition,
        ]);
        $response->setCallback(function () use ($entry, $options) {
            $stream = $entry->getDisk()->readStream($entry->getStoragePath($options['useThumbnail']));
            fpassthru($stream);
            fclose($stream);
        });
        return $response;
    }
}
