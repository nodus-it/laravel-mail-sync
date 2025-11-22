<?php

namespace NodusIT\LaravelMailSync\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NodusIT\LaravelMailSync\Database\Factories\MailMessageFactory;

class MailMessage extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return MailMessageFactory::new();
    }

    protected $fillable = [
        'mail_account_id',
        'remote_uid',
        'remote_msg_no',
        'message_id',
        'subject',
        'sent_at',
        'received_at',
        'size',
        'importance',
        'priority',
        'from_email',
        'from_name',
        'reply_to_email',
        'reply_to_name',
        'in_reply_to',
        'references',
        'thread_hash',
        'body_text',
        'body_html',
        'body_preview',
        'raw_body',
        'raw_headers',
        'is_seen',
        'is_answered',
        'is_flagged',
        'is_deleted',
        'is_draft',
        'is_recent',
        'flags',
        'synced_at',
        'checksum',
        'last_sync_error',
    ];

    protected $casts = [
        'mail_account_id' => 'integer',
        'remote_uid' => 'integer',
        'remote_msg_no' => 'integer',
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
        'size' => 'integer',
        'importance' => 'integer',
        'priority' => 'integer',
        'is_seen' => 'boolean',
        'is_answered' => 'boolean',
        'is_flagged' => 'boolean',
        'is_deleted' => 'boolean',
        'is_draft' => 'boolean',
        'is_recent' => 'boolean',
        'flags' => 'array',
        'synced_at' => 'timestamp',
    ];

    /**
     * Get the mail account that owns this message
     */
    public function mailAccount(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class);
    }

    /**
     * Scope to get only unread messages
     */
    public function scopeUnread($query)
    {
        return $query->where('is_seen', false);
    }

    /**
     * Scope to get only read messages
     */
    public function scopeRead($query)
    {
        return $query->where('is_seen', true);
    }

    /**
     * Scope to get flagged messages
     */
    public function scopeFlagged($query)
    {
        return $query->where('is_flagged', true);
    }

    /**
     * Get a preview of the message body
     */
    public function getBodyPreviewAttribute($value)
    {
        if ($value) {
            return $value;
        }

        // Generate preview from body_text if not set
        if ($this->body_text) {
            return substr(strip_tags($this->body_text), 0, 255);
        }

        // Generate preview from body_html if body_text is not available
        if ($this->body_html) {
            return substr(strip_tags($this->body_html), 0, 255);
        }

        return null;
    }
}
