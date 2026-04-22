<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AccountController
{


  public function index(Request $request, Response $response, $args){
    $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'banking');
    $result = $mysqli_connection->query("SELECT * FROM transactions");
    $results = $result->fetch_all(MYSQLI_ASSOC);

    $response->getBody()->write(json_encode($results));
    return $response->withHeader("Content-type", "application/json")->withStatus(200);
  }
  

  public function getTransactions(Request $request, Response $response, $args){
    $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'banking');
    $result = $mysqli_connection->query("SELECT * FROM transactions where account_id=".$args['id']);
    $results = $result->fetch_all(MYSQLI_ASSOC);

    $response->getBody()->write(json_encode($results));
    return $response->withHeader("Content-type", "application/json")->withStatus(200);
  }


    public function getSingleTransaction(Request $request, Response $response, $args){
    $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'banking');
    $result = $mysqli_connection->query("SELECT  * FROM transactions WHERE account_id=".$args['id'] . " and id_transaction=" . $args['tid']);
    $results = $result->fetch_all(MYSQLI_ASSOC);

    $response->getBody()->write(json_encode($results));
    return $response->withHeader("Content-type", "application/json")->withStatus(200);
  }


    public function getBalance(Request $request, Response $response, $args){
    $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'banking');
   $result = $mysqli_connection->query("
        SELECT balance_after 
        FROM transactions 
        WHERE account_id = " . $args['id'] . " 
        AND id_transaction = (
            SELECT MAX(id_transaction) 
            FROM transactions 
            WHERE account_id = " . $args['id'] . "
        )
    ");    
    $results = $result->fetch_all(MYSQLI_ASSOC);
    $response->getBody()->write(json_encode($results));
    return $response->withHeader("Content-type", "application/json")->withStatus(200);
  }


public function convertFiat(Request $request, Response $response, $args){
    $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'banking');

    // Esegui la query per ottenere il saldo
    $balance_result = $mysqli_connection->query("
        SELECT balance_after
        FROM transactions
        WHERE account_id = " . $args['id'] . "
        AND id_transaction = (
            SELECT MAX(id_transaction)
            FROM transactions
            WHERE account_id = " . $args['id'] . "
        )
    ");



    $from_result = $mysqli_connection->query("
        SELECT currency
        FROM accounts
        WHERE id_account = " . $args['id'] . "
    ");

$from = $from_result->fetch_assoc()['currency'];
        $balance = (float)$balance_result->fetch_row()[0]; 


    $to = strtoupper(trim($request->getQueryParams()['to'] ?? ''));

    $url = "https://api.frankfurter.dev/v1/latest?base={$from}&symbols={$to}";
    $json = @file_get_contents($url);

    if ($json === false) {
        $response->getBody()->write(json_encode([
            'error' => 'External exchange API unavailable'
        ]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(502);
    }

    $data = json_decode($json, true);

    $rate      = (float)$data['rates'][$to];
    $converted = round($balance * $rate, 2);

    $response->getBody()->write(json_encode([
        'converted_amount' => $converted,
        'balance' => $balance, 
        'cazzo' => $from
    ]));

    return $response->withHeader("Content-type", "application/json")->withStatus(200);
}




































  public function create(Request $request, Response $response, $args){
    $params = json_decode($request -> getBody(), true);
    $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'scuola');
$result = $mysqli_connection->query("
    INSERT INTO `certificazioni` (`alunno_id`, `titolo`, `votazione`, `ente`) 
    VALUES (
        '" . $args['id'] . "',
        '" . $params['titolo'] . "',
        '" . $params['votazione'] . "',
        '" . $params['ente'] . "'
    );
");    if($result){
      $results['message'] = "  La certificazione e' stata inserita " ;
    }
    else{

      $results['message'] = " lA certificazione NON e' stata inserita" ;
    }

    $response->getBody()->write(json_encode($results));
    return $response->withHeader("Content-type", "application/json")->withStatus(200);
  }  

  

  public function update(Request $request, Response $response, $args){
    $params = json_decode($request -> getBody(), true);
    $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'scuola');
  
  $result = $mysqli_connection->query(
    "UPDATE `certificazioni` 
     SET `alunno_id` = '" . $args['id'] . "',
         `titolo` = '" . $params['titolo'] . "',
         `votazione` = '" . $params['votazione'] . "',
         `ente` = '" . $params['ente'] . "'
     WHERE `id` = " . $args['cid']
);
    if($result){
      $results['message'] = "lo studente è aggiornato  " ;
    }
    else{

      $results['message'] = "lo studente NON è stato aggiornato " ;
    }

    $response->getBody()->write(json_encode($results));
    return $response->withHeader("Content-type", "application/json")->withStatus(200);
  }  


  public function destroy(Request $request, Response $response, $args){
    $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'scuola');
    $result = $mysqli_connection->query("DELETE FROM certificazioni WHERE alunno_id=".$args['id'] . " and id=" . $args['cid']);
    if($result){
      $results['message'] = "lo studente " . $args['id']. " rimosso con successo" ;
    }
    else{

      $results['message'] = "lo studente " . $args['id']. " NON è rimosso con successo" ;
    }
    $response->getBody()->write(json_encode($results));
    
    return $response->withHeader("Content-type", "application/json")->withStatus(200);
  }

public function convertCrypto(Request $request, Response $response, array $args){
    // 1. Connessione al DB (nota il $ iniziale)
    $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'banking');
    
    $accountId = (int)$args['id'];
    $params = $request->getQueryParams();
    $toCrypto = strtoupper(trim($params['to'] ?? ''));
  
    // 2. Validazione parametro 'to'
    if (!$toCrypto) {
        return $this->respondWithError($response, 400, 'Missing target cryptocurrency');
    }
  
    // 3. Recupero il conto dal DB (USO PREPARE, NON QUERY, e metto il $)
    $stmt = $mysqli_connection->prepare("SELECT id_account, currency FROM accounts WHERE id_account = ?");
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $result = $stmt->get_result();
    $account = $result->fetch_assoc();
  
    if (!$account) {
        return $this->respondWithError($response, 404, 'Account not found');
    }
  
    $fromCurrency = strtoupper($account['currency']);
  
    // 4. Calcolo il saldo attuale (CAMBIATO $this->mysqli IN $mysqli_connection)
    $stmt = $mysqli_connection->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END), 0) AS balance
        FROM transactions
        WHERE account_id = ?
    ");
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $balance = (float)($row['balance'] ?? 0);
  
    // 5. Costruisco la coppia di mercato per Binance (es. BTCEUR)
    $marketSymbol = $toCrypto . $fromCurrency;
  
    // 6. Chiamata all'API Binance (ticker/price)
    $url = "https://api.binance.com/api/v3/ticker/price?symbol=" . $marketSymbol;
    $json = @file_get_contents($url);
  
    if ($json === false) {
        return $this->respondWithError($response, 502, 'External Binance API unavailable');
    }
  
    $data = json_decode($json, true);
  
    // 7. Verifico che la coppia esista (gestione errore -1121 di Binance)
    if (isset($data['code']) && $data['code'] === -1121) {
        return $this->respondWithError($response, 400, 'Invalid symbol or unsupported crypto/fiat pair');
    }
  
    // Verifica che il prezzo sia effettivamente presente
    if (!isset($data['price'])) {
        return $this->respondWithError($response, 502, 'Unexpected response from Binance API');
    }
  
    $price = (float)$data['price'];
  
    // Sicurezza: evito la divisione per zero
    if ($price <= 0) {
        return $this->respondWithError($response, 502, 'Invalid price returned from Binance');
    }
  
    // 8. Calcolo la quantità di crypto (Saldo / Prezzo) arrotondata a 8 decimali
    $convertedAmount = round($balance / $price, 8);
  
    // 9. Costruisco la risposta di successo
    $payload = [
        'account_id'       => $accountId,
        'provider'         => 'Binance',
        'conversion_type'  => 'crypto',
        'from_currency'    => $fromCurrency,
        'to_crypto'        => $toCrypto,
        'market_symbol'    => $marketSymbol,
        'original_balance' => $balance,
        'price'            => $price,
        'converted_amount' => $convertedAmount
    ];
  
    $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));
  
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
}}




