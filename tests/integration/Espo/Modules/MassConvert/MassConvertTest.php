<?php

declare(strict_types=1);

namespace integration\Espo\Modules\MassConvert;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Crm\Entities\Campaign;
use Espo\Modules\Crm\Entities\Contact;
use Espo\Modules\Crm\Entities\Lead;
use Espo\Modules\MassConvert\Api\MassConvert;
use Espo\ORM\Entity;
use Fig\Http\Message\StatusCodeInterface;
use JsonException;
use Psr\Container\NotFoundExceptionInterface;
use tests\integration\Core\BaseTestCase;

final class MassConvertTest extends BaseTestCase
{
    /**
     * @throws BadRequest
     * @throws Conflict
     * @throws Error
     * @throws JsonException
     * @throws Forbidden
     * @throws NotFound
     * @throws NotFoundExceptionInterface
     */
    public function testConvertsLeadIntoMultipleEntities(): void
    {
        $this->setLeadEntityDefs([
            'massConvert' => [Contact::ENTITY_TYPE, Account::ENTITY_TYPE],
            'convertFields' => [
                Contact::ENTITY_TYPE => [
                    'firstName' => 'firstName',
                ],
                Account::ENTITY_TYPE => [
                    'firstName' => 'name',
                ],
            ],
        ]);

        $testLeadFirstName = 'Lead 1';
        $lead = $this->createEntity(Lead::ENTITY_TYPE, ['firstName' => $testLeadFirstName]);

        $this->processMassConvertRequest($lead->getId());

        $contact = $this->findEntityByAttribute(Contact::ENTITY_TYPE, 'firstName', $testLeadFirstName);
        $this->assertSame($testLeadFirstName, $contact->get('firstName'));

        $account = $this->findEntityByAttribute(Account::ENTITY_TYPE, 'name', $testLeadFirstName);
        $this->assertSame($testLeadFirstName, $account->get('name'));
    }

    /**
     * @throws BadRequest
     * @throws NotFoundExceptionInterface
     * @throws Conflict
     * @throws Error
     * @throws JsonException
     * @throws Forbidden
     * @throws NotFound
     */
    public function testConvertsManyToOneField(): void
    {
        $this->setLeadEntityDefs([
            'massConvert' => [Contact::ENTITY_TYPE],
            'convertFields' => [
                Contact::ENTITY_TYPE => [
                    'firstName' => 'firstName',
                ],
            ],
            'convertLinks' => [
                Contact::ENTITY_TYPE => [
                    [
                        'entityType' => Campaign::ENTITY_TYPE,
                        'linkName' => 'campaign',
                        'field' => 'campaignId',
                    ],
                ],
            ],
        ]);

        $testCampaign = $this->createEntity(Campaign::ENTITY_TYPE, ['name' => 'Campaign 1']);

        $testLeadFirstName = 'Lead 1';
        $lead = $this->createEntity(Lead::ENTITY_TYPE, [
            'firstName' => $testLeadFirstName,
            'campaignId' => $testCampaign->getId(),
        ]);

        $this->processMassConvertRequest($lead->getId());

        $contact = $this->findEntityByAttribute(Contact::ENTITY_TYPE, 'firstName', $testLeadFirstName);
        $this->assertSame($testCampaign->getId(), $contact->get('campaignId'));
    }

    private function findEntityByAttribute(string $entityType, string $attribute, $value): ?Entity
    {
        $queryBuilder = $this->getEntityManager()->getQueryBuilder();
        $query = $queryBuilder->select()
            ->from($entityType)
            ->where([$attribute => $value])
            ->build();

        $result = $this->getEntityManager()->getQueryExecutor()->execute($query);

        return $this->getEntityManager()->getEntityById($entityType, $result->fetchObject()->id);
    }

    /**
     * @throws BadRequest
     * @throws Conflict
     * @throws Error
     * @throws Forbidden
     * @throws JsonException
     * @throws NotFound
     */
    private function processMassConvertRequest(string ...$leadIds): void
    {
        $request = $this->createRequest('POST', [], ['Content-Type' => 'application/json'], json_encode([
            'ids' => $leadIds,
        ], JSON_THROW_ON_ERROR));

        $this->assertEquals(
            StatusCodeInterface::STATUS_OK,
            $this->getInjectableFactory()->create(MassConvert::class)->process($request)->getStatusCode()
        );
    }

    /**
     * @throws NotFoundExceptionInterface
     */
    private function setLeadEntityDefs(array $defs): void
    {
        $this->getApplication()->getContainer()->get('metadata')->set('entityDefs', Lead::ENTITY_TYPE, $defs);
    }

    private function createEntity(string $entityType, array $fields): Entity
    {
        $lead = $this->getEntityManager()->getEntity($entityType);

        array_map(static fn(string $key, mixed $value) => $lead->set($key, $value), array_keys($fields), $fields);

        $this->getEntityManager()->saveEntity($lead);

        return $this->getEntityManager()->getEntityById($entityType, $lead->getId());
    }
}
