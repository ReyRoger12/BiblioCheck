-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 06-02-2026 a las 21:09:37
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `bibliocheck`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumnos`
--

CREATE TABLE `alumnos` (
  `id` int(11) NOT NULL,
  `id_alumno` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `numero_control` varchar(50) DEFAULT NULL,
  `status` enum('Activo','Inactivo') NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `correo_electronico` varchar(100) DEFAULT NULL,
  `qr_semanal` varchar(255) DEFAULT NULL,
  `qr_ultima_actualizacion` datetime DEFAULT NULL,
  `puesto` varchar(255) NOT NULL DEFAULT '',
  `turno` enum('Matutino','Vespertino','Mixto') DEFAULT 'Matutino',
  `horas_objetivo` int(11) DEFAULT 4,
  `horario_ruta` varchar(255) DEFAULT NULL,
  `horario_json` text DEFAULT NULL,
  `semestre` varchar(100) DEFAULT NULL,
  `carrera` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `alumnos`
--

INSERT INTO `alumnos` (`id`, `id_alumno`, `nombre`, `numero_control`, `status`, `contrasena`, `telefono`, `correo_electronico`, `qr_semanal`, `qr_ultima_actualizacion`, `puesto`, `turno`, `horas_objetivo`, `horario_ruta`, `horario_json`, `semestre`, `carrera`) VALUES
(17, 'DOC000', 'BONIFAZ TAPIA, JONATHAN ALEJANDRO', '22270460', 'Activo', '$2y$10$26j/0O4zTBd50ESEt16rQOxf/oKAhGu2Qkrc6MyarFIGpv6nk/Lby', '9613031757', 'L22270460@TUXTLA.TECNM.MX', 'bc22e7329f5bb5c0172d7f22d3d77c0d', '2026-01-30 16:04:07', 'HEMEROTEC', 'Matutino', 5, '/uploads/horarios/17/horario.pdf', '{\"Lunes\":[[\"07:00\",\"14:00\"],[\"20:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"14:00\"]],\"Miércoles\":[[\"07:00\",\"14:00\"],[\"19:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"14:00\"]],\"Viernes\":[[\"07:00\",\"14:00\"]]}', '', 'INGENIERIA EN SISTEMAS COMPUTACIONALES'),
(20, 'DOC005', 'INTERIANO ZUNIGA, RODRIGO DE JESUS', '22270446', 'Activo', '$2y$10$tEFW6sPfT0aRt6jOqVD5f.q2hxq3MIRlS4DHZ8UNl53qfKSbezMR.', '9613267349', 'L22270446@TUXTLA.TECNM.MX', '395042aeeeab0e567cb545a34c9741d6', '2026-01-28 16:08:47', 'RECEPCION', 'Vespertino', 4, '/uploads/horarios/20/horario.pdf', '{\"Lunes\":[[\"07:00\",\"09:00\"],[\"12:00\",\"17:00\"],[\"19:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"08:00\"],[\"12:00\",\"14:00\"],[\"15:00\",\"21:00\"]],\"Miércoles\":[[\"07:00\",\"08:00\"],[\"09:00\",\"10:00\"],[\"12:00\",\"13:00\"],[\"15:00\",\"17:00\"],[\"19:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"10:00\"],[\"12:00\",\"14:00\"],[\"15:00\",\"21:00\"]],\"Viernes\":[[\"07:00\",\"08:00\"],[\"14:00\",\"17:00\"],[\"18:00\",\"21:00\"]]}', '', 'INGENIERIA EN SISTEMAS COMPUTACIONALES'),
(23, 'DOC003', 'MARROQUIN MENDEZ, JUDITH DEL CARMEN', '21270646', 'Activo', '$2y$10$ZqLkUVSii2OeZqW.aAaSP.FZP9cWc7vdPPNsprZEu1LloGzZCOt4u', '9612138205', 'L21270646@TUXTLA.TECNM.MX', NULL, NULL, 'RECEPCION', 'Mixto', 4, '/uploads/horarios/23/horario.pdf', '{\"Lunes\":[[\"07:00\",\"10:00\"],[\"12:00\",\"18:00\"],[\"20:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"10:00\"],[\"12:00\",\"18:00\"]],\"Miércoles\":[[\"07:00\",\"10:00\"],[\"12:00\",\"16:00\"],[\"19:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"10:00\"],[\"12:00\",\"18:00\"]],\"Viernes\":[[\"07:00\",\"18:00\"]]}', '10', 'INGENIERIA EN SISTEMAS COMPUTACIONALES'),
(24, 'DOC006', 'MEDINA CERVANTES, JULIO ALEJANDRO', '21270156', 'Activo', '$2y$10$JA.VYl9jbtxS.rZ6vdg1Eu83v6Ozjnq/90TzRDCvXuvVIbZXRXUwi', '9617017722', 'L21270156@TUXTLA.TECNM.MX', '025935ecf59be31e6a507a4ba116d526', '2026-01-28 19:43:08', 'RECEPCION', 'Mixto', 4, '/uploads/horarios/24/horario.pdf', '{\"Lunes\":[[\"07:00\",\"08:00\"],[\"10:00\",\"14:00\"],[\"16:00\",\"17:00\"],[\"19:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"12:00\"],[\"14:00\",\"15:00\"],[\"16:00\",\"21:00\"]],\"Miércoles\":[[\"07:00\",\"08:00\"],[\"10:00\",\"12:00\"],[\"14:00\",\"15:00\"],[\"16:00\",\"18:00\"],[\"19:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"09:00\"],[\"10:00\",\"13:00\"],[\"17:00\",\"18:00\"],[\"20:00\",\"21:00\"]],\"Viernes\":[[\"07:00\",\"18:00\"],[\"20:00\",\"21:00\"]]}', '11', 'INGENIERIA EN SISTEMAS COMPUTACIONALES');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumnos_puestos`
--

CREATE TABLE `alumnos_puestos` (
  `id_alumno` varchar(6) NOT NULL,
  `id_puesto` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `alumnos_puestos`
--

INSERT INTO `alumnos_puestos` (`id_alumno`, `id_puesto`) VALUES
('DOC000', 4),
('DOC005', 5),
('DOC003', 5);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencia`
--

CREATE TABLE `asistencia` (
  `id` int(11) NOT NULL,
  `alumno_id` int(11) DEFAULT NULL,
  `puesto_id` int(11) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `hora` time DEFAULT NULL,
  `tipo` enum('entrada','salida') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `asistencia`
--

INSERT INTO `asistencia` (`id`, `alumno_id`, `puesto_id`, `fecha`, `hora`, `tipo`) VALUES
(3, 17, 4, '2026-01-28', '13:03:34', 'entrada'),
(5, 20, 5, '2026-01-28', '16:10:14', 'entrada'),
(6, 20, 5, '2026-01-28', '19:45:39', 'salida'),
(7, 17, 4, '2026-01-28', '19:46:47', 'salida'),
(8, 17, 4, '2026-01-30', '16:51:24', 'entrada'),
(9, 20, 5, '2026-01-30', '16:52:59', 'entrada'),
(10, 20, 5, '2026-01-30', '17:12:07', 'salida'),
(11, 20, 5, '2026-01-30', '17:12:23', 'entrada'),
(12, 17, 4, '2026-01-30', '17:13:37', 'salida'),
(13, 20, 5, '2026-01-30', '17:32:07', 'salida'),
(14, 17, 4, '2026-01-30', '18:06:33', 'entrada');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documentos`
--

CREATE TABLE `documentos` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `ruta` varchar(500) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `fecha_subida` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos`
--

CREATE TABLE `permisos` (
  `id` int(11) NOT NULL,
  `alumno_id` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `motivo` text NOT NULL,
  `archivo_ruta` varchar(500) DEFAULT NULL,
  `estado` enum('pendiente','aprobado','rechazado') DEFAULT 'aprobado',
  `fecha_creacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `puestos`
--

CREATE TABLE `puestos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `puestos`
--

INSERT INTO `puestos` (`id`, `nombre`) VALUES
(3, 'RECEPCIÓN'),
(4, 'HEMEROTECA'),
(5, 'RECEPCION');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `puestos_trabajo`
--

CREATE TABLE `puestos_trabajo` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `puestos_trabajo`
--

INSERT INTO `puestos_trabajo` (`id`, `nombre`) VALUES
(1, 'RECEPCIÓN'),
(2, 'HEMEROTECA'),
(3, 'SALA DE COMPUTO'),
(4, 'OFICINA DE ORGANIZACIÓN BIBLIOGRAFICA'),
(5, 'JEFATURA'),
(6, 'ACERVO DE RESERVA'),
(7, 'AREA DE CONSULTA');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `registros_manuales`
--

CREATE TABLE `registros_manuales` (
  `id` int(11) NOT NULL,
  `alumno_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `entrada` time NOT NULL,
  `salida` time DEFAULT '20:00:00',
  `motivo` varchar(255) DEFAULT 'Registro Manual'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `tipo_usuario` enum('admin','docente') NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado` enum('activo','inactivo') DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `usuario`, `contrasena`, `tipo_usuario`, `fecha_creacion`, `estado`) VALUES
(3, 'Juan Ramirez ', 'JuanRa', '$2y$10$W6KbVKWA6RO.0asjXZye..z/nlARxluESsFZYmL6DoWGUvcge2tn.', 'admin', '2025-04-29 19:17:25', 'activo'),
(5, 'LISSETTE ESCOBAR RAMÍREZ', 'LissetteER', '$2y$10$G.DADYqgkmjN20mWZm3b..e9kd4/IXcvfwzCMhWI.k4akWkEhfE.u', 'admin', '2026-01-28 18:37:04', 'activo'),
(6, 'LISSETTE ESCOBAR RAMÍREZ', 'LissetteER', '$2y$10$mKyGsP/aIlrJ0Ulc3w9Ji.Ixq34Oua8m11w.FUjCzP6pLhvRcV/J6', 'admin', '2026-01-28 18:37:32', 'activo'),
(7, 'LUNA SNOWBALL HERNANDEZ', 'LunaSH', '$2y$10$uIy0HPmdx6kDBciNN6Ok8OqlQaRtF2iw7wlzy4hGaiEYSipTPEK3e', 'admin', '2026-01-28 18:40:37', 'activo');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `alumnos`
--
ALTER TABLE `alumnos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `asistencia`
--
ALTER TABLE `asistencia`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `documentos`
--
ALTER TABLE `documentos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `alumno_id` (`alumno_id`);

--
-- Indices de la tabla `puestos`
--
ALTER TABLE `puestos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `puestos_trabajo`
--
ALTER TABLE `puestos_trabajo`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `registros_manuales`
--
ALTER TABLE `registros_manuales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `alumno_id` (`alumno_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `alumnos`
--
ALTER TABLE `alumnos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT de la tabla `asistencia`
--
ALTER TABLE `asistencia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `documentos`
--
ALTER TABLE `documentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `permisos`
--
ALTER TABLE `permisos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `puestos`
--
ALTER TABLE `puestos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `puestos_trabajo`
--
ALTER TABLE `puestos_trabajo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `registros_manuales`
--
ALTER TABLE `registros_manuales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD CONSTRAINT `fk_permiso_alumno` FOREIGN KEY (`alumno_id`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `registros_manuales`
--
ALTER TABLE `registros_manuales`
  ADD CONSTRAINT `registros_manuales_ibfk_1` FOREIGN KEY (`alumno_id`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
