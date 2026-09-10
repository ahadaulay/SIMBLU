<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recap_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('indicator_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('indicator_version');
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('result_field', 30);
            $table->string('aggregation', 20);
            $table->string('denominator_field', 30)->nullable();
            $table->unsignedSmallInteger('ratio_scale')->default(1);
            $table->string('filter_field', 30)->nullable();
            $table->json('filter_values');
            $table->boolean('combine_units')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recap_metrics');
    }
};
