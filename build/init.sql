CREATE TABLE `accounts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `owner_name` VARCHAR(50) NOT NULL,
  `currency` VARCHAR(20) NOT NULL,
  `created_at`  Date NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `transactions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `account_id` INT(11) NOT NULL,
  `type` VARCHAR(20) NOT NULL,
  `amount` FLOAT(2) NOT NULL,
  `description` VARCHAR(300) NOT NULL,
  `created_at` Date not NULL, 
  `balance_after` FLOAT(2) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;