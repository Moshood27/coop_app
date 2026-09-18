<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class Accounting
{
    public static function feature(string $key): bool
    {
        return (bool) Config::get("accounting.features.$key", false);
    }

    public static function guard(string $key): bool
    {
        return (bool) Config::get("accounting.guards.$key", false);
    }

    public static function migrationApplied(string $group): bool
    {
        return (bool) Config::get("accounting.migrations_applied.$group", false);
    }

    public static function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function columnExists(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }
}
