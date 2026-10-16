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
        Schema::create('shots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('color', ['white', 'black']);
            $table->decimal('x', 6, 2);
            $table->decimal('y', 6, 2);
            $table->decimal('r', 5, 2);
            $table->unsignedTinyInteger('intensity');
            $table->dateTime('shot_at', 3);
            $table->dateTime('undone_at', 3)->nullable();
            $table->dateTime('deleted_at', 3)->nullable();
            $table->dateTime('state_changed_at', 3);
            $table->text('note')->nullable();
            // Categories are only hard-deleted with their user, so this only fires during that cascade.
            $table->foreignUuid('category_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('meta_changed_at', 3);
            $table->uuid('device_id');
            $table->unsignedBigInteger('seq');
            $table->timestamps(3);

            $table->index(['user_id', 'seq']);
            $table->index(['user_id', 'shot_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shots');
    }
};
