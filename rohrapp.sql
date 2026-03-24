-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 24 Mar 2026, 19:42:18
-- Sunucu sürümü: 10.4.32-MariaDB
-- PHP Sürümü: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `rohrapp`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `calls`
--

CREATE TABLE `calls` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `phone_number` varchar(50) DEFAULT NULL,
  `caller_name` varchar(200) DEFAULT NULL,
  `direction` enum('inbound','outbound') DEFAULT 'inbound',
  `duration` int(11) DEFAULT 0,
  `status` enum('answered','missed','voicemail','busy') DEFAULT 'missed',
  `notes` text DEFAULT NULL,
  `agent` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `calls`
--

INSERT INTO `calls` (`id`, `customer_id`, `phone_number`, `caller_name`, `direction`, `duration`, `status`, `notes`, `agent`, `created_at`) VALUES
(1, 1, '+49 171 1234567', 'Max Mustermann', 'inbound', 180, 'answered', 'Rohrverstopfung im Keller, Termin für morgen vereinbart', 'Karaaslan', '2026-03-24 17:36:40'),
(2, 2, '+49 152 9876543', 'Anna Schmidt', 'inbound', 0, 'missed', '', '', '2026-03-24 18:36:40'),
(3, NULL, '+49 175 5559999', 'Unbekannt', 'inbound', 45, 'answered', 'Preisanfrage Rohrreinigung', 'Karaaslan', '2026-03-24 19:06:40'),
(4, 4, '+49 176 3334444', 'Maria Hoffmann', 'inbound', 320, 'answered', 'Wartungsvertrag für 3 Mehrfamilienhäuser besprochen', 'Karaaslan', '2026-03-24 15:36:40'),
(5, 5, '+49 151 7778888', 'Thomas Becker', 'outbound', 90, 'answered', 'Rückruf wegen Angebot', 'Karaaslan', '2026-03-24 14:36:40'),
(6, NULL, '+49 160 1112222', 'Unbekannt', 'inbound', 0, 'missed', '', '', '2026-03-24 16:36:40');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `chat_conversations`
--

CREATE TABLE `chat_conversations` (
  `id` int(11) NOT NULL,
  `visitor_name` varchar(200) DEFAULT NULL,
  `visitor_email` varchar(200) DEFAULT NULL,
  `visitor_ip` varchar(45) DEFAULT NULL,
  `status` enum('active','closed','bot') DEFAULT 'active',
  `customer_id` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `closed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `chat_conversations`
--

INSERT INTO `chat_conversations` (`id`, `visitor_name`, `visitor_email`, `visitor_ip`, `status`, `customer_id`, `assigned_to`, `created_at`, `closed_at`) VALUES
(1, 'Sandra Klein', 'sandra@example.de', '192.168.1.100', 'active', NULL, NULL, '2026-03-24 19:36:40', NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender` enum('visitor','agent','bot') DEFAULT 'visitor',
  `content` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `conversation_id`, `sender`, `content`, `created_at`) VALUES
(1, 1, 'bot', 'Hallo! Wie kann ich Ihnen helfen? Ich bin der virtuelle Assistent von Die Rohrreiniger GmbH.', '2026-03-24 19:26:40'),
(2, 1, 'visitor', 'Hallo, was kostet eine Rohrreinigung?', '2026-03-24 19:27:40'),
(3, 1, 'bot', 'Die Kosten für eine Rohrreinigung hängen vom Umfang ab. Eine einfache Rohrreinigung beginnt ab ca. 89€. Für ein genaues Angebot können wir gerne einen Vor-Ort-Termin vereinbaren. Möchten Sie einen Termin?', '2026-03-24 19:28:40'),
(4, 1, 'visitor', 'Ja, gerne. Geht morgen Nachmittag?', '2026-03-24 19:29:40');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `company` varchar(200) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(200) DEFAULT NULL,
  `address` varchar(300) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `zip` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `source` enum('call','email','chat','manual','website') DEFAULT 'manual',
  `status` enum('active','inactive','blocked') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `customers`
--

INSERT INTO `customers` (`id`, `name`, `company`, `phone`, `email`, `address`, `city`, `zip`, `notes`, `source`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Max Mustermann', 'Mustermann GmbH', '+49 171 1234567', 'max@mustermann.de', 'Hauptstr. 1', 'Frankfurt', '60311', NULL, 'call', 'active', '2026-03-24 19:36:40', '2026-03-24 19:36:40'),
(2, 'Anna Schmidt', '', '+49 152 9876543', 'anna.schmidt@email.de', 'Berliner Str. 42', 'Gießen', '35390', NULL, 'email', 'active', '2026-03-24 19:36:40', '2026-03-24 19:36:40'),
(3, 'Peter Wagner', 'Wagner Immobilien', '+49 163 5551234', 'p.wagner@wagner-immo.de', 'Marktplatz 7', 'Wetzlar', '35578', NULL, 'chat', 'active', '2026-03-24 19:36:40', '2026-03-24 19:36:40'),
(4, 'Maria Hoffmann', 'Hausverwaltung Hoffmann', '+49 176 3334444', 'maria@hv-hoffmann.de', 'Schillerstr. 15', 'Gießen', '35394', NULL, 'website', 'active', '2026-03-24 19:36:40', '2026-03-24 19:36:40'),
(5, 'Thomas Becker', '', '+49 151 7778888', 'thomas.becker@gmx.de', 'Bahnhofstr. 8', 'Marburg', '35037', NULL, 'call', 'active', '2026-03-24 19:36:40', '2026-03-24 19:36:40');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `emails`
--

CREATE TABLE `emails` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `from_address` varchar(300) DEFAULT NULL,
  `to_address` varchar(300) DEFAULT NULL,
  `subject` varchar(500) DEFAULT NULL,
  `body` longtext DEFAULT NULL,
  `body_html` longtext DEFAULT NULL,
  `direction` enum('inbound','outbound') DEFAULT 'inbound',
  `status` enum('unread','read','replied','archived','draft') DEFAULT 'unread',
  `is_starred` tinyint(1) DEFAULT 0,
  `replied_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `emails`
--

INSERT INTO `emails` (`id`, `customer_id`, `from_address`, `to_address`, `subject`, `body`, `body_html`, `direction`, `status`, `is_starred`, `replied_at`, `created_at`) VALUES
(1, 2, 'anna.schmidt@email.de', 'info@rohrreiniger.de', 'Terminanfrage Rohrreinigung', 'Sehr geehrte Damen und Herren,\n\nich hätte gerne einen Termin für eine Rohrreinigung in meiner Wohnung. Die Küche ist betroffen.\n\nWann hätten Sie Zeit?\n\nMit freundlichen Grüßen\nAnna Schmidt', NULL, 'inbound', 'unread', 0, NULL, '2026-03-24 16:36:40'),
(2, 3, 'p.wagner@wagner-immo.de', 'info@rohrreiniger.de', 'Angebot für Mehrfamilienhaus', 'Hallo,\n\nwir benötigen eine Rohrreinigung für ein Mehrfamilienhaus mit 8 Einheiten in der Bahnhofstraße 12.\nKönnen Sie uns ein Angebot erstellen?\n\nMit freundlichen Grüßen\nPeter Wagner\nWagner Immobilien', NULL, 'inbound', 'unread', 0, NULL, '2026-03-24 18:36:40'),
(3, 4, 'maria@hv-hoffmann.de', 'info@rohrreiniger.de', 'Wartungsvertrag Verlängerung', 'Guten Tag,\n\nunser Wartungsvertrag läuft Ende des Monats aus. Wir möchten diesen gerne verlängern und um ein weiteres Objekt erweitern.\n\nBitte senden Sie uns ein aktualisiertes Angebot.\n\nFreundliche Grüße\nMaria Hoffmann', NULL, 'inbound', 'read', 0, NULL, '2026-03-24 13:36:40'),
(4, 1, 'info@rohrreiniger.de', 'max@mustermann.de', 'Re: Terminbestätigung', 'Sehr geehrter Herr Mustermann,\n\nhiermit bestätigen wir Ihren Termin für morgen, 14:00 Uhr.\n\nMit freundlichen Grüßen\nDie Rohrreiniger GmbH', NULL, 'outbound', 'read', 0, NULL, '2026-03-24 18:36:40');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_count` int(11) DEFAULT 1,
  `last_attempt` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `channel` enum('sms','whatsapp','contact_form','telegram') DEFAULT 'contact_form',
  `phone_number` varchar(50) DEFAULT NULL,
  `sender_name` varchar(200) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `direction` enum('inbound','outbound') DEFAULT 'inbound',
  `status` enum('unread','read','replied') DEFAULT 'unread',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `messages`
--

INSERT INTO `messages` (`id`, `customer_id`, `channel`, `phone_number`, `sender_name`, `content`, `direction`, `status`, `created_at`) VALUES
(1, NULL, 'contact_form', '', 'Klaus Weber', 'Hallo, ich habe eine verstopfte Toilette. Können Sie heute noch kommen? Dringend! PLZ 35398', 'inbound', 'unread', '2026-03-24 18:51:40'),
(2, 5, 'whatsapp', '+49 151 7778888', 'Thomas Becker', 'Danke für das Angebot, wir nehmen es an. Wann können Sie anfangen?', 'inbound', 'unread', '2026-03-24 17:36:40');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `settings`
--

CREATE TABLE `settings` (
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `settings`
--

INSERT INTO `settings` (`key`, `value`) VALUES
('chat_bot_enabled', '1'),
('chat_bot_greeting', 'Hallo! Wie kann ich Ihnen helfen? Ich bin der virtuelle Assistent von Die Rohrreiniger GmbH.'),
('chat_bot_prompt', 'Du bist ein freundlicher Kundenservice-Bot für Die Rohrreiniger GmbH, ein Rohrreinigungsunternehmen in Gießen. Beantworte Fragen zu Dienstleistungen, Preisen und Terminen.'),
('company_address', 'Friedrich-List-Str. 29, 35398 Gießen'),
('company_email', 'info@rohrreiniger.de'),
('company_name', 'Die Rohrreiniger GmbH'),
('company_phone', '+49 641 12345'),
('idle_timeout', '300'),
('notification_sound', '1'),
('theme', 'light');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','enterprise','professional','starter') DEFAULT 'starter',
  `name` varchar(200) DEFAULT NULL,
  `email` varchar(200) DEFAULT NULL,
  `avatar` varchar(500) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `name`, `email`, `avatar`, `last_login`, `created_at`) VALUES
(1, 'admin', '$2y$10$8KCu8kfBxcip.IUoi4AmguI8Ch/P8Nrfe9jzsKjATL2ASEmnPP/W6', 'admin', 'Administrator', 'admin@rohrapp.de', NULL, '2026-03-24 19:39:53', '2026-03-24 19:36:40'),
(2, 'enterprise', '$2y$10$yy6qWxCEV2y9Yc3OSd0zGOpoTFHwn3oCIZ22yO1ITsAIbDVHTggie', 'enterprise', 'Enterprise User', 'enterprise@demo.de', NULL, '2026-03-24 19:39:42', '2026-03-24 19:36:40'),
(3, 'professional', '$2y$10$nNybGRfd/9Wa32etOSUEL.mOjpMTBapSRJlOwQQd/DXAJdteso0E6', 'professional', 'Professional User', 'professional@demo.de', NULL, '2026-03-24 19:39:35', '2026-03-24 19:36:40'),
(4, 'starter', '$2y$10$1A61.39Uxa5nnIwlGkw3R.ckm7hrshd9.46ifoAsrNYFz9R1lGghy', 'starter', 'Starter User', 'starter@demo.de', NULL, '2026-03-24 19:39:23', '2026-03-24 19:36:40');

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Tablo için indeksler `calls`
--
ALTER TABLE `calls`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Tablo için indeksler `chat_conversations`
--
ALTER TABLE `chat_conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Tablo için indeksler `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversation` (`conversation_id`);

--
-- Tablo için indeksler `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_phone` (`phone`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`status`);

--
-- Tablo için indeksler `emails`
--
ALTER TABLE `emails`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_starred` (`is_starred`);

--
-- Tablo için indeksler `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip` (`ip_address`);

--
-- Tablo için indeksler `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Tablo için indeksler `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`key`);

--
-- Tablo için indeksler `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `calls`
--
ALTER TABLE `calls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Tablo için AUTO_INCREMENT değeri `chat_conversations`
--
ALTER TABLE `chat_conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Tablo için AUTO_INCREMENT değeri `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Tablo için AUTO_INCREMENT değeri `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Tablo için AUTO_INCREMENT değeri `emails`
--
ALTER TABLE `emails`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Tablo için AUTO_INCREMENT değeri `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Tablo için AUTO_INCREMENT değeri `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `calls`
--
ALTER TABLE `calls`
  ADD CONSTRAINT `calls_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `chat_conversations`
--
ALTER TABLE `chat_conversations`
  ADD CONSTRAINT `chat_conversations_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_conversations_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `emails`
--
ALTER TABLE `emails`
  ADD CONSTRAINT `emails_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
