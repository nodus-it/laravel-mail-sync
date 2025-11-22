<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_accounts', function (Blueprint $table) {
            $table->id(); // bigint unsigned (PK)
            $table->string('name');
            $table->string('email_address');
            $table->string('host');
            $table->integer('port');
            $table->string('encryption')->nullable();
            $table->string('username');
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_connection_error')->nullable();
            $table->timestamp('last_connection_failed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_accounts');
    }
};
