<?php

declare(strict_types=1);

include '../site/bootstrap.php';

$app = new \Espo\Core\Application();
$app->setupSystemUser();

if (file_exists('../src/scripts/AfterUninstall.php')) {
    include('../src/scripts/AfterUninstall.php');
    $afterUninstall = new AfterUninstall();
    $afterUninstall->run($app->getContainer());
}
