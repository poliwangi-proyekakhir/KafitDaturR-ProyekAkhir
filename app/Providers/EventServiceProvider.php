<?php

namespace App\Providers;

use App\Listeners\AttachUsersToNewlyUploadedFile;
use App\Listeners\DeleteShareableLinks;
use App\Listeners\FolderTotalSizeSubscriber;
use App\Listeners\HydrateUserWithSampleDriveContents;
use Common\Auth\Events\UserCreated;
use Common\Files\Events\FileEntriesDeleted;
use Common\Files\Events\FileEntryCreated;
use Event;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        FileEntryCreated::class => [
            AttachUsersToNewlyUploadedFile::class,
        ],

        FileEntriesDeleted::class => [
            DeleteShareableLinks::class,
        ],
    ];

    protected $subscribe = [
        FolderTotalSizeSubscriber::class
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        if (config('common.site.demo')) {
            Event::listen(UserCreated::class, HydrateUserWithSampleDriveContents::class);
        }
    }
}
