<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organizer_id')->index();
            $table->uuid('property_id')->index();
            $table->string('name');
            $table->string('type')->default('other');
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_days')->nullable();
            $table->jsonb('images')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organizer_id')->references('id')->on('organizers')->cascadeOnDelete();
            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
        });

        Schema::create('add_ons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organizer_id')->index();
            $table->uuid('property_id')->index();
            $table->string('name');
            $table->string('category')->default('other');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->unsignedInteger('max_per_booking')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organizer_id')->references('id')->on('organizers')->cascadeOnDelete();
            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
        });

        Schema::create('program_add_ons', function (Blueprint $table) {
            $table->id();
            $table->uuid('program_id');
            $table->uuid('add_on_id');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('program_id')->references('id')->on('programs')->cascadeOnDelete();
            $table->foreign('add_on_id')->references('id')->on('add_ons')->cascadeOnDelete();
            $table->unique(['program_id', 'add_on_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_add_ons');
        Schema::dropIfExists('add_ons');
        Schema::dropIfExists('programs');
    }
};
