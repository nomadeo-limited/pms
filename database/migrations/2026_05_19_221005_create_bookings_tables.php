<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organizer_id')->index();
            $table->uuid('property_id')->index();
            $table->uuid('program_id')->nullable()->index();
            $table->uuid('customer_id')->index();
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->unsignedInteger('nights');
            $table->unsignedInteger('guests')->default(1);
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('unpaid');
            $table->decimal('total_price', 10, 2)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->uuid('discount_id')->nullable();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('source')->default('direct');
            $table->string('external_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('organizer_id')->references('id')->on('organizers')->cascadeOnDelete();
            $table->foreign('property_id')->references('id')->on('properties');
            $table->foreign('program_id')->references('id')->on('programs')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('booking_units', function (Blueprint $table) {
            $table->id();
            $table->uuid('booking_id');
            $table->uuid('unit_id');
            $table->unsignedInteger('guests')->default(1);
            $table->decimal('price_per_night', 10, 2)->default(0);

            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('unit_id')->references('id')->on('units');
            $table->unique(['booking_id', 'unit_id']);
        });

        Schema::create('booking_add_ons', function (Blueprint $table) {
            $table->id();
            $table->uuid('booking_id');
            $table->uuid('add_on_id');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);

            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('add_on_id')->references('id')->on('add_ons');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_add_ons');
        Schema::dropIfExists('booking_units');
        Schema::dropIfExists('bookings');
    }
};
