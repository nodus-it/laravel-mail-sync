<?php

namespace NodusIT\LaravelMailSync\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use NodusIT\LaravelMailSync\Database\Factories\MailAccountFactory;

class MailAccount extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return MailAccountFactory::new();
    }

    protected $fillable = [
        'name',
        'email_address',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'is_active',
        'last_synced_at',
        'last_connection_error',
        'last_connection_failed_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_connection_failed_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'port' => 'integer',
    ];

    protected $hidden = [
        'password',
    ];
}
