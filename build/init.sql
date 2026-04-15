

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