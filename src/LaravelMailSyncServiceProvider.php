<?php

namespace NodusIT\LaravelMailSync;

use NodusIT\LaravelMailSync\Services\MailAccountService;
use NodusIT\LaravelMailSync\Services\MailMessageService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelMailSyncServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-mail-sync')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('2025_11_22_000000_create_mail_accounts_table')
            ->hasMigration('2025_11_22_000001_create_mail_messages_table');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(MailAccountService::class, function ($app) {
            return new MailAccountService;
        });

        $this->app->singleton(MailMessageService::class, function ($app) {
            return new MailMessageService;
        });
    }
}
