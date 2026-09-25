<?php

namespace Tests\Unit;

use App\Support\SimpleXlsxWriter;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * The hand-rolled .xlsx writer behind XLSX exports (REP-03, spec 0003 D).
 *
 * No framework needed: the writer is plain PHP and ZipArchive, so this
 * extends PHPUnit's TestCase directly and runs without a database.
 */
class SimpleXlsxWriterTest extends TestCase
{
    /** @var list<string> */
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_it_writes_a_zip_whose_sheet_holds_the_cell_values()
    {
        $path = $this->write(
            ['Name', 'Score', 'Active'],
            [
                ['Amina Saadi', 87.5, true],
                ['Karim & <Ben Ali>', 3, false],
                ['Sofia "Merad"', null, 'x'],
            ],
        );

        $this->assertFileExists($path);
        $this->assertStringEndsWith('.xlsx', $path);
        $this->assertSame('PK', file_get_contents($path, false, null, 0, 2));

        $sheet = $this->part($path, 'xl/worksheets/sheet1.xml');

        // Header row, bold (style 1), inline strings.
        $this->assertStringContainsString('<c r="A1" s="1" t="inlineStr"><is><t xml:space="preserve">Name</t></is></c>', $sheet);
        $this->assertStringContainsString('<t xml:space="preserve">Score</t>', $sheet);

        // Values land in the right cells with the right types.
        $this->assertStringContainsString('<c r="A2" t="inlineStr"><is><t xml:space="preserve">Amina Saadi</t></is></c>', $sheet);
        $this->assertStringContainsString('<c r="B2"><v>87.5</v></c>', $sheet);
        $this->assertStringContainsString('<c r="C2" t="b"><v>1</v></c>', $sheet);
        $this->assertStringContainsString('<c r="B3"><v>3</v></c>', $sheet);
        $this->assertStringContainsString('<c r="C3" t="b"><v>0</v></c>', $sheet);

        // XML-escaped, never raw.
        $this->assertStringContainsString('Karim &amp; &lt;Ben Ali&gt;', $sheet);
        $this->assertStringContainsString('Sofia &quot;Merad&quot;', $sheet);
        $this->assertStringNotContainsString('<Ben Ali>', $sheet);

        // A null is a missing cell, not an empty string cell.
        $this->assertStringNotContainsString('r="B4"', $sheet);
        $this->assertStringContainsString('<c r="C4" t="inlineStr">', $sheet);
    }

    public function test_the_package_carries_every_part_a_reader_needs()
    {
        $path = $this->write(['A'], [[1]]);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));

        foreach ([
            '[Content_Types].xml',
            '_rels/.rels',
            'xl/workbook.xml',
            'xl/_rels/workbook.xml.rels',
            'xl/worksheets/sheet1.xml',
            'xl/styles.xml',
        ] as $name) {
            $this->assertNotFalse($zip->locateName($name), "Missing part {$name}");
        }

        $workbook = (string) $zip->getFromName('xl/workbook.xml');
        $this->assertStringContainsString('<sheet name="Export" sheetId="1" r:id="rId1"/>', $workbook);

        $zip->close();
    }

    public function test_numeric_strings_stay_strings_and_numbers_stay_numbers()
    {
        $sheet = $this->part(
            $this->write(['Code', 'Count', 'Ratio'], [['007', 7, 0.25]]),
            'xl/worksheets/sheet1.xml',
        );

        // A participant code such as 007 must not turn into 7.
        $this->assertStringContainsString('<c r="A2" t="inlineStr"><is><t xml:space="preserve">007</t></is></c>', $sheet);
        $this->assertStringContainsString('<c r="B2"><v>7</v></c>', $sheet);
        $this->assertStringContainsString('<c r="C2"><v>0.25</v></c>', $sheet);
    }

    public function test_columns_run_past_z()
    {
        $this->assertSame('A', SimpleXlsxWriter::columnLetter(0));
        $this->assertSame('Z', SimpleXlsxWriter::columnLetter(25));
        $this->assertSame('AA', SimpleXlsxWriter::columnLetter(26));
        $this->assertSame('AB', SimpleXlsxWriter::columnLetter(27));
        $this->assertSame('BA', SimpleXlsxWriter::columnLetter(52));

        $headers = array_map(fn (int $i): string => "H{$i}", range(1, 28));
        $sheet = $this->part($this->write($headers, []), 'xl/worksheets/sheet1.xml');

        $this->assertStringContainsString('<c r="AB1"', $sheet);
    }

    public function test_rows_may_come_from_a_generator_and_associative_arrays()
    {
        $rows = (function () {
            yield ['name' => 'Row one', 'n' => 1];
            yield ['name' => 'Row two', 'n' => 2];
        })();

        $sheet = $this->part($this->write(['Name', 'N'], $rows), 'xl/worksheets/sheet1.xml');

        $this->assertStringContainsString('<row r="2">', $sheet);
        $this->assertStringContainsString('<row r="3">', $sheet);
        $this->assertStringContainsString('Row two', $sheet);
        $this->assertStringContainsString('<c r="B3"><v>2</v></c>', $sheet);
    }

    public function test_control_characters_are_dropped_and_the_sheet_name_is_sanitised()
    {
        $path = SimpleXlsxWriter::fromRows(['A'], [["bad\x01value"]], 'Answers: [long] / name that goes past thirty one characters');
        $this->paths[] = $path;

        $this->assertStringContainsString('badvalue', $this->part($path, 'xl/worksheets/sheet1.xml'));

        $workbook = $this->part($path, 'xl/workbook.xml');
        $this->assertStringNotContainsString('[', $workbook);
        $this->assertStringNotContainsString('/', substr($workbook, (int) strpos($workbook, '<sheet name="'), 45));
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  list<string>  $headers
     * @param  iterable<int, array<int|string, mixed>>  $rows
     */
    private function write(array $headers, iterable $rows): string
    {
        $path = SimpleXlsxWriter::fromRows($headers, $rows);
        $this->paths[] = $path;

        return $path;
    }

    private function part(string $path, string $name): string
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path), "Could not open {$path}");

        $content = $zip->getFromName($name);
        $zip->close();

        $this->assertNotFalse($content, "Missing part {$name}");

        return (string) $content;
    }
}
