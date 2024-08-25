<?php

declare(strict_types=1);

/**
 * @noinspection PhpIllegalPsrClassPathInspection
 */
namespace Espo\Modules\MassConvert\Lead;

use Espo\Core\Acl;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\Crm\Entities\Campaign;
use Espo\ORM\Entity;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Metadata;
use Espo\Modules\Crm\Entities\Lead;
use Espo\ORM\Repository\RDBRelation;
use Espo\ORM\Repository\RDBRepository;
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

    /**
     * @throws Forbidden
     */
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

    /**
     * @throws NotFound
     */
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

    /**
     * @throws Forbidden
     */
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

    /**
     * @throws NotFound
     */
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

    /**
     * @throws Forbidden
     * @throws NotFound
     */
    public function testConvertsLeadIntoEntity(): void
    {
        $testLeadId = '1';
        $testEntityType = 'foo';
        $testEntity = $this->createStub(Entity::class);
        $testEntity->method('getEntityType')->willReturn($testEntityType);
        $testLead = $this->createStub(Lead::class);

        $this->stubAcl->method('checkEntityCreate')->with($testEntity)->willReturn(true);
        $this->stubAcl->method('checkEntityEdit')->with($testLead)->willReturn(true);

        $this->stubMetadata->method('get')->willReturnMap([
            ['entityDefs.Lead.massConvert', [], [$testEntityType]],
            ["entityDefs.Lead.convertFields.$testEntityType", [], []],
            ["entityDefs.Lead.convertLinks.$testEntityType", [], []],
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

    /**
     * @throws Forbidden
     * @throws NotFound
     */
    public function testLinksLeadConnections(): void
    {
        $testLeadId = '1';
        $testEntityType = 'foo';
        $testRelatedCampaignId = '2';
        $testEntity = $this->createStub(Entity::class);
        $testEntity->method('getEntityType')->willReturn($testEntityType);
        $testLead = $this->createStub(Lead::class);
        $testLead->method('get')->with('campaignId')->willReturn($testRelatedCampaignId);

        $this->stubAcl->method('checkEntityCreate')->with($testEntity)->willReturn(true);
        $this->stubAcl->method('checkEntityEdit')->with($testLead)->willReturn(true);

        $this->stubMetadata->method('get')->willReturnMap([
            ['entityDefs.Lead.massConvert', [], [$testEntityType]],
            ["entityDefs.Lead.convertFields.$testEntityType", [], []],
            [
                "entityDefs.Lead.convertLinks.$testEntityType",
                [],
                [
                    $testEntityType => [
                        'entityType' => Campaign::ENTITY_TYPE,
                        'linkName' => 'campaign',
                        'field' => 'campaignId',
                    ],
                ],
            ],
        ]);

        $relatedCampaign = $this->createStub(Campaign::class);
        $relatedCampaign->method('hasId')->willReturn(true);

        $mockRelation = $this->createMock(RDBRelation::class);
        $mockRelation->expects($this->once())->method('relate')->with($relatedCampaign, null, []);

        $stubRepository = $this->createMock(RDBRepository::class);
        $stubRepository->method('getRelation')->with($testEntity, 'campaign')->willReturn($mockRelation);

        $this->mockEntityManager->method('getRDBRepository')->with($testEntityType)->willReturn($stubRepository);

        $this->mockEntityManager->method('getEntity')->willReturnMap([
            [Lead::ENTITY_TYPE, $testLeadId, $testLead],
            [$testEntityType, null, $testEntity],
            [Campaign::ENTITY_TYPE, $testRelatedCampaignId, $relatedCampaign],
        ]);

        $this->mockEntityManager->expects($this->exactly(2))->method('saveEntity')->willReturnOnConsecutiveCalls(
            $this->returnCallback(fn(Entity $entity) => $this->assertSame($testEntity, $entity)),
            $this->returnCallback(fn($entity) => $this->assertSame($testLead, $entity)),
        );

        $this->service->convert($testLeadId);
    }
}
