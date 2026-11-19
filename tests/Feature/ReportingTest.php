<?php

namespace Tests\Feature;

use App\Jobs\BuildReportingExport;
use App\Livewire\AssignmentTable;
use App\Livewire\CategoryTable;
use App\Livewire\ImportTable;
use App\Livewire\IndicatorTable;
use App\Livewire\PeriodTable;
use App\Livewire\RecapTable;
use App\Livewire\ReportEditor;
use App\Livewire\ReportingCycleTable;
use App\Livewire\ReportTable;
use App\Livewire\UnitTable;
use App\Livewire\UserTable;
use App\Models\Indicator;
use App\Models\IndicatorAssignment;
use App\Models\IndicatorCategory;
use App\Models\RecapMetric;
use App\Models\ReportingCycle;
use App\Models\ReportingExport;
use App\Models\ReportingPeriod;
use App\Models\Unit;
use App\Models\UnitReport;
use App\Models\User;
use App\Services\IndicatorCalculator;
use App\Services\ReportRecap;
use App\Services\ReportWorkflow;
use App\Services\SpreadsheetExport;
use App\Services\WorkbookReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_background_export_rechecks_access_and_private_download(): void
    {
        Storage::fake('local');
        Queue::fake();
        $this->verified($this->period, 8);
        Livewire::test(RecapTable::class)->set('periodIds', [$this->period->id])->call('queueExport')->assertHasNoErrors();
        $export = ReportingExport::firstOrFail();
        Queue::assertPushed(BuildReportingExport::class);
        $job = new BuildReportingExport($export->id);
        $job->handle(app(ReportRecap::class), app(SpreadsheetExport::class));
        $this->assertSame('READY', $export->fresh()->status);
        $this->actingAs($this->admin)->get(route('recaps.download', $export))->assertOk();
        $this->actingAs($this->operator)->get(route('recaps.download', $export))->assertForbidden();
        $this->admin->update(['role' => 'PIMPINAN']);
        $this->actingAs($this->admin->fresh())->get(route('recaps.download', $export))->assertForbidden();
    }

    public function test_unit_sees_assigned_open_period_and_can_start_report_from_list(): void
    {
        $closed = ReportingPeriod::create(['year' => 2026, 'quarter' => 2, 'deadline' => '2026-07-10', 'status' => 'CLOSED']);
        $this->assign($closed);
        ReportingPeriod::create(['year' => 2026, 'quarter' => 3, 'deadline' => '2026-10-10', 'status' => 'OPEN']);
        $this->actingAs($this->operator);

        $component = Livewire::test(ReportTable::class)
            ->assertSee('Laporan yang perlu diisi')
            ->assertViewHas('pendingPeriods', fn ($periods) => $periods->pluck('id')->all() === [$this->period->id]);

        $component->call('start', $this->period->id);
        $report = UnitReport::where('unit_id', $this->unit->id)->where('period_id', $this->period->id)->firstOrFail();
        $component->assertRedirect(route('reports.show', $report));
        Livewire::test(ReportTable::class)
            ->assertDontSee('Laporan yang perlu diisi')
            ->assertViewHas('pendingPeriods', fn ($periods) => $periods->isEmpty());

        $this->actingAs($this->admin);
        Livewire::test(ReportTable::class)->assertViewHas('pendingPeriods', fn ($periods) => $periods->isEmpty());
    }

    private User $admin;

    private User $operator;

    private Unit $unit;

    private ReportingPeriod $period;

    private Indicator $indicator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'ADMIN']);
        $this->unit = Unit::create(['code' => 'ACA', 'name' => 'Akademik']);
        $this->operator = User::factory()->create(['role' => 'UNIT', 'unit_id' => $this->unit->id]);
        $this->period = ReportingPeriod::create(['year' => 2026, 'quarter' => 1, 'deadline' => '2026-04-10', 'status' => 'OPEN']);
        $category = IndicatorCategory::create(['code' => 'STU', 'name' => 'Mahasiswa']);
        $this->indicator = Indicator::create([
            'category_id' => $category->id, 'code' => 'NEW', 'name' => 'Mahasiswa baru', 'form_type' => 'SINGLE', 'aggregation' => 'SUM', 'direction' => 'UP_GOOD', 'result_field' => 'amount', 'created_by' => $this->admin->id,
            'fields' => [$this->field('amount')], 'items' => [],
        ]);
        $this->assign($this->period);
        $this->actingAs($this->admin);
    }

    private function field(string $code, string $type = 'NUMBER'): array
    {
        return ['code' => $code, 'label' => $code, 'type' => $type, 'unit' => 'orang', 'required' => true, 'decimals' => 0, 'min' => 0, 'max' => '', 'options' => '', 'operation' => 'SUM', 'operands' => ''];
    }

    private function assign(ReportingPeriod $period): void
    {
        IndicatorAssignment::create(['period_id' => $period->id, 'unit_id' => $this->unit->id, 'indicator_id' => $this->indicator->id, 'required' => true, 'assigned_by' => $this->admin->id]);
    }

    private function start(): UnitReport
    {
        $this->actingAs($this->operator);

        return app(ReportWorkflow::class)->start($this->period->id, $this->operator);
    }

    private function configureAssignedIndicator(array $attributes): void
    {
        $this->indicator->update($attributes);
        $assignment = IndicatorAssignment::where('period_id', $this->period->id)->sole();
        app(ReportWorkflow::class)->updateAssignedIndicator($this->period->id, $this->unit->id, $assignment->id);
    }

    private function verified(ReportingPeriod $period, int $amount): UnitReport
    {
        $this->actingAs($this->operator);
        $report = app(ReportWorkflow::class)->start($period->id, $this->operator);
        $entry = $report->entries()->first();
        Livewire::test(ReportEditor::class, ['reportId' => $report->id])->set('sections.'.$entry->id.'.rows.0.values.amount', $amount)->call('submit')->assertHasNoErrors();
        $this->actingAs($this->admin);
        Livewire::test(ReportEditor::class, ['reportId' => $report->id])->call('transition', 'VERIFIED')->assertHasNoErrors();

        return $report->fresh();
    }

    public function test_admin_pages_use_livewire_and_render_without_errors(): void
    {
        foreach (['units.index' => UnitTable::class, 'users.index' => UserTable::class, 'periods.index' => ReportingCycleTable::class, 'categories.index' => CategoryTable::class, 'indicators.index' => IndicatorTable::class, 'assignments.index' => AssignmentTable::class] as $route => $component) {
            $this->get(route($route))->assertOk()->assertSeeLivewire($component);
        }
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('reports.index'))->assertOk();
        $this->get(route('recaps.index'))->assertOk();
    }

    public function test_units_and_unassigned_accounts_cannot_manage_master_data(): void
    {
        foreach ([$this->operator, User::factory()->create(['role' => 'PIMPINAN']), User::factory()->create()] as $user) {
            $this->actingAs($user)->get(route('units.index'))->assertForbidden();
            Livewire::test(UnitTable::class)->assertForbidden();
        }
        $this->actingAs(User::factory()->create())->get(route('reports.index'))->assertForbidden();
    }

    public function test_master_crud_validation_hierarchy_and_account_protection(): void
    {
        Livewire::test(UnitTable::class)->set('form.code', 'LIB')->set('form.name', 'Perpustakaan')->call('save')->assertHasNoErrors();
        Livewire::test(UnitTable::class)->set('form.code', 'ACA')->set('form.name', 'Duplikat')->call('save')->assertHasErrors('form.code');
        Livewire::test(UnitTable::class)->call('openEditModal', $this->unit->id)->set('form.parent_id', $this->unit->id)->call('save')->assertHasErrors('form.parent_id');
        Livewire::test(UserTable::class)->call('openEditModal', $this->admin->id)->set('form.username', 'admin')->set('form.role', 'PIMPINAN')->call('save')->assertHasErrors('form.role');
        Livewire::test(PeriodTable::class)->set('form.year', 2026)->set('form.quarter', 1)->set('form.deadline', '2026-04-10')->call('save')->assertHasErrors('form.quarter');
        Livewire::test(IndicatorTable::class)->call('openEditModal', $this->indicator->id)->set('form.name', 'Penerimaan baru')->call('save')->assertHasNoErrors();
        $this->assertSame(2, $this->indicator->fresh()->version);
    }

    public function test_draft_accepts_missing_values_but_submit_requires_completeness_and_zero_is_valid(): void
    {
        $report = $this->start();
        $entry = $report->entries()->first();
        Livewire::test(ReportEditor::class, ['reportId' => $report->id])
            ->call('save')
            ->assertHasNoErrors()
            ->call('submit')
            ->assertHasErrors('sections.'.$entry->id.'.rows.0.values.amount')
            ->assertDispatched('report-validation-failed')
            ->assertSee('Laporan belum dapat diproses.')
            ->assertSee('Indikator “Mahasiswa baru”, butir “Mahasiswa baru”')
            ->assertSee('kolom amount wajib diisi')
            ->set('sections.'.$entry->id.'.rows.0.values.amount', 0)
            ->call('submit')
            ->assertHasNoErrors();
        $this->assertSame('SUBMITTED', $report->fresh()->status);
        Livewire::test(ReportEditor::class, ['reportId' => $report->id])
            ->call('save')
            ->assertHasErrors('report')
            ->assertDispatched('report-validation-failed')
            ->assertSee('statusnya sudah diajukan');
    }

    public function test_previous_quarter_data_is_copied_and_can_be_extended_without_changing_source(): void
    {
        $this->configureAssignedIndicator(['form_type' => 'DYNAMIC']);
        $cycle = ReportingCycle::create([
            'year' => 2026,
            'frequency' => 'QUARTERLY',
            'created_by' => $this->admin->id,
        ]);
        $this->period->update(['cycle_id' => $cycle->id]);
        $secondPeriod = ReportingPeriod::create([
            'cycle_id' => $cycle->id,
            'year' => 2026,
            'quarter' => 2,
            'deadline' => '2026-07-10',
            'status' => 'OPEN',
        ]);
        $this->assign($secondPeriod);

        $firstReport = $this->start();
        $firstEntry = $firstReport->entries()->firstOrFail();
        $secondReport = app(ReportWorkflow::class)->start($secondPeriod->id, $this->operator);
        $secondEntry = $secondReport->entries()->firstOrFail();

        // The checkbox stays hidden while the previous quarter has no data.
        Livewire::test(ReportEditor::class, ['reportId' => $secondReport->id])
            ->assertDontSee('Salin data dari triwulan sebelumnya')
            ->set('sections.'.$secondEntry->id.'.use_previous', true)
            ->call('save')
            ->assertHasErrors('sections.'.$secondEntry->id.'.use_previous');

        Livewire::test(ReportEditor::class, ['reportId' => $firstReport->id])
            ->call('addRow', $firstEntry->id)
            ->set('sections.'.$firstEntry->id.'.rows.0.label', 'Data lama')
            ->set('sections.'.$firstEntry->id.'.rows.0.values.amount', 10)
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test(ReportEditor::class, ['reportId' => $secondReport->id])
            ->assertSee('Salin data dari triwulan sebelumnya (TW 1 / 2026)')
            ->call('togglePrevious', $secondEntry->id, true)
            ->assertSet('sections.'.$secondEntry->id.'.rows.0.values.amount', 10)
            ->assertSee('Silakan ubah atau tambahkan data')
            ->call('addRow', $secondEntry->id)
            ->set('sections.'.$secondEntry->id.'.rows.1.label', 'Tambahan baru')
            ->set('sections.'.$secondEntry->id.'.rows.1.values.amount', 5)
            ->call('submit')
            ->assertHasNoErrors();

        $secondEntry->refresh();
        $this->assertSame($firstEntry->id, $secondEntry->source_entry_id);
        $this->assertEquals(10, $secondEntry->rows[0]['values']['amount']);
        $this->assertEquals(5, $secondEntry->rows[1]['values']['amount']);
        $this->assertCount(1, $firstEntry->fresh()->rows);

        $this->actingAs($this->admin);
        Livewire::test(ReportEditor::class, ['reportId' => $secondReport->id])
            ->call('transition', 'VERIFIED')
            ->assertHasNoErrors();

        $this->actingAs($this->operator);
        Livewire::test(ReportEditor::class, ['reportId' => $firstReport->id])
            ->set('sections.'.$firstEntry->id.'.rows.0.values.amount', 25)
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test(ReportEditor::class, ['reportId' => $secondReport->id])
            ->assertSet('sections.'.$secondEntry->id.'.rows.0.values.amount', 10)
            ->assertSet('sections.'.$secondEntry->id.'.rows.1.values.amount', 5);

        $this->actingAs($this->admin);
        $result = app(ReportRecap::class)->build([
            'periods' => [$secondPeriod->id],
            'units' => [],
            'indicators' => [],
            'grouping' => 'TOTAL',
        ]);

        $this->assertEquals(15, $result['rows'][0]['value']);
    }

    public function test_optional_row_details_must_match_the_main_total_on_submit(): void
    {
        $this->configureAssignedIndicator([
            'form_type' => 'FIXED',
            'items' => [
                ['code' => 'lokal', 'label' => 'Lokal'],
                ['code' => 'internasional', 'label' => 'Internasional'],
            ],
        ]);
        $report = $this->start();
        $entry = $report->entries()->firstOrFail();
        $path = 'sections.'.$entry->id.'.rows.0';

        $component = Livewire::test(ReportEditor::class, ['reportId' => $report->id])
            ->assertSee('Lokal')
            ->set($path.'.values.amount', 3)
            ->set('sections.'.$entry->id.'.rows.1.values.amount', 0)
            ->call('openDetails', $entry->id, 0)
            ->assertDispatched('open-report-detail-modal')
            ->call('addDetailRow')
            ->set('detailDraft.0.amount', '')
            ->assertSee('Lengkapi nama dan jumlah pada seluruh baris detail.')
            ->set('detailDraft.0.name', 'Prestasi A')
            ->set('detailDraft.0.amount', 1)
            ->assertSee('Total detail belum sama dengan nilai utama.')
            ->assertSee('aria-disabled="true"', false);

        $component->call('saveDetails')
            ->assertHasErrors('detailDraft')
            ->assertSet($path.'.details', [])
            ->call('openDetails', $entry->id, 0)
            ->assertSet('detailDraft', [])
            ->call('addDetailRow')
            ->set('detailDraft.0.name', 'Prestasi A')
            ->set('detailDraft.0.amount', 3)
            ->assertDontSee('Total detail belum sama dengan nilai utama.')
            ->assertSee('aria-disabled="false"', false)
            ->call('saveDetails')
            ->assertHasNoErrors()
            ->assertDispatched('close-report-detail-modal')
            ->assertSet($path.'.details.0.name', 'Prestasi A')
            ->call('save')
            ->assertHasNoErrors();

        $component->set($path.'.details.0.amount', 1)
            ->call('submit')
            ->assertHasErrors($path.'.details')
            ->set($path.'.details.0.amount', 3)
            ->call('submit')
            ->assertHasNoErrors();

        $details = $entry->fresh()->rows[0]['details'];
        $this->assertSame('Prestasi A', $details[0]['name']);
        $this->assertEquals(3, $details[0]['amount']);
    }

    public function test_admin_and_unit_can_open_details_when_period_is_closed(): void
    {
        $report = $this->start();
        $entry = $report->entries()->firstOrFail();
        $rows = $entry->rows;
        $rows[0]['values']['amount'] = 3;
        $rows[0]['details'] = [['name' => 'Prestasi A', 'amount' => 3]];
        $entry->update(['rows' => $rows]);
        $report->update(['status' => 'VERIFIED', 'verified_by' => $this->admin->id, 'verified_at' => now()]);
        $this->period->update(['status' => 'CLOSED']);

        foreach ([$this->operator, $this->admin] as $user) {
            $this->actingAs($user);
            Livewire::test(ReportEditor::class, ['reportId' => $report->id])
                ->call('openDetails', $entry->id, 0)
                ->assertDispatched('open-report-detail-modal')
                ->assertSet('detailDraft.0.name', 'Prestasi A')
                ->assertViewHas(
                    'selectedDetail',
                    fn (array $detail): bool => $detail['details'][0]['name'] === 'Prestasi A',
                );
        }
    }

    public function test_revision_verification_reopening_and_snapshots(): void
    {
        $report = $this->verified($this->period, 10);
        $this->indicator->update(['name' => 'Nama baru', 'version' => 2]);
        $this->assertSame('Mahasiswa baru', $report->entries()->first()->definition['name']);
        Livewire::test(ReportEditor::class, ['reportId' => $report->id])->call('transition', 'REVISION')->assertHasErrors('note')->set('note', 'Perbaiki jumlah mahasiswa.')->call('transition', 'REVISION')->assertHasNoErrors();
        $this->assertSame('REVISION', $report->fresh()->status);
        $this->assertCount(0, app(ReportRecap::class)->build(['periods' => [$this->period->id], 'units' => [], 'indicators' => [], 'grouping' => 'TOTAL'])['rows']);
        $this->actingAs($this->operator);
        $entry = $report->entries()->first();
        Livewire::test(ReportEditor::class, ['reportId' => $report->id])->set('sections.'.$entry->id.'.rows.0.values.amount', 12)->call('submit')->assertHasNoErrors();
        $this->actingAs($this->admin);
        $component = Livewire::test(ReportEditor::class, ['reportId' => $report->id])->call('transition', 'VERIFIED')->assertHasErrors('note');
        $component->call('resolveNote', $report->notes()->first()->id)->call('transition', 'VERIFIED')->assertHasNoErrors();
        $old = $report->histories()->where('to_status', 'VERIFIED')->orderBy('id')->first();
        $this->assertEquals(10, $old->snapshot['entries'][0]['rows'][0]['values']['amount']);
        $this->get(route('reports.history', $old))->assertOk()->assertSee('Mahasiswa baru');
    }

    public function test_admin_can_reset_only_the_selected_report_to_a_blank_draft(): void
    {
        Storage::fake('local');
        $report = $this->start();
        $entry = $report->entries()->firstOrFail();

        Livewire::test(ReportEditor::class, ['reportId' => $report->id])
            ->set('sections.'.$entry->id.'.rows.0.values.amount', 12)
            ->set('sections.'.$entry->id.'.explanation', 'Data awal')
            ->call('submit')
            ->assertHasNoErrors();

        Storage::disk('local')->put('reporting/'.$report->id.'/bukti.pdf', 'bukti');
        $report->attachments()->create([
            'entry_id' => $entry->id,
            'uploaded_by' => $this->operator->id,
            'name' => 'bukti.pdf',
            'path' => 'reporting/'.$report->id.'/bukti.pdf',
            'mime_type' => 'application/pdf',
            'size' => 5,
        ]);
        $report->notes()->create([
            'user_id' => $this->admin->id,
            'note' => 'Catatan lama',
        ]);

        Livewire::test(ReportEditor::class, ['reportId' => $report->id])
            ->call('resetReport')
            ->assertForbidden();

        $this->actingAs($this->admin);
        Livewire::test(ReportEditor::class, ['reportId' => $report->id])
            ->assertSee('Reset laporan ini')
            ->assertSee('Semua isian indikator, detail, lampiran, dan catatan laporan ini akan dihapus.')
            ->call('resetReport')
            ->assertHasNoErrors()
            ->assertSee('berhasil direset menjadi draf kosong');

        $report->refresh();
        $entry->refresh();

        $this->assertSame('DRAFT', $report->status);
        $this->assertNull($report->submitted_at);
        $this->assertNull($report->verified_by);
        $this->assertNull($report->verified_at);
        $this->assertNull($entry->source_entry_id);
        $this->assertFalse($entry->no_activity);
        $this->assertNull($entry->explanation);
        $this->assertNull($entry->rows[0]['values']['amount']);
        $this->assertSame([], $entry->rows[0]['details']);
        $this->assertCount(0, $report->attachments);
        $this->assertCount(0, $report->notes);
        Storage::disk('local')->assertMissing('reporting/'.$report->id.'/bukti.pdf');
        $this->assertSame(
            'Laporan direset oleh admin dan dikembalikan menjadi draf kosong.',
            $report->histories()->latest('id')->firstOrFail()->note,
        );
    }

    public function test_other_units_cannot_read_change_or_download_report(): void
    {
        Storage::fake('local');
        $report = $this->start();
        $attachment = $report->attachments()->create(['entry_id' => $report->entries()->first()->id, 'uploaded_by' => $this->operator->id, 'name' => 'bukti.pdf', 'path' => 'private.pdf', 'mime_type' => 'application/pdf', 'size' => 4]);
        $other = Unit::create(['code' => 'OTHER', 'name' => 'Unit lain']);
        $this->actingAs(User::factory()->create(['role' => 'UNIT', 'unit_id' => $other->id]));
        $this->get(route('reports.show', $report))->assertForbidden();
        $this->get(route('reports.attachment', $attachment))->assertForbidden();
        Livewire::test(ReportEditor::class, ['reportId' => $report->id])->assertForbidden();
    }

    public function test_stale_writes_and_closed_period_are_rejected(): void
    {
        $report = $this->start();
        $first = Livewire::test(ReportEditor::class, ['reportId' => $report->id]);
        $second = Livewire::test(ReportEditor::class, ['reportId' => $report->id]);
        $first->call('save')->assertHasNoErrors();
        $second->call('save')->assertHasErrors('report');
        $this->period->update(['status' => 'CLOSED']);
        Livewire::test(ReportEditor::class, ['reportId' => $report->id])
            ->call('save')
            ->assertHasErrors('report')
            ->assertDispatched('report-validation-failed')
            ->assertSee('periode pengisian belum dibuka atau sudah ditutup');
    }

    public function test_assignment_cannot_change_after_report_is_created(): void
    {
        $this->start();
        $this->actingAs($this->admin);
        $assignment = IndicatorAssignment::firstOrFail();

        Livewire::test(AssignmentTable::class)
            ->call('openEditModal', $assignment->id)
            ->set('form.responsible_user_id', $this->operator->id)
            ->set('form.required', false)
            ->call('save')
            ->assertHasErrors('form.period_id');

        Livewire::test(AssignmentTable::class)
            ->call('openDetail', $assignment->period_id, $assignment->unit_id)
            ->call('deleteIndicator', $assignment->id)
            ->assertHasErrors('form.indicator_id');

        $this->assertModelExists($assignment);
    }

    public function test_update_refreshes_only_the_selected_indicator_and_preserves_saved_values(): void
    {
        $previous = ReportingPeriod::create(['year' => 2025, 'quarter' => 4, 'deadline' => '2026-01-10', 'status' => 'OPEN']);
        $this->assign($previous);
        $previousReport = $this->verified($previous, 10);
        $previous->update(['status' => 'CLOSED']);
        $other = $this->indicator->replicate();
        $other->code = 'OTHER';
        $other->save();
        $assignment = IndicatorAssignment::where('period_id', $this->period->id)->sole();
        $otherAssignment = $assignment->replicate();
        $otherAssignment->indicator_id = $other->id;
        $otherAssignment->definition = null;
        $otherAssignment->save();

        $report = $this->start();
        $entry = $report->entries()->where('indicator_id', $this->indicator->id)->sole();
        $otherEntry = $report->entries()->where('indicator_id', $other->id)->sole();
        $rows = $entry->rows;
        $rows[0]['values']['amount'] = 7;
        $entry->update(['rows' => $rows]);
        $this->indicator->update(['name' => 'Nama terbaru', 'version' => 2, 'fields' => [$this->field('amount'), $this->field('extra')]]);
        $other->update(['name' => 'Indikator lain terbaru', 'version' => 2]);

        $this->actingAs($this->admin);
        $component = Livewire::test(AssignmentTable::class)
            ->call('openDetail', $this->period->id, $this->unit->id)
            ->assertSee('Update')
            ->call('updateIndicator', $assignment->id)
            ->assertHasNoErrors()
            ->assertSee('Nama terbaru');

        $this->assertSame(2, $assignment->fresh()->definition['version']);
        $this->assertSame(2, $entry->fresh()->definition['version']);
        $this->assertEquals(['amount' => 7, 'extra' => null], $entry->fresh()->rows[0]['values']);
        $this->assertSame(1, $otherEntry->fresh()->definition['version']);
        $this->assertSame(1, $otherAssignment->fresh()->definition['version']);
        $this->assertSame(1, $previousReport->entries()->sole()->definition['version']);
        $this->assertSame(2, $report->fresh()->version);

        $this->indicator->update(['version' => 3, 'fields' => [$this->field('extra')], 'result_field' => 'extra']);
        $component->call('updateIndicator', $assignment->id)->assertHasNoErrors();
        $this->assertSame(3, $entry->fresh()->definition['version']);
        $this->assertSame(['extra' => null], $entry->fresh()->rows[0]['values']);

        $previousAssignment = IndicatorAssignment::where('period_id', $previous->id)->sole();
        $component->call('updateIndicator', $previousAssignment->id)->assertHasErrors('updateIndicator');
        $report->update(['status' => 'SUBMITTED']);
        Livewire::test(AssignmentTable::class)
            ->call('openDetail', $this->period->id, $this->unit->id)
            ->assertDontSee('Update')
            ->call('updateIndicator', $assignment->id)->assertHasErrors('updateIndicator');
        Livewire::test(AssignmentTable::class)->call('openDetail', $previous->id, $this->unit->id)
            ->call('updateIndicator', $previousAssignment->id)->assertHasErrors('updateIndicator');
        $this->assertSame(1, $previousAssignment->fresh()->definition['version']);
    }

    public function test_update_can_refresh_an_assignment_before_the_report_exists_and_requires_admin(): void
    {
        $assignment = IndicatorAssignment::where('period_id', $this->period->id)->sole();
        $this->indicator->update(['name' => 'Nama terbaru', 'version' => 2]);

        Livewire::test(AssignmentTable::class)
            ->call('openDetail', $this->period->id, $this->unit->id)
            ->assertSee('Mahasiswa baru')
            ->call('updateIndicator', $assignment->id)
            ->assertHasNoErrors();
        $this->assertSame(2, $this->start()->entries()->sole()->definition['version']);
        Livewire::test(AssignmentTable::class)->assertForbidden();
    }

    public function test_update_expands_a_filled_single_row_into_a_fixed_table(): void
    {
        $report = $this->start();
        $entry = $report->entries()->sole();
        $rows = $entry->rows;
        $rows[0]['values']['amount'] = 7;
        $entry->update(['rows' => $rows]);
        $items = [['code' => 'extra', 'label' => 'Butir baru']];
        $this->indicator->update(['form_type' => 'FIXED', 'version' => 2, 'items' => $items]);
        $assignment = IndicatorAssignment::where('period_id', $this->period->id)->sole();

        $this->actingAs($this->admin);
        $component = Livewire::test(AssignmentTable::class)->call('openDetail', $this->period->id, $this->unit->id);
        $component->call('updateIndicator', $assignment->id)->assertHasErrors('updateIndicator');
        $this->assertSame('SINGLE', $entry->fresh()->definition['form_type']);

        $items[] = ['code' => 'single', 'label' => 'Butir lama'];
        $this->indicator->update(['items' => $items]);
        $component->call('updateIndicator', $assignment->id)->assertHasNoErrors();
        $rows = $entry->fresh()->rows;
        $this->assertSame(['extra', 'single'], array_column($rows, 'key'));
        $this->assertNull($rows[0]['values']['amount']);
        $this->assertEquals(7, $rows[1]['values']['amount']);
    }

    public function test_update_requires_confirmation_before_overwriting_incompatible_data(): void
    {
        $report = $this->start();
        $entry = $report->entries()->sole();
        $rows = $entry->rows;
        $rows[0]['values']['amount'] = 7;
        $entry->update(['rows' => $rows]);
        $this->indicator->update(['form_type' => 'DYNAMIC', 'version' => 2]);
        $assignment = IndicatorAssignment::where('period_id', $this->period->id)->sole();

        $this->actingAs($this->admin);
        $component = Livewire::test(AssignmentTable::class)
            ->call('openDetail', $this->period->id, $this->unit->id)
            ->call('updateIndicator', $assignment->id)
            ->assertSee('Data tidak sesuai');
        $this->assertEquals(7, $entry->fresh()->rows[0]['values']['amount']);
        $component->call('cancelIndicatorOverwrite')->assertDontSee('Data tidak sesuai');
        $this->assertSame(1, $assignment->fresh()->definition['version']);

        $component->call('updateIndicator', $assignment->id);
        $report->increment('version');
        $component->call('confirmIndicatorOverwrite')->assertHasErrors('updateIndicator');
        $this->assertEquals(7, $entry->fresh()->rows[0]['values']['amount']);

        $component->call('updateIndicator', $assignment->id)
            ->call('confirmIndicatorOverwrite')->assertHasNoErrors();
        $this->assertSame([], $entry->fresh()->rows);
        $this->assertSame('DYNAMIC', $assignment->fresh()->definition['form_type']);
        $backup = $report->histories()->where('note', 'like', 'Salinan laporan sebelum%')->sole();
        $this->assertEquals(7, $backup->snapshot['entries'][0]['rows'][0]['values']['amount']);
    }

    public function test_update_tracks_fixed_rows_across_renames_and_reused_codes(): void
    {
        $this->configureAssignedIndicator([
            'form_type' => 'FIXED',
            'items' => [
                ['code' => 'keep', 'label' => 'Butir lama'],
                ['code' => 'reuse', 'label' => 'Butir yang diganti'],
                ['code' => 'remove', 'label' => 'Butir yang dihapus'],
                ['code' => 'same', 'label' => 'Label lama'],
            ],
        ]);
        $report = $this->start();
        $entry = $report->entries()->sole();
        $rows = $entry->rows;
        $rows[0]['values']['amount'] = 12;
        $rows[1]['values']['amount'] = 34;
        $rows[2]['values']['amount'] = 56;
        $rows[3]['values']['amount'] = 78;
        $entry->update(['rows' => $rows]);

        $this->actingAs($this->admin);
        Livewire::test(IndicatorTable::class)
            ->call('openEditModal', $this->indicator->id)
            ->set('items.0.code', 'renamed')
            ->set('items.0.label', 'Butir diperbarui')
            ->set('items.3.label', 'Label baru')
            ->call('removeItem', 2)
            ->call('removeItem', 1)
            ->call('addItem')
            ->set('items.2.code', 'reuse')
            ->set('items.2.label', 'Butir baru')
            ->call('save')->assertHasNoErrors();

        $assignment = IndicatorAssignment::where('period_id', $this->period->id)->sole();
        Livewire::test(AssignmentTable::class)
            ->call('openDetail', $this->period->id, $this->unit->id)
            ->call('updateIndicator', $assignment->id)->assertHasNoErrors();

        $rows = $entry->fresh()->rows;
        $this->assertSame(['renamed', 'same', 'reuse'], array_column($rows, 'key'));
        $this->assertSame(['Butir diperbarui', 'Label baru', 'Butir baru'], array_column($rows, 'label'));
        $this->assertEquals(12, $rows[0]['values']['amount']);
        $this->assertEquals(78, $rows[1]['values']['amount']);
        $this->assertNull($rows[2]['values']['amount']);
        $note = $report->histories()->latest('id')->firstOrFail()->note;
        $this->assertStringContainsString('Baris diubah: keep → renamed', $note);
        $this->assertStringContainsString('Baris diubah: same', $note);
        $this->assertStringContainsString('Baris dihapus: remove', $note);
        $this->assertStringContainsString('Baris dihapus: reuse', $note);
        $this->assertStringContainsString('Baris ditambah: reuse', $note);
    }

    public function test_assignment_indicator_can_be_deleted_before_report_is_created(): void
    {
        $assignment = IndicatorAssignment::firstOrFail();

        Livewire::test(AssignmentTable::class)
            ->call('openDetail', $assignment->period_id, $assignment->unit_id)
            ->call('deleteIndicator', $assignment->id)
            ->assertHasNoErrors();

        $this->assertModelMissing($assignment);
    }

    public function test_custom_recap_uses_exact_selected_year_quarter_pairs_and_exports(): void
    {
        $this->verified($this->period, 10);
        $second = ReportingPeriod::create(['year' => 2027, 'quarter' => 3, 'deadline' => '2027-10-10', 'status' => 'OPEN']);
        $this->assign($second);
        $this->verified($second, 25);
        $third = ReportingPeriod::create(['year' => 2027, 'quarter' => 4, 'deadline' => '2028-01-10', 'status' => 'OPEN']);
        $this->assign($third);
        $filters = ['periods' => [$this->period->id, $second->id, $third->id], 'units' => [], 'indicators' => [], 'grouping' => 'TOTAL'];
        $result = app(ReportRecap::class)->build($filters);
        $this->assertEquals(35, $result['rows'][0]['value']);
        $this->assertCount(1, $result['missing']);
        Livewire::test(RecapTable::class)
            ->set('periodIds', [$this->period->id, $second->id])
            ->set('grouping', 'TOTAL')
            ->call('showStatistics')
            ->assertRedirect();
        $this->get(route('recaps.statistics').'?'.http_build_query($filters))
            ->assertOk()
            ->assertSee('Statistik Laporan Custom')
            ->assertSee('35')
            ->assertDontSee('35,0000');
        $this->get(route('recaps.print').'?'.http_build_query($filters))->assertOk()->assertSee('35,0000');
        $path = app(SpreadsheetExport::class)->create($result);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path));
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('<v>35</v>', $xml);
        $zip->close();
        unlink($path);
    }

    public function test_calculation_rules_ratio_stock_unique_and_invalid_expression(): void
    {
        $calculator = app(IndicatorCalculator::class);
        $definition = $this->indicator->definition();
        $rows = ['2026-1' => [['values' => ['amount' => 10, 'total' => 100]]], '2026-2' => [['values' => ['amount' => 30, 'total' => 50]]]];
        $definition['aggregation'] = 'LAST';
        $this->assertEquals(30, $calculator->aggregate($definition, $rows));
        $definition['aggregation'] = 'RATIO';
        $definition['denominator_field'] = 'total';
        $definition['ratio_scale'] = 100;
        $this->assertEqualsWithDelta(26.6667, $calculator->aggregate($definition, $rows), 0.0001);
        $definition['aggregation'] = 'DISTINCT';
        $this->assertSame(2, $calculator->aggregate($definition, $rows));
        $fields = [$this->field('amount'), $this->field('result', 'CALCULATED')];
        $fields[1]['operands'] = 'amount,system';
        $this->expectException(ValidationException::class);
        $calculator->validateDefinition($fields, [], 'SINGLE', 'result', 'SUM', null);
    }

    public function test_admin_form_errors_use_visible_field_names(): void
    {
        Livewire::test(IndicatorTable::class)
            ->call('save')
            ->assertHasErrors(['form.category_id' => 'required'])
            ->assertSee('kategori indikator wajib diisi.')
            ->assertDontSee('form.category_id wajib diisi.');

        Livewire::test(ImportTable::class)
            ->call('preview')
            ->assertHasErrors(['file' => 'required'])
            ->assertSee('Pilih berkas Excel untuk diimpor.')
            ->assertSee('lembar Excel wajib diisi.');
    }

    public function test_missing_pages_explain_what_to_do(): void
    {
        $this->get('/halaman-siblu-tidak-ada')
            ->assertNotFound()
            ->assertSee('Halaman atau data tidak ditemukan.')
            ->assertSee('Ke beranda SIBLU');
    }

    public function test_livewire_signed_upload_accepts_https_from_the_configured_proxy(): void
    {
        Storage::fake('tmp-for-tests');
        config(['app.trusted_proxies' => '172.18.0.1']);
        URL::forceScheme('https');
        $url = str_replace('https://', 'http://', URL::temporarySignedRoute('livewire.upload-file', now()->addMinute()));
        $file = fn () => ['files' => [UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf')]];
        $headers = ['X-Forwarded-Proto' => 'https'];

        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.2'])->postJson($url, $file(), $headers)->assertUnauthorized();
        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.1'])->postJson($url, $file(), $headers)
            ->assertOk()->assertJsonCount(1, 'paths');
    }

    public function test_livewire_temporary_upload_enforces_attachment_limits(): void
    {
        Storage::fake('tmp-for-tests');
        $url = URL::temporarySignedRoute('livewire.upload-file', now()->addMinute());
        $this->postJson($url, ['files' => [UploadedFile::fake()->create('proof.pdf', 20480, 'application/pdf')]])
            ->assertOk()->assertJsonCount(1, 'paths');
        $this->postJson($url, ['files' => [UploadedFile::fake()->create('proof.pdf', 20481, 'application/pdf')]])
            ->assertUnprocessable()->assertJsonValidationErrors('files.0')
            ->assertJsonFragment(['files.0' => ['Ukuran berkas maksimal 20 MB.']]);
        $this->postJson($url, ['files' => [UploadedFile::fake()->image('proof.jpg')]])
            ->assertUnprocessable()->assertJsonValidationErrors('files.0');
        $this->postJson($url, ['files' => [
            UploadedFile::fake()->create('proof.xlsx', 10),
            UploadedFile::fake()->create('proof.docx', 10),
        ]])->assertOk()->assertJsonCount(2, 'paths');
    }

    public function test_attachment_editor_only_accepts_pdf_xlsx_and_docx_up_to_twenty_mb(): void
    {
        Storage::fake('local');
        $report = $this->start();
        $upload = 'uploads.'.$report->entries()->sole()->id;
        $editor = Livewire::test(ReportEditor::class, ['reportId' => $report->id])
            ->assertSee('PDF, XLSX, DOCX, maks. 20 MB', false)
            ->assertSee('accept=".pdf,.xlsx,.docx"', false)
            ->set($upload, UploadedFile::fake()->create('proof.pdf', 20481, 'application/pdf'))->assertHasErrors($upload)
            ->set($upload, UploadedFile::fake()->create('proof.txt', 10, 'application/pdf'))->assertHasErrors($upload)
            ->set($upload, UploadedFile::fake()->create('proof.pdf', 10, 'image/jpeg'))->assertHasErrors($upload);
        $this->assertSame(0, $report->attachments()->count());

        $editor->set($upload, UploadedFile::fake()->create('proof.pdf', 20480, 'application/pdf'))->assertHasNoErrors()
            ->set($upload, UploadedFile::fake()->create('proof.xlsx', 10))->assertHasNoErrors()
            ->set($upload, UploadedFile::fake()->create('proof.docx', 10))->assertHasNoErrors();
        $this->assertSame(3, $report->attachments()->count());
    }

    public function test_private_attachment_upload_and_required_evidence(): void
    {
        Storage::fake('local');
        $this->configureAssignedIndicator(['requires_attachment' => true]);
        $report = $this->start();
        $entry = $report->entries()->first();
        Livewire::test(ReportEditor::class, ['reportId' => $report->id])->set('sections.'.$entry->id.'.rows.0.values.amount', 2)->call('submit')->assertHasErrors('sections.'.$entry->id.'.attachment')->set('uploads.'.$entry->id, UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'))->assertHasNoErrors()->call('submit')->assertHasNoErrors();
        $attachment = $report->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->get(route('reports.attachment', $attachment))->assertOk();
    }

    public function test_operator_can_delete_an_attachment_from_an_editable_report(): void
    {
        Storage::fake('local');
        $report = $this->start();
        $entry = $report->entries()->sole();
        $editor = Livewire::test(ReportEditor::class, ['reportId' => $report->id]);
        $editor->set('uploads.'.$entry->id, UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'))->assertHasNoErrors();
        $attachment = $report->attachments()->sole();

        $editor->set('sections.'.$entry->id.'.explanation', 'Isian belum disimpan')
            ->call('deleteAttachment', $attachment->id)
            ->assertHasNoErrors()
            ->assertSet('sections.'.$entry->id.'.explanation', 'Isian belum disimpan');

        $this->assertFalse($report->attachments()->whereKey($attachment->id)->exists());
        Storage::disk('local')->assertMissing($attachment->path);
        $this->assertSame('Lampiran dihapus.', $report->histories()->latest('id')->firstOrFail()->note);
    }

    public function test_inactive_account_cannot_login_and_registration_cannot_assign_role(): void
    {
        auth()->logout();
        $this->operator->update(['active' => false, 'username' => 'operator', 'password' => 'Password123']);
        $this->post(route('login.store'), ['email' => 'operator', 'password' => 'Password123'])->assertSessionHasErrors('email');
        $this->post(route('register.store'), ['name' => 'New', 'email' => 'new@example.test', 'password' => 'Password123', 'password_confirmation' => 'Password123', 'role' => 'ADMIN'])->assertRedirect();
        $this->assertNull(User::where('email', 'new@example.test')->firstOrFail()->role);
    }

    public function test_filtered_metric_counts_people_once_across_units_and_periods(): void
    {
        $this->configureAssignedIndicator(['form_type' => 'DYNAMIC', 'aggregation' => 'DISTINCT', 'result_field' => 'student', 'fields' => [$this->field('student', 'TEXT'), array_replace($this->field('level', 'CHOICE'), ['options' => "Lokal\nNasional\nInternasional"])]]);
        $report = $this->start();
        $entry = $report->entries()->first();
        $rows = [['key' => 'a', 'label' => 'Prestasi A', 'values' => ['student' => '123', 'level' => 'Nasional']], ['key' => 'b', 'label' => 'Prestasi B', 'values' => ['student' => '123', 'level' => 'Internasional']], ['key' => 'c', 'label' => 'Prestasi C', 'values' => ['student' => '456', 'level' => 'Lokal']]];
        Livewire::test(ReportEditor::class, ['reportId' => $report->id])->set('sections.'.$entry->id.'.rows', $rows)->call('submit')->assertHasNoErrors();
        $this->actingAs($this->admin);
        Livewire::test(ReportEditor::class, ['reportId' => $report->id])->call('transition', 'VERIFIED')->assertHasNoErrors();
        $metric = RecapMetric::create(['indicator_id' => $this->indicator->id, 'indicator_version' => 1, 'code' => 'NATIONAL', 'name' => 'Mahasiswa prestasi nasional', 'result_field' => 'student', 'aggregation' => 'DISTINCT', 'filter_field' => 'level', 'filter_values' => ['Nasional', 'Internasional'], 'combine_units' => true]);
        $filters = ['periods' => [$this->period->id], 'units' => [], 'indicators' => [], 'metrics' => [$metric->id], 'grouping' => 'TOTAL'];
        $result = app(ReportRecap::class)->build($filters);
        $this->assertCount(1, $result['rows']);
        $this->assertSame(1, $result['rows'][0]['value']);
        $this->get(route('metrics.index'))->assertOk();
        Livewire::test(RecapTable::class)->set('periodIds', [$this->period->id])->set('metricIds', [$metric->id])->assertSee('Mahasiswa prestasi nasional');
        $metric->update(['indicator_version' => 2]);
        $result = app(ReportRecap::class)->build($filters);
        $this->assertCount(0, $result['rows']);
        $this->assertCount(1, $result['incompatible']);
    }

    public function test_import_preview_and_save_preserve_source_and_reject_overwrite(): void
    {
        $this->get(route('imports.index'))->assertOk();
        $report = $this->verified($this->period, 9);
        $export = app(ReportRecap::class)->build(['periods' => [$this->period->id], 'units' => [], 'indicators' => [], 'grouping' => 'TOTAL']);
        $path = app(SpreadsheetExport::class)->create($export);
        $reader = app(WorkbookReader::class);
        $this->assertSame('9', $reader->read($path, 'Rekap')['cells']['G6']['value']);
        $next = ReportingPeriod::create(['year' => 2025, 'quarter' => 1, 'deadline' => '2025-04-10', 'status' => 'OPEN']);
        $this->assign($next);
        $file = UploadedFile::fake()->createWithContent('historical.xlsx', file_get_contents($path));
        $component = Livewire::test(ImportTable::class)->set('file', $file)->set('periodId', (string) $next->id)->set('unitId', (string) $this->unit->id)->set('indicatorId', (string) $this->indicator->id)->set('sheetName', 'Rekap')->set('firstRow', 6)->set('lastRow', 6)->set('columns.amount', 'G')->call('preview');
        $this->assertSame([], $component->instance()->getErrorBag()->all());
        $component->set('lastRow', 7)->call('save')->assertHasErrors('file');
        $component->set('lastRow', 6)->call('preview')->call('save')->assertHasNoErrors();
        $imported = UnitReport::where('period_id', $next->id)->firstOrFail();
        $this->assertSame('DRAFT', $imported->status);
        $this->assertEquals(9, $imported->entries()->first()->rows[0]['values']['amount']);
        $component->call('preview')->call('save')->assertHasErrors('file');
        unlink($path);
    }

    public function test_numeric_comparison_and_average_period_totals(): void
    {
        $old = ReportingPeriod::create(['year' => 2025, 'quarter' => 1, 'deadline' => '2025-04-10', 'status' => 'OPEN']);
        $this->assign($old);
        $this->verified($old, 5);
        $this->verified($this->period, 10);
        $result = app(ReportRecap::class)->build(['periods' => [$this->period->id], 'units' => [], 'indicators' => [], 'grouping' => 'TOTAL']);
        $this->assertSame('NAIK', $result['rows'][0]['condition']);
        $this->assertEquals(100, $result['rows'][0]['change_percent']);
        $definition = $this->indicator->definition();
        $definition['aggregation'] = 'AVERAGE';
        $this->assertEquals(20, app(IndicatorCalculator::class)->aggregate($definition, ['2026-1' => [['values' => ['amount' => 10]], ['values' => ['amount' => 20]]], '2026-2' => [['values' => ['amount' => 10]]]]));
    }

    public function test_dynamic_zero_activity_and_invalid_choice_are_validated(): void
    {
        $this->configureAssignedIndicator(['form_type' => 'DYNAMIC']);
        $report = $this->start();
        $entry = $report->entries()->first();
        Livewire::test(ReportEditor::class, ['reportId' => $report->id])->set('sections.'.$entry->id.'.no_activity', true)->call('submit')->assertHasErrors('sections.'.$entry->id.'.explanation')->set('sections.'.$entry->id.'.explanation', 'Tidak ada penerimaan pada periode ini.')->call('submit')->assertHasNoErrors();
        $this->actingAs($this->admin);
        Livewire::test(ReportEditor::class, ['reportId' => $report->id])->call('transition', 'VERIFIED')->assertHasNoErrors();
        $result = app(ReportRecap::class)->build(['periods' => [$this->period->id], 'units' => [], 'indicators' => [], 'grouping' => 'TOTAL']);
        $this->assertSame(0, $result['rows'][0]['value']);
        $definition = $this->indicator->definition();
        $definition['form_type'] = 'SINGLE';
        $definition['fields'] = [array_replace($this->field('amount', 'CHOICE'), ['options' => 'Unggul'])];
        $this->expectException(ValidationException::class);
        app(IndicatorCalculator::class)->normalize($definition, [['key' => 'single', 'label' => 'Test', 'values' => ['amount' => 'Invalid']]], true);
    }
}
