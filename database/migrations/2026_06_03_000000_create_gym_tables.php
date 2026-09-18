<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('name');
            $table->string('phone', 20);
            $table->enum('gender', ['ذكر', 'أنثى'])->default('ذكر');
            $table->string('whatsapp', 30)->nullable();
            $table->string('image_path')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->integer('days');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('member_id', 10);
            $table->string('plan_name', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('amount', 10, 2);
            $table->decimal('paid', 10, 2)->default(0.00);
            $table->decimal('remaining', 10, 2)->default(0.00);
            $table->enum('status', ['فعال', 'منتهي', 'مجمد'])->default('فعال');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('member_name');
            $table->dateTime('date');
            $table->decimal('amount', 10, 2);
            $table->enum('method', ['نقدي', 'تحويل']);
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('name', 100);
            $table->decimal('price', 10, 2);
            $table->integer('stock')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->dateTime('date');
            $table->text('products');
            $table->decimal('total', 10, 2);
            $table->enum('method', ['نقدي', 'تحويل']);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('checkins', function (Blueprint $table) {
            $table->id();
            $table->string('member_id', 10);
            $table->string('member_name');
            $table->string('time', 20);
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('password');
            $table->string('name', 100);
            $table->enum('role', ['مدير النظام', 'موظف الاستقبال', 'المحاسب', 'المدقق المالي', 'مشترك']);
            $table->string('member_id', 10)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('member_id')->references('id')->on('members')->onDelete('set null');
        });

        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->nullable();
            $table->string('name', 100)->nullable();
            $table->string('role', 50)->nullable();
            $table->string('action', 100);
            $table->text('details');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('measurements', function (Blueprint $table) {
            $table->id();
            $table->string('member_id', 10);
            $table->decimal('weight', 5, 2);
            $table->decimal('height', 5, 2);
            $table->decimal('fat_percentage', 5, 2);
            $table->decimal('muscle_mass', 5, 2);
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurements');
        Schema::dropIfExists('activity_log');
        Schema::dropIfExists('users');
        Schema::dropIfExists('checkins');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('products');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('members');
    }
};
