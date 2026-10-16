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
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('kind', ['white', 'black']);
            $table->string('name', 60);
            $table->char('color', 7)->nullable();
            $table->string('icon', 40)->nullable();
            $table->string('preset_key', 40)->nullable();
            $table->integer('sort')->default(0);
            $table->dateTime('changed_at', 3);
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
        Schema::dropIfExists('categories');
    }
};
