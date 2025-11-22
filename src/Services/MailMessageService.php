<?php

namespace NodusIT\LaravelMailSync\Services;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use NodusIT\LaravelMailSync\Models\MailAccount;
use NodusIT\LaravelMailSync\Models\MailMessage;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Message;

class MailMessageService
{
    protected ClientManager $clientManager;

    public function __construct(?ClientManager $clientManager = null)
    {
        $this->clientManager = $clientManager ?? new ClientManager([]);
    }

    /**
     * Sync messages from a mail account
     *
     * @param MailAccount $mailAccount
     * @param string $folderName
     * @param int|null $limit
     * @return Collection
     * @throws Exception
     */
    public function syncMessages(MailAccount $mailAccount, string $folderName = 'INBOX', ?int $limit = null): Collection
    {
        try {
            $client = $this->createClient($mailAccount);
            $client->connect();

            $folder = $client->getFolder($folderName);
            $messages = $folder->messages()->all();

            if ($limit) {
                $messages = $messages->limit($limit);
            }

            $syncedMessages = collect();

            foreach ($messages->get() as $message) {
                try {
                    $mailMessage = $this->storeMessage($mailAccount, $message);
                    if ($mailMessage) {
                        $syncedMessages->push($mailMessage);
                    }
                } catch (Exception $e) {
                    Log::warning('Failed to sync message', [
                        'mail_account_id' => $mailAccount->id,
                        'message_uid' => $message->getUid(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $client->disconnect();

            // Update last synced timestamp
            $mailAccount->update(['last_synced_at' => now()]);

            return $syncedMessages;

        } catch (ConnectionFailedException $e) {
            $mailAccount->update([
                'last_connection_failed_at' => now(),
                'last_connection_error' => 'Connection failed: ' . $e->getMessage(),
            ]);
            throw new Exception('Failed to connect to mail server: ' . $e->getMessage(), 0, $e);
        } catch (Exception $e) {
            throw new Exception('Failed to sync messages: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Store a single message in the database
     *
     * @param MailAccount $mailAccount
     * @param Message $message
     * @return MailMessage|null
     */
    public function storeMessage(MailAccount $mailAccount, Message $message): ?MailMessage
    {
        $remoteUid = $message->getUid();

        // Check if message already exists
        $existingMessage = MailMessage::where('mail_account_id', $mailAccount->id)
            ->where('remote_uid', $remoteUid)
            ->first();

        if ($existingMessage) {
            // Update existing message with current flags and status
            return $this->updateExistingMessage($existingMessage, $message);
        }

        // Create new message
        return $this->createNewMessage($mailAccount, $message);
    }

    /**
     * Create a new mail message from IMAP message
     *
     * @param MailAccount $mailAccount
     * @param Message $message
     * @return MailMessage
     */
    protected function createNewMessage(MailAccount $mailAccount, Message $message): MailMessage
    {
        $messageData = $this->parseMessage($message);
        $messageData['mail_account_id'] = $mailAccount->id;
        $messageData['synced_at'] = now();

        return MailMessage::create($messageData);
    }

    /**
     * Update existing message with current data
     *
     * @param MailMessage $existingMessage
     * @param Message $message
     * @return MailMessage
     */
    protected function updateExistingMessage(MailMessage $existingMessage, Message $message): MailMessage
    {
        $flags = $message->getFlags();

        $existingMessage->update([
            'is_seen' => $flags->contains('Seen'),
            'is_answered' => $flags->contains('Answered'),
            'is_flagged' => $flags->contains('Flagged'),
            'is_deleted' => $flags->contains('Deleted'),
            'is_draft' => $flags->contains('Draft'),
            'is_recent' => $flags->contains('Recent'),
            'flags' => $flags->toArray(),
            'synced_at' => now(),
            'last_sync_error' => null,
        ]);

        return $existingMessage->fresh();
    }

    /**
     * Parse IMAP message into array suitable for MailMessage model
     *
     * @param Message $message
     * @return array
     */
    protected function parseMessage(Message $message): array
    {
        $flags = $message->getFlags();
        $from = $message->getFrom()->first();
        $replyTo = $message->getReplyTo()->first();

        // Generate body preview
        $bodyText = $message->getTextBody();
        $bodyHtml = $message->getHTMLBody();
        $bodyPreview = null;

        if ($bodyText) {
            $bodyPreview = substr(strip_tags($bodyText), 0, 255);
        } elseif ($bodyHtml) {
            $bodyPreview = substr(strip_tags($bodyHtml), 0, 255);
        }

        // Generate checksum for duplicate detection
        $checksumData = $message->getMessageId() . $message->getSubject() . $message->getDate();
        $checksum = hash('sha256', $checksumData);

        return [
            'remote_uid' => $message->getUid(),
            'remote_msg_no' => $message->getMsgn(),
            'message_id' => $message->getMessageId(),
            'subject' => $message->getSubject(),
            'sent_at' => $message->getDate(),
            'received_at' => now(), // We don't have the actual received date from IMAP
            'size' => $message->getSize(),
            'importance' => $this->parseImportance($message),
            'priority' => $this->parsePriority($message),
            'from_email' => $from ? $from->mail : null,
            'from_name' => $from ? $from->personal : null,
            'reply_to_email' => $replyTo ? $replyTo->mail : null,
            'reply_to_name' => $replyTo ? $replyTo->personal : null,
            'in_reply_to' => $message->getInReplyTo(),
            //'references' => $message->getReferences() ? implode(' ', $message->getReferences()) : null,
            'thread_hash' => $this->generateThreadHash($message),
            'body_text' => $bodyText,
            'body_html' => $bodyHtml,
            'body_preview' => $bodyPreview,
            'raw_body' => null, // We don't store raw body by default to save space
            'raw_headers' => $message->getRawHeader(),
            'is_seen' => $flags->contains('Seen'),
            'is_answered' => $flags->contains('Answered'),
            'is_flagged' => $flags->contains('Flagged'),
            'is_deleted' => $flags->contains('Deleted'),
            'is_draft' => $flags->contains('Draft'),
            'is_recent' => $flags->contains('Recent'),
            'flags' => $flags->toArray(),
            'checksum' => $checksum,
            'last_sync_error' => null,
        ];
    }

    /**
     * Parse message importance from headers
     *
     * @param Message $message
     * @return int|null
     */
    protected function parseImportance(Message $message): ?int
    {
        try {
            $importance = $message->getHeader()->get('importance');
            if ($importance && $importance->first()) {
                return match (strtolower($importance->first())) {
                    'high' => 1,
                    'normal' => 3,
                    'low' => 5,
                    default => null,
                };
            }
        } catch (Exception $e) {
            // Header doesn't exist or can't be parsed
        }
        return null;
    }

    /**
     * Parse message priority from headers
     *
     * @param Message $message
     * @return int|null
     */
    protected function parsePriority(Message $message): ?int
    {
        try {
            $priority = $message->getHeader()->get('x-priority');
            if ($priority && $priority->first()) {
                $priorityValue = (int) $priority->first();
                return $priorityValue >= 1 && $priorityValue <= 5 ? $priorityValue : null;
            }
        } catch (Exception $e) {
            // Header doesn't exist or can't be parsed
        }
        return null;
    }

    /**
     * Generate thread hash for message threading
     *
     * @param Message $message
     * @return string|null
     */
    protected function generateThreadHash(Message $message): ?string
    {
        $subject = $message->getSubject();
        $inReplyTo = $message->getInReplyTo();
        $references = $message->getReferences();

        // Clean subject from Re: and Fwd: prefixes
        $cleanSubject = preg_replace('/^(Re:|Fwd?:)\s*/i', '', $subject);

        if ($inReplyTo || $references) {
            // Use the first reference or in-reply-to as thread identifier
            $threadId = $inReplyTo ?: ($references ? $references[0] : null);
            return $threadId ? hash('sha256', $threadId) : null;
        }

        // For new threads, use cleaned subject
        return $cleanSubject ? hash('sha256', $cleanSubject) : null;
    }

    /**
     * Create IMAP client for mail account
     *
     * @param MailAccount $mailAccount
     * @return \Webklex\PHPIMAP\Client
     * @throws Exception
     */
    protected function createClient(MailAccount $mailAccount)
    {
        try {
            return $this->clientManager->make([
                'host' => $mailAccount->host,
                'port' => $mailAccount->port,
                'encryption' => $mailAccount->encryption,
                'validate_cert' => true,
                'username' => $mailAccount->username,
                'password' => $mailAccount->password,
                'protocol' => 'imap',
            ]);
        } catch (Exception $e) {
            throw new Exception('Failed to create IMAP client: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get available folders for a mail account
     *
     * @param MailAccount $mailAccount
     * @return Collection
     * @throws Exception
     */
    public function getFolders(MailAccount $mailAccount): Collection
    {
        try {
            $client = $this->createClient($mailAccount);
            $client->connect();

            $folders = $client->getFolders();
            $client->disconnect();

            return $folders->map(function ($folder) {
                return [
                    'name' => $folder->name,
                    'full_name' => $folder->full_name,
                    'delimiter' => $folder->delimiter,
                    'has_children' => $folder->hasChildren(),
                ];
            });

        } catch (Exception $e) {
            throw new Exception('Failed to get folders: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get message count for a specific folder
     *
     * @param MailAccount $mailAccount
     * @param string $folderName
     * @return array
     * @throws Exception
     */
    public function getMessageCount(MailAccount $mailAccount, string $folderName = 'INBOX'): array
    {
        try {
            $client = $this->createClient($mailAccount);
            $client->connect();

            $folder = $client->getFolder($folderName);
            $total = $folder->messages()->count();
            $unread = $folder->messages()->unseen()->count();

            $client->disconnect();

            return [
                'total' => $total,
                'unread' => $unread,
                'read' => $total - $unread,
            ];

        } catch (Exception $e) {
            throw new Exception('Failed to get message count: ' . $e->getMessage(), 0, $e);
        }
    }
}
