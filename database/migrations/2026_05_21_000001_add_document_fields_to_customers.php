<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->renameColumn('passport_number', 'document_number');
            $table->string('document_type')->nullable()->after('document_number'); // passport, national_id, other
            $table->char('document_country', 2)->nullable()->after('document_type');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['document_type', 'document_country']);
            $table->renameColumn('document_number', 'passport_number');
        });
    }
};
