<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->dateTime('date');
            $table->string('category', 120);
            $table->decimal('amount', 12, 2);
            $table->string('method', 30)->default('نقدي');
            $table->string('recipient', 180)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
