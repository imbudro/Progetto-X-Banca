<?php

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/controllers/AccountController.php';

$app = AppFactory::create();

$app->get('/test', function (Request $request, Response $response, array $args) {
    $response->getBody()->write("Test page");
    return $response;
});

$app->get('/hello/{name}', function (Request $request, Response $response, array $args) {
    $name = $args['name'];
    $response->getBody()->write("Hello, $name");
    return $response;
});

$app->get('/accounts/', "AccountController:index");


// =============================================
// LISTA MOVIMENTI
// =============================================
$app->get('/accounts/{id}/transactions', 'AccountController:getTransactions');

// =============================================
// DETTAGLIO SINGOLO MOVIMENTO
// =============================================
$app->get('/accounts/{id}/transactions/{tid}', 'AccountController:getSingleTransaction');

// =============================================
// SALDO
// =============================================
$app->get('/accounts/{id}/balance', 'AccountController:getBalance');


// =============================================
// CONVERSIONE FIAT (Frankfurter)
// =============================================
$app->get('/accounts/{id}/balance/convert/fiat', 'AccountController:convertFiat');

// =============================================
// CONVERSIONE CRYPTO (Binance)
// =============================================
$app->get('/accounts/{id}/balance/convert/crypto', 'AccountController:convertCrypto');













// =============================================
// DEPOSITO
// =============================================
$app->post('/accounts/{id}/deposits', 'AccountController:deposit');

// =============================================
// PRELIEVO
// =============================================
$app->post('/accounts/{id}/withdrawals', 'AccountController:withdrawal');

// =============================================
// MODIFICA DESCRIZIONE MOVIMENTO
// =============================================
$app->put('/accounts/{id}/transactions/{tid}', 'AccountController:updateTransaction');

// =============================================
// ELIMINA MOVIMENTO (solo l'ultimo)
// =============================================
$app->delete('/accounts/{id}/transactions/{tid}', 'AccountController:deleteTransaction');

$app->run();
