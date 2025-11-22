<?php

namespace NodusIT\LaravelMailSync;

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
}
