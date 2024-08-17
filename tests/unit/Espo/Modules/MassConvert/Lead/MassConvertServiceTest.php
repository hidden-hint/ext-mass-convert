<?php

declare(strict_types=1);

namespace Espo\Modules\MassConvert\Lead;

use Espo\Core\Acl;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\ORM\Entity;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Metadata;
use Espo\Modules\Crm\Entities\Lead;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Espo\Modules\MassConvert\Lead\MassConvertService
 */
final class MassConvertServiceTest extends TestCase
{
    private MassConvertService $service;
    private EntityManager $mockEntityManager;
    private Metadata $stubMetadata;
    private Acl $stubAcl;

    protected function setUp(): void
    {
        $this->mockEntityManager = $this->createMock(EntityManager::class);
        $this->stubMetadata = $this->createMock(Metadata::class);
        $this->stubAcl = $this->createMock(Acl::class);

        $this->service = new MassConvertService($this->mockEntityManager, $this->stubMetadata, $this->stubAcl);
    }

    public function testThrowsExceptionIfLeadNotFound(): void
    {
        $testLeadId = '1';
        $testEntityType = 'foo';

        $this->mockEntityManager->method('getEntity')->with(Lead::ENTITY_TYPE, $testLeadId)->willReturn(null);

        $this->stubMetadata->method('get')->with('entityDefs.Lead.massConvert', [])
            ->willReturn([$testEntityType]);

        $this->expectException(NotFound::class);
        $this->expectExceptionMessage("Lead not found: $testLeadId");

        $this->service->convert($testLeadId);
    }

    public function testThrowsExceptionIfUserHasNoPermissionsToEditLead(): void
    {
        $testLeadId = '1';
        $testEntityType = 'foo';
        $dummyLead = $this->createStub(Lead::class);

        $this->stubAcl->method('checkEntityEdit')->with($dummyLead)->willReturn(false);

        $this->stubMetadata->method('get')->with('entityDefs.Lead.massConvert', [])
            ->willReturn([$testEntityType]);

        $this->mockEntityManager->method('getEntity')->with(Lead::ENTITY_TYPE, $testLeadId)->willReturn($dummyLead);

        $this->expectException(Forbidden::class);
        $this->expectExceptionMessage('No edit access.');

        $this->service->convert($testLeadId);
    }

    public function testThrowsExceptionIfDestinationEntityDoesNotExist(): void
    {
        $testLeadId = '1';
        $testEntityType = 'foo';
        $dummyLead = $this->createStub(Lead::class);

        $this->stubAcl->method('checkEntityEdit')->with($dummyLead)->willReturn(true);

        $this->stubMetadata->method('get')->with('entityDefs.Lead.massConvert', [])
            ->willReturn([$testEntityType]);

        $this->mockEntityManager->method('getEntity')->willReturnMap([
            [Lead::ENTITY_TYPE, $testLeadId, $dummyLead],
            [$testEntityType, null, null],
        ]);

        $this->expectException(NotFound::class);
        $this->expectExceptionMessage("Entity not found: $testEntityType");

        $this->service->convert($testLeadId);
    }

    public function testThrowsExceptionIfUserHasNoPermissionsToCreateEntity(): void
    {
        $testLeadId = '1';
        $testEntityType = 'foo';
        $testEntity = $this->createStub(Entity::class);
        $dummyLead = $this->createStub(Lead::class);

        $this->stubAcl->method('checkEntityCreate')->with($testEntity)->willReturn(false);
        $this->stubAcl->method('checkEntityEdit')->with($dummyLead)->willReturn(true);

        $this->stubMetadata->method('get')->with('entityDefs.Lead.massConvert', [])
            ->willReturn([$testEntityType]);

        $this->mockEntityManager->method('getEntity')->willReturnMap([
            [Lead::ENTITY_TYPE, $testLeadId, $dummyLead],
            [$testEntityType, null, $testEntity],
        ]);

        $this->expectException(Forbidden::class);
        $this->expectExceptionMessage("No create access for '$testEntityType'.");

        $this->service->convert($testLeadId);
    }

    public function testConvertsLeadIntoEntity(): void
    {
        $testLeadId = '1';
        $testEntityType = 'foo';
        $testEntity = $this->createStub(Entity::class);
        $testLead = $this->createStub(Lead::class);

        $this->stubAcl->method('checkEntityCreate')->with($testEntity)->willReturn(true);
        $this->stubAcl->method('checkEntityEdit')->with($testLead)->willReturn(true);

        $this->stubMetadata->method('get')->willReturnMap([
            ['entityDefs.Lead.massConvert', [], [$testEntityType]],
            ["entityDefs.Lead.convertFields.$testEntityType", [], []],
        ]);

        $this->mockEntityManager->method('getEntity')->willReturnMap([
            [Lead::ENTITY_TYPE, $testLeadId, $testLead],
            [$testEntityType, null, $testEntity],
        ]);

        $this->mockEntityManager->expects($this->exactly(2))->method('saveEntity')->willReturnOnConsecutiveCalls(
            $this->returnCallback(fn(Entity $entity) => $this->assertSame($testEntity, $entity)),
            $this->returnCallback(fn($entity) => $this->assertSame($testLead, $entity)),
        );

        $this->service->convert($testLeadId);
    }
}
