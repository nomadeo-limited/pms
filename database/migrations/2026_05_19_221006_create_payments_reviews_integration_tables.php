<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organizer_id')->index();
            $table->uuid('property_id')->nullable()->index();
            $table->uuid('program_id')->nullable()->index();
            $table->string('type');
            $table->decimal('deposit_percentage', 5, 2)->nullable();
            $table->unsignedInteger('balance_due_days_before')->nullable();
            $table->timestamps();

            $table->foreign('organizer_id')->references('id')->on('organizers')->cascadeOnDelete();
            $table->foreign('property_id')->references('id')->on('properties')->nullOnDelete();
            $table->foreign('program_id')->references('id')->on('programs')->nullOnDelete();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organizer_id')->index();
            $table->uuid('booking_id')->index();
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('USD');
            $table->string('method');
            $table->string('status')->default('pending');
            $table->string('stripe_payment_intent_id')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('organizer_id')->references('id')->on('organizers')->cascadeOnDelete();
            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organizer_id')->index();
            $table->uuid('booking_id')->unique();
            $table->uuid('customer_id')->index();
            $table->unsignedTinyInteger('overall_rating');
            $table->unsignedTinyInteger('accommodation_rating')->nullable();
            $table->unsignedTinyInteger('program_rating')->nullable();
            $table->unsignedTinyInteger('staff_rating')->nullable();
            $table->unsignedTinyInteger('value_rating')->nullable();
            $table->text('comment')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->foreign('organizer_id')->references('id')->on('organizers')->cascadeOnDelete();
            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers');
        });

        Schema::create('integration_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organizer_id')->index();
            $table->uuid('property_id')->nullable()->index();
            $table->string('name');
            $table->string('token_hash');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organizer_id')->references('id')->on('organizers')->cascadeOnDelete();
            $table->foreign('property_id')->references('id')->on('properties')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_tokens');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_rules');
    }
};
