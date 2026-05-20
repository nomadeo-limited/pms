<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organizer_id')->index();
            $table->string('name');
            $table->string('slug');
            $table->string('type')->default('other');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->char('country', 2)->nullable();
            $table->string('timezone')->default('UTC');
            $table->char('currency', 3)->default('USD');
            $table->string('locale')->default('en');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->text('description')->nullable();
            $table->jsonb('amenities')->default('[]');
            $table->jsonb('images')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organizer_id')->references('id')->on('organizers')->cascadeOnDelete();
            $table->unique(['organizer_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
