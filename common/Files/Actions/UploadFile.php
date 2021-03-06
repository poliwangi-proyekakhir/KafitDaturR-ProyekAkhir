<?php

namespace Common\Files\Actions;

use Common\Files\Events\FileEntryCreated;
use Common\Files\FileEntry;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Image;
use Intervention\Image\Constraint;
use Intervention\Image\Exception\NotReadableException;
use Storage;

class UploadFile
{
    /**
     * @param string $disk
     * @param UploadedFile $uploadedFile
     * @param array $params
     * @return FileEntry
     */
    public function execute($disk, $uploadedFile, $params)
    {
        $fileEntry = app(CreateFileEntry::class)
            ->execute($uploadedFile, $params);

        $this->storeUpload($disk, $fileEntry, $uploadedFile);

        if ($disk !== 'public') {
            event(new FileEntryCreated($fileEntry, $params));
        }

        return $fileEntry;
    }

    private function storeUpload($diskName, FileEntry $entry, $contents)
    {
        if ($diskName === 'public') {
            $disk = Storage::disk('public');
            $prefix = $entry->disk_prefix;
        } else {
            $disk = Storage::disk('uploads');
            $prefix = $entry->file_name;
        }

        $options = [
            'mimetype' => $entry->mime,
            'visibility' => config('common.site.remote_file_visibility'),
        ];

        if (is_a($contents, UploadedFile::class)) {
            $disk->putFileAs($prefix, $contents, $entry->file_name, $options);
        } else {
            $disk->put("$prefix/{$entry->file_name}", $contents, $options);
        }

        if ($diskName !== 'public') {
            try {
                $this->maybeCreateThumbnail($disk, $entry, $contents);
            } catch (NotReadableException $e) {
                //
            }
        }
    }

    private function maybeCreateThumbnail(FilesystemAdapter $disk, FileEntry $entry, $contents)
    {
        // only create thumbnail for images over 500KB in size
        if ($entry->type === 'image' && $entry->file_size > 500000) {
            $img = Image::make($contents);

            $img->fit(350, 250, function (Constraint $constraint) {
                $constraint->upsize();
            });

            $img->encode('jpg', 60);

            $disk->put("{$entry->file_name}/thumbnail.jpg", $img);

            $entry->fill(['thumbnail' => true])->save();
        }
    }
}
