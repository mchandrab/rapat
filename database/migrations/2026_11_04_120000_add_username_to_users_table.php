<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 50)->nullable()->unique()->after('name');
        });

        DB::table('users')->orderBy('id')->get(['id', 'email'])->each(function (object $user): void {
            $base = Str::lower(Str::before((string) $user->email, '@'));
            $base = preg_replace('/[^a-z0-9._-]/', '', $base) ?: 'user'.$user->id;
            $base = strlen($base) < 3 ? 'user'.$user->id : substr($base, 0, 50);
            $username = $base;

            if (DB::table('users')->where('username', $username)->exists()) {
                $username = substr($base, 0, 50 - strlen((string) $user->id) - 1).'-'.$user->id;
            }

            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 50)->nullable(false)->change();
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('users')->whereNull('email')->orderBy('id')->get(['id', 'username'])->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update([
                'email' => $user->username.'@legacy.invalid',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable(false)->change();
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
