<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AccountController
{
    // Connessione al database.
    private function getConnection(): mysqli
    {
        return new mysqli('my_mariadb', 'root', 'ciccio', 'banking');
    }

    // Risposta JSON standardizzata.
    private function jsonResponse(Response $response, array $payload, int $status): Response
    {
        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    // Verifica esistenza account.
    private function accountExists(mysqli $connection, $accountId): bool
    {
        $check = $connection->query("SELECT id_account FROM accounts WHERE id_account = " . $accountId);
        return $check && $check->num_rows > 0;
    }

    // Verifica esistenza transazione per account.
    private function transactionExists(mysqli $connection, $accountId, $transactionId): bool
    {
        $check = $connection->query("
            SELECT id_transaction FROM transactions
            WHERE account_id = " . $accountId . " AND id_transaction = " . $transactionId . "
        ");
        return $check && $check->num_rows > 0;
    }

    // Recupera ultimo saldo (ultimo balance_after disponibile).
    private function getLatestBalance(mysqli $connection, $accountId): float
    {
        $result = $connection->query("
            SELECT balance_after
            FROM transactions
            WHERE account_id = " . $accountId . "
            AND id_transaction = (
                SELECT MAX(id_transaction)
                FROM transactions
                WHERE account_id = " . $accountId . "
            )
        ");

        if (!$result || $result->num_rows === 0) {
            return 0.0;
        }

        return (float)$result->fetch_row()[0];
    }

    // Recupera valuta dell'account.
    private function getAccountCurrency(mysqli $connection, $accountId): ?string
    {
        $result = $connection->query("
            SELECT currency
            FROM accounts
            WHERE id_account = " . $accountId . "
        ");

        if (!$result || $result->num_rows === 0) {
            return null;
        }

        return $result->fetch_assoc()['currency'];
    }

    // MOSTRA TUTTI GLI ACCOUNT (transazioni).
    public function index(Request $request, Response $response, $args)
    {
        $connection = $this->getConnection();
        $result = $connection->query("SELECT * FROM transactions");
        $results = $result->fetch_all(MYSQLI_ASSOC);

        return $this->jsonResponse($response, $results, 200);
    }

    // LISTA MOVIMENTI di un account (mantiene i controlli originali).
    public function getTransactions(Request $request, Response $response, $args)
    {
        $connection = $this->getConnection();

        if (!$this->accountExists($connection, $args['id'])) {
            return $this->jsonResponse($response, ['error' => 'Account not found'], 404);
        }

        if (!$this->transactionExists($connection, $args['id'], $args['tid'])) {
            return $this->jsonResponse($response, ['error' => 'Transaction not found'], 404);
        }

        $result = $connection->query("SELECT * FROM transactions WHERE account_id=" . $args['id']);
        $results = $result->fetch_all(MYSQLI_ASSOC);

        return $this->jsonResponse($response, $results, 200);
    }

    // DETTAGLIO singolo movimento.
    public function getSingleTransaction(Request $request, Response $response, $args)
    {
        $connection = $this->getConnection();

        $result = $connection->query("
            SELECT * FROM transactions
            WHERE account_id=" . $args['id'] . " AND id_transaction=" . $args['tid']);
        $results = $result->fetch_all(MYSQLI_ASSOC);

        return $this->jsonResponse($response, $results, 200);
    }

    // SALDO attuale dell'account.
    public function getBalance(Request $request, Response $response, $args)
    {
        $connection = $this->getConnection();

        if (!$this->accountExists($connection, $args['id'])) {
            return $this->jsonResponse($response, ['error' => 'Account not found'], 404);
        }

        $result = $connection->query("
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

        return $this->jsonResponse($response, $results, 200);
    }

    // CONVERSIONE saldo in valuta FIAT tramite API Frankfurter.
    public function convertFiat(Request $request, Response $response, $args)
    {
        $connection = $this->getConnection();

        if (!$this->accountExists($connection, $args['id'])) {
            return $this->jsonResponse($response, ['error' => 'Account not found'], 404);
        }

        $from = $this->getAccountCurrency($connection, $args['id']);
        $balance = $this->getLatestBalance($connection, $args['id']);
        $to = strtoupper(trim($request->getQueryParams()['to'] ?? ''));

        $url = "https://api.frankfurter.dev/v1/latest?base={$from}&symbols={$to}";
        $json = @file_get_contents($url);

        if ($json === false) {
            return $this->jsonResponse($response, ['error' => 'External exchange API unavailable'], 502);
        }

        $data = json_decode($json, true);

        if (!isset($data['rates'][$to])) {
            return $this->jsonResponse($response, ['error' => 'Target currency not supported'], 400);
        }

        $rate = (float)$data['rates'][$to];
        $converted = round($balance * $rate, 2);

        return $this->jsonResponse($response, [
            'converted_amount' => $converted,
            'balance' => $balance
        ], 200);
    }

    // CONVERSIONE saldo in crypto tramite API Binance.
    public function convertCrypto(Request $request, Response $response, $args)
    {
        $connection = $this->getConnection();

        if (!$this->accountExists($connection, $args['id'])) {
            return $this->jsonResponse($response, ['error' => 'Account not found'], 404);
        }

        $balanceResult = $connection->query("
            SELECT balance_after
            FROM transactions
            WHERE account_id = " . $args['id'] . "
            AND id_transaction = (
                SELECT MAX(id_transaction)
                FROM transactions
                WHERE account_id = " . $args['id'] . "
            )
        ");

        $from = $this->getAccountCurrency($connection, $args['id']);
        $to = strtoupper(trim($request->getQueryParams()['to'] ?? ''));

        // Mantiene il comportamento originale: inizializzazione e lettura condizionata da result set.
        $balance = 0.0;
        if ($balanceResult && $balanceResult->num_rows > 0) {
            $balance = (float)$balanceResult->fetch_row()[0];
        }

        $url = "https://api.binance.com/api/v3/ticker/price?symbol={$to}{$from}";
        $json = @file_get_contents($url);
        $data = json_decode($json, true);

        if (!isset($data['price'])) {
            return $this->jsonResponse($response, ['error' => 'Invalid cryptocurrency pair or symbol not supported'], 400);
        }

        $rate = (float)$data['price'];
        $converted = round($balance / $rate, 8);

        return $this->jsonResponse($response, [
            'converted_amount' => $converted,
            'balance' => $balance,
           
        ], 200);
    }

    // DEPOSITO: valida importo, calcola nuovo saldo, inserisce movimento.
    public function deposit(Request $request, Response $response, $args)
    {
        $params = json_decode($request->getBody(), true);
        $connection = $this->getConnection();

        if (!$this->accountExists($connection, $args['id'])) {
            return $this->jsonResponse($response, ['error' => 'Account not found'], 404);
        }

        if (!isset($params['amount']) || (float)$params['amount'] <= 0) {
            return $this->jsonResponse($response, ['error' => 'Amount must be greater than 0'], 400);
        }

        $currentBalance = $this->getLatestBalance($connection, $args['id']);
        $amount = (float)$params['amount'];
        $description = $params['description'] ?? 'Deposito';
        $newBalance = $currentBalance + $amount;

        $result = $connection->query("
            INSERT INTO transactions (account_id, amount, description, balance_after)
            VALUES (
                " . $args['id'] . ",
                " . $amount . ",
                '" . $description . "',
                " . $newBalance . "
            )
        ");

        if ($result) {
            $results['message'] = "Deposito di $amount effettuato con successo.";
            $results['new_balance'] = $newBalance;
        } else {
            $results['message'] = "Errore durante il deposito.";
        }

        return $this->jsonResponse($response, $results, 200);
    }

    // PRELIEVO: valida importo/fondi, calcola saldo e inserisce movimento negativo.
    public function withdrawal(Request $request, Response $response, $args)
    {
        $params = json_decode($request->getBody(), true);
        $connection = $this->getConnection();

        if (!$this->accountExists($connection, $args['id'])) {
            return $this->jsonResponse($response, ['error' => 'Account not found'], 404);
        }

        if (!isset($params['amount']) || (float)$params['amount'] <= 0) {
            return $this->jsonResponse($response, ['error' => 'Amount must be greater than 0'], 400);
        }

        $balanceResult = $connection->query("
            SELECT balance_after
            FROM transactions
            WHERE account_id = " . $args['id'] . "
            ORDER BY id_transaction DESC LIMIT 1
        ");
        $currentBalance = (float)$balanceResult->fetch_row()[0];

        $amount = (float)$params['amount'];
        $description = $params['description'] ?? 'Prelievo';

        if ($currentBalance < $amount) {
            return $this->jsonResponse($response, ['error' => 'Insufficient funds'], 422);
        }

        $newBalance = $currentBalance - $amount;

        $result = $connection->query("
            INSERT INTO transactions (account_id, amount, description, balance_after)
            VALUES (
                " . $args['id'] . ",
                -" . $amount . ",
                '" . $description . "',
                " . $newBalance . "
            )
        ");

        if ($result) {
            $results['message'] = "Prelievo di $amount effettuato con successo.";
            $results['new_balance'] = $newBalance;
        } else {
            $results['message'] = "Errore durante il prelievo.";
        }

        return $this->jsonResponse($response, $results, 200);
    }

    // MODIFICA descrizione di un movimento esistente.
    public function updateTransaction(Request $request, Response $response, $args)
    {
        $params = json_decode($request->getBody(), true);
        $connection = $this->getConnection();

        if (!isset($params['description']) || trim($params['description']) === '') {
            return $this->jsonResponse($response, ['error' => 'Description is required'], 400);
        }

        if (!$this->transactionExists($connection, $args['id'], $args['tid'])) {
            return $this->jsonResponse($response, ['error' => 'Transaction not found'], 404);
        }

        $description = $params['description'];

        $result = $connection->query("
            UPDATE transactions
            SET description = '" . $description . "'
            WHERE account_id = " . $args['id'] . "
            AND id_transaction = " . $args['tid'] . "
        ");

        if ($result) {
            $results['message'] = "Descrizione del movimento aggiornata con successo.";
        } else {
            $results['message'] = "Errore durante l'aggiornamento del movimento.";
        }

        return $this->jsonResponse($response, $results, 200);
    }

    // ELIMINA movimento solo se è l'ultimo registrato per l'account.
    public function deleteTransaction(Request $request, Response $response, $args)
    {
        $connection = $this->getConnection();

        if (!$this->transactionExists($connection, $args['id'], $args['tid'])) {
            return $this->jsonResponse($response, ['error' => 'Transaction not found'], 404);
        }

        $maxResult = $connection->query("
            SELECT MAX(id_transaction)
            FROM transactions
            WHERE account_id = " . $args['id'] . "
        ");
        $maxId = $maxResult->fetch_row()[0];

        if ($args['tid'] != $maxId) {
            $results['message'] = "Operazione negata: e' possibile eliminare solo l'ULTIMO movimento registrato per non corrompere lo storico dei saldi.";
            return $this->jsonResponse($response, $results, 403);
        }

        $result = $connection->query("
            DELETE FROM transactions
            WHERE account_id = " . $args['id'] . "
            AND id_transaction = " . $args['tid'] . "
        ");

        if ($result) {
            $results['message'] = "Ultimo movimento eliminato con successo.";
        } else {
            $results['message'] = "Errore durante l'eliminazione del movimento.";
        }

        return $this->jsonResponse($response, $results, 200);
    }
}