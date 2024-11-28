<?php

declare(strict_types=1);

namespace Espo\Modules\MassConvert\Lead;

use Espo\Core\Acl;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Metadata;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Crm\Entities\Contact;
use Espo\Modules\Crm\Entities\Lead;
use Espo\ORM\Entity;

class MassConvertService
{
    public function __construct(private EntityManager $entityManager, private Metadata $metadata, private Acl $acl) {}

    /**
     * @throws Forbidden
     * @throws NotFound
     */
    public function convert(string ...$ids): void
    {
        array_map(fn(string $entityType) => array_map(
            fn(string $leadId) => $this->convertTo($entityType, $leadId),
            $ids
        ), $this->metadata->get('entityDefs.Lead.massConvert', []));
    }

    /**
     * @throws Forbidden
     * @throws NotFound
     */
    private function convertTo(string $entityType, string $leadId): void
    {
        /** @var Lead $lead */
        $lead = $this->entityManager->getEntity(Lead::ENTITY_TYPE, $leadId);

        if (!$lead) {
            throw new NotFound("Lead not found: $leadId");
        }

        if (!$this->acl->checkEntityEdit($lead)) {
            throw new Forbidden('No edit access.');
        }

        $newEntity = $this->createEntity($entityType, $lead);

        $this->updateLead($lead, $newEntity);
    }

    /**
     * @throws Forbidden
     * @throws NotFound
     */
    private function createEntity(string $entityType, Lead $lead): Entity
    {
        $entity = $this->entityManager->getEntity($entityType);

        if (null === $entity) {
            throw new NotFound("Entity not found: $entityType");
        }

        if (!$this->acl->checkEntityCreate($entity)) {
            throw new Forbidden("No create access for '$entityType'.");
        }

        $this->setFields($entity, $lead);
        $this->entityManager->saveEntity($entity);
        $this->setLinks($entity, $lead);

        return $entity;
    }

    private function updateLead(Lead $lead, Entity $newEntity): void
    {
        $lead->set('status', Lead::STATUS_CONVERTED);

        if (Contact::ENTITY_TYPE === $newEntity->getEntityType()) {
            $lead->set('createdContactId', $newEntity->getId());
        }

        if (Account::ENTITY_TYPE === $newEntity->getEntityType()) {
            $lead->set('createdAccountId', $newEntity->getId());
        }

        $this->entityManager->saveEntity($lead);
    }

    private function setFields(Entity $entity, Lead $lead): void
    {
        $fieldsMap = $this->metadata->get(sprintf('entityDefs.Lead.convertFields.%s', $entity->getEntityType()), []);

        array_map(
            static fn(string $fromField, string $toField) => $entity->set($toField, $lead->get($fromField)),
            array_keys($fieldsMap),
            array_values($fieldsMap)
        );
    }

    /**
     * @throws NotFound
     */
    private function setLinks(Entity $entity, Lead $lead): void
    {
        $links = $this->metadata->get(sprintf('entityDefs.Lead.convertLinks.%s', $entity->getEntityType()), []);
        $repository = $this->entityManager->getRDBRepository($entity->getEntityType());

        array_map(function(array $link) use ($repository, $entity, $lead): void {
            $relatedEntity = $this->entityManager->getEntity($link['entityType'], $lead->get($link['field']));

            if (null === $relatedEntity || ! $relatedEntity->hasId()) {
                throw new NotFound(sprintf('Related %s %s not found', $link['entityType'], $lead->get($link['field'])));
            }

            $repository->getRelation($entity, $link['linkName'])->relate($relatedEntity);
        }, $links);
    }
}
