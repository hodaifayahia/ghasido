<?php

namespace Tests\Feature\Admin\Reports;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Reports\ReportExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exports run with the page's filters, in three formats, leave an audit
 * row, respect the tenant boundary and strip what a manager may not see
 * (REP-03, REP-05..08, ROLE-02, ROLE-04, SEC-06; spec 0003 Part D).
 */
class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_export_streams_the_long_format_answers_and_writes_an_audit_row()
    {
        $world = ReportsWorld::build();
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)
            ->get(route('reports.export', ['dataset' => 'answers', 'format' => 'csv', 'hotel' => $world->hotelA->id]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        // One row per answer with the research columns (REP-06, DATA-01, DATA-11).
        $this->assertStringContainsString('"Answer ID","Participant code",Employee,Username,Email,Hotel,Department,Context', $csv);
        $this->assertStringContainsString('"Question version"', $csv);
        $this->assertStringContainsString('"Raw answer (JSON)"', $csv);
        $this->assertStringContainsString('"AI task_completion score"', $csv);
        $this->assertStringContainsString('"Time taken (ms)"', $csv);
        $this->assertStringContainsString('P-ALICE1,"Alice Amrani"', $csv);
        $this->assertStringContainsString('"Reception Pre-test"', $csv);
        $this->assertStringContainsString('"{""i1"":""A""}"', $csv);
        $this->assertStringContainsString('12345', $csv);
        $this->assertStringNotContainsString('Carol', $csv);

        $audit = AuditLog::query()->where('action', ReportExporter::AUDIT_ACTION)->sole();
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame('answers', $audit->changes['dataset'] ?? null);
        $this->assertSame('csv', $audit->changes['format'] ?? null);
        $this->assertSame(1, $audit->changes['row_count'] ?? null);
        $this->assertSame((string) $world->hotelA->id, (string) ($audit->changes['filters']['hotel'] ?? ''));
    }

    public function test_xlsx_export_downloads_a_workbook()
    {
        ReportsWorld::build();

        $response = $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('reports.export', ['dataset' => 'employees', 'format' => 'xlsx']))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));

        $file = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($file));
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertStringContainsString('Participant code', $sheet);
        $this->assertStringContainsString('Alice Amrani', $sheet);
        $this->assertStringContainsString('Carol Cherif', $sheet);

        $this->assertSame(3, AuditLog::query()->where('action', ReportExporter::AUDIT_ACTION)->sole()->changes['row_count'] ?? null);
    }

    public function test_pdf_export_renders_the_print_page()
    {
        ReportsWorld::build();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('reports.export', ['dataset' => 'comparison', 'format' => 'pdf']))
            ->assertOk()
            ->assertViewIs('reports.print')
            ->assertSee('Pre/Post Comparison')
            ->assertSee('Alice Amrani')
            ->assertSee('Total Employees')
            ->assertSee('window.print', false);
    }

    public function test_every_dataset_exports_in_every_format()
    {
        ReportsWorld::build();
        $admin = User::factory()->superAdmin()->create();

        foreach (['employees', 'answers', 'roleplay', 'lessons', 'comparison', 'anonymised'] as $dataset) {
            foreach (['csv', 'xlsx', 'pdf'] as $format) {
                $this->actingAs($admin)
                    ->get(route('reports.export', ['dataset' => $dataset, 'format' => $format]))
                    ->assertOk();
            }
        }

        $this->assertSame(18, AuditLog::query()->where('action', ReportExporter::AUDIT_ACTION)->count());
    }

    public function test_the_anonymised_export_carries_no_identity_and_needs_its_own_permission()
    {
        $world = ReportsWorld::build();

        $csv = $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('reports.export', ['dataset' => 'anonymised', 'format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF\"Answer ID\",\"Participant code\",Hotel,Department", $csv);
        $this->assertStringContainsString('P-ALICE1', $csv);
        $this->assertStringNotContainsString('Alice Amrani', $csv);
        $this->assertStringNotContainsString('alice.amrani', $csv);
        $this->assertStringNotContainsString('Username', $csv);
        $this->assertStringNotContainsString('Email', $csv);

        // A manager holds no reports export capability at all (client
        // decision), so the anonymised dataset is refused like every other.
        $this->actingAs($world->managerOfA())
            ->get(route('reports.export', ['dataset' => 'anonymised', 'format' => 'csv']))
            ->assertForbidden();

        $this->assertSame(1, AuditLog::query()->where('action', ReportExporter::AUDIT_ACTION)->count());
    }

    public function test_a_manager_can_no_longer_export_but_the_super_admin_still_can()
    {
        // Client decision: Reports & Export is removed from the manager role,
        // so every export is a 403 for them (narrows REP-08). The Super
        // Admin's sheet still carries the full transcript (ROLE-04).
        $world = ReportsWorld::build();

        foreach (['roleplay', 'employees', 'answers'] as $dataset) {
            $this->actingAs($world->managerOfA())
                ->get(route('reports.export', ['dataset' => $dataset, 'format' => 'csv']))
                ->assertForbidden();
        }

        $full = $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('reports.export', ['dataset' => 'roleplay', 'format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Transcript (JSON)', $full);
        $this->assertStringContainsString('Hello! I have a reservation for tonight.', $full);

        $this->assertSame(1, AuditLog::query()->where('action', ReportExporter::AUDIT_ACTION)->count());
    }

    public function test_a_manager_cannot_export_another_hotel_and_an_employee_cannot_export_at_all()
    {
        $world = ReportsWorld::build();
        $manager = $world->managerOfA();

        $this->actingAs($manager)
            ->get(route('reports.export', ['dataset' => 'employees', 'format' => 'csv', 'hotel' => $world->hotelB->id]))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('reports.export', ['dataset' => 'answers', 'format' => 'csv', 'employee' => $world->carol->id]))
            ->assertForbidden();

        $this->actingAs($world->alice)
            ->get(route('reports.export', ['dataset' => 'employees', 'format' => 'csv']))
            ->assertForbidden();

        $this->assertSame(0, AuditLog::query()->where('action', ReportExporter::AUDIT_ACTION)->count());
    }

    public function test_an_unknown_dataset_or_format_is_rejected()
    {
        ReportsWorld::build();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->from(route('reports-export'))
            ->get(route('reports.export', ['dataset' => 'everything', 'format' => 'csv']))
            ->assertRedirect(route('reports-export'))
            ->assertSessionHasErrors('dataset');

        $this->actingAs($admin)
            ->from(route('reports-export'))
            ->get(route('reports.export', ['dataset' => 'employees', 'format' => 'docx']))
            ->assertSessionHasErrors('format');

        $this->assertSame(0, AuditLog::count());
    }
}
