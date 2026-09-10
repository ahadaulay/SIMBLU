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
use App\Livewire\UnitTable;
use App\Livewire\UserTable;
use App\Models\Indicator;
use App\Models\IndicatorAssignment;
use App\Models\IndicatorCategory;
use App\Models\RecapMetric;
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
        foreach (['units.index' => UnitTable::class, 'users.index' => UserTable::class, 'periods.index' => PeriodTable::class, 'categories.index' => CategoryTable::class, 'indicators.index' => IndicatorTable::class, 'assignments.index' => AssignmentTable::class] as $route => $component) {
            $this->get(route($route))->assertOk()->assertSeeLivewire($component);
        }
        $this->get(route('reporting.dashboard'))->assertOk();
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
        Livewire::test(ReportEditor::class, ['reportId' => $report->id])->call('save')->assertHasNoErrors()->call('submit')->assertHasErrors('sections.'.$entry->id.'.rows.0.values.amount')->set('sections.'.$entry->id.'.rows.0.values.amount', 0)->call('submit')->assertHasNoErrors();
        $this->assertSame('SUBMITTED', $report->fresh()->status);
        Livewire::test(ReportEditor::class, ['reportId' => $report->id])->call('save')->assertForbidden();
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
        Livewire::test(ReportEditor::class, ['reportId' => $report->id])->call('save')->assertForbidden();
    }

    public function test_assignment_cannot_change_after_report_is_created(): void
    {
        $this->start();
        $this->actingAs($this->admin);
        $assignment = IndicatorAssignment::firstOrFail();

        Livewire::test(AssignmentTable::class)
            ->call('openEditModal', $assignment->id)
            ->set('form.required', false)
            ->call('save')
            ->assertHasErrors('form.period_id');

        Livewire::test(AssignmentTable::class)
            ->call('openDetail', $assignment->period_id, $assignment->unit_id)
            ->call('deleteIndicator', $assignment->id)
            ->assertHasErrors('form.indicator_id');

        $this->assertModelExists($assignment);
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
        Livewire::test(RecapTable::class)->set('periodIds', [$this->period->id, $second->id])->set('grouping', 'TOTAL')->assertSee('35,0000');
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

    public function test_private_attachment_upload_and_required_evidence(): void
    {
        Storage::fake('local');
        $this->indicator->update(['requires_attachment' => true]);
        $report = $this->start();
        $entry = $report->entries()->first();
        Livewire::test(ReportEditor::class, ['reportId' => $report->id])->set('sections.'.$entry->id.'.rows.0.values.amount', 2)->call('submit')->assertHasErrors('sections.'.$entry->id.'.attachment')->set('uploads.'.$entry->id, UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'))->call('upload', $entry->id)->assertHasNoErrors()->call('submit')->assertHasNoErrors();
        $attachment = $report->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->get(route('reports.attachment', $attachment))->assertOk();
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
        $this->indicator->update(['form_type' => 'DYNAMIC', 'aggregation' => 'DISTINCT', 'result_field' => 'student', 'fields' => [$this->field('student', 'TEXT'), array_replace($this->field('level', 'CHOICE'), ['options' => "Lokal\nNasional\nInternasional"])]]);
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
        $this->indicator->update(['form_type' => 'DYNAMIC']);
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
