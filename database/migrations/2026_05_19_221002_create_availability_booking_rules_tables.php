<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organizer_id')->index();
            $table->string('ruleable_type');
            $table->uuid('ruleable_id');
            $table->string('rule_type');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->char('weekday_mask', 7)->default('1111111');
            $table->boolean('is_start_date')->default(false);
            $table->unsignedInteger('capacity')->nullable();
            $table->timestamps();

            $table->foreign('organizer_id')->references('id')->on('organizers')->cascadeOnDelete();
            $table->index(['ruleable_type', 'ruleable_id']);
        });

        Schema::create('booking_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organizer_id')->index();
            $table->uuid('property_id')->nullable()->index();
            $table->uuid('program_id')->nullable()->index();
            $table->unsignedInteger('min_nights')->nullable();
            $table->unsignedInteger('max_nights')->nullable();
            $table->char('check_in_days', 7)->default('1111111');
            $table->char('check_out_days', 7)->default('1111111');
            $table->unsignedInteger('min_advance_days')->nullable();
            $table->unsignedInteger('max_advance_days')->nullable();
            $table->timestamps();

            $table->foreign('organizer_id')->references('id')->on('organizers')->cascadeOnDelete();
            $table->foreign('property_id')->references('id')->on('properties')->nullOnDelete();
            $table->foreign('program_id')->references('id')->on('programs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_rules');
        Schema::dropIfExists('availability_rules');
    }
};
