<?php

use Slim\App;

return static function (App $app) {
    $show = ($_ENV['APP_ENV'] ?? 'dev') === 'dev';
    $app->addErrorMiddleware($show, true, true);
};
