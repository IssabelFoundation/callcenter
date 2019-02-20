-- MySQL dump 10.16  Distrib 10.2.14-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: call_center
-- ------------------------------------------------------
-- Server version	5.5.56-MariaDB

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
-- Table structure for table `agent`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `call_center` /*!40100 DEFAULT CHARACTER SET utf8 */;

USE `call_center`;

DROP TABLE IF EXISTS `agent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `agent` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('Agent','SIP','IAX2') NOT NULL DEFAULT 'Agent',
  `number` varchar(40) NOT NULL,
  `name` varchar(250) NOT NULL,
  `password` varchar(250) NOT NULL,
  `estatus` enum('A','I') DEFAULT 'A',
  `eccp_password` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `agent_type` (`estatus`,`type`,`number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `agent`
--

LOCK TABLES `agent` WRITE;
/*!40000 ALTER TABLE `agent` DISABLE KEYS */;
/*!40000 ALTER TABLE `agent` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit`
--

DROP TABLE IF EXISTS `audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_agent` int(10) unsigned NOT NULL,
  `id_break` int(10) unsigned DEFAULT NULL,
  `datetime_init` datetime NOT NULL,
  `datetime_end` datetime DEFAULT NULL,
  `duration` time DEFAULT NULL,
  `ext_parked` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_agent` (`id_agent`),
  KEY `id_break` (`id_break`),
  KEY `agent_break_datetime` (`id_agent`,`id_break`,`datetime_init`),
  CONSTRAINT `audit_ibfk_1` FOREIGN KEY (`id_agent`) REFERENCES `agent` (`id`),
  CONSTRAINT `audit_ibfk_2` FOREIGN KEY (`id_break`) REFERENCES `break` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit`
--

LOCK TABLES `audit` WRITE;
/*!40000 ALTER TABLE `audit` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `break`
--

DROP TABLE IF EXISTS `break`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `break` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(250) NOT NULL,
  `description` varchar(250) DEFAULT NULL,
  `status` varchar(1) NOT NULL DEFAULT 'A',
  `tipo` enum('B','H') DEFAULT 'B',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `break`
--

LOCK TABLES `break` WRITE;
/*!40000 ALTER TABLE `break` DISABLE KEYS */;
INSERT INTO `break` VALUES (1,'Hold','Hold','A','H');
/*!40000 ALTER TABLE `break` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `call_attribute`
--

DROP TABLE IF EXISTS `call_attribute`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `call_attribute` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_call` int(10) unsigned NOT NULL,
  `data` text NULL,
  PRIMARY KEY (`id`),
  KEY `id_call` (`id_call`),
  CONSTRAINT `call_attribute_ibfk_1` FOREIGN KEY (`id_call`) REFERENCES `calls` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `call_attribute`
--

LOCK TABLES `call_attribute` WRITE;
/*!40000 ALTER TABLE `call_attribute` DISABLE KEYS */;
/*!40000 ALTER TABLE `call_attribute` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `call_entry`
--

DROP TABLE IF EXISTS `call_entry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `call_entry` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_agent` int(10) unsigned DEFAULT NULL,
  `id_queue_call_entry` int(10) unsigned NOT NULL,
  `id_contact` int(10) unsigned DEFAULT NULL,
  `callerid` varchar(15) NOT NULL,
  `datetime_init` datetime DEFAULT NULL,
  `datetime_end` datetime DEFAULT NULL,
  `duration` int(10) unsigned DEFAULT NULL,
  `status` varchar(32) DEFAULT NULL,
  `transfer` varchar(6) DEFAULT NULL,
  `datetime_entry_queue` datetime DEFAULT NULL,
  `duration_wait` int(11) DEFAULT NULL,
  `uniqueid` varchar(32) DEFAULT NULL,
  `id_campaign` int(10) unsigned DEFAULT NULL,
  `trunk` varchar(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_agent` (`id_agent`),
  KEY `id_queue_call_entry` (`id_queue_call_entry`),
  KEY `id_contact` (`id_contact`),
  KEY `call_entry_ibfk_4` (`id_campaign`),
  KEY `datetime_init` (`datetime_init`),
  KEY `datetime_entry_queue` (`datetime_entry_queue`),
  CONSTRAINT `call_entry_ibfk_1` FOREIGN KEY (`id_agent`) REFERENCES `agent` (`id`),
  CONSTRAINT `call_entry_ibfk_2` FOREIGN KEY (`id_queue_call_entry`) REFERENCES `queue_call_entry` (`id`),
  CONSTRAINT `call_entry_ibfk_3` FOREIGN KEY (`id_contact`) REFERENCES `contact` (`id`),
  CONSTRAINT `call_entry_ibfk_4` FOREIGN KEY (`id_campaign`) REFERENCES `campaign_entry` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `call_entry`
--

LOCK TABLES `call_entry` WRITE;
/*!40000 ALTER TABLE `call_entry` DISABLE KEYS */;
/*!40000 ALTER TABLE `call_entry` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `call_progress_log`
--

DROP TABLE IF EXISTS `call_progress_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `call_progress_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `datetime_entry` datetime NOT NULL,
  `id_campaign_incoming` int(10) unsigned DEFAULT NULL,
  `id_call_incoming` int(10) unsigned DEFAULT NULL,
  `id_campaign_outgoing` int(10) unsigned DEFAULT NULL,
  `id_call_outgoing` int(10) unsigned DEFAULT NULL,
  `new_status` varchar(32) NOT NULL,
  `retry` int(10) unsigned DEFAULT NULL,
  `uniqueid` varchar(32) DEFAULT NULL,
  `trunk` varchar(20) DEFAULT NULL,
  `id_agent` int(10) unsigned DEFAULT NULL,
  `duration` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `call_progress_log_ibfk_4` (`id_agent`),
  KEY `call_progress_log_ibfk_5` (`id_campaign_incoming`),
  KEY `call_progress_log_ibfk_6` (`id_campaign_outgoing`),
  KEY `incoming_datetime_entry` (`id_call_incoming`,`datetime_entry`),
  KEY `outgoing_datetime_entry` (`id_call_outgoing`,`datetime_entry`),
  CONSTRAINT `call_progress_log_ibfk_1` FOREIGN KEY (`id_call_incoming`) REFERENCES `call_entry` (`id`),
  CONSTRAINT `call_progress_log_ibfk_2` FOREIGN KEY (`id_call_outgoing`) REFERENCES `calls` (`id`),
  CONSTRAINT `call_progress_log_ibfk_4` FOREIGN KEY (`id_agent`) REFERENCES `agent` (`id`),
  CONSTRAINT `call_progress_log_ibfk_5` FOREIGN KEY (`id_campaign_incoming`) REFERENCES `campaign_entry` (`id`),
  CONSTRAINT `call_progress_log_ibfk_6` FOREIGN KEY (`id_campaign_outgoing`) REFERENCES `campaign` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `call_progress_log`
--

LOCK TABLES `call_progress_log` WRITE;
/*!40000 ALTER TABLE `call_progress_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `call_progress_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `call_recording`
--

DROP TABLE IF EXISTS `call_recording`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `call_recording` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `datetime_entry` datetime NOT NULL,
  `id_call_incoming` int(10) unsigned DEFAULT NULL,
  `id_call_outgoing` int(10) unsigned DEFAULT NULL,
  `uniqueid` varchar(32) NOT NULL,
  `channel` varchar(80) NOT NULL,
  `recordingfile` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `call_recording_ibfk_1` (`id_call_incoming`),
  KEY `call_recording_ibfk_2` (`id_call_outgoing`),
  CONSTRAINT `call_recording_ibfk_1` FOREIGN KEY (`id_call_incoming`) REFERENCES `call_entry` (`id`),
  CONSTRAINT `call_recording_ibfk_2` FOREIGN KEY (`id_call_outgoing`) REFERENCES `calls` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `call_recording`
--

LOCK TABLES `call_recording` WRITE;
/*!40000 ALTER TABLE `call_recording` DISABLE KEYS */;
/*!40000 ALTER TABLE `call_recording` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `calls`
--

DROP TABLE IF EXISTS `calls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `calls` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_list` int(10) unsigned NOT NULL,
  `phone` varchar(32) NOT NULL,
  `status` varchar(32) DEFAULT NULL,
  `uniqueid` varchar(32) DEFAULT NULL,
  `fecha_llamada` datetime DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `retries` int(10) unsigned NOT NULL DEFAULT '0',
  `duration` int(10) unsigned DEFAULT NULL,
  `id_agent` int(10) unsigned DEFAULT NULL,
  `transfer` varchar(6) DEFAULT NULL,
  `datetime_entry_queue` datetime DEFAULT NULL,
  `duration_wait` int(11) DEFAULT NULL,
  `dnc` int(1) NOT NULL DEFAULT '0',
  `date_init` date DEFAULT NULL,
  `date_end` date DEFAULT NULL,
  `time_init` time DEFAULT NULL,
  `time_end` time DEFAULT NULL,
  `agent` varchar(32) DEFAULT NULL,
  `failure_cause` int(10) unsigned DEFAULT NULL,
  `failure_cause_txt` varchar(32) DEFAULT NULL,
  `datetime_originate` datetime DEFAULT NULL,
  `trunk` varchar(20) DEFAULT NULL,
  `scheduled` tinyint(1) NOT NULL DEFAULT '0',
  `callerid` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_campaign` (`id_campaign`),
  KEY `calls_ibfk_2` (`id_agent`),
  KEY `campaign_date_schedule` (`id_campaign`,`date_init`,`date_end`,`time_init`,`time_end`),
  KEY `datetime_init` (`start_time`),
  KEY `datetime_entry_queue` (`datetime_entry_queue`),
  CONSTRAINT `calls_ibfk_1` FOREIGN KEY (`id_list`) REFERENCES `campaign_lists` (`id`),
  CONSTRAINT `calls_ibfk_2` FOREIGN KEY (`id_agent`) REFERENCES `agent` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `calls`
--

LOCK TABLES `calls` WRITE;
/*!40000 ALTER TABLE `calls` DISABLE KEYS */;
/*!40000 ALTER TABLE `calls` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `campaign_lists`
--

DROP TABLE IF EXISTS `campaign_lists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `campaign_lists` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `id_campaign` int(11) unsigned NOT NULL,
  `type` tinyint(1) NOT NULL default '0' COMMENT '0 - out, 1 - in',
  `name` varchar(255) NOT NULL,
  `upload` varchar(255) NOT NULL,
  `date_entered` datetime NOT NULL,
  `status` tinyint(1) NOT NULL default '2' COMMENT '1 - Activa, 2 - Detenida, 3 - Terminada',
  `total_calls` int(11) NOT NULL default '0',
  `pending_calls` int(11) NOT NULL default '0',
  `sent_calls` int(11) NOT NULL default '0',
  `answered_calls` int(11) NOT NULL default '0',
  `no_answer_calls` int(11) NOT NULL default '0',
  `failed_calls` int(11) NOT NULL default '0',
  `paused_calls` int(11) NOT NULL default '0',
  `abandoned_calls` int(11) NOT NULL default '0',
  `short_calls` int(11) NOT NULL default '0',
  `is_recycled` tinyint(1) NOT NULL default '0' COMMENT '0 - False, 1 - True',
  `id_parent_list` INT(11) UNSIGNED NULL DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL default '0' COMMENT '0 - Activo, 1 - Eliminado',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campaign_lists`
--

LOCK TABLES `campaign_lists` WRITE;
/*!40000 ALTER TABLE `campaign_lists` DISABLE KEYS */;
/*!40000 ALTER TABLE `campaign_lists` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `campaign`
--

DROP TABLE IF EXISTS `campaign`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campaign` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `datetime_init` date NOT NULL,
  `datetime_end` date NOT NULL,
  `daytime_init` time NOT NULL,
  `daytime_end` time NOT NULL,
  `retries` int(10) unsigned NOT NULL DEFAULT '1',
  `trunk` varchar(255) DEFAULT NULL,
  `context` varchar(32) NOT NULL,
  `queue` varchar(16) NOT NULL,
  `max_canales` int(10) unsigned NOT NULL DEFAULT '0',
  `num_completadas` int(10) unsigned DEFAULT NULL,
  `promedio` int(10) unsigned DEFAULT NULL,
  `desviacion` int(10) unsigned DEFAULT NULL,
  `script` text NOT NULL,
  `estatus` varchar(1) NOT NULL DEFAULT 'A',
  `id_url` int(10) unsigned DEFAULT NULL,
  `callerid` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_url` (`id_url`),
  CONSTRAINT `campaign_ibfk_1` FOREIGN KEY (`id_url`) REFERENCES `campaign_external_url` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campaign`
--

LOCK TABLES `campaign` WRITE;
/*!40000 ALTER TABLE `campaign` DISABLE KEYS */;
/*!40000 ALTER TABLE `campaign` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `campaign_entry`
--

DROP TABLE IF EXISTS `campaign_entry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campaign_entry` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL DEFAULT '',
  `id_queue_call_entry` int(10) unsigned NOT NULL,
  `id_form` int(10) unsigned DEFAULT NULL,
  `datetime_init` date NOT NULL,
  `datetime_end` date NOT NULL,
  `daytime_init` time NOT NULL,
  `daytime_end` time NOT NULL,
  `estatus` varchar(1) NOT NULL DEFAULT 'A',
  `script` text NOT NULL,
  `id_url` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_queue_call_entry` (`id_queue_call_entry`),
  KEY `id_form` (`id_form`),
  KEY `id_url` (`id_url`),
  CONSTRAINT `campaign_entry_ibfk_1` FOREIGN KEY (`id_queue_call_entry`) REFERENCES `queue_call_entry` (`id`),
  CONSTRAINT `campaign_entry_ibfk_2` FOREIGN KEY (`id_form`) REFERENCES `form` (`id`),
  CONSTRAINT `campaign_entry_ibfk_3` FOREIGN KEY (`id_url`) REFERENCES `campaign_external_url` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campaign_entry`
--

LOCK TABLES `campaign_entry` WRITE;
/*!40000 ALTER TABLE `campaign_entry` DISABLE KEYS */;
/*!40000 ALTER TABLE `campaign_entry` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `campaign_external_url`
--

DROP TABLE IF EXISTS `campaign_external_url`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campaign_external_url` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `urltemplate` varchar(250) NOT NULL,
  `description` varchar(64) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `opentype` varchar(16) NOT NULL DEFAULT 'window',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campaign_external_url`
--

LOCK TABLES `campaign_external_url` WRITE;
/*!40000 ALTER TABLE `campaign_external_url` DISABLE KEYS */;
/*!40000 ALTER TABLE `campaign_external_url` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `campaign_form`
--

DROP TABLE IF EXISTS `campaign_form`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campaign_form` (
  `id_campaign` int(10) unsigned NOT NULL,
  `id_form` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_campaign`,`id_form`),
  KEY `id_campaign` (`id_campaign`),
  KEY `id_form` (`id_form`),
  CONSTRAINT `campaign_form_ibfk_1` FOREIGN KEY (`id_campaign`) REFERENCES `campaign` (`id`),
  CONSTRAINT `campaign_form_ibfk_2` FOREIGN KEY (`id_form`) REFERENCES `form` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campaign_form`
--

LOCK TABLES `campaign_form` WRITE;
/*!40000 ALTER TABLE `campaign_form` DISABLE KEYS */;
/*!40000 ALTER TABLE `campaign_form` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `campaign_form_entry`
--

DROP TABLE IF EXISTS `campaign_form_entry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campaign_form_entry` (
  `id_campaign` int(10) unsigned NOT NULL,
  `id_form` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_campaign`,`id_form`),
  KEY `id_campaign` (`id_campaign`),
  KEY `id_form` (`id_form`),
  CONSTRAINT `campaign_form_entry_ibfk_1` FOREIGN KEY (`id_campaign`) REFERENCES `campaign_entry` (`id`),
  CONSTRAINT `campaign_form_entry_ibfk_2` FOREIGN KEY (`id_form`) REFERENCES `form` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campaign_form_entry`
--

LOCK TABLES `campaign_form_entry` WRITE;
/*!40000 ALTER TABLE `campaign_form_entry` DISABLE KEYS */;
/*!40000 ALTER TABLE `campaign_form_entry` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact`
--

DROP TABLE IF EXISTS `contact`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cedula_ruc` varchar(15) NOT NULL,
  `name` varchar(50) NOT NULL,
  `telefono` varchar(15) NOT NULL,
  `apellido` varchar(50) NOT NULL,
  `origen` varchar(4) DEFAULT 'crm',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact`
--

LOCK TABLES `contact` WRITE;
/*!40000 ALTER TABLE `contact` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `current_call_entry`
--

DROP TABLE IF EXISTS `current_call_entry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `current_call_entry` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_agent` int(10) unsigned NOT NULL,
  `id_queue_call_entry` int(10) unsigned NOT NULL,
  `id_call_entry` int(10) unsigned NOT NULL,
  `callerid` varchar(15) NOT NULL,
  `datetime_init` datetime NOT NULL,
  `uniqueid` varchar(32) DEFAULT NULL,
  `ChannelClient` varchar(32) DEFAULT NULL,
  `hold` enum('N','S') DEFAULT 'N',
  PRIMARY KEY (`id`),
  KEY `id_agent` (`id_agent`),
  KEY `id_queue_call_entry` (`id_queue_call_entry`),
  KEY `id_call_entry` (`id_call_entry`),
  CONSTRAINT `current_call_entry_ibfk_1` FOREIGN KEY (`id_agent`) REFERENCES `agent` (`id`),
  CONSTRAINT `current_call_entry_ibfk_2` FOREIGN KEY (`id_queue_call_entry`) REFERENCES `queue_call_entry` (`id`),
  CONSTRAINT `current_call_entry_ibfk_3` FOREIGN KEY (`id_call_entry`) REFERENCES `call_entry` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `current_call_entry`
--

LOCK TABLES `current_call_entry` WRITE;
/*!40000 ALTER TABLE `current_call_entry` DISABLE KEYS */;
/*!40000 ALTER TABLE `current_call_entry` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `current_calls`
--

DROP TABLE IF EXISTS `current_calls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `current_calls` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_call` int(10) unsigned NOT NULL,
  `fecha_inicio` datetime NOT NULL,
  `uniqueid` varchar(32) DEFAULT NULL,
  `queue` varchar(16) NOT NULL,
  `agentnum` varchar(16) NOT NULL,
  `event` varchar(32) NOT NULL,
  `Channel` varchar(32) NOT NULL DEFAULT '',
  `ChannelClient` varchar(32) DEFAULT NULL,
  `hold` enum('N','S') DEFAULT 'N',
  PRIMARY KEY (`id`),
  KEY `id_call` (`id_call`),
  CONSTRAINT `current_calls_ibfk_1` FOREIGN KEY (`id_call`) REFERENCES `calls` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `current_calls`
--

LOCK TABLES `current_calls` WRITE;
/*!40000 ALTER TABLE `current_calls` DISABLE KEYS */;
/*!40000 ALTER TABLE `current_calls` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dont_call`
--

DROP TABLE IF EXISTS `dont_call`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dont_call` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `caller_id` varchar(15) NOT NULL,
  `date_income` datetime DEFAULT NULL,
  `status` varchar(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `callerid` (`caller_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dont_call`
--

LOCK TABLES `dont_call` WRITE;
/*!40000 ALTER TABLE `dont_call` DISABLE KEYS */;
/*!40000 ALTER TABLE `dont_call` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eccp_authorized_clients`
--

DROP TABLE IF EXISTS `eccp_authorized_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eccp_authorized_clients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL,
  `md5_password` varchar(32) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eccp_authorized_clients`
--

LOCK TABLES `eccp_authorized_clients` WRITE;
/*!40000 ALTER TABLE `eccp_authorized_clients` DISABLE KEYS */;
/*!40000 ALTER TABLE `eccp_authorized_clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `form`
--

DROP TABLE IF EXISTS `form`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `form` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(40) NOT NULL,
  `descripcion` varchar(150) NOT NULL,
  `estatus` varchar(1) NOT NULL DEFAULT 'A',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `form`
--

LOCK TABLES `form` WRITE;
/*!40000 ALTER TABLE `form` DISABLE KEYS */;
/*!40000 ALTER TABLE `form` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `form_data_recolected`
--

DROP TABLE IF EXISTS `form_data_recolected`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `form_data_recolected` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_calls` int(10) unsigned NOT NULL,
  `id_form_field` int(10) unsigned NOT NULL,
  `value` varchar(250) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_form_field` (`id_form_field`),
  KEY `id_calls` (`id_calls`),
  CONSTRAINT `form_data_recolected_ibfk_1` FOREIGN KEY (`id_form_field`) REFERENCES `form_field` (`id`),
  CONSTRAINT `form_data_recolected_ibfk_2` FOREIGN KEY (`id_calls`) REFERENCES `calls` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `form_data_recolected`
--

LOCK TABLES `form_data_recolected` WRITE;
/*!40000 ALTER TABLE `form_data_recolected` DISABLE KEYS */;
/*!40000 ALTER TABLE `form_data_recolected` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `form_data_recolected_entry`
--

DROP TABLE IF EXISTS `form_data_recolected_entry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `form_data_recolected_entry` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_call_entry` int(10) unsigned NOT NULL,
  `id_form_field` int(10) unsigned NOT NULL,
  `value` varchar(250) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_call_entry` (`id_call_entry`),
  KEY `id_form_field` (`id_form_field`),
  CONSTRAINT `form_data_recolected_entry_ibfk_1` FOREIGN KEY (`id_call_entry`) REFERENCES `call_entry` (`id`),
  CONSTRAINT `form_data_recolected_entry_ibfk_2` FOREIGN KEY (`id_form_field`) REFERENCES `form_field` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `form_data_recolected_entry`
--

LOCK TABLES `form_data_recolected_entry` WRITE;
/*!40000 ALTER TABLE `form_data_recolected_entry` DISABLE KEYS */;
/*!40000 ALTER TABLE `form_data_recolected_entry` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `form_field`
--

DROP TABLE IF EXISTS `form_field`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `form_field` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_form` int(10) unsigned NOT NULL,
  `etiqueta` text NOT NULL,
  `value` text NOT NULL,
  `tipo` varchar(25) NOT NULL,
  `orden` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_form` (`id_form`),
  CONSTRAINT `form_field_ibfk_1` FOREIGN KEY (`id_form`) REFERENCES `form` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `form_field`
--

LOCK TABLES `form_field` WRITE;
/*!40000 ALTER TABLE `form_field` DISABLE KEYS */;
/*!40000 ALTER TABLE `form_field` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `queue_call_entry`
--

DROP TABLE IF EXISTS `queue_call_entry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `queue_call_entry` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(50) DEFAULT NULL,
  `date_init` date DEFAULT NULL,
  `time_init` time DEFAULT NULL,
  `date_end` date DEFAULT NULL,
  `time_end` time DEFAULT NULL,
  `estatus` varchar(1) NOT NULL DEFAULT 'A',
  `script` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `queue_call_entry`
--

LOCK TABLES `queue_call_entry` WRITE;
/*!40000 ALTER TABLE `queue_call_entry` DISABLE KEYS */;
/*!40000 ALTER TABLE `queue_call_entry` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `valor_config`
--

DROP TABLE IF EXISTS `valor_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `valor_config` (
  `config_key` varchar(32) NOT NULL,
  `config_value` varchar(128) NOT NULL,
  `config_blob` blob,
  PRIMARY KEY (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `valor_config`
--

LOCK TABLES `valor_config` WRITE;
/*!40000 ALTER TABLE `valor_config` DISABLE KEYS */;
/*!40000 ALTER TABLE `valor_config` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2018-07-07 20:15:19

GRANT all ON call_center.* to asterisk@localhost identified by 'asterisk';


