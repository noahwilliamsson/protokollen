-- MySQL dump 10.13  Distrib 5.5.40, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: pk
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
-- Table structure for table `browser_profiles`
--

DROP TABLE IF EXISTS `browser_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `browser_profiles` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `browser` varchar(255) DEFAULT NULL,
  `json` mediumtext,
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COMMENT='Protokollen: Browser SSL profiles with scraped data from https://www.ssllabs.com/ssltest/clients.html';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `certs`
--

DROP TABLE IF EXISTS `certs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `certs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `pem_sha256` varchar(64) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `x509` mediumtext CHARACTER SET utf8 NOT NULL,
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pem_sha256` (`pem_sha256`)
) ENGINE=InnoDB AUTO_INCREMENT=807 DEFAULT CHARSET=utf8mb4 COMMENT='Protokollen: X.509 cert store for scan data';
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
  `domain` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `domain_email` varchar(255) DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT '2014-11-22 17:45:00',
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `domain` (`domain`)
) ENGINE=InnoDB AUTO_INCREMENT=891 DEFAULT CHARSET=utf8 COMMENT='Protokollen: List of organizations';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `entity_sources`
--

DROP TABLE IF EXISTS `entity_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `entity_sources` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `entity_id` int(11) unsigned NOT NULL,
  `source` varchar(255) NOT NULL DEFAULT '',
  `source_id` varchar(255) DEFAULT '',
  `source_url` varchar(255) DEFAULT NULL,
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `entity_id` (`entity_id`),
  CONSTRAINT `entity_sources_ibfk_1` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=256 DEFAULT CHARSET=utf8mb4 COMMENT='Protokollen: Link between entity and a public source with more information';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `entity_tags`
--

DROP TABLE IF EXISTS `entity_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `entity_tags` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `entity_id` int(11) unsigned NOT NULL,
  `tag_id` int(11) unsigned NOT NULL,
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `entity_id` (`entity_id`),
  KEY `tag_id` (`tag_id`),
  CONSTRAINT `entity_tags_ibfk_1` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `entity_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=921 DEFAULT CHARSET=utf8mb4 COMMENT='Protokollen: Tags associated with entities';
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
  `json_sha256` varchar(64) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `service` varchar(64) DEFAULT NULL COMMENT '(only to make browsing table data more useful)',
  `json` mediumtext NOT NULL,
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_id` (`service_id`,`json_sha256`),
  CONSTRAINT `json_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30825 DEFAULT CHARSET=utf8mb4 COMMENT='Protokollen: JSON store for scan data';
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
) ENGINE=InnoDB AUTO_INCREMENT=34146 DEFAULT CHARSET=utf8mb4 COMMENT='Protokollen: Log messages from scans';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reports`
--

DROP TABLE IF EXISTS `reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reports` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `entity_id` int(11) unsigned NOT NULL,
  `created` date NOT NULL,
  `dnssec` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'DNSSEC on zone?',
  `ns_total` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'NS hostnames',
  `ns_ipv4` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'NS hostnames with A records',
  `ns_ipv6` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'NS hostnames with AAAA records',
  `mx_total` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'MX hostnames',
  `mx_ipv4` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'MX hostnames with A',
  `mx_ipv6` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'MX hostnames with AAAA',
  `mx_starttls` int(11) unsigned NOT NULL DEFAULT '0',
  `web_total` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'Total number of HTTP/HTTPS hostnames',
  `web_ipv4` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of web hostnames with A records',
  `web_ipv6` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of web hostnames with AAAA records',
  `https` varchar(16) NOT NULL DEFAULT 'no' COMMENT 'Partial if accessible over https in addition to http',
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `entity_id` (`entity_id`,`created`),
  CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1757 DEFAULT CHARSET=utf8mb4;
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
) ENGINE=InnoDB AUTO_INCREMENT=3690 DEFAULT CHARSET=utf8mb4 COMMENT='Protokollen: List of services (DNS, HTTP, SMTP, Webmail, ..)';
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
  `entry_type` enum('current','revision') CHARACTER SET utf8 NOT NULL DEFAULT 'revision',
  `created` datetime NOT NULL,
  `until` datetime DEFAULT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`),
  KEY `svc_group_id` (`svc_group_id`),
  CONSTRAINT `svc_group_map_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `svc_group_map_ibfk_2` FOREIGN KEY (`svc_group_id`) REFERENCES `svc_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3747 DEFAULT CHARSET=utf8mb4 COMMENT='Protokollen: Link between services and service groups, with revisions';
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
  `hash` varchar(64) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hash` (`hash`)
) ENGINE=InnoDB AUTO_INCREMENT=2944 DEFAULT CHARSET=utf8mb4 COMMENT='Protokollen: Group of service hostnames (hostname, port, prio, protocol) as JSON';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tags`
--

DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tags` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tag` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_swedish_ci NOT NULL DEFAULT '',
  `tag_source` varchar(32) DEFAULT NULL,
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tag` (`tag`)
) ENGINE=InnoDB AUTO_INCREMENT=130 DEFAULT CHARSET=utf8mb4 COMMENT='Protokollen: List of tag names';
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
  `entry_type` enum('current','revision') NOT NULL DEFAULT 'revision',
  `json_id` int(11) unsigned DEFAULT NULL,
  `json_sha256` varchar(64) DEFAULT NULL,
  `num_hosts` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of hosts',
  `num_a` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of A records',
  `num_aaaa` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of AAAA records',
  `num_cname` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of CNAME records',
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
) ENGINE=InnoDB AUTO_INCREMENT=8376 DEFAULT CHARSET=utf8 COMMENT='Protokollen: Number of A, AAAA and CNAME records in a service group';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_dnssec_statuses`
--

DROP TABLE IF EXISTS `test_dnssec_statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_dnssec_statuses` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int(11) unsigned NOT NULL,
  `svc_group_id` int(11) unsigned NOT NULL,
  `entry_type` enum('current','revision') NOT NULL DEFAULT 'revision',
  `json_id` int(11) unsigned DEFAULT NULL,
  `json_sha256` varchar(64) DEFAULT NULL,
  `num_hosts` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of hosts',
  `num_dnskey` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of hosts with DNSKEY in zone',
  `num_ds` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of hosts with DNSKEY at parent zone',
  `num_secure` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of DNSSEC validated hosts',
  `created` datetime NOT NULL,
  `until` datetime DEFAULT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`),
  KEY `json_id` (`json_id`),
  KEY `svc_group_id` (`svc_group_id`),
  CONSTRAINT `test_dnssec_statuses_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `test_dnssec_statuses_ibfk_2` FOREIGN KEY (`json_id`) REFERENCES `json` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `test_dnssec_statuses_ibfk_3` FOREIGN KEY (`svc_group_id`) REFERENCES `svc_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Protokollen: DNSSEC validation of hosts in service group';
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
) ENGINE=InnoDB AUTO_INCREMENT=3643 DEFAULT CHARSET=utf8mb4 COMMENT='Protokollen: X.509 cert store for scan data';
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
  `entry_type` enum('current','revision') NOT NULL DEFAULT 'revision',
  `json_id` int(11) unsigned DEFAULT NULL,
  `json_sha256` varchar(64) NOT NULL DEFAULT '',
  `hostname` varchar(255) NOT NULL DEFAULT '',
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
) ENGINE=InnoDB AUTO_INCREMENT=20538 DEFAULT CHARSET=utf8 COMMENT='Protokollen: Basic TLS support status as returned from sslprobe runs';
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
  `entry_type` enum('current','revision') NOT NULL DEFAULT 'revision',
  `json_id` int(11) unsigned DEFAULT NULL,
  `json_sha256` varchar(64) DEFAULT NULL,
  `url` text COMMENT 'Final URL (after redirects)',
  `title` varchar(255) DEFAULT '' COMMENT 'Final document title',
  `errors` text,
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
) ENGINE=InnoDB AUTO_INCREMENT=5966 DEFAULT CHARSET=utf8 COMMENT='Protokollen: Website URL preferences webservers in a service group';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2014-11-23 15:23:05
