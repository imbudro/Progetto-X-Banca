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

}
