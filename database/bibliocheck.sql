-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 25-11-2025 a las 16:11:55
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
  `horario_ruta` varchar(255) DEFAULT NULL,
  `horario_json` text DEFAULT NULL,
  `semestre` varchar(100) DEFAULT NULL,
  `carrera` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `alumnos`
--

INSERT INTO `alumnos` (`id`, `id_alumno`, `nombre`, `numero_control`, `status`, `contrasena`, `telefono`, `correo_electronico`, `qr_semanal`, `qr_ultima_actualizacion`, `puesto`, `horario_ruta`, `horario_json`, `semestre`, `carrera`) VALUES
(6, 'DOC001', 'Juan Perez', 'CP123', 'Activo', '$2y$10$1wfrhnl/Dfbt7j9e7nnIhu0izlCtG6f2zYtzhGyvvEwO3SnuQSmwm', '9312123121', 'Juan.perez@gmail.com', 'd9d902d2fdca0e824bc79e51ad227b44', '2025-05-01 19:58:24', '', NULL, NULL, NULL, NULL),
(7, 'DOC002', 'Pedro Sanchez', 'CEP321', 'Activo', '$2y$10$CeABfd5kWGyklC/rGubAAeLq.QVA6G2FOuCDol8tK89qKqqxUwG76', '993123012391', 'Pedro.sanchez@gmail.com', 'fdfaf4ca6246e0259f55ba1f2c13d050', '2025-05-02 02:31:48', '', '/uploads/horarios/7/horario.pdf', '{\"Lunes\":[[\"07:00\",\"14:00\"],[\"20:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"14:00\"],[\"18:00\",\"21:00\"]],\"Miércoles\":[[\"07:00\",\"15:00\"]],\"Jueves\":[[\"07:00\",\"15:00\"],[\"20:00\",\"21:00\"]],\"Viernes\":[[\"07:00\",\"14:00\"],[\"20:00\",\"21:00\"]],\"Sábado\":[[\"07:00\",\"21:00\"]]}', '', ''),
(12, 'DOC004', 'rodrigo', '22270446', 'Activo', '$2y$10$KxIpgg/Ub/YQrinEUu.ZG.A1/JDt94X00jVfZi4/YTsF2VKn3KLiS', '9613267349', 'L22270446@gmail.com', '6cab61d54c69e33955685a932190b659', '2025-11-24 19:31:01', 'recepcion', '/uploads/horarios/12/horario.pdf', '{\"Lunes\":[[\"07:00\",\"14:00\"],[\"20:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"14:00\"],[\"18:00\",\"21:00\"]],\"Miércoles\":[[\"07:00\",\"15:00\"]],\"Jueves\":[[\"07:00\",\"15:00\"],[\"20:00\",\"21:00\"]],\"Viernes\":[[\"07:00\",\"14:00\"],[\"20:00\",\"21:00\"]],\"Sábado\":[[\"07:00\",\"21:00\"]]}', 'septimo', 'ing. sistemas computacionales');

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
('DOC001', 1),
('DOC002', 2),
('DOC004', 3);

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
(1, 12, 3, '2025-11-25', '09:01:26', 'entrada');

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
(1, 'Matemáticas Avanzadas'),
(2, 'Programación Web'),
(3, 'RECEPCIÓN');

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
(4, 'LISSETTE ESCOBAR RAMÍREZ ', 'LissetteER', '$2y$10$aOYcYzuOGsZxYfnwl7gY6e29pRyaKpGovXIImvyeW/Ew6sKEK.E7u', 'admin', '2025-11-23 17:26:53', 'activo');

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
-- Indices de la tabla `puestos`
--
ALTER TABLE `puestos`
  ADD PRIMARY KEY (`id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `asistencia`
--
ALTER TABLE `asistencia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `documentos`
--
ALTER TABLE `documentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `puestos`
--
ALTER TABLE `puestos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
