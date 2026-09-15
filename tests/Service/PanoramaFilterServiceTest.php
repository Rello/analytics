<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Tests\Service;

use OCA\Analytics\Service\PanoramaFilterService as Filters;
use PHPUnit\Framework\TestCase;

class PanoramaFilterServiceTest extends TestCase {
    private function variable(string $dimension = 'dimension1'): array {
        return ['id' => 'date', 'label' => 'Date', 'enabled' => true, 'operator' => 'EQ', 'defaultValue' => null,
            'mappings' => [['reportId' => 1, 'dimension' => $dimension, 'dimensionLabel' => 'Date']]];
    }

    public function testConfigurationRoundTripAndEmptyLegacyConfiguration(): void {
        $pages = [['reports' => [['type' => 0, 'value' => 1]]]];
        $this->assertSame([], Filters::normalize([], $pages));
        $this->assertSame([$this->variable()], Filters::normalize(json_encode([$this->variable()]), $pages));
    }

    public function testUnknownReportMappingIsRejected(): void {
        $this->expectException(\InvalidArgumentException::class);
        Filters::normalize([$this->variable()], [['reports' => [['type' => 1, 'value' => 1]]]]);
    }

    public function testOverlappingEnabledVariablesAreRejected(): void {
        $second = $this->variable();
        $second['id'] = 'second';
        $this->expectException(\InvalidArgumentException::class);
        Filters::normalize([$this->variable(), $second], [['reports' => [['type' => 0, 'value' => 1]]]]);
    }

    public function testDisabledVariableCanRetainMapping(): void {
        $second = $this->variable();
        $second['id'] = 'second';
        $second['enabled'] = false;
        $this->assertCount(2, Filters::normalize([$this->variable(), $second], [['reports' => [['type' => 0, 'value' => 1]]]]));
    }

    public function testOverrideReplacesAllConditionsForDimensionAndPreservesOptions(): void {
        $options = ['filter' => [
            ['dimension' => 'dimension1', 'option' => 'GT', 'value' => '2020'],
            ['dimension' => 'dimension1', 'option' => 'LT', 'value' => '2022'],
            ['dimension' => 'dimension2', 'option' => 'EQ', 'value' => 'DE'],
        ], 'aggregate' => false, 'sort' => ['order' => 'ASC']];
        $metadata = ['id' => 1, 'filteroptions' => json_encode($options), 'chartoptions' => 'original'];
        $result = Filters::apply($metadata, [$this->variable()], ['date' => '%last2months%']);
        $merged = json_decode($result['filteroptions'], true);
        $this->assertSame([$options['filter'][2], ['dimension' => 'dimension1', 'option' => 'EQ', 'value' => '%last2months%']], $merged['filter']);
        $this->assertFalse($merged['aggregate']);
        $this->assertSame($options['sort'], $merged['sort']);
        $this->assertSame('original', $result['chartoptions']);
        $this->assertSame($options, json_decode($metadata['filteroptions'], true));
    }

    public function testLegacyAssociativeFiltersAndExternalDimensionZero(): void {
        $metadata = ['id' => 1, 'filteroptions' => json_encode(['filter' => [
            '0' => ['option' => 'EQ', 'value' => 'old'],
            '1' => ['option' => 'EQ', 'value' => 'keep'],
        ]])];
        $result = Filters::apply($metadata, [$this->variable('0')], ['date' => 'new']);
        $conditions = json_decode($result['filteroptions'], true)['filter'];
        $this->assertCount(2, $conditions);
        $this->assertSame('keep', $conditions[0]['value']);
        $this->assertSame('0', $conditions[1]['dimension']);
        Filters::validateDimensions($result['panoramaMappings'], [0 => 'Date', 1 => 'Country']);
    }

    public function testResetEmptyAndUnrelatedValuesKeepOriginalReport(): void {
        $metadata = ['id' => 1, 'filteroptions' => null];
        foreach ([[], ['date' => ''], ['date' => null]] as $values) {
            $this->assertSame($metadata, Filters::apply($metadata, [$this->variable()], $values));
        }
        $metadata['id'] = 2;
        $this->assertSame($metadata, Filters::apply($metadata, [$this->variable()], ['date' => '2026']));
    }

    public function testDisabledVariableValuesAreRejected(): void {
        $variable = $this->variable();
        $variable['enabled'] = false;
        $this->expectException(\InvalidArgumentException::class);
        Filters::apply(['id' => 1], [$variable], ['date' => '2026']);
    }

    public function testUnknownVariablesAreRejectedEvenIfEmpty(): void {
        $this->expectException(\InvalidArgumentException::class);
        Filters::apply(['id' => 1], [], ['unknown' => '']);
    }

    public function testViewerCanOverrideDefaultOperatorAndUseMultipleConditions(): void {
        $selections = [['option' => 'GT', 'value' => '2024'], ['option' => 'LT', 'value' => '2027']];
        $result = Filters::apply(['id' => 1, 'filteroptions' => null], [$this->variable()], ['date' => $selections]);
        $this->assertSame([
            ['dimension' => 'dimension1', 'option' => 'GT', 'value' => '2024'],
            ['dimension' => 'dimension1', 'option' => 'LT', 'value' => '2027'],
        ], json_decode($result['filteroptions'], true)['filter']);
        $this->assertSame('EQ', $this->variable()['operator']);
    }

    public function testUnsupportedViewerOperatorIsRejected(): void {
        $this->expectException(\InvalidArgumentException::class);
        Filters::apply(['id' => 1], [$this->variable()], ['date' => [['option' => 'SQL', 'value' => 'x']]]);
    }

    public function testRenamedDimensionIsRejected(): void {
        $this->expectException(\InvalidArgumentException::class);
        Filters::validateDimensions($this->variable()['mappings'], ['dimension1' => 'Country']);
    }

    public function testMissingDimensionIsRejected(): void {
        $this->expectException(\InvalidArgumentException::class);
        Filters::validateDimensions($this->variable()['mappings'], []);
    }
}
