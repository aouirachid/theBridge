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
        Schema::create('offer_cost_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_offer_id')->constrained()->cascadeOnDelete();
            $table->string('standard_code', 40)->nullable();
            $table->string('name', 120);
            $table->string('normalized_name', 120);
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['product_offer_id', 'standard_code']);
            $table->unique(['product_offer_id', 'normalized_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offer_cost_components');
    }
};
