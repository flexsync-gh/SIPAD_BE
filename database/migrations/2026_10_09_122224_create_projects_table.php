<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_name', 128)->unique();
            $table->foreignId('project_owner')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('project_associate', 64)->nullable();
            $table->string('project_description', 255)->nullable();
            $table->foreignId('project_team')->unique()->constrained('teams')->cascadeOnDelete();
            $table->timestamps();
        });

        try {
            DB::statement('CREATE VIEW project AS SELECT * FROM projects');
        } catch (Throwable $e) {
            // View creation fallback
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement('DROP VIEW IF EXISTS project');
        } catch (Throwable $e) {
            // Drop view fallback
        }

        Schema::dropIfExists('projects');
    }
};
