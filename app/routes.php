<?php

declare(strict_types=1);
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;



/**
 * @OA\Info(title="BoothBooker API", version="1.0.0")
 */

 return function (App $app) {
    $app->get('/docs', function ($request, $response, $args) {
        return $response->withHeader('Location', '/swagger-ui-master/dev-helpers/index.html')->withStatus(302);
    });
};
