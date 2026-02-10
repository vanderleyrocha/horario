-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Tempo de geração: 01/02/2026 às 00:04
-- Versão do servidor: 8.0.44-0ubuntu0.24.04.2
-- Versão do PHP: 8.4.17

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `u815349007_decagono`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `turmas`
--

CREATE TABLE `turmas` (
  `id` bigint UNSIGNED NOT NULL,
  `escola_id` bigint UNSIGNED NOT NULL,
  `nome` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ano` smallint UNSIGNED NOT NULL,
  `modalidade` tinyint UNSIGNED NOT NULL COMMENT 'Campo obsoleto - manter por compatibilidade',
  `turno` tinyint UNSIGNED NOT NULL,
  `serie_id` bigint UNSIGNED NOT NULL,
  `itinerario` enum('Nenhum','Uma rota','Bloco de rotas','FTP') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Nenhum' COMMENT 'Tipo de itinerário formativo da turma',
  `bloco_id` bigint UNSIGNED DEFAULT NULL,
  `padrinho` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `padrinho_id` bigint UNSIGNED DEFAULT NULL,
  `vagas` smallint UNSIGNED NOT NULL DEFAULT '40' COMMENT 'Número máximo de alunos na turma',
  `status` enum('Em formação','Em andamento','Encerrada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Em formação',
  `data_inicio_andamento` date DEFAULT NULL,
  `user_insert_id` bigint UNSIGNED DEFAULT NULL,
  `user_update_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `turmas`
--

INSERT INTO `turmas` (`id`, `nome`, `codigo`, `turno`, `numero_alunos`, `ano`, `ativa`, `created_at`) VALUES
(185, '1ª Série A', '1A', 'integral', 40, 2026, 1, '2026-02-01 00:04:45'),
(186, '1ª Série B', '1B', 'integral', 40, 2026, 1, '2026-02-01 00:04:45'),
(187, '1ª Série C', '1C', 'integral', 40, 2026, 1, '2026-02-01 00:04:45'),
(188, '1ª Série D', '1D', 'integral', 40, 2026, 1, '2026-02-01 00:04:45'),
(189, '1ª Série E', '1E', 'integral', 40, 2026, 1, '2026-02-01 00:04:45'),
(191, '2ª Série A', '2A', 'integral', 40, 2026, 1, '2026-02-01 00:04:45'),
(192, '2ª Série B', '2B', 'integral', 40, 2026, 1, '2026-02-01 00:04:45'),
(193, '2ª Série C', '2C', 'integral', 40, 2026, 1, '2026-02-01 00:04:45'),
(195, '3ª Série A', '3A', 'integral', 40, 2026, 1, '2026-02-01 00:04:45'),
(196, '3ª Série B', '3B', 'integral', 40, 2026, 1, '2026-02-01 00:04:45'),
(197, '3ª Série C', '3C', 'integral', 40, 2026, 1, '2026-02-01 00:04:45'),
(209, '3ª série D', '3D', 'integral', 40, 2026, 1, '2026-02-01 00:04:45');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `turmas`
--
ALTER TABLE `turmas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `turmas_bloco_id_foreign` (`bloco_id`),
  ADD KEY `turmas_padrinho_id_foreign` (`padrinho_id`),
  ADD KEY `turmas_user_insert_id_foreign` (`user_insert_id`),
  ADD KEY `turmas_user_update_id_foreign` (`user_update_id`),
  ADD KEY `turmas_escola_id_status_index` (`escola_id`,`status`),
  ADD KEY `turmas_serie_id_turno_index` (`serie_id`,`turno`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `turmas`
--
ALTER TABLE `turmas`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=235;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `turmas`
--
ALTER TABLE `turmas`
  ADD CONSTRAINT `turmas_bloco_id_foreign` FOREIGN KEY (`bloco_id`) REFERENCES `blocos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `turmas_escola_id_foreign` FOREIGN KEY (`escola_id`) REFERENCES `escolas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `turmas_padrinho_id_foreign` FOREIGN KEY (`padrinho_id`) REFERENCES `servidores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `turmas_serie_id_foreign` FOREIGN KEY (`serie_id`) REFERENCES `series` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `turmas_user_insert_id_foreign` FOREIGN KEY (`user_insert_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `turmas_user_update_id_foreign` FOREIGN KEY (`user_update_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
