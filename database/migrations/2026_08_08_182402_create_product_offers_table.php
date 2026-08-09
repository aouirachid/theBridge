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
        Schema::create('product_offers', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('replaces_product_offer_id')->nullable()->unique();
            $table->string('crop', 120);
            $table->string('origin', 255);
            $table->decimal('available_quantity_kg', 10, 2);
            $table->timestamp('availability_starts_at');
            $table->timestamp('availability_ends_at');
            $table->unsignedBigInteger('farmer_payment_minor');
            $table->unsignedBigInteger('platform_margin_minor');
            $table->unsignedBigInteger('final_price_minor');
            $table->unsignedInteger('farmer_share_bps');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->foreign('replaces_product_offer_id')
                ->references('id')
                ->on('product_offers')
                ->restrictOnDelete();

            $table->index(['published_at', 'superseded_at', 'withdrawn_at']);
            $table->index(['created_by_user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_offers');
    }
};
