<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organizer_id')->index();
            $table->uuid('property_id')->index();
            $table->string('name');
            $table->string('category')->default('other');
            $table->text('description')->nullable();
            $table->unsignedInteger('max_capacity')->default(1);
            $table->jsonb('amenities')->default('[]');
            $table->jsonb('images')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organizer_id')->references('id')->on('organizers')->cascadeOnDelete();
            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organizer_id')->index();
            $table->uuid('property_id')->index();
            $table->uuid('room_type_id')->index();
            $table->string('name');
            $table->string('bed_category')->default('single');
            $table->unsignedInteger('capacity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organizer_id')->references('id')->on('organizers')->cascadeOnDelete();
            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
            $table->foreign('room_type_id')->references('id')->on('room_types')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
        Schema::dropIfExists('room_types');
    }
};
