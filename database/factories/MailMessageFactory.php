<?php

namespace NodusIT\LaravelMailSync\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NodusIT\LaravelMailSync\Models\MailAccount;
use NodusIT\LaravelMailSync\Models\MailMessage;

class MailMessageFactory extends Factory
{
    protected $model = MailMessage::class;

    public function definition(): array
    {
        $sentAt = $this->faker->dateTimeBetween('-1 month', 'now');
        $receivedAt = $this->faker->dateTimeBetween($sentAt, 'now');
        $bodyText = $this->faker->paragraphs(3, true);
        $bodyHtml = '<p>'.implode('</p><p>', $this->faker->paragraphs(3)).'</p>';

        return [
            'mail_account_id' => MailAccount::factory(),
            'remote_uid' => $this->faker->unique()->numberBetween(1, 999999),
            'remote_msg_no' => $this->faker->optional(0.8)->numberBetween(1, 9999),
            'message_id' => '<'.$this->faker->uuid().'@'.$this->faker->domainName().'>',
            'subject' => $this->faker->sentence(),
            'sent_at' => $sentAt,
            'received_at' => $receivedAt,
            'size' => $this->faker->numberBetween(1024, 1048576), // 1KB to 1MB
            'importance' => $this->faker->optional(0.3)->numberBetween(1, 5),
            'priority' => $this->faker->optional(0.3)->numberBetween(1, 5),
            'from_email' => $this->faker->safeEmail(),
            'from_name' => $this->faker->name(),
            'reply_to_email' => $this->faker->optional(0.3)->safeEmail(),
            'reply_to_name' => $this->faker->optional(0.3)->name(),
            'in_reply_to' => $this->faker->optional(0.2)->regexify('<[a-f0-9-]{36}@[a-z0-9.-]+\.[a-z]{2,}>'),
            'references' => $this->faker->optional(0.2)->regexify('<[a-f0-9-]{36}@[a-z0-9.-]+\.[a-z]{2,}>'),
            'thread_hash' => $this->faker->optional(0.5)->sha256(),
            'body_text' => $bodyText,
            'body_html' => $bodyHtml,
            'body_preview' => substr(strip_tags($bodyText), 0, 255),
            'raw_body' => $this->faker->optional(0.7)->text(2000),
            'raw_headers' => $this->generateRawHeaders(),
            'is_seen' => $this->faker->boolean(60),
            'is_answered' => $this->faker->boolean(20),
            'is_flagged' => $this->faker->boolean(10),
            'is_deleted' => $this->faker->boolean(5),
            'is_draft' => $this->faker->boolean(5),
            'is_recent' => $this->faker->boolean(30),
            'flags' => $this->faker->optional(0.5)->randomElements(['\\Seen', '\\Answered', '\\Flagged', '\\Deleted', '\\Draft'], $this->faker->numberBetween(0, 3)),
            'synced_at' => $this->faker->optional(0.9)->dateTimeBetween($receivedAt, 'now'),
            'checksum' => $this->faker->optional(0.8)->sha256(),
            'last_sync_error' => $this->faker->optional(0.1)->sentence(),
        ];
    }

    /**
     * Generate realistic raw email headers
     */
    private function generateRawHeaders(): string
    {
        $headers = [
            'Return-Path: <'.$this->faker->safeEmail().'>',
            'Received: from '.$this->faker->domainName().' by '.$this->faker->domainName(),
            'Date: '.$this->faker->dateTimeThisMonth()->format('D, d M Y H:i:s O'),
            'From: '.$this->faker->name().' <'.$this->faker->safeEmail().'>',
            'To: '.$this->faker->name().' <'.$this->faker->safeEmail().'>',
            'Subject: '.$this->faker->sentence(),
            'Message-ID: <'.$this->faker->uuid().'@'.$this->faker->domainName().'>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="'.$this->faker->uuid().'"',
            'X-Mailer: '.$this->faker->randomElement(['Outlook', 'Thunderbird', 'Apple Mail', 'Gmail']),
        ];

        return implode("\r\n", $headers);
    }

    /**
     * Create an unread message
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_seen' => false,
            'is_recent' => true,
        ]);
    }

    /**
     * Create a read message
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_seen' => true,
            'is_recent' => false,
        ]);
    }

    /**
     * Create a flagged message
     */
    public function flagged(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_flagged' => true,
            'flags' => array_merge($attributes['flags'] ?? [], ['\\Flagged']),
        ]);
    }

    /**
     * Create a draft message
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_draft' => true,
            'is_seen' => true,
            'flags' => array_merge($attributes['flags'] ?? [], ['\\Draft']),
        ]);
    }

    /**
     * Create a message with sync error
     */
    public function withSyncError(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_sync_error' => $this->faker->sentence(),
            'synced_at' => null,
        ]);
    }

    /**
     * Create a message for a specific mail account
     */
    public function forAccount(MailAccount $account): static
    {
        return $this->state(fn (array $attributes) => [
            'mail_account_id' => $account->id,
        ]);
    }
}
