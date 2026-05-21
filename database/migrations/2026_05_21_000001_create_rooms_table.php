<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organizer_id')->index();
            $table->uuid('property_id')->index();
            $table->uuid('room_type_id')->index();
            $table->string('name');
            $table->string('floor')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organizer_id')->references('id')->on('organizers')->cascadeOnDelete();
            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
            $table->foreign('room_type_id')->references('id')->on('room_types')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
