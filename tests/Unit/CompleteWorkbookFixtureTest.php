<?php

namespace Tests\Unit;

use JsonException;
use PHPUnit\Framework\TestCase;

class CompleteWorkbookFixtureTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function test_fixture_preserves_the_complete_reference_workbook(): void
    {
        $dataDirectory = dirname(__DIR__, 2).'/database/seeders/data';
        $workbookPath = $dataDirectory.'/data-kinerja-layanan-unit-2025-2026.xlsx';
        $fixturePath = $dataDirectory.'/data-kinerja-layanan-unit-2025-2026.json.gz';

        $compressed = file_get_contents($fixturePath);

        $this->assertNotFalse($compressed);

        $json = gzdecode($compressed);

        $this->assertNotFalse($json);

        $fixture = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(
            '9d7987c49f5d911ea632cd4955cf49ce97c1679837bbeb0fd1b844e64b64944a',
            hash_file('sha256', $workbookPath),
        );
        $this->assertSame(19, $fixture['sheet_count']);
        $this->assertSame(17542, $fixture['coordinate_count']);
        $this->assertSame(3753, $fixture['nonempty_count']);
        $this->assertSame(4, $fixture['formula_count']);
        $this->assertSame(820, $fixture['merged_count']);
        $this->assertSame(220, $fixture['style_count']);
        $this->assertCount(19, $fixture['sheets']);
        $this->assertSame('REKAP DATA ', $fixture['sheets'][0]['name']);

        $coordinateCount = 0;
        $mergedCount = 0;

        foreach ($fixture['sheets'] as $sheet) {
            $expectedCoordinates = $sheet['max_row'] * $sheet['max_column'];

            $this->assertCount($expectedCoordinates, $sheet['cells']);

            $coordinateCount += count($sheet['cells']);
            $mergedCount += count($sheet['merged_ranges']);
        }

        $this->assertSame($fixture['coordinate_count'], $coordinateCount);
        $this->assertSame($fixture['merged_count'], $mergedCount);
    }
}
