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
        Schema::create('sends', function (Blueprint $table) {
            $table->binary('id', length: 16, fixed: true)->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->string('name', 255);
            $table->dateTime('valid_to')->index();
            $table->binary('public_id', length: 16, fixed: true)->unique();

            $table->unique(['user_id', 'name']);
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sends');
    }
};
