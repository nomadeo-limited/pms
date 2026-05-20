<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->uuid('room_type_id')->nullable()->after('property_id');
            $table->decimal('base_price', 10, 2)->nullable()->after('duration_days');
            $table->char('currency', 3)->default('USD')->after('base_price');

            $table->foreign('room_type_id')->references('id')->on('room_types')->nullOnDelete();
        });

        Schema::table('program_add_ons', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->after('is_default');
        });
    }

    public function down(): void
    {
        Schema::table('program_add_ons', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->dropForeign(['room_type_id']);
            $table->dropColumn(['room_type_id', 'base_price', 'currency']);
        });
    }
};
