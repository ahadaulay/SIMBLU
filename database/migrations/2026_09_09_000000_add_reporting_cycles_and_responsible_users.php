<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporting_cycles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('frequency', 20);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::table('reporting_periods', function (Blueprint $table): void {
            $table->foreignId('cycle_id')
                ->nullable()
                ->after('id')
                ->constrained('reporting_cycles')
                ->restrictOnDelete();
        });

        Schema::table('indicator_assignments', function (Blueprint $table): void {
            $table->foreignId('responsible_user_id')
                ->nullable()
                ->after('indicator_id')
                ->constrained('users')
                ->restrictOnDelete();
        });

        $now = now();

        foreach (DB::table('reporting_periods')->select('year')->distinct()->get() as $period) {
            $cycleId = DB::table('reporting_cycles')->insertGetId([
                'year' => $period->year,
                'frequency' => 'QUARTERLY',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('reporting_periods')
                ->where('year', $period->year)
                ->update(['cycle_id' => $cycleId]);
        }
    }

    public function down(): void
    {
        Schema::table('indicator_assignments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('responsible_user_id');
        });

        Schema::table('reporting_periods', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cycle_id');
        });

        Schema::dropIfExists('reporting_cycles');
    }
};
