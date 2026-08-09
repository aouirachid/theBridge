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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->char('submission_hash', 64)->unique();
            $table->foreignId('product_offer_id')->constrained()->restrictOnDelete();
            $table->foreignId('offer_delivery_slot_id')->constrained()->restrictOnDelete();
            $table->string('channel', 8);
            $table->string('status', 16)->default('pending');
            $table->unsignedBigInteger('quantity_hundredths');
            $table->char('currency', 3);
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('total_minor');
            $table->uuid('offer_public_id_snapshot');
            $table->string('crop_snapshot', 120);
            $table->date('service_date');
            $table->dateTime('slot_starts_at');
            $table->dateTime('slot_ends_at');
            $table->string('delivery_zone', 40);
            $table->text('customer_name');
            $table->text('business_name')->nullable();
            $table->text('phone');
            $table->text('email')->nullable();
            $table->text('delivery_address')->nullable();
            $table->text('delivery_note')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'service_date']);
            $table->index(['product_offer_id', 'status']);
            $table->index(['channel', 'service_date']);
            $table->index(['delivery_zone', 'service_date']);
            $table->index(['created_at', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
