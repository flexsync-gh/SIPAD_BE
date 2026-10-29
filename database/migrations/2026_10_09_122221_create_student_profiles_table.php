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
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('nim', 50)->nullable()->unique();
            $table->boolean('is_bypass')->default(false);
            $table->unsignedTinyInteger('role_1_id')->nullable();
            $table->unsignedTinyInteger('role_2_id')->nullable();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('portfolio_link', 255)->nullable();
            $table->timestamps();

            $table->foreign('role_1_id')->references('id')->on('roles')->nullOnDelete();
            $table->foreign('role_2_id')->references('id')->on('roles')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
