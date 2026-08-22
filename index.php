<?php
declare(strict_types=1);

/**
 * Front controller - all MVC requests enter here.
 */
require __DIR__ . '/app/bootstrap.php';

$app = new \App\Core\App();
$app->run();
