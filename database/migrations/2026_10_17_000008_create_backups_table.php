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
        Schema::create('backups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('reason', ['clear', 'import_replace', 'restore']);
            $table->unsignedInteger('shots_count');
            // An ExportFile document; dropped (null) once the user deletes the backup.
            $table->longText('payload')->nullable();
            $table->dateTime('created_at', 3);
            $table->dateTime('deleted_at', 3)->nullable();
            $table->unsignedBigInteger('seq');

            $table->index(['user_id', 'seq']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
