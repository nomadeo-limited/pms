<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->char('country', 2)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->string('timezone')->default('UTC');
            $table->string('locale')->default('en');
            $table->string('picture')->nullable();
            $table->text('description')->nullable();
            $table->string('short_description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('organizer_user', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->uuid('organizer_id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('organizer_id')->references('id')->on('organizers')->cascadeOnDelete();
            $table->unique(['user_id', 'organizer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_user');
        Schema::dropIfExists('organizers');
    }
};
