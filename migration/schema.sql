-- Elite Padel League — Schema v2.0
-- Plataforma propia, sin WordPress

CREATE DATABASE IF NOT EXISTS `epleague`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `epleague`;

-- -------------------------------------------------------
-- Jugadores: usuario + perfil + jugador en una sola tabla
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `jugadores` (
  `id`                    INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  -- Acceso
  `email`                 VARCHAR(255)     NOT NULL,
  `password`              VARCHAR(255)     NOT NULL,
  `rol`                   ENUM('jugador','admin') NOT NULL DEFAULT 'jugador',
  `estado`                ENUM('activo','inactivo','suspendido') NOT NULL DEFAULT 'activo',
  -- Datos personales
  `nombre`                VARCHAR(100)     NOT NULL,
  `apellido`              VARCHAR(150)     NOT NULL DEFAULT '',
  `alias`                 VARCHAR(100)     DEFAULT NULL,
  `rut`                   VARCHAR(15)      DEFAULT NULL,
  `fecha_nacimiento`      DATE             DEFAULT NULL,
  `sexo`                  ENUM('M','F','otro') DEFAULT NULL,
  `foto`                  VARCHAR(500)     DEFAULT NULL,
  -- Contacto
  `telefono`              VARCHAR(30)      DEFAULT NULL,
  `whatsapp`              VARCHAR(30)      DEFAULT NULL,
  `comuna`                VARCHAR(100)     DEFAULT NULL,
  `profesion`             VARCHAR(150)     DEFAULT NULL,
  -- Datos deportivos
  `nivel`                 TINYINT UNSIGNED DEFAULT 5,
  `lado`                  ENUM('derecha','reves','ambos') DEFAULT NULL,
  `pala`                  VARCHAR(100)     DEFAULT NULL,
  `talla`                 VARCHAR(10)      DEFAULT NULL,
  `frecuencia_juego`      ENUM('1_semana','2_semana','3_o_mas','ocasional') DEFAULT NULL,
  -- Sistema
  `wp_user_id`            INT              DEFAULT NULL,
  `reset_token`           VARCHAR(100)     DEFAULT NULL,
  `reset_token_expires`   DATETIME         DEFAULT NULL,
  `created_at`            TIMESTAMP        DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            TIMESTAMP        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Ligas (torneos)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ligas` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`              VARCHAR(200) NOT NULL,
  `temporada`           VARCHAR(50)  DEFAULT NULL,
  `categoria`           TINYINT UNSIGNED DEFAULT NULL,
  `estado`              ENUM('proximamente','inscripcion','activa','finalizada') NOT NULL DEFAULT 'inscripcion',
  `precio`              DECIMAL(10,2) DEFAULT NULL,
  `fecha_inicio`        DATE         DEFAULT NULL,
  `fecha_fin`           DATE         DEFAULT NULL,
  `sede`                VARCHAR(200) DEFAULT NULL,
  `url_maps`            VARCHAR(500) DEFAULT NULL,
  `descripcion`         TEXT         DEFAULT NULL,
  `foto_portada`        VARCHAR(500) DEFAULT NULL,
  `mailerlite_group_id` VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Equipos (parejas)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `equipos` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`      VARCHAR(200) NOT NULL,
  `jugador1_id` INT UNSIGNED NOT NULL,
  `jugador2_id` INT UNSIGNED NOT NULL,
  `estado`      ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `wp_team_id`  INT          DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`jugador1_id`) REFERENCES `jugadores`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`jugador2_id`) REFERENCES `jugadores`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Liga ↔ Equipos
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `liga_equipos` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `liga_id`   INT UNSIGNED NOT NULL,
  `equipo_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_liga_equipo` (`liga_id`, `equipo_id`),
  FOREIGN KEY (`liga_id`)   REFERENCES `ligas`(`id`),
  FOREIGN KEY (`equipo_id`) REFERENCES `equipos`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Partidos
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `partidos` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `liga_id`              INT UNSIGNED NOT NULL,
  `equipo_local_id`      INT UNSIGNED NOT NULL,
  `equipo_visitante_id`  INT UNSIGNED NOT NULL,
  `jornada`              TINYINT UNSIGNED DEFAULT NULL,
  `cancha`               VARCHAR(100) DEFAULT NULL,
  `fecha_programada`     DATETIME     DEFAULT NULL,
  `fecha_jugado`         DATETIME     DEFAULT NULL,
  `estado`               ENUM('pendiente','jugado','reprogramado','walkover','no_presentado') NOT NULL DEFAULT 'pendiente',
  `sets_local`           TINYINT      DEFAULT 0,
  `sets_visitante`       TINYINT      DEFAULT 0,
  `games_s1_local`       TINYINT      DEFAULT NULL,
  `games_s1_visitante`   TINYINT      DEFAULT NULL,
  `games_s2_local`       TINYINT      DEFAULT NULL,
  `games_s2_visitante`   TINYINT      DEFAULT NULL,
  `games_s3_local`       TINYINT      DEFAULT NULL,
  `games_s3_visitante`   TINYINT      DEFAULT NULL,
  `ganador_id`           INT UNSIGNED DEFAULT NULL,
  `ingresado_por`        INT UNSIGNED DEFAULT NULL,
  `wp_event_id`          INT          DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`liga_id`)             REFERENCES `ligas`(`id`),
  FOREIGN KEY (`equipo_local_id`)     REFERENCES `equipos`(`id`),
  FOREIGN KEY (`equipo_visitante_id`) REFERENCES `equipos`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Clasificación (se recalcula automáticamente)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `clasificacion` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `liga_id`      INT UNSIGNED NOT NULL,
  `equipo_id`    INT UNSIGNED NOT NULL,
  `posicion`     TINYINT UNSIGNED DEFAULT NULL,
  `pj`           INT UNSIGNED DEFAULT 0,
  `pg`           INT UNSIGNED DEFAULT 0,
  `pp`           INT UNSIGNED DEFAULT 0,
  `games_favor`  INT DEFAULT 0,
  `games_contra` INT DEFAULT 0,
  `puntos`       INT DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_liga_equipo` (`liga_id`, `equipo_id`),
  FOREIGN KEY (`liga_id`)   REFERENCES `ligas`(`id`),
  FOREIGN KEY (`equipo_id`) REFERENCES `equipos`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Solicitudes de reprogramación
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `solicitudes_reprogramacion` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `partido_id`       INT UNSIGNED NOT NULL,
  `solicitante_id`   INT UNSIGNED NOT NULL,
  `motivo`           TEXT         DEFAULT NULL,
  `fecha_propuesta`  DATETIME     DEFAULT NULL,
  `rival_no_responde` TINYINT(1)  NOT NULL DEFAULT 0,
  `mutuo_acuerdo`    TINYINT(1)   NOT NULL DEFAULT 0,
  `estado`           ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `fecha_aprobada`   DATETIME     DEFAULT NULL,
  `cancha_aprobada`  VARCHAR(100) DEFAULT NULL,
  `aprobado_por`     INT UNSIGNED DEFAULT NULL,
  `created_at`       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`partido_id`)     REFERENCES `partidos`(`id`),
  FOREIGN KEY (`solicitante_id`) REFERENCES `jugadores`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Inscripciones a torneos
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inscripciones` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `jugador_id`  INT UNSIGNED NOT NULL,
  `liga_id`     INT UNSIGNED NOT NULL,
  `equipo_id`   INT UNSIGNED DEFAULT NULL,
  `rol_equipo`  ENUM('capitan','partner') NOT NULL DEFAULT 'capitan',
  `token`       VARCHAR(64)  DEFAULT NULL,
  `fecha`       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `estado`      ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `pago_estado` ENUM('pendiente','pagado','exento')      NOT NULL DEFAULT 'pendiente',
  `pago_monto`  DECIMAL(10,2) DEFAULT NULL,
  `pago_ref`    VARCHAR(200) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`jugador_id`) REFERENCES `jugadores`(`id`),
  FOREIGN KEY (`liga_id`)    REFERENCES `ligas`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Suplentes ("Galletas")
-- Un jugador puede ser galleta de un equipo por liga
-- Máx 2 galletas por equipo, máx 9 partidos por galleta
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suplentes` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `liga_id`          INT UNSIGNED NOT NULL,
  `equipo_id`        INT UNSIGNED NOT NULL,
  `jugador_id`       INT UNSIGNED NOT NULL,
  `partidos_jugados` TINYINT UNSIGNED DEFAULT 0,
  `estado`           ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `registrado_por`   INT UNSIGNED NOT NULL,
  `created_at`       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_jugador_liga` (`jugador_id`, `liga_id`),
  FOREIGN KEY (`liga_id`)        REFERENCES `ligas`(`id`),
  FOREIGN KEY (`equipo_id`)      REFERENCES `equipos`(`id`),
  FOREIGN KEY (`jugador_id`)     REFERENCES `jugadores`(`id`),
  FOREIGN KEY (`registrado_por`) REFERENCES `jugadores`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Registro de partidos jugados por suplente
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suplente_partidos` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `suplente_id` INT UNSIGNED NOT NULL,
  `partido_id`  INT UNSIGNED NOT NULL,
  `registrado_por` INT UNSIGNED NOT NULL,
  `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_suplente_partido` (`suplente_id`, `partido_id`),
  FOREIGN KEY (`suplente_id`)     REFERENCES `suplentes`(`id`),
  FOREIGN KEY (`partido_id`)      REFERENCES `partidos`(`id`),
  FOREIGN KEY (`registrado_por`)  REFERENCES `jugadores`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Finanzas — Ingresos
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finanzas_ingresos` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `liga_id`     INT UNSIGNED DEFAULT NULL,
  `jugador_id`  INT UNSIGNED DEFAULT NULL,
  `tipo`        ENUM('mercadopago','manual','global') NOT NULL DEFAULT 'manual',
  `concepto`    VARCHAR(300) NOT NULL,
  `monto`       DECIMAL(10,2) NOT NULL,
  `referencia`  VARCHAR(200) DEFAULT NULL,
  `estado`      ENUM('confirmado','pendiente','rechazado') NOT NULL DEFAULT 'confirmado',
  `registrado_por` INT UNSIGNED DEFAULT NULL,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`liga_id`)   REFERENCES `ligas`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`jugador_id`) REFERENCES `jugadores`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Finanzas — Egresos
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finanzas_egresos` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `liga_id`      INT UNSIGNED DEFAULT NULL,
  `categoria`    ENUM('canchas','pelotas','trofeos','marketing','otros') NOT NULL DEFAULT 'otros',
  `concepto`     VARCHAR(300) NOT NULL,
  `monto`        DECIMAL(10,2) NOT NULL,
  `comprobante`  VARCHAR(500) DEFAULT NULL,
  `registrado_por` INT UNSIGNED DEFAULT NULL,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`liga_id`) REFERENCES `ligas`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Saldos Apps (Easycancha, Playtomic, etc.)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `saldos_apps` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `app`        VARCHAR(100) NOT NULL,
  `tipo`       ENUM('recarga','consumo') NOT NULL,
  `monto`      DECIMAL(10,2) NOT NULL,
  `descripcion` VARCHAR(300) DEFAULT NULL,
  `registrado_por` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
