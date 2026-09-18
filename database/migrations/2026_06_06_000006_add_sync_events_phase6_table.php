<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_events', function (Blueprint $table) {
            $table->id();
            $table->string('client_event_id', 120)->nullable()->unique();
            $table->string('action', 80)->index();
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('result_message', 255)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_events');
    }
};
