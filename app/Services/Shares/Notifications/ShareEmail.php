<?php

namespace App\Services\Shares\Notifications;

use App;
use App\FileEntry;
use App\User;
use Common\Mail\MailTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ShareEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var string
     */
    public $displayName;

    /**
     * @var string
     */
    public $itemName;

    /**
     * @var string
     */
    public $link;

    /**
     * @var bool
     */
    public $emailMessage;

    /**
     * @var array
     */
    protected $sharees;

    /**
     * @param User $sharer
     * @param array $sharees
     * @param array $entryIds
     */
    public function __construct(User $sharer, $sharees, $entryIds)
    {
        $this->displayName = $sharer->display_name;
        $this->itemName = app(FileEntry::class)->whereIn('id', $entryIds)->pluck('name')->implode(', ');
        $this->link = url('drive/shares');
        $this->emailMessage = false;
        $this->sharees = $sharees;
    }

    public function build()
    {
        $template = App::make(MailTemplates::class)->getByAction('share', [
            'display_name' => $this->displayName,
            'item_name'=> $this->itemName
        ]);

        return $this->to($this->sharees)
            ->subject($template['subject'])
            ->view($template['html_view'])
            ->text($template['plain_view']);
    }
}
