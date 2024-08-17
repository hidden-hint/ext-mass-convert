<?php

declare(strict_types=1);

namespace Espo\Modules\MassConvert\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Modules\MassConvert\Lead\MassConvertService;

final class MassConvert implements Action
{
    public function __construct(private readonly MassConvertService $service) {}

    public function process(Request $request): Response
    {
        $this->service->convert(...$request->getParsedBody()->ids);

        return ResponseComposer::json(true);
    }
}
