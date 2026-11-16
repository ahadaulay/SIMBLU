<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $entries = DB::table('report_entries')
            ->select(['id', 'source_entry_id', 'rows', 'no_activity'])
            ->get()
            ->keyBy('id');

        $resolve = function (int $id, array $visited = []) use (&$resolve, $entries): array {
            $entry = $entries->get($id);

            if (! $entry || ! $entry->source_entry_id || in_array($id, $visited, true)) {
                return [
                    'rows' => $entry?->rows ?? '[]',
                    'no_activity' => (bool) ($entry?->no_activity ?? false),
                ];
            }

            return $resolve((int) $entry->source_entry_id, [...$visited, $id]);
        };

        foreach ($entries->whereNotNull('source_entry_id') as $entry) {
            $source = $resolve((int) $entry->source_entry_id);

            DB::table('report_entries')->where('id', $entry->id)->update([
                'rows' => $source['rows'],
                'no_activity' => $source['no_activity'],
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Independent copies cannot be restored as live references without changing newer data.
    }
};
