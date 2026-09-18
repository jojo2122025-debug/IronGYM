<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('specialty', 150)->nullable();
            $table->text('bio')->nullable();
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('trainer_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trainer_id')->index();
            $table->string('member_id', 10)->index();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->foreign('trainer_id')->references('id')->on('trainers')->cascadeOnDelete();
            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
            $table->unique(['trainer_id', 'member_id', 'start_date'], 'trainer_member_start_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_assignments');
        Schema::dropIfExists('trainers');
    }
};
