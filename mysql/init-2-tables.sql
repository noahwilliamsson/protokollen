-- MySQL dump 10.13  Distrib 5.5.40, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: pk
-- ------------------------------------------------------
-- Server version	5.5.40-0ubuntu0.14.04.1

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
-- Table structure for table `certs`
--

DROP TABLE IF EXISTS `certs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `certs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `pem_sha256` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '',
  `x509` mediumtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pem_sha256` (`pem_sha256`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Protokollen: X.509 cert store for scan data';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `entities`
--

DROP TABLE IF EXISTS `entities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `entities` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `org` varchar(255) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `org_short` varchar(255) CHARACTER SET utf8 COLLATE utf8_swedish_ci DEFAULT NULL,
  `org_group` varchar(255) CHARACTER SET utf8 COLLATE utf8_swedish_ci DEFAULT NULL,
  `cat` varchar(255) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT 'Svenska nyhetssajter',
  `domain` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `domain_email` varchar(255) DEFAULT NULL,
  `version` int(10) unsigned NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT '2014-11-08 00:00:00',
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `domain` (`domain`)
) ENGINE=InnoDB AUTO_INCREMENT=623 DEFAULT CHARSET=utf8 COMMENT='Protokollen: List of organizations';
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
  `json_sha256` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '',
  `service` varchar(64) DEFAULT NULL COMMENT '(only to make browsing table data more useful)',
  `json` mediumtext NOT NULL,
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_id` (`service_id`,`json_sha256`),
  CONSTRAINT `json_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3610 DEFAULT CHARSET=utf8mb4 COMMENT='Protokollen: JSON store for scan data';
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
  `json_id` int(10) unsigned DEFAULT NULL COMMENT '(only to make debugging or table browsing easier)',
  `hostname` varchar(255) DEFAULT '',
  `service` varchar(64) NOT NULL DEFAULT '' COMMENT '(only to make browsing table data more useful)',
  `log` text NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`),
  KEY `json_id` (`json_id`),
  CONSTRAINT `logs_ibfk_2` FOREIGN KEY (`json_id`) REFERENCES `json` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3209 DEFAULT CHARSET=utf8mb4 COMMENT='Protokollen: Log messages from scans';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nodes`
--

DROP TABLE IF EXISTS `nodes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nodes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Protokollen: List of nodes (IP-addresses)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_hostnames`
--

DROP TABLE IF EXISTS `service_hostnames`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_hostnames` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int(11) unsigned NOT NULL,
  `entity_id` int(11) unsigned NOT NULL,
  `entry_type` enum('current','revision') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT 'revision',
  `hostname` varchar(255) NOT NULL DEFAULT '',
  `service_type` varchar(16) NOT NULL COMMENT '(only to make browsing table data more useful)',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`),
  KEY `entity_id` (`entity_id`),
  CONSTRAINT `service_hostnames_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `service_hostnames_ibfk_2` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1931 DEFAULT CHARSET=utf8mb4 COMMENT='Protokollen: List of hostnames associated with a service';
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
  `entry_type` enum('current','revision') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT 'revision',
  `domain` varchar(255) NOT NULL DEFAULT '',
  `title` varchar(255) DEFAULT '',
  `preferred_url` varchar(255) DEFAULT '',
  `http_preferred_url` varchar(255) DEFAULT '',
  `https_preferred_url` varchar(255) DEFAULT '',
  `https_error` varchar(255) DEFAULT NULL,
  `json_id` int(11) unsigned DEFAULT NULL,
  `json_sha256` varchar(64) DEFAULT NULL,
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`),
  KEY `json_id` (`json_id`),
  CONSTRAINT `service_http_preferences_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `service_http_preferences_ibfk_2` FOREIGN KEY (`json_id`) REFERENCES `json` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3453 DEFAULT CHARSET=utf8 COMMENT='Protokollen: Website URL preferences for apex domain vs www and for http vs https';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_sets`
--

DROP TABLE IF EXISTS `service_sets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_sets` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int(11) unsigned NOT NULL,
  `entity_id` int(11) unsigned NOT NULL,
  `entry_type` enum('current','revision') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT 'current',
  `json_id` int(11) unsigned DEFAULT NULL,
  `json_sha256` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT '',
  `service_type` varchar(16) NOT NULL COMMENT '(only to make browsing table data more useful)',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`),
  KEY `entity_id` (`entity_id`),
  KEY `json_id` (`json_id`),
  CONSTRAINT `service_sets_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `service_sets_ibfk_2` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `service_sets_ibfk_3` FOREIGN KEY (`json_id`) REFERENCES `json` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Protokollen: Representation of hostnames associated with a service';
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
  `entry_type` enum('current','revision') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT 'current',
  `hostname` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '',
  `num_ips` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `sslv2` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `sslv3` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `tlsv1` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `tlsv1_1` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `tlsv1_2` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `json_id` int(11) unsigned DEFAULT NULL,
  `json_sha256` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `hostname_id` (`hostname_id`),
  KEY `service_id` (`service_id`),
  KEY `json_id` (`json_id`),
  CONSTRAINT `service_tls_statuses_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `service_tls_statuses_ibfk_2` FOREIGN KEY (`hostname_id`) REFERENCES `service_hostnames` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `service_tls_statuses_ibfk_3` FOREIGN KEY (`json_id`) REFERENCES `json` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3971 DEFAULT CHARSET=utf8 COMMENT='Protokollen: Basic TLS support status as returned from sslprobe runs';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_vhosts`
--

DROP TABLE IF EXISTS `service_vhosts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_vhosts` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `service_set_id` int(11) unsigned NOT NULL,
  `service_id` int(11) unsigned NOT NULL,
  `node_id` int(11) unsigned NOT NULL,
  `entry_type` enum('current','revision') CHARACTER SET utf8 NOT NULL DEFAULT 'current',
  `hostname` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '',
  `ip` varchar(45) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '(redundant but nice for table browsing)',
  `service_type` varchar(16) NOT NULL COMMENT '(only to make browsing table data more useful)',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`),
  KEY `service_set_id` (`service_set_id`),
  KEY `node_id` (`node_id`),
  CONSTRAINT `service_vhosts_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `service_vhosts_ibfk_2` FOREIGN KEY (`service_set_id`) REFERENCES `service_sets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `service_vhosts_ibfk_3` FOREIGN KEY (`node_id`) REFERENCES `nodes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Protokollen: Mapping between service hostnames and nodes (addresses)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `entity_id` int(11) unsigned NOT NULL,
  `entity_domain` varchar(255) NOT NULL DEFAULT '',
  `service_type` varchar(16) NOT NULL DEFAULT 'HTTP',
  `service_name` varchar(64) NOT NULL DEFAULT '',
  `service_desc` varchar(255) DEFAULT '',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `entity_id` (`entity_id`,`service_type`,`service_name`),
  CONSTRAINT `services_ibfk_1` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1306 DEFAULT CHARSET=utf8mb4 COMMENT='Protokollen: List of services (DNS, HTTP, SMTP, Webmail, ..)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `svc_group_map`
--

DROP TABLE IF EXISTS `svc_group_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `svc_group_map` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int(11) unsigned NOT NULL,
  `svc_group_id` int(11) unsigned NOT NULL,
  `entry_type` enum('current','revision') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT 'revision',
  `created` datetime NOT NULL,
  `until` datetime DEFAULT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`),
  KEY `svc_group_id` (`svc_group_id`),
  CONSTRAINT `svc_group_map_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `svc_group_map_ibfk_2` FOREIGN KEY (`svc_group_id`) REFERENCES `svc_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Protokollen: Link between services and service groups, with revisions';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `svc_groups`
--

DROP TABLE IF EXISTS `svc_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `svc_groups` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `json` mediumtext NOT NULL,
  `hash` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hash` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Protokollen: Group of service hostnames (hostname, port, prio, protocol) as JSON';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_dns_addresses`
--

DROP TABLE IF EXISTS `test_dns_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_dns_addresses` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int(11) unsigned NOT NULL,
  `svc_group_id` int(11) unsigned NOT NULL,
  `entry_type` enum('current','revision') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT 'revision',
  `json_id` int(11) unsigned DEFAULT NULL,
  `json_sha256` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `num_hosts` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'Final URL (after redirects)',
  `num_a` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'Final document title',
  `num_aaaa` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'Final document title',
  `num_cname` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'Final document title',
  `created` datetime NOT NULL,
  `until` datetime DEFAULT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`),
  KEY `json_id` (`json_id`),
  KEY `svc_group_id` (`svc_group_id`),
  CONSTRAINT `test_dns_addresses_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `test_dns_addresses_ibfk_2` FOREIGN KEY (`json_id`) REFERENCES `json` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `test_dns_addresses_ibfk_3` FOREIGN KEY (`svc_group_id`) REFERENCES `svc_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Protokollen: Number of A, AAAA and CNAME records in a service group';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_sslprobe_certs`
--

DROP TABLE IF EXISTS `test_sslprobe_certs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_sslprobe_certs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cert_id` int(11) unsigned NOT NULL,
  `svc_group_id` int(11) unsigned NOT NULL,
  `hostname` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `cert_id` (`cert_id`,`svc_group_id`,`hostname`),
  KEY `svc_group_id` (`svc_group_id`),
  CONSTRAINT `test_sslprobe_certs_ibfk_2` FOREIGN KEY (`svc_group_id`) REFERENCES `svc_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `test_sslprobe_certs_ibfk_1` FOREIGN KEY (`cert_id`) REFERENCES `certs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Protokollen: X.509 cert store for scan data';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_sslprobes`
--

DROP TABLE IF EXISTS `test_sslprobes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_sslprobes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int(11) unsigned NOT NULL,
  `svc_group_id` int(11) unsigned NOT NULL,
  `entry_type` enum('current','revision') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT 'revision',
  `json_id` int(11) unsigned DEFAULT NULL,
  `json_sha256` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '',
  `hostname` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '',
  `sslv2` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `sslv3` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `tlsv1` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `tlsv1_1` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `tlsv1_2` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `created` datetime NOT NULL,
  `until` datetime DEFAULT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`),
  KEY `svc_group_id` (`svc_group_id`),
  KEY `json_id` (`json_id`),
  CONSTRAINT `test_sslprobes_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `test_sslprobes_ibfk_2` FOREIGN KEY (`svc_group_id`) REFERENCES `svc_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `test_sslprobes_ibfk_3` FOREIGN KEY (`json_id`) REFERENCES `json` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Protokollen: Basic TLS support status as returned from sslprobe runs';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_www_prefs`
--

DROP TABLE IF EXISTS `test_www_prefs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_www_prefs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int(11) unsigned NOT NULL,
  `svc_group_id` int(11) unsigned NOT NULL,
  `entry_type` enum('current','revision') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT 'revision',
  `json_id` int(11) unsigned DEFAULT NULL,
  `json_sha256` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `url` text COMMENT 'Final URL (after redirects)',
  `title` varchar(255) DEFAULT '' COMMENT 'Final document title',
  `created` datetime NOT NULL,
  `until` datetime DEFAULT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`),
  KEY `json_id` (`json_id`),
  KEY `svc_group_id` (`svc_group_id`),
  CONSTRAINT `test_www_prefs_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `test_www_prefs_ibfk_3` FOREIGN KEY (`json_id`) REFERENCES `json` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `test_www_prefs_ibfk_4` FOREIGN KEY (`svc_group_id`) REFERENCES `svc_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Protokollen: Website URL preferences webservers in a service group';
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
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2014-11-16 15:05:57
