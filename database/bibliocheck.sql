-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 09-02-2026 a las 14:58:20
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
  `qr_token` varchar(100) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `numero_control` varchar(50) DEFAULT NULL,
  `status` enum('Activo','Inactivo') NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `correo_electronico` varchar(100) DEFAULT NULL,
  `qr_semanal` varchar(255) DEFAULT NULL,
  `qr_unico` varchar(50) DEFAULT NULL,
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

INSERT INTO `alumnos` (`id`, `id_alumno`, `qr_token`, `nombre`, `numero_control`, `status`, `contrasena`, `telefono`, `correo_electronico`, `qr_semanal`, `qr_unico`, `qr_ultima_actualizacion`, `puesto`, `turno`, `horas_objetivo`, `horario_ruta`, `horario_json`, `semestre`, `carrera`) VALUES
(46, 'DOC001', '8e5dd5e990d9ad835871f9dabc3f3cdb', 'INTERIANO ZUNIGA, RODRIGO DE JESUS', '22270446', 'Activo', '$2y$10$eEfq6qNdgT92bBNFpqhPyuQWqKeBTXeLyrt8biVSK5Uf9o7EnlK2q', '9613217349', 'L22270446@tuxtla.tecnm.mx', NULL, NULL, NULL, 'RECEPCIÓN', 'Vespertino', 4, NULL, '{\"Lunes\":[[\"07:00\",\"09:00\"],[\"12:00\",\"17:00\"],[\"19:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"08:00\"],[\"12:00\",\"14:00\"],[\"15:00\",\"21:00\"]],\"Miércoles\":[[\"07:00\",\"08:00\"],[\"09:00\",\"10:00\"],[\"12:00\",\"13:00\"],[\"15:00\",\"17:00\"],[\"19:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"10:00\"],[\"12:00\",\"14:00\"],[\"15:00\",\"21:00\"]],\"Viernes\":[[\"07:00\",\"08:00\"],[\"14:00\",\"17:00\"],[\"18:00\",\"21:00\"]]}', '8', 'INGENIERIA EN SISTEMAS COMPUTACIONALES'),
(47, 'DOC002', '9f10aa1597b1e5d2146bcf4e5c43658b', 'HERNANDEZ LOPEZ, EDDY GERARDO', '23270087', 'Activo', '$2y$10$RX8487SqfkcWAnkxvT8kL.fVhRB9am48TnaW5lRIKU/xIY9RDY5GS', '', 'L23270087@tuxtla.tecnm.mx', NULL, NULL, NULL, 'ACERVO DE RESERVA', 'Vespertino', 4, NULL, '{\"Lunes\":[[\"07:00\",\"08:00\"],[\"16:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"10:00\"],[\"15:00\",\"21:00\"]],\"Miércoles\":[[\"07:00\",\"10:00\"],[\"16:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"09:00\"],[\"16:00\",\"21:00\"]],\"Viernes\":[[\"07:00\",\"09:00\"],[\"14:00\",\"15:00\"],[\"16:00\",\"21:00\"]]}', '7', 'INGENIERIA EN GESTION EMPRESARIAL 2023'),
(48, 'DOC003', '7b0a1ab1ec8e23357442da9086724837', 'HERNANDEZ SALINAS, SERGIO IVAN', '23270100', 'Activo', '$2y$10$gETaA20sShubhWI0pHxnrOljhavVlrMJH4veFF6/iT7yxFjX8OsWC', '', 'L23270100@tuxtla.tecnm.mx', NULL, NULL, NULL, 'SALA DE COMPUTO', 'Matutino', 4, NULL, '{\"Lunes\":[[\"07:00\",\"15:00\"]],\"Martes\":[[\"07:00\",\"14:00\"],[\"16:00\",\"17:00\"]],\"Miércoles\":[[\"07:00\",\"14:00\"],[\"17:00\",\"18:00\"],[\"19:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"13:00\"],[\"19:00\",\"21:00\"]],\"Viernes\":[[\"07:00\",\"18:00\"],[\"20:00\",\"21:00\"]]}', '7', 'INGENIERIA EN SISTEMAS COMPUTACIONALES'),
(49, 'DOC004', '2095d3f0ff1542bac476b1fb9448fd90', 'FERNANDEZ PEREZ, DANIELA PAULINA', '23270260', 'Activo', '$2y$10$jHGy0nodtmLRjbMbxRiAh.Izgo6hUtbameituf1V1vGP7G9ruqjTu', '', 'L23270260@tuxtla.tecnm.mx', NULL, NULL, NULL, 'HEMEROTECA', 'Matutino', 4, NULL, '{\"Lunes\":[[\"07:00\",\"14:00\"],[\"20:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"15:00\"],[\"19:00\",\"21:00\"]],\"Miércoles\":[[\"07:00\",\"14:00\"],[\"19:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"15:00\"],[\"19:00\",\"21:00\"]],\"Viernes\":[[\"07:00\",\"15:00\"],[\"20:00\",\"21:00\"]]}', '7', 'INGENIERIA EN GESTION EMPRESARIAL 2023'),
(50, 'DOC005', '7f93459011ccf0f2cd3d92d668cf9a8e', 'HERNANDEZ GARCIA, OCTAVIO ISMAEL', '22270792', 'Activo', '$2y$10$7hBJh7HAS/qjBz7bnHTa7..9Px77MvJcy6G2/lAwIt00WWcoa4.5.', '', 'L22270792@tuxtla.tecnm.mx', NULL, NULL, NULL, 'AREA DE CONSULTA', 'Matutino', 4, NULL, '{\"Lunes\":[[\"07:00\",\"14:00\"],[\"19:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"14:00\"],[\"19:00\",\"21:00\"]],\"Miércoles\":[[\"07:00\",\"13:00\"],[\"17:00\",\"18:00\"],[\"20:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"15:00\"]],\"Viernes\":[[\"07:00\",\"14:00\"],[\"18:00\",\"21:00\"]]}', '8', 'INGENIERIA MECANICA'),
(51, 'DOC006', '76d828d0caf9b7d6767dd0076042218b', 'GUTIERREZ MENESES, CARLOS EDUARDO', '22270781', 'Activo', '$2y$10$y.Of9JgEtiBe4R.S40uyau5Fza1m/S7oh3gbVGjN66Pi4VY4yuD3S', '', 'L22270781@tuxtla.tecnm.mx', NULL, NULL, NULL, 'HEMEROTECA', 'Matutino', 4, NULL, '{\"Lunes\":[[\"07:00\",\"14:00\"],[\"19:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"14:00\"],[\"19:00\",\"21:00\"]],\"Miércoles\":[[\"07:00\",\"13:00\"],[\"17:00\",\"18:00\"],[\"20:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"15:00\"]],\"Viernes\":[[\"07:00\",\"14:00\"],[\"18:00\",\"21:00\"]]}', '8', 'INGENIERIA MECANICA'),
(52, 'DOC007', 'c563a71301b1d248d915a251e2d55f71', 'GARCIA HERNANDEZ, GERARDO EMMANUEL', '22270528', 'Activo', '$2y$10$Q96hSnRJjz7hCdDx6pWwFOfHhkIRFFUhWQY.SjDnXusDXO.8FasoK', '', 'L22270528@tuxtla.tecnm.mx', NULL, NULL, NULL, 'OFICINA DE ORGANIZACIÓN BIBLIOGRAFICA', 'Matutino', 4, NULL, '{\"Lunes\":[[\"07:00\",\"14:00\"],[\"20:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"14:00\"]],\"Miércoles\":[[\"07:00\",\"14:00\"],[\"19:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"14:00\"]],\"Viernes\":[[\"07:00\",\"14:00\"]]}', '8', 'INGENIERIA EN SISTEMAS COMPUTACIONALES'),
(53, 'DOC008', '27c03c659118ff4b6e0e748a726acb98', 'PEREZ BANDA, CLAUDIA YARLETH', '23270300', 'Activo', '$2y$10$f0XWtufWfAc9R7DDqyxmOOLHOYxgLJrNqwzHZebACKbOkHDhGYtgW', '', 'L23270300@tuxtla.tecnm.mx', NULL, NULL, NULL, 'ACERVO DE RESERVA', 'Matutino', 4, NULL, '{\"Lunes\":[[\"07:00\",\"14:00\"],[\"20:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"15:00\"],[\"19:00\",\"21:00\"]],\"Miércoles\":[[\"07:00\",\"14:00\"],[\"19:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"15:00\"],[\"19:00\",\"21:00\"]],\"Viernes\":[[\"07:00\",\"15:00\"],[\"20:00\",\"21:00\"]]}', '7', 'INGENIERIA EN GESTION EMPRESARIAL 2023'),
(54, 'DOC009', '21e5a4a3f1338e6ca8e3e0e67ccd52cd', 'MARROQUIN MENDEZ, JUDITH DEL CARMEN', '21270646', 'Activo', '$2y$10$DmoWklU/oGiDeRgn10iOCeRirTSF0Tr/JuE3HGdcPJSQWLa5d86rm', '', 'L21270646@tuxtla.tecnm.mx', NULL, NULL, NULL, 'RECEPCIÓN', 'Mixto', 4, NULL, '{\"Lunes\":[[\"07:00\",\"10:00\"],[\"12:00\",\"18:00\"],[\"20:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"10:00\"],[\"12:00\",\"18:00\"]],\"Miércoles\":[[\"07:00\",\"10:00\"],[\"12:00\",\"16:00\"],[\"19:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"10:00\"],[\"12:00\",\"18:00\"]],\"Viernes\":[[\"07:00\",\"18:00\"]]}', '10', 'INGENIERIA EN SISTEMAS COMPUTACIONALES'),
(55, 'DOC010', 'f5dcc26d745b17a575e300b76a10e82f', 'MORALES JUAREZ, RICARDO KALEB', '22270494', 'Activo', '$2y$10$7w/tK/7.zFiA/z7ez.tM2O3lTWr3zxJDbjDYStTRB8Lm8GmB4Xta6', '', 'L22270494@tuxtla.tecnm.mx', NULL, NULL, NULL, 'ACERVO DE RESERVA', 'Matutino', 4, NULL, '{\"Lunes\":[[\"07:00\",\"15:00\"]],\"Martes\":[[\"07:00\",\"15:00\"]],\"Miércoles\":[[\"07:00\",\"15:00\"]],\"Jueves\":[[\"07:00\",\"15:00\"],[\"18:00\",\"21:00\"]],\"Viernes\":[[\"07:00\",\"14:00\"],[\"20:00\",\"21:00\"]]}', '8', 'INGENIERIA EN SISTEMAS COMPUTACIONALES'),
(56, 'DOC011', '70722b9742713ac4fb782a6341ac8072', 'RAYO SOTO, JOSE FRANCISCO', '22271135', 'Activo', '$2y$10$r7J58G.8U8jMfyeR9ny2fu6J49o/1LQoN9SXc.XQBk/jNxJp5Lhg6', '', 'L22271135@tuxtla.tecnm.mx', NULL, NULL, NULL, 'SALA DE COMPUTO', 'Matutino', 4, NULL, '{\"Lunes\":[[\"07:00\",\"12:00\"],[\"13:00\",\"14:00\"],[\"20:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"12:00\"],[\"16:00\",\"18:00\"]],\"Miércoles\":[[\"07:00\",\"12:00\"],[\"19:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"16:00\"]],\"Viernes\":[[\"07:00\",\"14:00\"],[\"16:00\",\"18:00\"]]}', '8', 'INGENIERIA EN SISTEMAS COMPUTACIONALES'),
(57, 'DOC012', '58b6e49b019fa4e848d62a01b798d951', 'HERNANDEZ MOLINA, DIANA LAURA', '23270268', 'Activo', '$2y$10$BlE/q7C5HC4cVRqVXukUI.y4L/7LrQ9TSPloqkfytKj5Bm7LlsZmC', '', 'L23270268@tuxtla.tecnm.mx', NULL, NULL, NULL, 'HEMEROTECA', 'Matutino', 4, NULL, '{\"Lunes\":[[\"07:00\",\"09:00\"],[\"11:00\",\"14:00\"],[\"20:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"15:00\"],[\"19:00\",\"21:00\"]],\"Miércoles\":[[\"07:00\",\"09:00\"],[\"11:00\",\"14:00\"],[\"19:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"15:00\"],[\"19:00\",\"21:00\"]],\"Viernes\":[[\"07:00\",\"10:00\"],[\"12:00\",\"15:00\"],[\"20:00\",\"21:00\"]]}', '7', 'INGENIERIA EN GESTION EMPRESARIAL 2023'),
(58, 'DOC013', '8388f159cce8fcf22bead4372a9a0989', 'LOPEZ SANCHEZ, DAMARIS JACQUELINE', '23270791', 'Activo', '$2y$10$GiruwBOOGH5ntzPfgw//dOZOLU.GSrcFa4bQQr2PrNA4YMD./LlXC', '', 'L23270791@tuxtla.tecnm.mx', NULL, NULL, NULL, 'ACERVO DE RESERVA', 'Vespertino', 4, NULL, '{\"Lunes\":[[\"07:00\",\"09:00\"],[\"14:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"10:00\"],[\"14:00\",\"21:00\"]],\"Miércoles\":[[\"07:00\",\"09:00\"],[\"14:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"08:00\"],[\"13:00\",\"21:00\"]],\"Viernes\":[[\"07:00\",\"09:00\"],[\"13:00\",\"21:00\"]]}', '6', 'INGENIERIA EN GESTION EMPRESARIAL 2023'),
(59, 'DOC014', '723635292ad89534103e36500c0dfb19', 'TREJO JUAREZ, SOFIA', '23270809', 'Activo', '$2y$10$02WqR.JOwLkkvWWvio4EGO45FJDk36y4RGPV13gvPKMRNdW/U/eDa', '', 'L23270809@tuxtla.tecnm.mx', NULL, NULL, NULL, 'HEMEROTECA', 'Vespertino', 4, NULL, '{\"Lunes\":[[\"07:00\",\"09:00\"],[\"14:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"10:00\"],[\"14:00\",\"21:00\"]],\"Miércoles\":[[\"07:00\",\"09:00\"],[\"14:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"08:00\"],[\"13:00\",\"21:00\"]],\"Viernes\":[[\"07:00\",\"09:00\"],[\"13:00\",\"21:00\"]]}', '6', 'INGENIERIA EN GESTION EMPRESARIAL 2023'),
(60, 'DOC015', '0d0ece4a2a687a1152b01f963d5a0ecb', 'GUTIERREZ ROMERO, KEVIN BRIAN', '22270491', 'Activo', '$2y$10$KM8wrLlWFGMeNkoTik7duOLOajRmhlzDZGZyYeKkwMLfYOqy4P73O', '', 'L22270491@tuxtla.tecnm.mx', NULL, NULL, NULL, 'SALA DE COMPUTO', 'Matutino', 4, NULL, '{\"Lunes\":[[\"07:00\",\"09:00\"],[\"11:00\",\"17:00\"]],\"Martes\":[[\"07:00\",\"08:00\"],[\"10:00\",\"14:00\"],[\"18:00\",\"21:00\"]],\"Miércoles\":[[\"07:00\",\"14:00\"],[\"19:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"13:00\"]],\"Viernes\":[[\"07:00\",\"09:00\"],[\"10:00\",\"14:00\"],[\"16:00\",\"21:00\"]]}', '8', 'INGENIERIA EN SISTEMAS COMPUTACIONALES'),
(61, 'DOC016', 'b6702de90c802657726bfca08c53c4c1', 'ARCOS NORIEGA, KARLA JULIA', '23270258', 'Activo', '$2y$10$fmXS6M..FIdpKxBgOPf3m.scp3RHLZzqe3d9.OH13BNj3m3rPd28m', '', 'L23270258@tuxtla.tecnm.mx', NULL, NULL, NULL, 'ACERVO DE RESERVA', 'Matutino', 4, NULL, '{\"Lunes\":[[\"07:00\",\"14:00\"],[\"20:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"15:00\"],[\"19:00\",\"21:00\"]],\"Miércoles\":[[\"07:00\",\"14:00\"],[\"19:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"15:00\"],[\"19:00\",\"21:00\"]],\"Viernes\":[[\"07:00\",\"15:00\"],[\"20:00\",\"21:00\"]]}', '7', 'INGENIERIA EN GESTION EMPRESARIAL 2023'),
(62, 'DOC017', '3bfcc654154c0bb444161fef6790b67a', 'DE LOS SANTOS MENDOZA, NICOLE GUADALUPE', '23270824', 'Activo', '$2y$10$HGrFdyGmuE2Qyyd8Wlxwwu5jdjcqHp4mrY0C059Hx1yQuVxAHDZjq', '', 'L23270824@tuxtla.tecnm.mx', NULL, NULL, NULL, 'HEMEROTECA', 'Vespertino', 4, NULL, '{\"Lunes\":[[\"07:00\",\"09:00\"],[\"14:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"10:00\"],[\"14:00\",\"21:00\"]],\"Miércoles\":[[\"07:00\",\"09:00\"],[\"14:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"08:00\"],[\"13:00\",\"21:00\"]],\"Viernes\":[[\"07:00\",\"09:00\"],[\"13:00\",\"21:00\"]]}', '6', 'INGENIERIA EN GESTION EMPRESARIAL 2023'),
(63, 'DOC018', '6afe45c404b1feb191bf9df2ce0c44e8', 'BONIFAZ TAPIA, JONATHAN ALEJANDRO', '22270460', 'Activo', '$2y$10$Ipbzvd.yW3lO1Ua9JTBX/uUQfOoMawEE/BAgvE3t3wyQwob9VjpmW', '', 'L22270460@tuxtla.tecnm.mx', NULL, NULL, NULL, 'HEMEROTECA', 'Matutino', 4, NULL, '{\"Lunes\":[[\"07:00\",\"14:00\"],[\"20:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"14:00\"]],\"Miércoles\":[[\"07:00\",\"14:00\"],[\"19:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"14:00\"]],\"Viernes\":[[\"07:00\",\"14:00\"]]}', '8', 'INGENIERIA EN SISTEMAS COMPUTACIONALES'),
(64, 'DOC019', '561a537290e255f5e08a38e4615c0d08', 'VICENTE CADENA, DIEGO EDUARDO', '23270072', 'Activo', '$2y$10$XnRJf00Lr7xJviVm2QiTe./09YLmvY8X69Ho/21kl6TFP4pf.Kf/C', '', 'L23270072@tuxtla.tecnm.mx', NULL, NULL, NULL, 'OFICINA DE ORGANIZACIÓN BIBLIOGRAFICA', 'Matutino', 4, NULL, '{\"Lunes\":[[\"07:00\",\"10:00\"],[\"13:00\",\"14:00\"],[\"20:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"12:00\"],[\"16:00\",\"18:00\"],[\"20:00\",\"21:00\"]],\"Miércoles\":[[\"07:00\",\"12:00\"],[\"16:00\",\"18:00\"],[\"19:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"10:00\"],[\"12:00\",\"16:00\"],[\"20:00\",\"21:00\"]],\"Viernes\":[[\"07:00\",\"09:00\"],[\"10:00\",\"14:00\"],[\"16:00\",\"18:00\"],[\"20:00\",\"21:00\"]]}', '7', 'INGENIERIA EN SISTEMAS COMPUTACIONALES'),
(65, 'DOC020', '2a22c977ca6515296794f4f60118f8a5', 'VILLALOBOS DOMINGUEZ, GIOVANI YANICK', '23270054', 'Activo', '$2y$10$2gIXMhswirVBIMO55q0yYujIrFqht040Nu0f7NjUDdt9lKn7IWBzS', '', 'L23270054@tuxtla.tecnm.mx', NULL, NULL, NULL, 'SALA DE COMPUTO', 'Matutino', 4, NULL, '{\"Lunes\":[[\"07:00\",\"14:00\"],[\"20:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"14:00\"]],\"Miércoles\":[[\"07:00\",\"14:00\"],[\"19:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"14:00\"]],\"Viernes\":[[\"07:00\",\"14:00\"]]}', '7', 'INGENIERIA EN SISTEMAS COMPUTACIONALES'),
(66, 'DOC021', 'f00285cf1b42cb070a604459d5dc7b63', 'CRUZ CORTEZ, DARINKA ARITZEL', '22270025', 'Activo', '$2y$10$dLw1qZP5mbwMr.6XMPX4UOmXCc3ngKI4hmI7FyJEtaPLn/LlYpINW', '', 'L22270025@tuxtla.tecnm.mx', NULL, NULL, NULL, 'HEMEROTECA', 'Matutino', 4, NULL, '{\"Lunes\":[[\"07:00\",\"14:00\"],[\"20:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"14:00\"]],\"Miércoles\":[[\"07:00\",\"14:00\"],[\"19:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"14:00\"]],\"Viernes\":[[\"07:00\",\"14:00\"]]}', '9', 'INGENIERIA EN SISTEMAS COMPUTACIONALES'),
(67, 'DOC022', '4347837feb22e86c7b5a251bc5bb4360', 'HERNANDEZ GONZALEZ, MARIA FERNANDA', '23270058', 'Activo', '$2y$10$Q6mHIMb8ldEoSggTfwCWMuySEbhLUsPtUR6vf7hfrY1MPn/CMQGHu', '', 'L23270058@tuxtla.tecnm.mx', NULL, NULL, NULL, 'ACERVO DE RESERVA', 'Matutino', 4, NULL, '{\"Lunes\":[[\"07:00\",\"14:00\"],[\"20:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"14:00\"]],\"Miércoles\":[[\"07:00\",\"14:00\"],[\"19:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"14:00\"]],\"Viernes\":[[\"07:00\",\"14:00\"]]}', '7', 'INGENIERIA EN SISTEMAS COMPUTACIONALES'),
(68, 'DOC023', 'e7550e95098633ab2de3d39c2c03a2cc', 'CRUZ MARTINEZ, CRISTOBAL', '22270448', 'Activo', '$2y$10$dOvFdEQQYPSsS0j8OuB.tulHr6R6zYX25jN/W9JVzdwKEewpyuS92', '', 'L22270448@tuxtla.tecnm.mx', NULL, NULL, NULL, 'ACERVO DE RESERVA', 'Matutino', 4, NULL, '{\"Lunes\":[[\"07:00\",\"15:00\"]],\"Martes\":[[\"07:00\",\"15:00\"]],\"Miércoles\":[[\"07:00\",\"15:00\"]],\"Jueves\":[[\"07:00\",\"15:00\"],[\"18:00\",\"21:00\"]],\"Viernes\":[[\"07:00\",\"14:00\"],[\"20:00\",\"21:00\"]]}', '8', 'INGENIERIA EN SISTEMAS COMPUTACIONALES'),
(69, 'DOC024', '1a0f36fe837a531c888e6c587eea6042', 'MEDINA CERVANTES, JULIO ALEJANDRO', '21270156', 'Activo', '$2y$10$ESAqsSDzsf/g92N4vjtlp.BRrmzVm9uHlnKbRfPdyxz3YnOX4JDyS', '', 'L21270156@tuxtla.tecnm.mx', NULL, NULL, NULL, 'RECEPCIÓN', 'Mixto', 4, NULL, '{\"Lunes\":[[\"07:00\",\"08:00\"],[\"10:00\",\"14:00\"],[\"16:00\",\"17:00\"],[\"19:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"12:00\"],[\"14:00\",\"15:00\"],[\"16:00\",\"21:00\"]],\"Miércoles\":[[\"07:00\",\"08:00\"],[\"10:00\",\"12:00\"],[\"14:00\",\"15:00\"],[\"16:00\",\"18:00\"],[\"19:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"09:00\"],[\"10:00\",\"13:00\"],[\"17:00\",\"18:00\"],[\"20:00\",\"21:00\"]],\"Viernes\":[[\"07:00\",\"18:00\"],[\"20:00\",\"21:00\"]]}', '11', 'INGENIERIA EN SISTEMAS COMPUTACIONALES'),
(70, 'DOC025', '3533146aff430b43e89b3b26c2bf5832', 'HERNANDEZ RODRIGUEZ, ANTONIO DE JESUS', '22270785', 'Activo', '$2y$10$y0zsDN2I2uGvJvtKX31/uuCaboW2tRN3sM7bj9UG1D.Afznlliz86', '', 'L22270785@tuxtla.tecnm.mx', NULL, NULL, NULL, 'RECEPCIÓN', 'Matutino', 4, NULL, '{\"Lunes\":[[\"07:00\",\"14:00\"],[\"19:00\",\"21:00\"]],\"Martes\":[[\"07:00\",\"14:00\"],[\"19:00\",\"21:00\"]],\"Miércoles\":[[\"07:00\",\"13:00\"],[\"17:00\",\"18:00\"],[\"20:00\",\"21:00\"]],\"Jueves\":[[\"07:00\",\"15:00\"]],\"Viernes\":[[\"07:00\",\"14:00\"],[\"18:00\",\"21:00\"]]}', '8', 'INGENIERIA MECANICA');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumnos_puestos`
--

CREATE TABLE `alumnos_puestos` (
  `id_alumno` varchar(6) NOT NULL,
  `id_puesto` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `tipo` enum('entrada','salida') DEFAULT NULL,
  `foto_ruta` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `qr_token` (`qr_token`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

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
