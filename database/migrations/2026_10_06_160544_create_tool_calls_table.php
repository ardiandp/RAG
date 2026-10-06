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
        Schema::create('tool_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tool_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tool_name');
            $table->jsonb('arguments')->nullable();
            $table->jsonb('result')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('step')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tool_calls');
    }
};
