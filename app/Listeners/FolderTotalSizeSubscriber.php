<?php

namespace App\Listeners;

use App\FileEntry;
use Common\Files\Events\FileEntriesDeleted;
use Common\Files\Events\FileEntriesMoved;
use Common\Files\Events\FileEntriesRestored;
use Common\Files\Events\FileEntryCreated;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Collection;

class FolderTotalSizeSubscriber
{
    /**
     * @param  FileEntryCreated  $event
     * @return void
     */
    public function onEntryCreated(FileEntryCreated $event)
    {
        $entry = $event->fileEntry;
        if ($entry->type !== 'folder' && $entry->parent_id) {
            app(FileEntry::class)->where('id', $entry->parent_id)->increment('file_size', $entry->file_size);
        }
    }

    /**
     * @param FileEntriesDeleted|FileEntriesRestored $event
     */
    public function onEntriesDeletedOrRestored($event)
    {
        $groupedEntries = app(FileEntry::class)
            ->withTrashed()
            ->whereIn('id', $event->entryIds)
            ->whereNotNull('parent_id')
            ->get()
            ->groupBy('parent_id');

        $method = is_a($event, FileEntriesDeleted::class) ? 'decrement' : 'increment';

        $groupedEntries->each(function(Collection $entries, $parentId) use($method) {
            app(FileEntry::class)->where('id', $parentId)->$method('file_size', $entries->sum('file_size'));
        });
    }

    public function onEntriesMoved(FileEntriesMoved $event)
    {
        $movedEntriesSize = app(FileEntry::class)
            ->whereIn('id', $event->entryIds)
            ->sum('file_size');

        // files could be moved from or to root
        if ($event->destination) {
            app(FileEntry::class)->where('id', $event->destination)->increment('file_size', $movedEntriesSize);
        }
        if ($event->source) {
            app(FileEntry::class)->where('id', $event->source)->decrement('file_size', $movedEntriesSize);
        }
    }

    /**
     * @param  Dispatcher  $events
     */
    public function subscribe($events)
    {
        $events->listen(
            FileEntryCreated::class,
            self::class . '@onEntryCreated'
        );

        $events->listen(
            FileEntriesMoved::class,
            self::class . '@onEntriesMoved'
        );

        $events->listen(
            [FileEntriesDeleted::class, FileEntriesRestored::class],
            self::class . '@onEntriesDeletedOrRestored'
        );
    }
}
