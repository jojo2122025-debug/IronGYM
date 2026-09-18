<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('channel', 30)->default('whatsapp');
            $table->string('type', 80);
            $table->string('title_template', 180);
            $table->text('body_template');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['type', 'channel'], 'notification_templates_type_channel_unique');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('member_id', 20)->nullable()->index();
            $table->unsignedBigInteger('template_id')->nullable()->index();
            $table->string('channel', 30)->default('whatsapp');
            $table->string('type', 80)->default('manual');
            $table->string('title', 180);
            $table->text('body');
            $table->string('status', 30)->default('pending');
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->text('provider_response')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->nullOnDelete();
            $table->foreign('template_id')->references('id')->on('notification_templates')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_templates');
    }
};
