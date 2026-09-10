<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 100)->nullable()->unique();
            $table->string('role', 20)->nullable()->index();
            $table->foreignId('unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('active')->default(true);
        });
        Schema::create('reporting_periods', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('quarter');
            $table->date('deadline');
            $table->string('status', 20)->default('DRAFT');
            $table->timestamps();
            $table->unique(['year', 'quarter']);
        });
        Schema::create('indicator_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('indicators', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('indicator_categories')->restrictOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('form_type', 20)->default('SINGLE');
            $table->string('aggregation', 20)->default('SUM');
            $table->string('direction', 20)->default('NEUTRAL');
            $table->string('result_field', 30);
            $table->string('denominator_field', 30)->nullable();
            $table->decimal('ratio_scale', 8, 2)->default(1);
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('requires_explanation')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->json('fields');
            $table->json('items');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('indicator_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('period_id')->constrained('reporting_periods')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('indicator_id')->constrained()->restrictOnDelete();
            $table->boolean('required')->default(true);
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['period_id', 'unit_id', 'indicator_id'], 'assignment_period_unit_indicator_unique');
        });
        Schema::create('unit_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('period_id')->constrained('reporting_periods')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('DRAFT')->index();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['unit_id', 'period_id']);
        });
        Schema::create('report_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained('unit_reports')->restrictOnDelete();
            $table->foreignId('indicator_id')->constrained()->restrictOnDelete();
            $table->json('definition');
            $table->boolean('required');
            $table->boolean('no_activity')->default(false);
            $table->json('rows');
            $table->text('explanation')->nullable();
            $table->string('responsible_name', 150)->nullable();
            $table->timestamps();
            $table->unique(['report_id', 'indicator_id']);
        });
        Schema::create('report_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained('unit_reports')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('note')->nullable();
            $table->json('snapshot');
            $table->timestamp('created_at');
        });
        Schema::create('verification_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained('unit_reports')->restrictOnDelete();
            $table->foreignId('entry_id')->nullable()->constrained('report_entries')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('note');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
        Schema::create('report_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained('unit_reports')->restrictOnDelete();
            $table->foreignId('entry_id')->nullable()->constrained('report_entries')->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
        Schema::create('period_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('period_id')->constrained('reporting_periods')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->text('note');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['period_histories', 'report_attachments', 'verification_notes', 'report_histories', 'report_entries', 'unit_reports', 'indicator_assignments', 'indicators', 'indicator_categories', 'reporting_periods'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('unit_id');
            $table->dropColumn(['username', 'role', 'active']);
        });
        Schema::dropIfExists('units');
    }
};
