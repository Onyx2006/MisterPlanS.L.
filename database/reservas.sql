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

CREATE TABLE reserva_evento (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reserva_id     INT UNSIGNED NOT NULL,
    tipo           ENUM('creada','confirmada','cancelada') NOT NULL,
    descripcion    VARCHAR(255) NOT NULL,
    fecha_creacion DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_evento_reserva (reserva_id, fecha_creacion),
    CONSTRAINT fk_evento_reserva FOREIGN KEY (reserva_id) REFERENCES reserva (id) ON DELETE CASCADE -- Soy consciente que ON DELETE CASCADE puede ser peligroso pero para una prueba rápida me sirve
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DATOS DE EJEMPLO
-- Mezcla de estados y fechas (pasadas y futuras) para que los filtros se vean funcionar (Requisito)
-- Le puse un apóstrofo (id 8) y HTML en las notas (id 11) para comprobar el escapado

INSERT INTO reserva (id, nombre_huesped, email_huesped, nombre_alojamiento, fecha_entrada, fecha_salida, estado, importe, notas, fecha_creacion) VALUES
( 1, 'Oliver Gamboa Mesa', 'olivergamboamesa@example.com', 'Hotel Costa del sol', '2026-09-12', '2026-09-15', 'confirmada',  345.00, 'Llegada tarde, después de las 22:00.', '2026-08-20 10:15:00'),
( 2, 'Carlos Ruiz Martín', 'carlos57ruiz@example.com', 'Apartamentos Casa Miguel', '2026-10-02', '2026-10-09', 'confirmada',  812.50, NULL, '2026-08-30 17:42:00'),
( 3, 'Marta Gómez Salas', 'martayumgomez@example.com', 'Hotel Costa del sol', '2026-10-24', '2026-10-26', 'pendiente',   198.00, 'Habitación doble con cuna.', '2026-10-10 09:03:00'),
( 4, 'John Peterson', 'john.peterson@example.com', 'Casa Rural Berenjena', '2026-11-06', '2026-11-10', 'confirmada',  520.00, 'Viaja con perro pequeño.', '2026-09-18 12:30:00'),
( 5, 'Elena Navarro Pastor', 'elena.nava.rro@example.com', 'Parador de Ronda', '2026-11-14', '2026-11-16', 'pendiente',   330.00, NULL, '2026-10-12 18:20:00'),
( 6, 'Pablo Díaz Rey', 'pablodiazrey@example.com', 'Apartamentos Casa Miguel', '2026-09-05', '2026-09-08', 'cancelada',   276.00, 'Cancelada por el cliente.', '2026-08-02 08:55:00'),
( 7, 'Sofía Marín Vega', 'sofia.09.marin@example.com', 'Hotel Costalita Cancelada', '2026-12-26', '2027-01-02', 'confirmada', 1450.00, 'Cena de Nochevieja incluida.','2026-09-25 20:10:00'),
( 8, 'Andrés O''Brien', 'andreseyeyobrien@example.com', 'Hotel Costalita Cancelada', '2026-10-30', '2026-11-02', 'pendiente',   405.75, 'Pide late check-out.', '2026-10-14 11:47:00'),
( 9, 'Laura Castillo Pérez', 'laura654654castillo@example.com', 'Casa Rural Berenjena', '2026-10-17', '2026-10-19', 'cancelada',   240.00, NULL, '2026-09-30 16:05:00'),
(10, 'Ahmed El Idrissi', 'ahmed.elidrissi@example.com', 'Parador de Ronda', '2026-12-05', '2026-12-08', 'confirmada',  690.00, 'Puente de diciembre.', '2026-10-01 09:12:00'),
(11, 'Beatriz Solís Vidal', 'beatrizvidalsolis@example.com', 'Hotel Costa del sol', '2026-11-20', '2026-11-23', 'pendiente',   372.00, 'Aniversario. <b>Prueba de escapado</b> <script>alert(1)</script>', '2026-10-15 13:33:00'),
(12, 'Iván Torres Gil', 'ivan.torresgil09@example.com', 'Apartamentos Casa Miguel', '2026-08-01', '2026-08-05', 'confirmada',  560.00, NULL, '2026-06-20 19:00:00');

INSERT INTO reserva_evento (reserva_id, tipo, descripcion, fecha_creacion) VALUES
( 1, 'creada', 'Reserva creada en estado pendiente.', '2026-08-20 10:15:00'),
( 1, 'confirmada', 'Estado cambiado de ''pendiente'' a ''confirmada''.', '2026-08-20 12:00:00'),
( 2, 'creada', 'Reserva creada en estado pendiente.', '2026-08-30 17:42:00'),
( 2, 'confirmada', 'Estado cambiado de ''pendiente'' a ''confirmada''.', '2026-08-31 09:10:00'),
( 3, 'creada', 'Reserva creada en estado pendiente.', '2026-10-10 09:03:00'),
( 4, 'creada', 'Reserva creada en estado pendiente.', '2026-09-18 12:30:00'),
( 4, 'confirmada', 'Estado cambiado de ''pendiente'' a ''confirmada''.', '2026-09-18 15:45:00'),
( 5, 'creada', 'Reserva creada en estado pendiente.', '2026-10-12 18:20:00'),
( 6, 'creada', 'Reserva creada en estado pendiente.', '2026-08-02 08:55:00'),
( 6, 'cancelada',  'Estado cambiado de ''pendiente'' a ''cancelada''.', '2026-08-10 14:20:00'),
( 7, 'creada', 'Reserva creada en estado pendiente.', '2026-09-25 20:10:00'),
( 7, 'confirmada', 'Estado cambiado de ''pendiente'' a ''confirmada''.', '2026-09-26 08:30:00'),
( 8, 'creada', 'Reserva creada en estado pendiente.', '2026-10-14 11:47:00'),
( 9, 'creada', 'Reserva creada en estado pendiente.', '2026-09-30 16:05:00'),
( 9, 'confirmada', 'Estado cambiado de ''pendiente'' a ''confirmada''.', '2026-10-01 10:00:00'),
( 9, 'cancelada',  'Estado cambiado de ''confirmada'' a ''cancelada''.', '2026-10-09 17:25:00'),
(10, 'creada', 'Reserva creada en estado pendiente.', '2026-10-01 09:12:00'),
(10, 'confirmada', 'Estado cambiado de ''pendiente'' a ''confirmada''.', '2026-10-01 11:40:00'),
(11, 'creada', 'Reserva creada en estado pendiente.', '2026-10-15 13:33:00'),
(12, 'creada', 'Reserva creada en estado pendiente.', '2026-06-20 19:00:00'),
(12, 'confirmada', 'Estado cambiado de ''pendiente'' a ''confirmada''.', '2026-06-21 09:00:00');

-- Podría decirle a la IA que me genere más datos de ejemplo pero con todos estos puedo probar todas las funcionalidades