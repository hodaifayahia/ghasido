<?php

namespace Tests\Unit;

use App\Services\Reports\Exporters\DatasetExport;
use PHPUnit\Framework\TestCase;

/**
 * CSV formula injection is neutralised in exports (security audit
 * 2026-11-08); numbers and ordinary text are unchanged.
 */
class CsvInjectionTest extends TestCase
{
    public function test_formula_like_text_gets_a_leading_apostrophe()
    {
        $this->assertSame(
            ["'=HYPERLINK(\"http://x\")", "'+1+1", "'-2+3", "'@SUM(A1)", 'Hello', -5, 3.5, ''],
            DatasetExport::csvCells(['=HYPERLINK("http://x")', '+1+1', '-2+3', '@SUM(A1)', 'Hello', -5, 3.5, null]),
        );
    }
}
