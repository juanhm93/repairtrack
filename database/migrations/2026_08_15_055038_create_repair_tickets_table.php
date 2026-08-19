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
        Schema::create('repair_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('public_token')->unique();
            $table->string('device_type');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->text('reported_issue');
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->date('received_at');
            $table->date('estimated_delivery_at')->nullable();
            $table->string('status');
            $table->timestamps();

            $table->index('status');
            $table->index('received_at');
            $table->index('estimated_delivery_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repair_tickets');
    }
};
