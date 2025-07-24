-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 18-07-2025 a las 20:25:09
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
-- Base de datos: `sisec`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `dispositivos`
--

CREATE TABLE `dispositivos` (
  `id` int(11) NOT NULL,
  `equipo` varchar(255) DEFAULT NULL,
  `fecha` date NOT NULL,
  `modelo` varchar(100) NOT NULL,
  `estado` varchar(50) NOT NULL,
  `sucursal` varchar(100) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `serie` varchar(100) DEFAULT NULL,
  `mac` varchar(100) DEFAULT NULL,
  `vms` varchar(100) DEFAULT NULL,
  `servidor` varchar(100) DEFAULT NULL,
  `switch` varchar(100) DEFAULT NULL,
  `puerto` varchar(50) DEFAULT NULL,
  `area` varchar(100) DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `imagen2` varchar(255) DEFAULT NULL,
  `imagen3` varchar(255) DEFAULT NULL,
  `qr` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `dispositivos`
--

INSERT INTO `dispositivos` (`id`, `equipo`, `fecha`, `modelo`, `estado`, `sucursal`, `observaciones`, `serie`, `mac`, `vms`, `servidor`, `switch`, `puerto`, `area`, `imagen`, `imagen2`, `imagen3`, `qr`) VALUES
(1, 'Joystick', '2025-06-02', 'N/A', 'Activo', 'Suburbia Campeche', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'joystick.png', 'img2_68752101da1ef.png', 'img3_68752101da419.png', 'qr_1.png'),
(2, 'DVR', '2025-06-02', 'N/A', 'Activo', 'Suburbia Campeche', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'DVR.png', 'img2_6875228b861a5.png', 'img3_6875228b863dd.png', 'qr_2.png'),
(3, 'DVR (conexiones)', '2025-06-02', 'N/A', 'Activo', 'Suburbia Campeche', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'img1_68752566f173b.png', 'img2_68752566f18bb.png', 'img3_68752566f1a80.png', 'qr_3.png'),
(4, 'NVR', '2025-06-02', 'N/A', 'Activo', 'Suburbia Campeche', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'img1_68752637a1c0b.png', 'img2_68752637a1d54.png', 'img3_68752637a1e68.png', 'qr_4.png'),
(5, 'NVR (conexiones)', '2025-06-02', 'N/A', 'Activo', 'Suburbia Campeche', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'img1_6875273fb714c.png', 'img2_6875273fb7326.png', 'img3_6875273fb741b.png', 'qr_5.png'),
(6, 'Rack', '2025-06-02', 'N/A', 'Activo', 'Suburbia Campeche', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'img1_687527e552bae.png', 'img2_687527e552d6f.png', 'img3_687527e553141.png', 'qr_6.png'),
(7, 'Monitor', '2025-06-02', 'N/A', 'Activo', 'Suburbia Campeche', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'CAJA GRAL', 'img1_687528806335d.png', 'img2_6875288063530.png', 'img3_6875288063739.png', 'qr_7.png'),
(8, 'Monitor', '2025-06-02', 'N/A', 'Activo', 'Suburbia Campeche', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'ENTRADA', 'img1_6875290c8e24a.png', 'img2_6875290c8e372.png', 'img3_6875290c8e421.png', 'qr_8.png'),
(9, 'Fuentes de camaras', '2025-06-02', 'N/A', 'Activo', 'Suburbia Campeche', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'img1_6875297f078d9.png', 'img2_6875297f07b15.png', 'img3_6875297f07ca6.png', 'qr_9.png'),
(10, 'Cámara', '2025-06-02', 'SCV-6085R', 'Activo', 'Suburbia Campeche', 'DOMO 1 M1', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'CAJA GENERAL', 'img1_68752a57b2892.png', 'img2_68752a57b29da.png', 'img3_68752a57b2a98.png', 'qr_10.png'),
(11, 'Cámara ', '2025-06-02', 'SCV-6085R', 'Activo', 'Suburbia Campeche', 'DOMO 2 M1', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'PERFUMERÍA', 'img1_68752b8b434ca.png', 'img2_68752b8b437a4.png', 'img3_68752b8b43870.png', 'qr_11.png'),
(12, 'Cámara', '2025-06-02', 'SCP-3371', 'Activo', 'Suburbia Campeche', 'DOMO 3 M1 PTZ', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'TELEFONIA Y HARLINE ', 'img1_68752cbcdfdb2.png', 'img2_68752cbcdff36.png', 'img3_68752cbce002a.png', 'qr_12.png'),
(13, 'Camara', '2025-06-02', 'SCP-3371', 'Activo', 'Suburbia Campeche', 'DOMO 4 M1 PTZ', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'PTZ PERFUMERIA', 'img1_6875319af1e21.png', 'img2_6875319af1f68.png', 'img3_6875319af201f.png', 'qr_13.png'),
(14, 'Camara', '2025-06-02', 'SCV-6085R', 'Activo', 'Suburbia Campeche', 'DOMO 5 M1', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'DEPORTES CABALLEROS', 'img1_68759284144f1.png', 'img2_6875928414944.png', 'img3_6875928414ace.png', 'qr_14.png'),
(15, 'Camara', '2025-06-02', 'SCV-6085R', 'Activo', 'Suburbia Campeche', 'DOMO 6 M1', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'CABALLEROS CASUAL', 'img1_687671aebeb4e.png', 'img2_687671aebefe8.png', 'img3_687671aebf0d9.png', 'qr_15.png'),
(16, 'Cámara', '2025-06-02', 'SCV-6085R', 'Activo', 'Suburbia Campeche', 'DOMO 7 M1', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'ACC CLIENTES', 'img1_6876724cd4d67.png', 'img2_6876724cd4ede.png', 'img3_6876724cd4fc6.png', 'qr_16.png'),
(17, 'Cámara', '2025-06-02', 'SCV-6085R', 'Activo', 'Suburbia Campeche', 'DOMO 8 M1', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'ESTACIONAMIENTO', 'img1_687672de44f8d.png', 'img2_687672de451a9.png', 'img3_687672de452b1.png', 'qr_17.png'),
(18, 'Cámara', '2025-07-15', 'SCV-6085R', 'Activo', 'Puebla', 'Domo', '2', '00:11', 'Avigilion', '1', 'Cisco', '10', 'PERFUMERÍA', 'img1_687694a5c69df.png', 'img2_687694a5c6fe2.png', 'img3_687694a5c7375.png', 'qr_18.png'),
(19, 'Camara', '2025-07-15', 'TZ3039', 'Activo', 'Suburbia Campeche', 'Nuevo', '3', '00:22', 'Avigilion', '2', '2', '10', 'Perfumería', 'img1_68769a8bbb676.png', 'img2_68769a8bbbbc2.png', 'img3_68769a8bbc135.png', 'qr_19.png'),
(20, 'Prueba', '2025-07-18', 'Prueba', 'Activo', 'Prueba', 'Prueba', '1', '1', '1', '1', '1', '4f', 'ggg', 'img1_687a8c194a667.png', 'img2_687a8c194ada1.png', 'img3_687a8c194e4e4.png', 'qr_20.png');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL,
  `mensaje` text NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `vista` tinyint(1) DEFAULT 0,
  `fecha` datetime DEFAULT current_timestamp(),
  `visto` tinyint(1) NOT NULL DEFAULT 0,
  `dispositivo_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `notificaciones`
--

INSERT INTO `notificaciones` (`id`, `mensaje`, `usuario_id`, `vista`, `fecha`, `visto`, `dispositivo_id`) VALUES
(1, 'El técnico Prueba registró un nuevo dispositivo.', 12, 0, '2025-07-07 21:38:06', 1, NULL),
(4, 'El técnico Prueba registró un nuevo dispositivo.', 12, 0, '2025-07-07 15:53:39', 1, 27),
(5, 'El técnico Prueba modificó el dispositivo con ID #25.', 12, 0, '2025-07-07 15:53:54', 1, 25),
(6, 'El Mantenimientos Mantenimientos registró un nuevo dispositivo.', 20, 0, '2025-07-14 10:34:34', 1, 13),
(7, 'El Mantenimientos Patricia modificó el dispositivo con ID #13.', 21, 0, '2025-07-14 10:37:12', 1, 13),
(8, 'El Mantenimientos Mantenimientos modificó el dispositivo con ID #20.', 20, 0, '2025-07-18 12:09:52', 1, 20);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `clave` varchar(255) DEFAULT NULL,
  `rol` varchar(50) NOT NULL,
  `foto` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `clave`, `rol`, `foto`) VALUES
(7, 'Administrador', '$2y$10$02DVVf93s6YtmVqy5O8g0OaV7D3r1ylzp3rdsiyjjUO6Y3HM5zdsm', 'Administrador', 'usr_6871458a5503c.png'),
(20, 'Mantenimientos', '$2y$10$Be9RMNtWiqn83RAAJKZMrOSqsaXyxVw83rmpryyzzLEWVhNq6R06i', 'Mantenimientos', 'usr_687530aaa4247.jpg'),
(21, 'Patricia', '$2y$10$5J5M0daluPK1OK2IngbUsujVamk4k9nSU.9jq5kd8RnURe25vqZFq', 'Mantenimientos', 'usr_687531fb91e89.jpg'),
(22, 'Invitado', '$2y$10$JYiwq34DbsA7aUfTCR9FYeCE8BlOU94FPDKdjHXSf.zm4WtBEsSBa', 'Invitado', NULL);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `dispositivos`
--
ALTER TABLE `dispositivos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
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
-- AUTO_INCREMENT de la tabla `dispositivos`
--
ALTER TABLE `dispositivos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
