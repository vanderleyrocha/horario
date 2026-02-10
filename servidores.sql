-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Tempo de geração: 01/02/2026 às 00:15
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
-- Estrutura para tabela `servidores`
--

CREATE TABLE `servidores` (
  `id` bigint UNSIGNED NOT NULL,
  `escola_id` bigint UNSIGNED NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `matricula` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `digito_contrato` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome_abreviado` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nascimento` date DEFAULT NULL,
  `sexo` enum('Masculino','Feminino') COLLATE utf8mb4_unicode_ci NOT NULL,
  `contrato` enum('Efetivo','Temporário','Permuta') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contrato2` enum('Efetivo','Temporário','Permuta') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cargo` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `funcao` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `formacao` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vinculo` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lotacao` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_institucional` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cpf` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_nomeacao` date DEFAULT NULL,
  `data_lotacao` date DEFAULT NULL,
  `naturalidade` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado_civil` enum('Solteiro(a)','Casado(a)','Divorciado(a)','Separado(a)','Viúvo(a)') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome_da_mae` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `endereco` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bairro` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cep` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uf` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `escolaridade` enum('Analfabeto','Ensino Fundamental','Ensino Médio','Ensino Superior','Pós Graduação','Mestrado','Doutorado') COLLATE utf8mb4_unicode_ci NOT NULL,
  `curso_licenciatura` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instituicao_licenciatura` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ano_conclusao_licenciatura` int UNSIGNED DEFAULT NULL,
  `max_tutorado` int UNSIGNED DEFAULT NULL,
  `selecionado` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `servidores`
--

INSERT INTO `servidores` (`id`, `nome`, `email`, `carga_horaria_maxima`, `ativo`, `created_at`) VALUES
(74, 'José Eudes de Moura', 'jse.eudes7@gmail.com', 28, 1, '2026-02-01 00:15:09'),
(156, 'Carolina Teixeira da Silveira', 'karolynna.ts@gmail.com', 28, 1, '2026-02-01 00:15:09'),
(374, 'Tassio Bessa de Viveiros Alves', 'tassio.bessa@gmail.com', 28, 1, '2026-02-01 00:15:09'),
(375, 'Áleson José Corrêa dos Passos', 'alesonpassos@gmail.com', 28, 1, '2026-02-01 00:15:09'),
(386, 'Abigail de Queiroz Santana', 'abigailsantana70@gmail.com', 28, 1, '2026-02-01 00:15:09'),
(395, 'Célia Santos da Silva', 'celiadsantis@gmail.com', 28, 1, '2026-02-01 00:15:09'),
(406, 'Caroline Ketlyn Martins da Silva', 'carolketlyn1795@gmail.com', 28, 1, '2026-02-01 00:15:09'),
(415, 'Elimayk Lima Santos', 'maykespanhol@gmail.com', 28, 1, '2026-02-01 00:15:09'),
(426, 'Allan Henrique Galvão Rodrigues', 'allangalvao62@gmail.com', 28, 1, '2026-02-01 00:15:09'),
(427, 'Nathaly Karen Correia', 'NATHALYKAREN16@GMAIL.COM', 28, 1, '2026-02-01 00:15:09'),
(428, 'Dayan Kennedy Soares de Carvalho', 'dayan.kennedy1@gmail.com', 28, 1, '2026-02-01 00:15:09'),
(429, 'Maria Claudiane Nascimento de Sousa', 'annynasou31@gmail.com', 28, 1, '2026-02-01 00:15:09'),
(433, 'Maria Gabrielle Gonçalves Vieira', 'mgaby.ac23@gmail.com', 28, 1, '2026-02-01 00:15:09'),
(434, 'Emanoela Maria Freire dos Santos', 'emanoelafreire68@gmail.com', 28, 1, '2026-02-01 00:15:09'),
(469, 'Paulo Roberto Aquino de Souza', 'paulo_aquino25@hotmail.com', 28, 1, '2026-02-01 00:15:09'),
(474, 'Neurivania Menezes Castelo Branco', 'neurivania.castelo@prof.see.ac.gov.br', 28, 1, '2026-02-01 00:15:09'),
(514, 'Rayane Gomes da Silva', 'gomesrayane565@gmail.com', 28, 1, '2026-02-01 00:15:09'),
(516, 'Ana Jamile Saady Flores de Araújo', 'millysaadyof@gmail.com', 28, 1, '2026-02-01 00:15:09'),
(518, 'Wellyta da Silva Damasceno Rodrigues', 'wellytaac@gmail.com', 28, 1, '2026-02-01 00:15:09');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `servidores`
--
ALTER TABLE `servidores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `servidores_escola_id_foreign` (`escola_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `servidores`
--
ALTER TABLE `servidores`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=528;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `servidores`
--
ALTER TABLE `servidores`
  ADD CONSTRAINT `servidores_escola_id_foreign` FOREIGN KEY (`escola_id`) REFERENCES `escolas` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
