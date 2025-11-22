<?php

namespace NodusIT\LaravelMailSync\Facades;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use NodusIT\LaravelMailSync\Models\MailAccount;
use NodusIT\LaravelMailSync\Models\MailMessage as MailMessageModel;
use NodusIT\LaravelMailSync\Services\MailMessageService;
use Webklex\PHPIMAP\Message;

/**
 * MailMessage Facade
 *
 * Provides static access to the MailMessageService for syncing and managing email messages.
 * This facade allows you to perform mail message operations like syncing from IMAP servers,
 * storing messages, and retrieving folder information.
 *
 * @method static Collection syncMessages(MailAccount $mailAccount, string $folderName = 'INBOX', ?int $limit = null) Sync messages from a mail account
 * @method static MailMessageModel|null storeMessage(MailAccount $mailAccount, Message $message) Store a single message in the database
 * @method static Collection getFolders(MailAccount $mailAccount) Get available folders for a mail account
 * @method static array getMessageCount(MailAccount $mailAccount, string $folderName = 'INBOX') Get message count for a specific folder
 *
 * @throws \Exception When IMAP connection fails or sync operations fail
 *
 * @see \NodusIT\LaravelMailSync\Services\MailMessageService
 */
class MailMessage extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return MailMessageService::class;
    }
}
