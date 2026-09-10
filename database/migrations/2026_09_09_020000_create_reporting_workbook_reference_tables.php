<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporting_workbook_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('year_start');
            $table->unsignedSmallInteger('year_end');
            $table->string('source_filename');
            $table->string('source_path');
            $table->char('sha256', 64);
            $table->unsignedSmallInteger('sheet_count');
            $table->unsignedInteger('coordinate_count');
            $table->unsignedInteger('nonempty_count');
            $table->unsignedInteger('formula_count');
            $table->unsignedInteger('merged_count');
            $table->unsignedInteger('style_count');
            $table->json('metadata');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('reporting_workbook_sheets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')
                ->constrained('reporting_workbook_templates')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('name');
            $table->unsignedInteger('max_row');
            $table->unsignedSmallInteger('max_column');
            $table->json('metadata');
            $table->timestamps();
            $table->unique(['template_id', 'position']);
        });

        Schema::create('reporting_workbook_cells', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sheet_id')
                ->constrained('reporting_workbook_sheets')
                ->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->unsignedSmallInteger('column_number');
            $table->longText('value_json')->nullable();
            $table->string('data_type', 10);
            $table->unsignedInteger('style_id');
            $table->string('number_format')->nullable();
            $table->json('metadata')->nullable();
            $table->unique(['sheet_id', 'row_number', 'column_number'], 'workbook_cell_coordinate_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporting_workbook_cells');
        Schema::dropIfExists('reporting_workbook_sheets');
        Schema::dropIfExists('reporting_workbook_templates');
    }
};
