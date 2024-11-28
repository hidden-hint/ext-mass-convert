<?php

declare(strict_types=1);

namespace Espo\Modules\MassConvert\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Select\SearchParams;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Core\Utils\Json;
use Espo\Modules\Crm\Entities\Lead;
use Espo\Modules\MassConvert\Lead\MassConvertService;
use Espo\ORM\Collection;
use Espo\ORM\EntityManager;
use JsonException;
use stdClass;

final readonly class MassConvert implements Action
{
    public function __construct(
        private MassConvertService $service,
        private SelectBuilderFactory $selectBuilderFactory,
        private EntityManager $entityManager,
    ) {}

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     * @throws JsonException
     */
    public function process(Request $request): Response
    {
        $this->service->convert(...$this->getIds($request->getParsedBody()));

        return ResponseComposer::json(true);
    }

    /**
     * @throws BadRequest
     * @throws JsonException
     * @throws Forbidden
     */
    private function getIds(stdClass $data): array
    {
        if (isset($data->ids) && is_array($data->ids)) {
            return $data->ids;
        }

        $collection = $this->getLeadsBySearchParams($this->buildSearchParams($data));

        return array_map(static fn(Lead $lead) => $lead->getId(), iterator_to_array($collection));
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     */
    private function getLeadsBySearchParams(SearchParams $searchParams): Collection
    {
        $query = $this->selectBuilderFactory->create()
            ->from(Lead::ENTITY_TYPE)
            ->withStrictAccessControl()
            ->withSearchParams($searchParams)
            ->build();

        return $this->entityManager
            ->getRDBRepository(Lead::ENTITY_TYPE)
            ->clone($query)
            ->find();
    }

    /**
     * @throws BadRequest
     * @throws JsonException
     */
    private function buildSearchParams(stdClass $data): SearchParams
    {
        $where = json_decode(Json::encode($data->where), true, 512, JSON_THROW_ON_ERROR);

        $params = json_decode(Json::encode(
            $data->searchParams ?? $data->selectData ?? (object)[]
        ), true, 512, JSON_THROW_ON_ERROR);

        if ($where !== null && !is_array($where)) {
            throw new BadRequest('Bad where.');
        }

        if ($where !== null) {
            $params['where'] = array_merge(
                $params['where'] ?? [],
                $where
            );
        }

        unset($params['select']);

        return SearchParams::fromRaw($params);
    }
}
