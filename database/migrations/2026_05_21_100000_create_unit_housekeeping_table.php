<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('unit_housekeeping', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('unit_id')->index();
            $table->uuid('property_id')->index();
            $table->uuid('organizer_id')->index();
            $table->date('date');
            $table->string('status')->default('dirty');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['unit_id', 'date']);
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
            $table->foreign('organizer_id')->references('id')->on('organizers')->cascadeOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('unit_housekeeping'); }
};
