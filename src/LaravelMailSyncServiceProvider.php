<?php

namespace NodusIT\LaravelMailSync;

use NodusIT\LaravelMailSync\Services\MailAccountService;
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
            ->hasMigration('create_laravel_mail_sync_table')
            ->hasMigration('create_mail_accounts_table');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(MailAccountService::class, function ($app) {
            return new MailAccountService();
        });
    }
}
