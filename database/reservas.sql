-- DDL + datos de ejemplo
-- Para el equipo de MisterPlan: crea una base de datos vacía (utf8mb4) y selecciónala; después importa este fichero
--   mysql -u root -p -e "CREATE DATABASE misterplan_reservas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
--   mysql -u root -p misterplan_reservas < database/reservas.sql
-- En phpMyAdmin (MAMP/WAMP/hosting): crea la BD, entra en ella y usa "Importar"

SET NAMES utf8mb4;

DROP TABLE IF EXISTS reserva_evento;
DROP TABLE IF EXISTS reserva;

CREATE TABLE reserva (
    id                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    nombre_huesped     VARCHAR(120)  NOT NULL,
    email_huesped      VARCHAR(190)  NOT NULL,
    nombre_alojamiento VARCHAR(150)  NOT NULL,
    fecha_entrada      DATE          NOT NULL,
    fecha_salida       DATE          NOT NULL,
    estado             ENUM('pendiente','confirmada','cancelada') NOT NULL DEFAULT 'pendiente',
    importe            DECIMAL(10,2) NOT NULL,
    notas              VARCHAR(1000) NULL,
    fecha_creacion     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- Seguridad para la integridad: aunque alguien escriba en la BD sin pasar por la API
    CONSTRAINT chk_reserva_fechas  CHECK (fecha_salida > fecha_entrada),
    CONSTRAINT chk_reserva_importe CHECK (importe > 0),
    -- Filtro por estado (+ orden por fecha) y filtro por rango de fechas (Requisito)
    KEY idx_reserva_estado_entrada (estado, fecha_entrada),
    KEY idx_reserva_fechas (fecha_entrada, fecha_salida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;