<?php

declare(strict_types=1);

namespace Espo\Modules\MassConvert\Api;

use Espo\Core\Api\Request;
use Espo\Modules\MassConvert\Lead\MassConvertService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Espo\Modules\MassConvert\Api\MassConvert
 */
final class MassConvertTest extends TestCase
{
    public function testDelegatesConversionToServiceClass(): void
    {
        $testIds = ['1', '2', '3'];

        $stubRequest = $this->createMock(Request::class);
        $stubRequest->method('getParsedBody')->willReturn((object) ['ids' => $testIds]);

        $mockService = $this->createMock(MassConvertService::class);
        $mockService->expects($this->once())->method('convert')->with(...$testIds);

        $action = new MassConvert($mockService);
        $response = $action->process($stubRequest);

        $this->assertSame('true', (string) $response->getBody());
    }
}
