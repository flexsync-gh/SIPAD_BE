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
        Schema::create('student_role_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('recommended_role_id')->nullable();
            $table->decimal('recommended_score', 5, 2)->nullable();
            $table->decimal('score_role_1', 5, 2)->nullable();
            $table->decimal('score_role_2', 5, 2)->nullable();
            $table->unsignedTinyInteger('final_role_id')->nullable();
            $table->boolean('is_overridden')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->string('evaluation_note', 255)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('recommended_role_id')->references('id')->on('roles')->nullOnDelete();
            $table->foreign('final_role_id')->references('id')->on('roles')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_role_evaluations');
    }
};
