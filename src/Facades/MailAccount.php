<?php

namespace NodusIT\LaravelMailSync\Facades;

use Illuminate\Support\Facades\Facade;
use NodusIT\LaravelMailSync\Models\MailAccount as MailAccountModel;
use NodusIT\LaravelMailSync\Services\MailAccountService;

/**
 * MailAccount Facade
 *
 * Provides static access to the MailAccountService for creating, updating, and managing mail accounts.
 * This facade allows you to perform mail account operations with validation and connection testing.
 *
 * @method static MailAccountModel create(array $data) Create a new mail account with validation and connection testing
 * @method static MailAccountModel update(MailAccountModel $mailAccount, array $data) Update an existing mail account with validation and connection testing
 * @method static bool testExistingConnection(MailAccountModel $mailAccount) Test connection for an existing mail account and update connection status
 *
 * @throws \Illuminate\Validation\ValidationException When validation fails
 * @throws \Exception When IMAP connection fails
 *
 * @see \NodusIT\LaravelMailSync\Services\MailAccountService
 */
class MailAccount extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return MailAccountService::class;
    }
}
