<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_entries', function (Blueprint $table): void {
            $table->foreignId('source_entry_id')
                ->nullable()
                ->after('indicator_id')
                ->constrained('report_entries')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('report_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_entry_id');
        });
    }
};
