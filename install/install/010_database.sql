CREATE TABLE `cronJobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uuid` varchar(128) NOT NULL,
  `status` int(11) NOT NULL,
  `message` varchar(1024) DEFAULT NULL,
  `event` varchar(255) NOT NULL,
  `requestJson` text DEFAULT NULL,
  `start` int(11) NOT NULL,
  `end` int(11) DEFAULT NULL,
  `recurring` varchar(2048) DEFAULT NULL,
  `errors` int(11) NOT NULL,
  `lastRun` int(11) DEFAULT NULL,
  `totalRuns` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `start` (`start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
