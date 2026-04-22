<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AccountController
{


  public function index(Request $request, Response $response, $args){
    $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'banking');
    $result = $mysqli_connection->query("SELECT * FROM transactions " );
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








public function convertCrypto(Request $request, Response $response, $args){
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

    $url ="https://api.binance.com/api/v3/ticker/price?symbol={$to}{$from}";

    $json = @file_get_contents($url);

    $data = json_decode($json, true);

     $rate = (float)$data['price'];
        
 
        $converted = round($balance / $rate, 8); 

    $response->getBody()->write(json_encode([
        'converted_amount' => $converted,
        'balance' => $balance, 
        'cazzo' => $to,
                'cazzi' => $from,

    ]));

    return $response->withHeader("Content-type", "application/json")->withStatus(200);
}



  // =============================================
  // DEPOSITO
  // =============================================
  public function deposit(Request $request, Response $response, $args){
    $params = json_decode($request->getBody(), true);
    $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'banking');

    // 1. Recupero il saldo attuale (l'ultimo balance_after)
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
    
    $current_balance = (float)$balance_result->fetch_row()[0];
  
    // 2. Calcolo nuovo saldo
    $amount = (float)$params['amount'];
    $description = $params['description'] ?? 'Deposito';
    $new_balance = $current_balance + $amount;

    // 3. Inserisco il nuovo movimento
    $result = $mysqli_connection->query("
        INSERT INTO transactions (account_id, amount, description, balance_after) 
        VALUES (
            " . $args['id'] . ", 
            " . $amount . ", 
            '" . $description . "', 
            " . $new_balance . "
        )
    ");

    if($result){
      $results['message'] = "Deposito di $amount effettuato con successo.";
      $results['new_balance'] = $new_balance;
    } else {
      $results['message'] = "Errore durante il deposito.";
    }

    $response->getBody()->write(json_encode($results));
    return $response->withHeader("Content-type", "application/json")->withStatus(200);
  }





  // =============================================
  // PRELIEVO
  // =============================================
  public function withdrawal(Request $request, Response $response, $args){
    $params = json_decode($request->getBody(), true);
    $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'banking');

    // 1. Recupero il saldo attuale
    $balance_result = $mysqli_connection->query("
        SELECT balance_after 
        FROM transactions 
        WHERE account_id = " . $args['id'] . " 
        ORDER BY id_transaction DESC LIMIT 1
    ");
    
   
      $current_balance = (float)$balance_result->fetch_row()[0];
    

    $amount = (float)$params['amount'];
    $description = $params['description'] ?? 'Prelievo';

    // (Opzionale) Controllo se ci sono fondi sufficienti
    if ($current_balance < $amount) {
        $results['message'] = "Fondi insufficienti per effettuare il prelievo.";
        $response->getBody()->write(json_encode($results));
        return $response->withHeader("Content-type", "application/json")->withStatus(400); // Bad Request
    }

    // 2. Calcolo nuovo saldo
    $new_balance = $current_balance - $amount;

    // 3. Inserisco il movimento (registro l'amount in negativo per coerenza, se preferisci)
    $result = $mysqli_connection->query("
        INSERT INTO transactions (account_id, amount, description, balance_after) 
        VALUES (
            " . $args['id'] . ", 
            -" . $amount . ", 
            '" . $description . "', 
            " . $new_balance . "
        )
    ");

    if($result){
      $results['message'] = "Prelievo di $amount effettuato con successo.";
      $results['new_balance'] = $new_balance;
    } else {
      $results['message'] = "Errore durante il prelievo.";
    }

    $response->getBody()->write(json_encode($results));
    return $response->withHeader("Content-type", "application/json")->withStatus(200);
  }




  // =============================================
  // MODIFICA DESCRIZIONE MOVIMENTO
  // =============================================
  public function updateTransaction(Request $request, Response $response, $args){
    $params = json_decode($request->getBody(), true);
    $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'banking');
  
    $description = $params['description'];

    // Aggiorniamo SOLO la descrizione per non sballare i saldi (balance_after)
    $result = $mysqli_connection->query("
        UPDATE transactions 
        SET description = '" . $description . "' 
        WHERE account_id = " . $args['id'] . " 
        AND id_transaction = " . $args['tid'] . "
    ");

    if($result){
      $results['message'] = "Descrizione del movimento aggiornata con successo.";
    } else {
      $results['message'] = "Errore durante l'aggiornamento del movimento.";
    }

    $response->getBody()->write(json_encode($results));
    return $response->withHeader("Content-type", "application/json")->withStatus(200);
  }

  // =============================================
  // ELIMINA MOVIMENTO (Solo l'ultimo)
  // =============================================
  public function deleteTransaction(Request $request, Response $response, $args){
    $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'banking');

    // 1. Trovo qual è l'ID dell'ULTIMO movimento per questo conto
    $max_result = $mysqli_connection->query("
        SELECT MAX(id_transaction) 
        FROM transactions 
        WHERE account_id = " . $args['id'] . "
    ");
    $max_id = $max_result->fetch_row()[0];

    // 2. Controllo se l'id passato corrisponde all'ultimo movimento
    if ($args['tid'] != $max_id) {
        $results['message'] = "Operazione negata: e' possibile eliminare solo l'ULTIMO movimento registrato per non corrompere lo storico dei saldi.";
        $response->getBody()->write(json_encode($results));
        return $response->withHeader("Content-type", "application/json")->withStatus(403); // Forbidden
    }

    // 3. Se corrisponde, elimino
    $result = $mysqli_connection->query("
        DELETE FROM transactions 
        WHERE account_id = " . $args['id'] . " 
        AND id_transaction = " . $args['tid'] . "
    ");

    if($result){
      $results['message'] = "Ultimo movimento eliminato con successo.";
    } else {
      $results['message'] = "Errore durante l'eliminazione del movimento.";
    }
    
    $response->getBody()->write(json_encode($results));
    return $response->withHeader("Content-type", "application/json")->withStatus(200);
  }
































































































//   public function create(Request $request, Response $response, $args){
//     $params = json_decode($request -> getBody(), true);
//     $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'scuola');
// $result = $mysqli_connection->query("
//     INSERT INTO `certificazioni` (`alunno_id`, `titolo`, `votazione`, `ente`) 
//     VALUES (
//         '" . $args['id'] . "',
//         '" . $params['titolo'] . "',
//         '" . $params['votazione'] . "',
//         '" . $params['ente'] . "'
//     );
// ");    if($result){
//       $results['message'] = "  La certificazione e' stata inserita " ;
//     }
//     else{

//       $results['message'] = " lA certificazione NON e' stata inserita" ;
//     }

//     $response->getBody()->write(json_encode($results));
//     return $response->withHeader("Content-type", "application/json")->withStatus(200);
//   }  

  

//   public function update(Request $request, Response $response, $args){
//     $params = json_decode($request -> getBody(), true);
//     $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'scuola');
  
//   $result = $mysqli_connection->query(
//     "UPDATE `certificazioni` 
//      SET `alunno_id` = '" . $args['id'] . "',
//          `titolo` = '" . $params['titolo'] . "',
//          `votazione` = '" . $params['votazione'] . "',
//          `ente` = '" . $params['ente'] . "'
//      WHERE `id` = " . $args['cid']
// );
//     if($result){
//       $results['message'] = "lo studente è aggiornato  " ;
//     }
//     else{

//       $results['message'] = "lo studente NON è stato aggiornato " ;
//     }

//     $response->getBody()->write(json_encode($results));
//     return $response->withHeader("Content-type", "application/json")->withStatus(200);
//   }  


//   public function destroy(Request $request, Response $response, $args){
//     $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'scuola');
//     $result = $mysqli_connection->query("DELETE FROM certificazioni WHERE alunno_id=".$args['id'] . " and id=" . $args['cid']);
//     if($result){
//       $results['message'] = "lo studente " . $args['id']. " rimosso con successo" ;
//     }
//     else{

//       $results['message'] = "lo studente " . $args['id']. " NON è rimosso con successo" ;
//     }
//     $response->getBody()->write(json_encode($results));
    
//     return $response->withHeader("Content-type", "application/json")->withStatus(200);
//   }
}




