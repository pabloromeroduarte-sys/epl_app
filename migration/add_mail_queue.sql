-- Cola de envío de emails (procesada por cron_mail_sender.php cada minuto)
CREATE TABLE IF NOT EXISTS `mail_queue` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `to_email`     VARCHAR(255) NOT NULL,
  `to_name`      VARCHAR(150) DEFAULT NULL,
  `subject`      VARCHAR(250) NOT NULL,
  `body_html`    MEDIUMTEXT  NOT NULL,
  `estado`       ENUM('pendiente','enviando','enviado','error') NOT NULL DEFAULT 'pendiente',
  `intentos`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `error_msg`    TEXT DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at`      DATETIME DEFAULT NULL,
  INDEX idx_estado_created (`estado`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
