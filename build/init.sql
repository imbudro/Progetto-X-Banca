

-- $app->get('/utente/{id_utente}/conto{id_conto}', "CertificazioniController:show");
CREATE TABLE `accounts` (
  `id_account` INT(11) NOT NULL AUTO_INCREMENT,
  `owner_name` VARCHAR(50) NOT NULL,
  `currency` VARCHAR(3) NOT NULL DEFAULT 'EUR',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_account`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `transactions` (
  `id_transaction` INT(11) NOT NULL AUTO_INCREMENT,
  `account_id` INT(11) NOT NULL,
  `type` ENUM('deposit', 'withdrawal') NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `description` VARCHAR(255),
  `balance_after` DECIMAL(10,2),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_transaction`),
  FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id_account`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Accounts
INSERT INTO accounts (owner_name, currency) VALUES
('Mario Rossi', 'EUR'),
('Luca Bianchi', 'EUR'),
('Anna Verdi', 'EUR');

-- Transactions
INSERT INTO transactions (account_id, type, amount, description, balance_after) VALUES
(1, 'deposit', 1000.00, 'Versamento iniziale', 1000.00),
(1, 'withdrawal', 200.00, 'Prelievo ATM', 800.00),
(1, 'deposit', 500.00, 'Bonifico ricevuto', 1300.00),
(2, 'deposit', 2500.00, 'Versamento iniziale', 2500.00),
(2, 'withdrawal', 150.50, 'Pagamento bolletta', 2349.50),
(3, 'deposit', 3000.00, 'Initial deposit', 3000.00),
(3, 'withdrawal', 99.99, 'Online purchase', 2900.01);