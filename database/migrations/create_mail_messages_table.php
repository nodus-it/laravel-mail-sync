<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mail_account_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('remote_uid');
            $table->unsignedBigInteger('remote_msg_no')->nullable();
            $table->string('message_id', 255)->nullable();

            $table->text('subject')->nullable();
            $table->datetime('sent_at')->nullable();
            $table->datetime('received_at')->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->unsignedTinyInteger('importance')->nullable();
            $table->unsignedTinyInteger('priority')->nullable();

            $table->string('from_email', 320)->nullable();
            $table->string('from_name', 191)->nullable();
            $table->string('reply_to_email', 320)->nullable();
            $table->string('reply_to_name', 191)->nullable();

            //$table->json('to_recipients')->nullable();
            //$table->json('cc_recipients')->nullable();
            //$table->json('bcc_recipients')->nullable();

            $table->string('in_reply_to', 255)->nullable();
            $table->text('references')->nullable();
            $table->string('thread_hash', 64)->nullable();

            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->string('body_preview', 255)->nullable();
            $table->longText('raw_body')->nullable();
            $table->longText('raw_headers')->nullable();

            $table->boolean('is_seen')->default(false);
            $table->boolean('is_answered')->default(false);
            $table->boolean('is_flagged')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->boolean('is_draft')->default(false);
            $table->boolean('is_recent')->default(false);

            $table->json('flags')->nullable();

            $table->timestamp('synced_at')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->text('last_sync_error')->nullable();

            $table->timestamps();

            $table->unique(['mail_account_id', 'remote_uid']);
            $table->index('message_id');
            $table->index(['mail_account_id', 'is_seen']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_messages');
    }
};
