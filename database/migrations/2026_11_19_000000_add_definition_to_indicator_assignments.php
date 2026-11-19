<?php

use App\Models\IndicatorAssignment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indicator_assignments', function (Blueprint $table): void {
            $table->json('definition')->nullable();
        });

        IndicatorAssignment::with('indicator.category')->chunkById(500, function ($assignments): void {
            $definitions = DB::table('indicator_assignments')
                ->join('unit_reports', function ($join): void {
                    $join->on('unit_reports.period_id', '=', 'indicator_assignments.period_id')
                        ->on('unit_reports.unit_id', '=', 'indicator_assignments.unit_id');
                })
                ->join('report_entries', function ($join): void {
                    $join->on('report_entries.report_id', '=', 'unit_reports.id')
                        ->on('report_entries.indicator_id', '=', 'indicator_assignments.indicator_id');
                })
                ->whereIn('indicator_assignments.id', $assignments->modelKeys())
                ->pluck('report_entries.definition', 'indicator_assignments.id');

            foreach ($assignments as $assignment) {
                DB::table('indicator_assignments')->where('id', $assignment->id)->update([
                    'definition' => $definitions->get($assignment->id)
                        ?? json_encode($assignment->indicator->definition(), JSON_THROW_ON_ERROR),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('indicator_assignments', fn (Blueprint $table) => $table->dropColumn('definition'));
    }
};
