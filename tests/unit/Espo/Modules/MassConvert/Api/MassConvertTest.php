<?php

declare(strict_types=1);

namespace Espo\Modules\MassConvert\Api;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Modules\MassConvert\Lead\MassConvertService;
use Espo\ORM\EntityManager;
use JsonException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Espo\Modules\MassConvert\Api\MassConvert
 */
final class MassConvertTest extends TestCase
{
    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws Conflict
     * @throws Error
     * @throws NotFound
     * @throws JsonException
     */
    public function testDelegatesConversionToServiceClass(): void
    {
        $testIds = ['1', '2', '3'];

        $stubRequest = $this->createMock(Request::class);
        $stubRequest->method('getParsedBody')->willReturn((object) ['ids' => $testIds]);

        $mockService = $this->createMock(MassConvertService::class);
        $mockService->expects($this->once())->method('convert')->with(...$testIds);

        $action = new MassConvert(
            $mockService,
            $this->createStub(SelectBuilderFactory::class),
            $this->createStub(EntityManager::class)
        );

        $response = $action->process($stubRequest);

        $this->assertSame('true', (string) $response->getBody());
    }
}
