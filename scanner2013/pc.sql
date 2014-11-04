-- MySQL dump 10.13  Distrib 5.5.40, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: pc
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
-- Table structure for table `dns_data`
--

DROP TABLE IF EXISTS `dns_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dns_data` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `view_id` int(10) unsigned NOT NULL,
  `zone_id` int(10) unsigned NOT NULL,
  `version` int(10) unsigned NOT NULL DEFAULT '0',
  `hostname` varchar(255) NOT NULL DEFAULT '',
  `rr_type` varchar(255) NOT NULL DEFAULT '',
  `rd_count` int(10) unsigned NOT NULL DEFAULT '1',
  `rr_data` varchar(255) NOT NULL DEFAULT '',
  `created` datetime DEFAULT NULL,
  `updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `zone_id` (`zone_id`),
  KEY `view_id` (`view_id`),
  CONSTRAINT `dns_data_ibfk_1` FOREIGN KEY (`zone_id`) REFERENCES `dns_zones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dns_data_ibfk_2` FOREIGN KEY (`view_id`) REFERENCES `dns_views` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2460928 DEFAULT CHARSET=utf8 COMMENT='Snapshots of DNS views';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dns_views`
--

DROP TABLE IF EXISTS `dns_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dns_views` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `zone_id` int(11) unsigned NOT NULL,
  `version` int(10) unsigned NOT NULL DEFAULT '0',
  `serial` varchar(255) NOT NULL DEFAULT '',
  `nameserver` varchar(255) NOT NULL DEFAULT '',
  `created` datetime DEFAULT NULL,
  `updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `zone_id` (`zone_id`,`serial`),
  CONSTRAINT `dns_views_ibfk_1` FOREIGN KEY (`zone_id`) REFERENCES `dns_zones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=364444 DEFAULT CHARSET=utf8 COMMENT='Versioned views (snapshots) of partial DNS zone data';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dns_zones`
--

DROP TABLE IF EXISTS `dns_zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dns_zones` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `domain` varchar(255) DEFAULT NULL,
  `version` int(10) unsigned NOT NULL DEFAULT '0',
  `created` datetime NOT NULL,
  `updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `domain` (`domain`)
) ENGINE=InnoDB AUTO_INCREMENT=118988 DEFAULT CHARSET=utf8 COMMENT='List of DNS zones';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hosts`
--

DROP TABLE IF EXISTS `hosts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hosts` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(64) NOT NULL DEFAULT '',
  `version` int(10) unsigned NOT NULL DEFAULT '0',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='List of hosts by IPv4/IPv6 address';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tcp_scans`
--

DROP TABLE IF EXISTS `tcp_scans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tcp_scans` (
  `ip` varchar(64) NOT NULL DEFAULT '',
  `port_25` int(11) NOT NULL DEFAULT '0',
  `port_80` int(11) NOT NULL DEFAULT '0',
  `port_443` int(11) NOT NULL DEFAULT '0',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Speed up probing by keeping a list of known open ports';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_results`
--

DROP TABLE IF EXISTS `test_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_results` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `test_id` int(10) unsigned NOT NULL,
  `test_key` varchar(255) NOT NULL,
  `test_data` text,
  `created` datetime NOT NULL,
  `completed` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tests`
--

DROP TABLE IF EXISTS `tests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tests` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `domain` varchar(255) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  `completed` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tls_certs`
--

DROP TABLE IF EXISTS `tls_certs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tls_certs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `der_sha1` varchar(16) DEFAULT NULL,
  `openssl_hash` varchar(16) DEFAULT NULL,
  `valid_from` datetime NOT NULL,
  `valid_to` datetime NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `cn` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `san` varchar(255) DEFAULT NULL,
  `bits` int(11) NOT NULL DEFAULT '2048',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6587 DEFAULT CHARSET=utf8 COMMENT='TLS certificate information';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tls_ciphers`
--

DROP TABLE IF EXISTS `tls_ciphers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tls_ciphers` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cbc` int(10) unsigned NOT NULL,
  `ecc` int(11) unsigned NOT NULL DEFAULT '0',
  `pfs` int(11) unsigned NOT NULL DEFAULT '0',
  `strength` enum('CLEAR','WEAK','MEDIUM','STRONG','UNKNOWN') NOT NULL DEFAULT 'UNKNOWN',
  `name` varchar(255) NOT NULL DEFAULT '',
  `protocols` varchar(255) DEFAULT NULL,
  `rfcs` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=458946 DEFAULT CHARSET=utf8 COMMENT='List of TLS ciphers';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tls_pems`
--

DROP TABLE IF EXISTS `tls_pems`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tls_pems` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `der_sha1` varchar(16) NOT NULL DEFAULT '',
  `openssl_hash` varchar(16) NOT NULL DEFAULT '',
  `pem` text,
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `der_sha1` (`der_sha1`)
) ENGINE=InnoDB AUTO_INCREMENT=6587 DEFAULT CHARSET=utf8 COMMENT='TLS certificate information';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tls_vhost_certificates`
--

DROP TABLE IF EXISTS `tls_vhost_certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tls_vhost_certificates` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tls_vhost_id` int(11) unsigned NOT NULL,
  `tls_vhost_protocol_id` int(11) NOT NULL,
  `idx` int(11) unsigned NOT NULL,
  `tls_cert_id` int(10) unsigned NOT NULL,
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tls_vhost_id` (`tls_vhost_id`,`tls_vhost_protocol_id`,`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tls_vhost_ciphers`
--

DROP TABLE IF EXISTS `tls_vhost_ciphers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tls_vhost_ciphers` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tls_vhost_id` int(11) unsigned NOT NULL,
  `tls_vhost_protocol_id` int(11) unsigned NOT NULL,
  `cipher_id` int(11) unsigned NOT NULL,
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tls_vhost_id` (`tls_vhost_id`,`tls_vhost_protocol_id`,`cipher_id`),
  KEY `tls_vhost_protocol_id` (`tls_vhost_protocol_id`),
  CONSTRAINT `tls_vhost_ciphers_ibfk_1` FOREIGN KEY (`tls_vhost_id`) REFERENCES `tls_vhosts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tls_vhost_ciphers_ibfk_2` FOREIGN KEY (`tls_vhost_protocol_id`) REFERENCES `tls_vhost_protocols` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=765074 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tls_vhost_protocols`
--

DROP TABLE IF EXISTS `tls_vhost_protocols`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tls_vhost_protocols` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tls_vhost_id` int(11) unsigned NOT NULL,
  `protocol` enum('SSL 2.0','SSL 3.0','TLS 1.0','TLS 1.1','TLS 1.2','unknown') NOT NULL DEFAULT 'unknown',
  `supported` int(11) NOT NULL DEFAULT '-1',
  `established_connections` int(11) NOT NULL DEFAULT '-1',
  `last_error` varchar(64) DEFAULT NULL,
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tls_vhost_id` (`tls_vhost_id`,`protocol`),
  CONSTRAINT `tls_vhost_protocols_ibfk_1` FOREIGN KEY (`tls_vhost_id`) REFERENCES `tls_vhosts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=294657 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tls_vhost_retry`
--

DROP TABLE IF EXISTS `tls_vhost_retry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tls_vhost_retry` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(64) NOT NULL DEFAULT '',
  `port` int(11) unsigned NOT NULL,
  `hostname` varchar(255) NOT NULL DEFAULT '',
  `reason` varchar(255) NOT NULL DEFAULT '',
  `created` datetime DEFAULT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip` (`ip`,`port`,`hostname`)
) ENGINE=InnoDB AUTO_INCREMENT=663 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tls_vhosts`
--

DROP TABLE IF EXISTS `tls_vhosts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tls_vhosts` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(64) NOT NULL DEFAULT '',
  `port` int(11) unsigned NOT NULL,
  `hostname` varchar(255) NOT NULL DEFAULT '',
  `created` datetime DEFAULT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip` (`ip`,`port`,`hostname`)
) ENGINE=InnoDB AUTO_INCREMENT=81941 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vhost_tls_services`
--

DROP TABLE IF EXISTS `vhost_tls_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vhost_tls_services` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `vhost_id` int(10) unsigned NOT NULL,
  `port` int(10) unsigned NOT NULL,
  `proto` enum('FTPS','SMTPS','POP3S','IMAPS','HTTPS','LDAPS','UNKNOWN') NOT NULL DEFAULT 'UNKNOWN',
  `version` int(10) unsigned NOT NULL DEFAULT '0',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vhost_id` (`vhost_id`,`port`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='List of TLS services associated with a vhost';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vhosts`
--

DROP TABLE IF EXISTS `vhosts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vhosts` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `host_id` int(10) unsigned DEFAULT NULL,
  `zone_id` int(10) unsigned DEFAULT NULL,
  `view_id` int(10) unsigned DEFAULT NULL,
  `hostname` varchar(255) NOT NULL DEFAULT '',
  `version` int(10) unsigned NOT NULL DEFAULT '0',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `host_id` (`host_id`),
  KEY `zone_id` (`zone_id`),
  KEY `view_id` (`view_id`),
  CONSTRAINT `vhosts_ibfk_1` FOREIGN KEY (`host_id`) REFERENCES `hosts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vhosts_ibfk_2` FOREIGN KEY (`zone_id`) REFERENCES `dns_zones` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vhosts_ibfk_3` FOREIGN KEY (`view_id`) REFERENCES `dns_views` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='List of virtual hosts';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vhosts_map`
--

DROP TABLE IF EXISTS `vhosts_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vhosts_map` (
  `vhost_id` int(11) unsigned NOT NULL,
  `host_id` int(10) unsigned NOT NULL,
  `version` int(10) unsigned NOT NULL DEFAULT '0',
  `zone_id` int(10) unsigned NOT NULL,
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`vhost_id`,`host_id`),
  KEY `host_id` (`host_id`),
  CONSTRAINT `vhosts_map_ibfk_1` FOREIGN KEY (`vhost_id`) REFERENCES `vhosts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vhosts_map_ibfk_2` FOREIGN KEY (`host_id`) REFERENCES `hosts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Mapping between virtual hosts and hosts';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2014-11-04 21:35:00
