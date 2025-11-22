<?php

namespace NodusIT\LaravelMailSync\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \NodusIT\LaravelMailSync\LaravelMailSync
 */
class LaravelMailSync extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \NodusIT\LaravelMailSync\LaravelMailSync::class;
    }
}
