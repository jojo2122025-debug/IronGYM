<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_programs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trainer_assignment_id')->index();
            $table->string('title', 180);
            $table->string('goal', 255)->nullable();
            $table->json('content_json')->nullable();
            $table->timestamps();

            $table->foreign('trainer_assignment_id')
                ->references('id')
                ->on('trainer_assignments')
                ->cascadeOnDelete();
        });

        Schema::create('nutrition_programs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trainer_assignment_id')->index();
            $table->string('title', 180);
            $table->string('goal', 255)->nullable();
            $table->json('content_json')->nullable();
            $table->timestamps();

            $table->foreign('trainer_assignment_id')
                ->references('id')
                ->on('trainer_assignments')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrition_programs');
        Schema::dropIfExists('training_programs');
    }
};
