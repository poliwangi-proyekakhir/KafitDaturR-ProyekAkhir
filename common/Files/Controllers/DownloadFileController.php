<?php namespace Common\Files\Controllers;

use Carbon\Carbon;
use Common\Files\Response\FileResponseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Common\Core\BaseController;
use Common\Files\FileEntry;
use ZipArchive;
use ZipStream\Option\Archive;
use ZipStream\ZipStream;

class DownloadFileController extends BaseController
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var FileEntry
     */
    private $fileEntry;

    /**
     * @var FileResponseFactory
     */
    private $fileResponseFactory;

    /**
     * @param Request $request
     * @param FileEntry $fileEntry
     * @param FileResponseFactory $fileResponseFactory
     */
    public function __construct(Request $request, FileEntry $fileEntry, FileResponseFactory $fileResponseFactory)
    {
        $this->request = $request;
        $this->fileEntry = $fileEntry;
        $this->fileResponseFactory = $fileResponseFactory;
    }

    public function download()
    {
        $hashes = explode(',', $this->request->get('hashes'));
        $ids = array_map(function($hash) {
            return $this->fileEntry->decodeHash($hash);
        }, $hashes);

        $entries = $this->fileEntry->whereIn('id', $ids)->get();

        // TODO: refactor file entry policy to accent multiple IDs
        $entries->each(function($entry) {
            $this->authorize('show', [FileEntry::class, $entry]);
        });

        if ($entries->count() === 1 && $entries->first()->type !== 'folder') {
            return $this->fileResponseFactory->create($entries->first(), 'attachment');
        } else {
            $this->streamZip($entries);
        }
    }

    /**
     * @param Collection $entries
     * @return void
     */
    private function streamZip(Collection $entries)
    {
        $options = new Archive();
        $options->setSendHttpHeaders(true);

        $timestamp = Carbon::now()->getTimestamp();
        $zip = new ZipStream("download-$timestamp.zip", $options);

        $this->fillZip($zip, $entries);
        $zip->finish();
    }

    /**
     * @param ZipStream $zip
     * @param Collection $entries
     */
    private function fillZip(ZipStream $zip, Collection $entries) {
        $entries->each(function(FileEntry $entry) use($zip) {
            if ($entry->type === 'folder') {
                // this will load all children, nested at any level, so no need to do a recursive loop
                $children = $entry->findChildren();
                $children->each(function(FileEntry $childEntry) use($zip, $entry, $children) {
                    $path = $this->transformPath($childEntry, $entry, $children);
                    if ($childEntry->type === 'folder') {
                        // add empty folder in case it has no children
                        $zip->addFile("$path/", '');
                    } else {
                        $this->addFileToZip($childEntry, $zip, $path);
                    }
                });
            } else {
                $this->addFileToZip($entry, $zip);
            }
        });
    }

    /**
     * @param FileEntry $entry
     * @param ZipStream $zip
     * @param string $path
     */
    private function addFileToZip(FileEntry $entry, ZipStream $zip, $path = null)
    {
        if ( ! $path) {
            $path = $entry->getNameWithExtension();
        }
        $stream = $entry->getDisk()->readStream($entry->getStoragePath());
        $zip->addFileFromStream($path, $stream);
    }

    /**
     * Replace entry IDs with names inside "path" property.
     *
     * @param FileEntry $entry
     * @param FileEntry $parent
     * @param Collection $folders
     * @return string
     */
    private function transformPath(FileEntry $entry, FileEntry $parent, Collection $folders)
    {
        if ( ! $entry->path) return $entry->getNameWithExtension();

        // '56/55/54 => [56,55,54]
        $path = array_filter(explode('/', $entry->path));
        $path = array_map(function($id) {
            return (int) $id;
        }, $path);

        //only generate path until specified parent and not root
        $path = array_slice($path, array_search($parent->id, $path));

        // last value will be id of the file itself, remove it
        array_pop($path);

        //map parent folder IDs to names
        $path = array_map(function($id) use($folders) {
            return $folders->find($id)->name;
        }, $path);

        return implode('/', $path) . '/' . $entry->getNameWithExtension();
    }
}
