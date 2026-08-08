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
        Schema::create('benchmark_comparisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_offer_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('supersedes_comparison_id')->nullable()->unique();
            $table->unsignedBigInteger('benchmark_price_minor');
            $table->string('market_name', 160);
            $table->string('source_type', 32);
            $table->string('source_reference', 500);
            $table->timestamp('observed_at');
            $table->boolean('is_demo')->default(false);
            $table->bigInteger('saving_minor')->nullable();
            $table->integer('saving_percentage_bps')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();

            $table->foreign('supersedes_comparison_id')
                ->references('id')
                ->on('benchmark_comparisons')
                ->restrictOnDelete();

            $table->index(['product_offer_id', 'published_at', 'superseded_at']);
            $table->index(['product_offer_id', 'observed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('benchmark_comparisons');
    }
};
