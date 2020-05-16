<?php

namespace App\Services\Shares;

use App\User;
use DB;
use Illuminate\Support\Collection;

class DetachUsersFromEntries
{
    /**
     * Detach (non owner) users from specified entries.
     *
     * @param array|Collection $entryIds
     * @param array|Collection $userIds
     */
    public function execute($entryIds, $userIds)
    {
        DB::table('file_entry_models')
            ->whereIn('file_entry_id', $entryIds)
            ->whereIn('model_id', $userIds)
            ->where('model_type', User::class)
            ->where('owner', false)
            ->delete();
    }
}
