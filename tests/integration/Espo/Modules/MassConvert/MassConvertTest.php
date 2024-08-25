<?php

declare(strict_types=1);

namespace integration\Espo\Modules\MassConvert;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Crm\Entities\Contact;
use Espo\Modules\Crm\Entities\Lead;
use Espo\Modules\MassConvert\Api\MassConvert;
use Espo\ORM\Entity;
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
        $testLeadFirstName = 'Lead 1';

        $lead = $this->getEntityManager()->getEntity(Lead::ENTITY_TYPE);
        $lead->set('firstName', $testLeadFirstName);
        $this->getEntityManager()->saveEntity($lead);

        $metadata = $this->getApplication()->getContainer()->get('metadata');
        $metadata->set('entityDefs', Lead::ENTITY_TYPE, [
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

        $this->auth();

        $lead = $this->getEntityManager()->getEntityById(Lead::ENTITY_TYPE, $lead->getId());

        $this->assertEquals($testLeadFirstName, $lead->get('firstName'));

        $request = $this->createRequest('POST', [], ['Content-Type' => 'application/json'], json_encode([
            'ids' => [$lead->getId()],
        ], JSON_THROW_ON_ERROR));

        $action = $this->getInjectableFactory()->create(MassConvert::class);
        $response = $action->process($request);

        $this->assertEquals(200, $response->getStatusCode());

        $contact = $this->findEntityByAttribute(Contact::ENTITY_TYPE, 'firstName', $testLeadFirstName);
        $this->assertSame($testLeadFirstName, $contact->get('firstName'));

        $account = $this->findEntityByAttribute(Account::ENTITY_TYPE, 'name', $testLeadFirstName);
        $this->assertSame($testLeadFirstName, $account->get('name'));
    }

    private function findEntityByAttribute(string $entityType, string $attribute, $value): ?Entity
    {
        $queryBuilder = $this->getEntityManager()->getQueryBuilder();
        $query = $queryBuilder->select()
            ->from($entityType)
            ->where([$attribute => $value])
            ->build();

        $result = $this->getEntityManager()->getQueryExecutor()->execute($query);

        return $this->getEntityManager()->getEntity($entityType, $result->fetchObject()->id);
    }
}
