<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (!Schema::hasColumn('members', 'membership_number')) {
                $table->string('membership_number', 30)->nullable()->unique();
            }
            if (!Schema::hasColumn('members', 'birth_date')) {
                $table->date('birth_date')->nullable();
            }
            if (!Schema::hasColumn('members', 'notes')) {
                $table->text('notes')->nullable();
            }
            if (!Schema::hasColumn('members', 'status')) {
                $table->string('status', 20)->default('active')->index();
            }
            if (!Schema::hasColumn('members', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'plan_id')) {
                $table->string('plan_id', 10)->nullable()->index();
            }
            if (!Schema::hasColumn('subscriptions', 'frozen_from')) {
                $table->date('frozen_from')->nullable();
            }
            if (!Schema::hasColumn('subscriptions', 'frozen_until')) {
                $table->date('frozen_until')->nullable();
            }
            if (!Schema::hasColumn('subscriptions', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'member_id')) {
                $table->string('member_id', 10)->nullable()->index();
            }
            if (!Schema::hasColumn('payments', 'subscription_id')) {
                $table->string('subscription_id', 10)->nullable()->index();
            }
            if (!Schema::hasColumn('payments', 'sale_id')) {
                $table->unsignedBigInteger('sale_id')->nullable()->index();
            }
            if (!Schema::hasColumn('payments', 'receipt_number')) {
                $table->string('receipt_number', 30)->nullable()->unique();
            }
            if (!Schema::hasColumn('payments', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });

        Schema::table('checkins', function (Blueprint $table) {
            if (!Schema::hasColumn('checkins', 'subscription_id')) {
                $table->string('subscription_id', 10)->nullable()->index();
            }
            if (!Schema::hasColumn('checkins', 'checkout_at')) {
                $table->dateTime('checkout_at')->nullable();
            }
            if (!Schema::hasColumn('checkins', 'source')) {
                $table->string('source', 20)->default('manual')->index();
            }
            if (!Schema::hasColumn('checkins', 'status')) {
                $table->string('status', 20)->default('allowed')->index();
            }
            if (!Schema::hasColumn('checkins', 'denial_reason')) {
                $table->string('denial_reason')->nullable();
            }
            if (!Schema::hasColumn('checkins', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });

        Schema::create('membership_cards', function (Blueprint $table) {
            $table->id();
            $table->string('member_id', 10)->index();
            $table->string('code', 100)->unique();
            $table->string('type', 20)->default('qr');
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id')->index();
            $table->string('product_id', 10)->index();
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total', 10, 2);
            $table->timestamps();

            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('membership_cards');

        Schema::table('checkins', function (Blueprint $table) {
            foreach (['subscription_id', 'checkout_at', 'source', 'status', 'denial_reason', 'updated_at'] as $column) {
                if (Schema::hasColumn('checkins', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            foreach (['member_id', 'subscription_id', 'sale_id', 'receipt_number', 'updated_at'] as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            foreach (['plan_id', 'frozen_from', 'frozen_until', 'updated_at'] as $column) {
                if (Schema::hasColumn('subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('members', function (Blueprint $table) {
            foreach (['membership_number', 'birth_date', 'notes', 'status', 'updated_at'] as $column) {
                if (Schema::hasColumn('members', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
