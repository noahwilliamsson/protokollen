-- MySQL dump 10.13  Distrib 5.5.28, for osx10.6 (i386)
--
-- Host: 127.1    Database: pk
-- ------------------------------------------------------
-- Server version	5.5.40-0ubuntu1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `entities`
--

DROP TABLE IF EXISTS `entities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `entities` (
  `entity_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `org` varchar(255) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `org_short` varchar(255) CHARACTER SET utf8 COLLATE utf8_swedish_ci DEFAULT NULL,
  `org_group` varchar(255) CHARACTER SET utf8 COLLATE utf8_swedish_ci DEFAULT NULL,
  `cat` varchar(255) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT 'Svenska nyhetssajter',
  `domain` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `domain_email` varchar(255) DEFAULT NULL,
  `version` int(10) unsigned NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT '2013-06-20 00:00:00',
  `updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`entity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=501 DEFAULT CHARSET=utf8 COMMENT='Protokollen: List of organizations';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `entity_services`
--

DROP TABLE IF EXISTS `entity_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `entity_services` (
  `service_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `entity_id` int(11) unsigned NOT NULL,
  `entity_domain` varchar(255) NOT NULL DEFAULT '',
  `service_type` varchar(16) NOT NULL DEFAULT 'HTTP',
  `service_name` varchar(64) NOT NULL DEFAULT '',
  `service_desc` varchar(255) DEFAULT '',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`service_id`),
  UNIQUE KEY `entity_id` (`entity_id`,`service_type`,`service_name`),
  CONSTRAINT `entity_services_ibfk_1` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`entity_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=990 DEFAULT CHARSET=utf8mb4 COMMENT='List of services: HTTP, SMTP, IMAP, Webmail, ..';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `json`
--

DROP TABLE IF EXISTS `json`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `json` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int(11) unsigned NOT NULL,
  `json_sha256` varchar(64) NOT NULL,
  `json` text NOT NULL,
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `json_sha256` (`json_sha256`,`service_id`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `json_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `entity_services` (`service_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `logs`
--

DROP TABLE IF EXISTS `logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int(11) unsigned NOT NULL,
  `hostname` varchar(255) DEFAULT '',
  `service` varchar(64) NOT NULL DEFAULT 'http',
  `log` text NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `entity_services` (`service_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_hostnames`
--

DROP TABLE IF EXISTS `service_hostnames`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_hostnames` (
  `hostname_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int(11) unsigned NOT NULL,
  `entity_id` int(11) unsigned NOT NULL,
  `zone_id` int(10) unsigned NOT NULL DEFAULT '0',
  `service_type` varchar(16) NOT NULL DEFAULT '',
  `hostname` varchar(255) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`hostname_id`),
  KEY `service_id` (`service_id`),
  KEY `entity_id` (`entity_id`),
  CONSTRAINT `service_hostnames_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `entity_services` (`service_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `service_hostnames_ibfk_2` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`entity_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1489 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_http_preferences`
--

DROP TABLE IF EXISTS `service_http_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_http_preferences` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int(11) unsigned NOT NULL,
  `entry_type` enum('current','revision') NOT NULL DEFAULT 'current',
  `domain` varchar(255) NOT NULL DEFAULT '',
  `title` varchar(255) DEFAULT '',
  `preferred_url` varchar(255) DEFAULT '',
  `http_preferred_url` varchar(255) DEFAULT '',
  `https_preferred_url` varchar(255) DEFAULT '',
  `https_error` varchar(255) DEFAULT NULL,
  `json_id` int(10) unsigned DEFAULT NULL,
  `json_sha256` varchar(64) DEFAULT NULL,
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `service_http_preferences_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `entity_services` (`service_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_tls_statuses`
--

DROP TABLE IF EXISTS `service_tls_statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_tls_statuses` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int(11) unsigned NOT NULL,
  `hostname_id` int(11) unsigned NOT NULL,
  `entry_type` enum('current','revision') NOT NULL DEFAULT 'current',
  `hostname` varchar(255) NOT NULL DEFAULT '',
  `num_ips` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `sslv2` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `sslv3` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `tlsv1` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `tlsv1_1` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `tlsv1_2` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `json_id` int(10) unsigned NOT NULL,
  `json_sha256` varchar(64) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`),
  KEY `hostname_id` (`hostname_id`),
  CONSTRAINT `service_tls_statuses_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `entity_services` (`service_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `service_tls_statuses_ibfk_2` FOREIGN KEY (`hostname_id`) REFERENCES `service_hostnames` (`hostname_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2014-11-04 19:50:42
