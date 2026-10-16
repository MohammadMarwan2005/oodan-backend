<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('google_sub')->nullable()->unique()->after('password');
            $table->unsignedBigInteger('sync_seq')->default(0)->after('remember_token');
            $table->boolean('is_admin')->default(false)->after('sync_seq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_sub']);
            $table->dropColumn(['google_sub', 'sync_seq', 'is_admin']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
