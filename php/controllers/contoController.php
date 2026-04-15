<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CertificazioniController
{
  public function index(Request $request, Response $response, $args){
    $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'scuola');
    $result = $mysqli_connection->query("SELECT * FROM certificazioni");
    $results = $result->fetch_all(MYSQLI_ASSOC);

    $response->getBody()->write(json_encode($results));
    return $response->withHeader("Content-type", "application/json")->withStatus(200);
  }
  
  
  public function show(Request $request, Response $response, $args){
    $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'scuola');
    $result = $mysqli_connection->query("SELECT * FROM certificazioni where alunno_id=".$args['id']);
    $results = $result->fetch_all(MYSQLI_ASSOC);

    $response->getBody()->write(json_encode($results));
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
