<?php
// habilitar para mostrar errores en producción.
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Cargar .env solo una vez aquí
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../', 'credenciales.env');
$dotenv->load();

$app = AppFactory::create();
$app->setBasePath('/cab/public');

// Agrega el middleware de routing
$app->addRoutingMiddleware();

// Manejo de errores personalizados
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;

$errorMiddleware = $app->addErrorMiddleware(true, true, true);

$errorMiddleware->setErrorHandler(HttpMethodNotAllowedException::class, function (
    Request $request,
    Throwable $exception,
    bool $displayErrorDetails
) use ($app) {
    $response = $app->getResponseFactory()->createResponse();
    $response->getBody()->write(json_encode([
        'error' => 'Método no permitido',
        'detalle' => 'El método HTTP utilizado no está permitido para este endpoint.'
    ]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(405);
});

$errorMiddleware->setErrorHandler(HttpNotFoundException::class, function (
    Request $request,
    Throwable $exception,
    bool $displayErrorDetails
) use ($app) {
    $response = $app->getResponseFactory()->createResponse();
    $response->getBody()->write(json_encode([
        'error' => 'Ruta no encontrada',
        'detalle' => 'El endpoint solicitado no existe.'
    ]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
});



$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Hello world!");
    return $response;
});

// Cargar rutas externas
(require __DIR__ . '/../routes/routes.php')($app);

$app->run();