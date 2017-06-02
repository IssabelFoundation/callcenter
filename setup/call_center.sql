-- MySQL dump 10.10
--
-- Host: localhost    Database: call_center
-- ------------------------------------------------------
-- Server version	5.0.22

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
-- Current Database: `call_center`
--


USE `call_center`;

--
-- Table structure for table `agent`
--
CREATE TABLE IF NOT EXISTS `agent` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `type` enum('Agent','SIP','IAX2') NOT NULL default 'Agent',
  `number` varchar(40) NOT NULL,
  `name` varchar(250) NOT NULL,
  `password` varchar(250) NOT NULL,
  `estatus` enum('A','I') default 'A',
  `eccp_password` varchar(128),
  PRIMARY KEY  (`id`),
  KEY `agent_type` (`estatus`,`type`,`number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `audit`
--
CREATE TABLE IF NOT EXISTS `audit` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `id_agent` int(10) unsigned NOT NULL,
  `id_break` int(10) unsigned DEFAULT NULL,
  `datetime_init` datetime NOT NULL,
  `datetime_end` datetime default NULL,
  `duration` time default NULL,
  `ext_parked` varchar(10) default NULL,
  PRIMARY KEY  (`id`),
  KEY `id_agent` (`id_agent`),
  KEY `id_break` (`id_break`),
  CONSTRAINT `audit_ibfk_1` FOREIGN KEY (`id_agent`) REFERENCES `agent` (`id`),
  CONSTRAINT `audit_ibfk_2` FOREIGN KEY (`id_break`) REFERENCES `break` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `break`
--

CREATE TABLE IF NOT EXISTS `break` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `name` varchar(250) NOT NULL,
  `description` varchar(250) default NULL,
  `status` varchar(1) NOT NULL default 'A',
  `tipo` enum('B','H') default 'B',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `break`
--
/*!40000 ALTER TABLE `break` DISABLE KEYS */;
LOCK TABLES `break` WRITE;
REPLACE INTO `break` VALUES (1,'Hold','Hold','A','H');
UNLOCK TABLES;
/*!40000 ALTER TABLE `break` ENABLE KEYS */;


--
-- Table structure for table `call_attribute`
--
CREATE TABLE IF NOT EXISTS `call_attribute` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `id_call` int(10) unsigned NOT NULL,
  `columna` varchar(30) default NULL,
  `value` varchar(128) NOT NULL,
  `column_number` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `id_call` (`id_call`),
  CONSTRAINT `call_attribute_ibfk_1` FOREIGN KEY (`id_call`) REFERENCES `calls` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `calls`
--

CREATE TABLE IF NOT EXISTS `calls` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `id_campaign` int(10) unsigned NOT NULL,
  `phone` varchar(32) NOT NULL,
  `status` varchar(32) default NULL,
  `uniqueid` varchar(32) default NULL,
  `fecha_llamada` datetime default NULL,	/* Received event: OriginateResponse */
  `start_time` datetime default NULL,		/* Received event: Link */
  `end_time` datetime default NULL,			/* Received event: Unlink/Hangup */
  `retries` int(10) unsigned NOT NULL default '0',
  `duration` int(10) unsigned default NULL,
  `id_agent` int(10) unsigned default NULL,
  `transfer` varchar(6) default NULL,
  `datetime_entry_queue` datetime default NULL,	/* Received event: Join */
  `duration_wait` int(11) default NULL,
  `dnc` int(1) NOT NULL default '0',

  `date_init`   date,
  `date_end`    date,
  `time_init`   time,
  `time_end`    time,

  /* 2009-05-07: If not NULL, this is a record of a call to be handled later
     by a specific agent. This indicates the agent that should handle the call.
     Format Agent/XXXX
   */
  `agent`       varchar(32),

  /* 2010-05-12: Failure cause number and description for a failed call */
  `failure_cause`		int(10) unsigned default NULL,
  `failure_cause_txt`	varchar(32) default NULL,

  /* 2010-06-18: Store timestamp of Originate call */
  `datetime_originate` datetime default NULL,

  /* 2012-12-07: Store trunk used to route outgoing call */
  `trunk`               varchar(20),

  /* 2015-12-12: Tell apart calls loaded from CSV and scheduled calls */
  `scheduled` BOOLEAN NOT NULL DEFAULT 0,

  PRIMARY KEY  (`id`),
  KEY `id_campaign` (`id_campaign`),
  KEY `calls_ibfk_2` (`id_agent`),
  KEY `campaign_date_schedule` (`id_campaign`, `date_init`, `date_end`, `time_init`, `time_end`),
  CONSTRAINT `calls_ibfk_1` FOREIGN KEY (`id_campaign`) REFERENCES `campaign` (`id`),
  CONSTRAINT `calls_ibfk_2` FOREIGN KEY (`id_agent`) REFERENCES `agent` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `campaign`
--
CREATE TABLE IF NOT EXISTS `campaign` (
    `id`                int(10) unsigned NOT NULL auto_increment,
    `name`              varchar(64) NOT NULL,
    `datetime_init`     date NOT NULL,
    `datetime_end`      date NOT NULL,
    `daytime_init`      time NOT NULL,
    `daytime_end`       time NOT NULL,
    `retries`           int(10) unsigned NOT NULL default '1',
    `trunk`             varchar(255),
    `context`           varchar(32) NOT NULL,
    `queue`             varchar(16) NOT NULL,
    `max_canales`       int(10) unsigned NOT NULL default '0',
    `num_completadas`   int(10) unsigned default NULL,
    `promedio`          int(10) unsigned default NULL,
    `desviacion`        int(10) unsigned default NULL,
    `script`            text NOT NULL,
    `estatus`           varchar(1) NOT NULL default 'A',
    `id_url`            int unsigned,

  PRIMARY KEY  (`id`),
  FOREIGN KEY (id_url)  REFERENCES campaign_external_url(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/* Upgrade from old length, if it applies */
ALTER TABLE campaign
CHANGE COLUMN trunk
trunk varchar(255);

--
-- Table structure for table `campaign_form`
--
CREATE TABLE IF NOT EXISTS `campaign_form` (
  `id_campaign` int(10) unsigned NOT NULL,
  `id_form` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`id_campaign`,`id_form`),
  KEY `id_campaign` (`id_campaign`),
  KEY `id_form` (`id_form`),
  CONSTRAINT `campaign_form_ibfk_1` FOREIGN KEY (`id_campaign`) REFERENCES `campaign` (`id`),
  CONSTRAINT `campaign_form_ibfk_2` FOREIGN KEY (`id_form`) REFERENCES `form` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


--
-- Table structure for table `contact`
--
CREATE TABLE IF NOT EXISTS `contact` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `cedula_ruc` varchar(15) NOT NULL,
  `name` varchar(50) NOT NULL,
  `telefono` varchar(15) NOT NULL,
  `apellido` varchar(50) NOT NULL,
  `origen` varchar(4) default 'crm',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


--
-- Table structure for table `current_call_entry`
--
CREATE TABLE IF NOT EXISTS `current_call_entry` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `id_agent` int(10) unsigned NOT NULL,
  `id_queue_call_entry` int(10) unsigned NOT NULL,
  `id_call_entry` int(10) unsigned NOT NULL,
  `callerid` varchar(15) NOT NULL,
  `datetime_init` datetime NOT NULL,
  `uniqueid` varchar(32) default NULL,
  `ChannelClient` varchar(32) default NULL,
  `hold` enum('N','S') default 'N',
  PRIMARY KEY  (`id`),
  KEY `id_agent` (`id_agent`),
  KEY `id_queue_call_entry` (`id_queue_call_entry`),
  KEY `id_call_entry` (`id_call_entry`),
  CONSTRAINT `current_call_entry_ibfk_1` FOREIGN KEY (`id_agent`) REFERENCES `agent` (`id`),
  CONSTRAINT `current_call_entry_ibfk_2` FOREIGN KEY (`id_queue_call_entry`) REFERENCES `queue_call_entry` (`id`),
  CONSTRAINT `current_call_entry_ibfk_3` FOREIGN KEY (`id_call_entry`) REFERENCES `call_entry` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


--
-- Table structure for table `current_calls`
--
CREATE TABLE IF NOT EXISTS `current_calls` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `id_call` int(10) unsigned NOT NULL,
  `fecha_inicio` datetime NOT NULL,
  `uniqueid` varchar(32) default NULL,
  `queue` varchar(16) NOT NULL,
  `agentnum` varchar(16) NOT NULL,
  `event` varchar(32) NOT NULL,
  `Channel` varchar(32) NOT NULL default '',
  `ChannelClient` varchar(32) default NULL,
  `hold` enum('N','S') default 'N',
  PRIMARY KEY  (`id`),
  KEY `id_call` (`id_call`),
  CONSTRAINT `current_calls_ibfk_1` FOREIGN KEY (`id_call`) REFERENCES `calls` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


--
-- Table structure for table `form`
--
CREATE TABLE IF NOT EXISTS `form` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `nombre` varchar(40) NOT NULL,
  `descripcion` varchar(150) NOT NULL,
  `estatus` varchar(1) NOT NULL default 'A',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


--
-- Table structure for table `form_data_recolected`
--
CREATE TABLE IF NOT EXISTS `form_data_recolected` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `id_calls` int(10) unsigned NOT NULL,
  `id_form_field` int(10) unsigned NOT NULL,
  `value` varchar(250) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `id_form_field` (`id_form_field`),
  KEY `id_calls` (`id_calls`),
  CONSTRAINT `form_data_recolected_ibfk_1` FOREIGN KEY (`id_form_field`) REFERENCES `form_field` (`id`),
  CONSTRAINT `form_data_recolected_ibfk_2` FOREIGN KEY (`id_calls`) REFERENCES `calls` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


--
-- Table structure for table `form_field`
--
CREATE TABLE IF NOT EXISTS `form_field` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `id_form` int(10) unsigned NOT NULL,
  `etiqueta` text NOT NULL,
  `value` text NOT NULL,
  `tipo` varchar(25) NOT NULL,
  `orden` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `id_form` (`id_form`),
  CONSTRAINT `form_field_ibfk_1` FOREIGN KEY (`id_form`) REFERENCES `form` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
ALTER TABLE form_field CHANGE COLUMN etiqueta etiqueta TEXT NOT NULL;
ALTER TABLE form_field CHANGE COLUMN value value TEXT NOT NULL;

--
-- Table structure for table `queue_call_entry`
--
CREATE TABLE IF NOT EXISTS `queue_call_entry` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `queue` varchar(50) default NULL,
  `date_init` date default NULL,
  `time_init` time default NULL,
  `date_end` date default NULL,
  `time_end` time default NULL,
  `estatus` varchar(1) NOT NULL default 'A',
  `script` text,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `dont_call`
--
CREATE TABLE IF NOT EXISTS `dont_call` (
  `id` int(11) NOT NULL auto_increment,
  `caller_id` varchar(15) NOT NULL,
  `date_income` datetime default NULL,
  `status` varchar(1) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

/*
 * Tabla valor_config, almacena configuraciones compartidas de Web
 */
CREATE TABLE IF NOT EXISTS valor_config
(
    config_key     varchar(32)     NOT NULL        PRIMARY KEY,
    config_value   varchar(128)    NOT NULL,
    config_blob    BLOB
) ENGINE=InnoDB;

/*
 * Tabla campaign_entry, almacena las campañas entrantes que hayan sido creadas
 */
CREATE TABLE IF NOT EXISTS campaign_entry
(
    id                  int unsigned NOT NULL   AUTO_INCREMENT  PRIMARY KEY,
    name                varchar(64)  NOT NULL DEFAULT '',
    id_queue_call_entry int unsigned NOT NULL,
    id_form             int unsigned,
    datetime_init       date    NOT NULL,
    datetime_end        date    NOT NULL,
    daytime_init        time    NOT NULL,
    daytime_end         time    NOT NULL,
    estatus             varchar(1)  NOT NULL DEFAULT 'A',
    script              text    NOT NULL,
    id_url              int unsigned,

    FOREIGN KEY (id_queue_call_entry) REFERENCES queue_call_entry(id),
    FOREIGN KEY (id_form) REFERENCES form(id),
    FOREIGN KEY (id_url)  REFERENCES campaign_external_url(id)
) ENGINE=InnoDB;

/*
 * Tabla form_data_recolected_entry, almacena la información recolectada para campañas entrantes
 */
CREATE TABLE IF NOT EXISTS form_data_recolected_entry
(
    id                  int unsigned    NOT NULL    AUTO_INCREMENT  PRIMARY KEY,
    id_call_entry       int unsigned    NOT NULL,
    id_form_field       int unsigned    NOT NULL,
    value               varchar(250)    NOT NULL,

    FOREIGN KEY (id_call_entry) REFERENCES call_entry (id),
    FOREIGN KEY (id_form_field) REFERENCES form_field (id)
) ENGINE=InnoDB;

--
-- Table structure for table `campaign_form_entry`
--
CREATE TABLE IF NOT EXISTS `campaign_form_entry` (
  `id_campaign` int(10) unsigned NOT NULL,
  `id_form` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`id_campaign`,`id_form`),
  KEY `id_campaign` (`id_campaign`),
  KEY `id_form` (`id_form`),
  CONSTRAINT `campaign_form_entry_ibfk_1` FOREIGN KEY (`id_campaign`) REFERENCES `campaign_entry` (`id`),
  CONSTRAINT `campaign_form_entry_ibfk_2` FOREIGN KEY (`id_form`) REFERENCES `form` (`id`)
) ENGINE=InnoDB;

--
-- Table structure for table `call_entry`
--
CREATE TABLE IF NOT EXISTS `call_entry` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `id_agent` int(10) unsigned default NULL,
  `id_queue_call_entry` int(10) unsigned NOT NULL,
  `id_contact` int(10) unsigned default NULL,
  `callerid` varchar(15) NOT NULL,
  `datetime_init` datetime default NULL,
  `datetime_end` datetime default NULL,
  `duration` int(10) unsigned default NULL,
  `status` varchar(32) default NULL,
  `transfer` varchar(6) default NULL,
  `datetime_entry_queue` datetime default NULL,
  `duration_wait` int(11) default NULL,
  `uniqueid` varchar(32) default NULL,
  `id_campaign` int(10) unsigned,
  `trunk` varchar(20) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `id_agent` (`id_agent`),
  KEY `id_queue_call_entry` (`id_queue_call_entry`),
  KEY `id_contact` (`id_contact`),
  CONSTRAINT `call_entry_ibfk_1` FOREIGN KEY (`id_agent`) REFERENCES `agent` (`id`),
  CONSTRAINT `call_entry_ibfk_2` FOREIGN KEY (`id_queue_call_entry`) REFERENCES `queue_call_entry` (`id`),
  CONSTRAINT `call_entry_ibfk_3` FOREIGN KEY (`id_contact`) REFERENCES `contact` (`id`),
  CONSTRAINT `call_entry_ibfk_4` FOREIGN KEY (`id_campaign`) REFERENCES `campaign_entry` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `current_call_entry`
--
CREATE TABLE IF NOT EXISTS `current_call_entry` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `id_agent` int(10) unsigned NOT NULL,
  `id_queue_call_entry` int(10) unsigned NOT NULL,
  `id_call_entry` int(10) unsigned NOT NULL,
  `callerid` varchar(15) NOT NULL,
  `datetime_init` datetime NOT NULL,
  `uniqueid` varchar(32) default NULL,
  `ChannelClient` varchar(32) default NULL,
  `hold` enum('N','S') default 'N',
  PRIMARY KEY  (`id`),
  KEY `id_agent` (`id_agent`),
  KEY `id_queue_call_entry` (`id_queue_call_entry`),
  KEY `id_call_entry` (`id_call_entry`),
  CONSTRAINT `current_call_entry_ibfk_1` FOREIGN KEY (`id_agent`) REFERENCES `agent` (`id`),
  CONSTRAINT `current_call_entry_ibfk_2` FOREIGN KEY (`id_queue_call_entry`) REFERENCES `queue_call_entry` (`id`),
  CONSTRAINT `current_call_entry_ibfk_3` FOREIGN KEY (`id_call_entry`) REFERENCES `call_entry` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `eccp_authorized_clients`
--
CREATE TABLE IF NOT EXISTS `eccp_authorized_clients` (
    `id` int(10) unsigned NOT NULL auto_increment,
    `username` varchar(64) NOT NULL,
    `md5_password` varchar(32) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `campaign_external_url`
--
CREATE TABLE IF NOT EXISTS `campaign_external_url` (
    `id` int(10) unsigned NOT NULL auto_increment,
    `urltemplate`   varchar(250)    NOT NULL,
    `description`   varchar(64)     NOT NULL,
    `active`        boolean         NOT NULL    DEFAULT 1,
    `opentype`      varchar(16)     NOT NULL    DEFAULT 'window', /* window iframe jsonp */

    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `call_progress_log`
--
CREATE TABLE IF NOT EXISTS `call_progress_log` (
    `id` int(10) unsigned NOT NULL auto_increment,

    `datetime_entry`    datetime    NOT NULL,
    `id_campaign_incoming`  int(10) unsigned,
    `id_call_incoming`      int(10) unsigned,
    `id_campaign_outgoing`  int(10) unsigned,
    `id_call_outgoing`      int(10) unsigned,

    `new_status`        varchar(32) NOT NULL,
    `retry`             int(10) unsigned,
    `uniqueid`          varchar(32),
    `trunk`             varchar(20),
    `id_agent`          int(10) unsigned,
    `duration`          int(10) unsigned,

    PRIMARY KEY (`id`),
    CONSTRAINT `call_progress_log_ibfk_1` FOREIGN KEY (`id_call_incoming`) REFERENCES `call_entry` (`id`),
    CONSTRAINT `call_progress_log_ibfk_2` FOREIGN KEY (`id_call_outgoing`) REFERENCES `calls` (`id`),
    CONSTRAINT `call_progress_log_ibfk_4` FOREIGN KEY (`id_agent`) REFERENCES `agent` (`id`),
    CONSTRAINT `call_progress_log_ibfk_5` FOREIGN KEY (`id_campaign_incoming`) REFERENCES `campaign_entry` (`id`),
    CONSTRAINT `call_progress_log_ibfk_6` FOREIGN KEY (`id_campaign_outgoing`) REFERENCES `campaign` (`id`),
    KEY `incoming_datetime_entry` (`id_call_incoming`, `datetime_entry`),
    KEY `outgoing_datetime_entry` (`id_call_outgoing`, `datetime_entry`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `call_recording`
--
CREATE TABLE IF NOT EXISTS `call_recording` (
    `id` int(10) unsigned NOT NULL auto_increment,

    `datetime_entry`    datetime    NOT NULL,
    `id_call_incoming`      int(10) unsigned,
    `id_call_outgoing`      int(10) unsigned,
    `uniqueid`              varchar(32) NOT NULL,
    `channel`               varchar(80) NOT NULL,
    `recordingfile`         varchar(255),

    PRIMARY KEY (`id`),
    CONSTRAINT `call_recording_ibfk_1` FOREIGN KEY (`id_call_incoming`) REFERENCES `call_entry` (`id`),
    CONSTRAINT `call_recording_ibfk_2` FOREIGN KEY (`id_call_outgoing`) REFERENCES `calls` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


/* Procedimiento que agrega soporte para DNC (DO NOT CALL) y quita la columna agent.queue */
DELIMITER ++ ;

DROP PROCEDURE IF EXISTS temp_actualizar_campos_2008_09_09 ++
CREATE PROCEDURE temp_actualizar_campos_2008_09_09 ()
    READS SQL DATA
    MODIFIES SQL DATA
BEGIN
	DECLARE l_existe_columna tinyint(1);

	SET l_existe_columna = 0;

	/* Verificar existencia de columna agent.queue que debe eliminarse */
	SELECT COUNT(*) INTO l_existe_columna
	FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = 'call_center'
		AND TABLE_NAME = 'agent'
		AND COLUMN_NAME = 'queue';
	IF l_existe_columna > 0 THEN
		ALTER TABLE agent
		DROP COLUMN queue;
	END IF;

	/* Verificar existencia de columna calls.dnc que debe agregarse */
	SELECT COUNT(*) INTO l_existe_columna
	FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = 'call_center'
		AND TABLE_NAME = 'calls'
		AND COLUMN_NAME = 'dnc';
	IF l_existe_columna = 0 THEN
		ALTER TABLE calls
		ADD COLUMN dnc int(1) NOT NULL DEFAULT '0';
	END IF;
END;
++
DELIMITER ; ++

CALL temp_actualizar_campos_2008_09_09();
DROP PROCEDURE IF EXISTS temp_actualizar_campos_2008_09_09;

/* Procedimiento para agregar infraestructura de recolección de datos para llamada entrante */
DELIMITER ++ ;

DROP PROCEDURE IF EXISTS temp_campania_entrante_2008_12_05 ++
CREATE PROCEDURE temp_campania_entrante_2008_12_05 ()
    READS SQL DATA
    MODIFIES SQL DATA
BEGIN
	DECLARE l_existe_columna tinyint(1);

	SET l_existe_columna = 0;

	/* Verificar existencia de columna call_entry.id_campaign que debe agregarse */
	SELECT COUNT(*) INTO l_existe_columna
	FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = 'call_center'
		AND TABLE_NAME = 'call_entry'
		AND COLUMN_NAME = 'id_campaign';
	IF l_existe_columna = 0 THEN
        ALTER TABLE call_entry
        ADD COLUMN id_campaign  int unsigned,
        ADD FOREIGN KEY (id_campaign) REFERENCES campaign_entry (id);
	END IF;
END;
++
DELIMITER ; ++

CALL temp_campania_entrante_2008_12_05();
DROP PROCEDURE IF EXISTS temp_campania_entrante_2008_12_05;


/* Procedimiento para agregar infraestructura de llamadas agendadas */
DELIMITER ++ ;

DROP PROCEDURE IF EXISTS temp_llamadas_agendadas_2009_02_20 ++
CREATE PROCEDURE temp_llamadas_agendadas_2009_02_20 ()
    READS SQL DATA
    MODIFIES SQL DATA
BEGIN
    DECLARE l_existe_columna tinyint(1);

    SET l_existe_columna = 0;

    /* Verificar existencia de columna calls.date_init que debe agregarse */
    SELECT COUNT(*) INTO l_existe_columna
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'call_center'
        AND TABLE_NAME = 'calls'
        AND COLUMN_NAME = 'date_init';
    IF l_existe_columna = 0 THEN
        ALTER TABLE calls
        ADD COLUMN date_init  date,
        ADD COLUMN date_end  date,
        ADD COLUMN time_init  time,
        ADD COLUMN time_end  time;
    END IF;
END;
++
DELIMITER ; ++

CALL temp_llamadas_agendadas_2009_02_20();
DROP PROCEDURE IF EXISTS temp_llamadas_agendadas_2009_02_20;

/* Procedimiento que agrega soporte para calls.agent para llamadas atendidas por agente */
DELIMITER ++ ;

DROP PROCEDURE IF EXISTS temp_calls_agent_2009_05_07 ++
CREATE PROCEDURE temp_calls_agent_2009_05_07 ()
    READS SQL DATA
    MODIFIES SQL DATA
BEGIN
    DECLARE l_existe_columna tinyint(1);

    SET l_existe_columna = 0;

    SELECT COUNT(*) INTO l_existe_columna
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'call_center'
        AND TABLE_NAME = 'calls'
        AND COLUMN_NAME = 'agent';
    IF l_existe_columna = 0 THEN
        ALTER TABLE calls
        ADD COLUMN agent varchar(32);
    END IF;
END;
++
DELIMITER ; ++

CALL temp_calls_agent_2009_05_07();
DROP PROCEDURE IF EXISTS temp_calls_agent_2009_05_07;


/* Procedimiento para agregar recolección de trunk de llamada entrante */
DELIMITER ++ ;

DROP PROCEDURE IF EXISTS temp_campania_entrante_trunk_2009_06_04 ++
CREATE PROCEDURE temp_campania_entrante_trunk_2009_06_04 ()
    READS SQL DATA
    MODIFIES SQL DATA
BEGIN
    DECLARE l_existe_columna tinyint(1);

    SET l_existe_columna = 0;

    /* Verificar existencia de columna call_entry.trunk que debe agregarse */
    SELECT COUNT(*) INTO l_existe_columna
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'call_center'
        AND TABLE_NAME = 'call_entry'
        AND COLUMN_NAME = 'trunk';
    IF l_existe_columna = 0 THEN
        ALTER TABLE call_entry
        ADD COLUMN trunk varchar(20) NOT NULL;
    END IF;
END;
++
DELIMITER ; ++

CALL temp_campania_entrante_trunk_2009_06_04();
DROP PROCEDURE IF EXISTS temp_campania_entrante_trunk_2009_06_04;

/* Procedimiento para agregar las columnas failure_cause y failure_cause_txt */
DELIMITER ++ ;

DROP PROCEDURE IF EXISTS temp_calls_failure_cause_2010_06_11 ++
CREATE PROCEDURE temp_calls_failure_cause_2010_06_11 ()
    READS SQL DATA
    MODIFIES SQL DATA
BEGIN
    DECLARE l_existe_columna tinyint(1);

    SET l_existe_columna = 0;

    /* Verificar existencia de columna calls.failure_cause que debe agregarse */
    SELECT COUNT(*) INTO l_existe_columna
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'call_center'
        AND TABLE_NAME = 'calls'
        AND COLUMN_NAME = 'failure_cause';
    IF l_existe_columna = 0 THEN
		ALTER TABLE calls
		ADD COLUMN failure_cause int(10) unsigned default null,
		ADD COLUMN failure_cause_txt varchar(32) default null;
    END IF;
END;
++
DELIMITER ; ++

CALL temp_calls_failure_cause_2010_06_11();
DROP PROCEDURE IF EXISTS temp_calls_failure_cause_2010_06_11;

/* Procedimiento para agregar columna que registra fecha de Originate */
DELIMITER ++ ;

DROP PROCEDURE IF EXISTS temp_calls_datetime_originate_2010_06_18 ++
CREATE PROCEDURE temp_calls_datetime_originate_2010_06_18 ()
    READS SQL DATA
    MODIFIES SQL DATA
BEGIN
    DECLARE l_existe_columna tinyint(1);

    SET l_existe_columna = 0;

    /* Verificar existencia de columna call_entry.trunk que debe agregarse */
    SELECT COUNT(*) INTO l_existe_columna
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'call_center'
        AND TABLE_NAME = 'calls'
        AND COLUMN_NAME = 'datetime_originate';
    IF l_existe_columna = 0 THEN
        ALTER TABLE calls ADD COLUMN datetime_originate datetime default NULL;
    END IF;
END;
++
DELIMITER ; ++

CALL temp_calls_datetime_originate_2010_06_18();
DROP PROCEDURE IF EXISTS temp_calls_datetime_originate_2010_06_18;

/* Procedimiento para agregar columna que registra clave de agente para ECCP */
DELIMITER ++ ;

DROP PROCEDURE IF EXISTS temp_agent_eccp_password_2011_02_14 ++
CREATE PROCEDURE temp_agent_eccp_password_2011_02_14 ()
    READS SQL DATA
    MODIFIES SQL DATA
BEGIN
    DECLARE l_existe_columna tinyint(1);

    SET l_existe_columna = 0;

    /* Verificar existencia de columna agent.eccp_password que debe agregarse */
    SELECT COUNT(*) INTO l_existe_columna
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'call_center'
        AND TABLE_NAME = 'agent'
        AND COLUMN_NAME = 'eccp_password';
    IF l_existe_columna = 0 THEN
        ALTER TABLE agent ADD COLUMN eccp_password varchar(128) default NULL;
    END IF;
END;
++
DELIMITER ; ++

CALL temp_agent_eccp_password_2011_02_14();
DROP PROCEDURE IF EXISTS temp_agent_eccp_password_2011_02_14;

/* Procedimiento para agregar columna que apunta a URL externo opcional */
DELIMITER ++ ;

DROP PROCEDURE IF EXISTS temp_campaign_external_url_2012_01_23 ++
CREATE PROCEDURE temp_campaign_external_url_2012_01_23 ()
    READS SQL DATA
    MODIFIES SQL DATA
BEGIN
    DECLARE l_existe_columna tinyint(1);

    SET l_existe_columna = 0;

    /* Verificar existencia de columna campaign.id_url que debe agregarse */
    SELECT COUNT(*) INTO l_existe_columna
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'call_center'
        AND TABLE_NAME = 'campaign'
        AND COLUMN_NAME = 'id_url';
    IF l_existe_columna = 0 THEN
        ALTER TABLE campaign
            ADD COLUMN id_url int unsigned,
            ADD FOREIGN KEY (id_url) REFERENCES campaign_external_url (id);
    END IF;

    /* Verificar existencia de columna campaign_entry.id_url que debe agregarse */
    SELECT COUNT(*) INTO l_existe_columna
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'call_center'
        AND TABLE_NAME = 'campaign_entry'
        AND COLUMN_NAME = 'id_url';
    IF l_existe_columna = 0 THEN
        ALTER TABLE campaign_entry
            ADD COLUMN id_url int unsigned,
            ADD FOREIGN KEY (id_url) REFERENCES campaign_external_url (id);
    END IF;
END;
++
DELIMITER ; ++

CALL temp_campaign_external_url_2012_01_23();
DROP PROCEDURE IF EXISTS temp_campaign_external_url_2012_01_23;

/* Procedimiento para agregar recolección de trunk de llamada saliente */
DELIMITER ++ ;

DROP PROCEDURE IF EXISTS temp_campania_saliente_trunk_2012_12_07 ++
CREATE PROCEDURE temp_campania_saliente_trunk_2012_12_07 ()
    READS SQL DATA
    MODIFIES SQL DATA
BEGIN
    DECLARE l_existe_columna tinyint(1);

    SET l_existe_columna = 0;

    /* Verificar existencia de columna calls.trunk que debe agregarse */
    SELECT COUNT(*) INTO l_existe_columna
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'call_center'
        AND TABLE_NAME = 'calls'
        AND COLUMN_NAME = 'trunk';
    IF l_existe_columna = 0 THEN
        ALTER TABLE calls
        ADD COLUMN trunk varchar(20);
    END IF;
END;
++
DELIMITER ; ++

CALL temp_campania_saliente_trunk_2012_12_07();
DROP PROCEDURE IF EXISTS temp_campania_saliente_trunk_2012_12_07;

/* Procedimiento para agregar tipo de agente para callback login */
DELIMITER ++ ;

DROP PROCEDURE IF EXISTS temp_agente_tipo_2012_12_26 ++
CREATE PROCEDURE temp_agente_tipo_2012_12_26 ()
    READS SQL DATA
    MODIFIES SQL DATA
BEGIN
    DECLARE l_existe_columna tinyint(1);

    SET l_existe_columna = 0;

    /* Verificar existencia de columna agent.type que debe agregarse */
    SELECT COUNT(*) INTO l_existe_columna
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'call_center'
        AND TABLE_NAME = 'agent'
        AND COLUMN_NAME = 'type';
    IF l_existe_columna = 0 THEN
        ALTER TABLE agent
        ADD COLUMN type enum('Agent','SIP','IAX2') DEFAULT 'Agent' NOT NULL AFTER id;
    END IF;
END;
++
DELIMITER ; ++

CALL temp_agente_tipo_2012_12_26();
DROP PROCEDURE IF EXISTS temp_agente_tipo_2012_12_26;


/* Procedimiento para agregar índices necesarios para acelerar getagentactivitysummary */
DELIMITER ++ ;

DROP PROCEDURE IF EXISTS temp_indice_agent_calls_2014_09_08 ++
CREATE PROCEDURE temp_indice_agent_calls_2014_09_08 ()
    READS SQL DATA
    MODIFIES SQL DATA
BEGIN
    DECLARE l_existe_indice tinyint(1);

    SET l_existe_indice = 0;

    /* Verificar existencia de índices que deben agregarse */
    SELECT COUNT(*) INTO l_existe_indice
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = 'call_center'
        AND TABLE_NAME = 'audit'
        AND INDEX_NAME = 'agent_break_datetime';
    IF l_existe_indice = 0 THEN
        ALTER TABLE audit ADD KEY agent_break_datetime (id_agent, id_break, datetime_init);
        ALTER TABLE calls ADD KEY datetime_init (start_time);
        ALTER TABLE calls ADD KEY datetime_entry_queue (datetime_entry_queue);
        ALTER TABLE call_entry ADD KEY datetime_init (datetime_init);
        ALTER TABLE call_entry ADD KEY datetime_entry_queue (datetime_entry_queue);
    END IF;
END;
++
DELIMITER ; ++

CALL temp_indice_agent_calls_2014_09_08();
DROP PROCEDURE IF EXISTS temp_indice_agent_calls_2014_09_08;


/* Procedimiento para agregar índices necesarios para acelerar dont_call */
DELIMITER ++ ;

DROP PROCEDURE IF EXISTS temp_indice_dont_call_2014_09_16 ++
CREATE PROCEDURE temp_indice_dont_call_2014_09_16 ()
    READS SQL DATA
    MODIFIES SQL DATA
BEGIN
    DECLARE l_existe_indice tinyint(1);

    SET l_existe_indice = 0;

    /* Verificar existencia de índices que deben agregarse */
    SELECT COUNT(*) INTO l_existe_indice
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = 'call_center'
        AND TABLE_NAME = 'dont_call'
        AND INDEX_NAME = 'callerid';
    IF l_existe_indice = 0 THEN
        ALTER TABLE dont_call ADD KEY callerid (caller_id);
    END IF;
END;
++
DELIMITER ; ++

CALL temp_indice_dont_call_2014_09_16();
DROP PROCEDURE IF EXISTS temp_indice_dont_call_2014_09_16;


/* Procedimiento para migrar tabla call_recording a formato InnoDB */
DELIMITER ++ ;

DROP PROCEDURE IF EXISTS temp_engine_call_recording_2015_07_13 ++
CREATE PROCEDURE temp_engine_call_recording_2015_07_13 ()
    READS SQL DATA
    MODIFIES SQL DATA
BEGIN
    DECLARE l_engine varchar(64);

    SET l_engine = '';

    /* Verificar motor de tabla */
    SELECT ENGINE INTO l_engine
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = 'call_center'
        AND TABLE_NAME = 'call_recording';
    IF l_engine = 'MyISAM' THEN
        ALTER TABLE call_recording ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ALTER TABLE call_recording ADD FOREIGN KEY (id_call_incoming) REFERENCES call_entry(id);
        ALTER TABLE call_recording ADD FOREIGN KEY (id_call_outgoing) REFERENCES calls(id);
    END IF;
END;
++
DELIMITER ; ++

CALL temp_engine_call_recording_2015_07_13();
DROP PROCEDURE IF EXISTS temp_engine_call_recording_2015_07_13;


/* Procedimiento para agregar columnas de distinción de llamada agendada */
DELIMITER ++ ;

DROP PROCEDURE IF EXISTS temp_campaign_scheduled_2015_12_12 ++
CREATE PROCEDURE temp_campaign_scheduled_2015_12_12 ()
    READS SQL DATA
    MODIFIES SQL DATA
BEGIN
    DECLARE l_existe_columna tinyint(1);

    SET l_existe_columna = 0;

    /* Verificar existencia de columna calls.scheduled que debe agregarse */
    SELECT COUNT(*) INTO l_existe_columna
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'call_center'
        AND TABLE_NAME = 'calls'
        AND COLUMN_NAME = 'scheduled';
    IF l_existe_columna = 0 THEN
        ALTER TABLE calls ADD COLUMN scheduled BOOLEAN NOT NULL DEFAULT 0;
    END IF;
END;
++
DELIMITER ; ++

CALL temp_campaign_scheduled_2015_12_12();
DROP PROCEDURE IF EXISTS temp_campaign_scheduled_2015_12_12;


/* Procedimiento para agregar índices necesarios para acelerar agent */
DELIMITER ++ ;

DROP PROCEDURE IF EXISTS temp_indice_agent_2016_01_27 ++
CREATE PROCEDURE temp_indice_agent_2016_01_27 ()
    READS SQL DATA
    MODIFIES SQL DATA
BEGIN
    DECLARE l_existe_indice tinyint(1);

    SET l_existe_indice = 0;

    /* Verificar existencia de índices que deben agregarse */
    SELECT COUNT(*) INTO l_existe_indice
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = 'call_center'
        AND TABLE_NAME = 'agent'
        AND INDEX_NAME = 'agent_type';
    IF l_existe_indice = 0 THEN
        ALTER TABLE agent ADD KEY `agent_type` (`estatus`,`type`,`number`);
    END IF;
END;
++
DELIMITER ; ++

CALL temp_indice_agent_2016_01_27();
DROP PROCEDURE IF EXISTS temp_indice_agent_2016_01_27;


/* Procedimiento para agregar índices necesarios para acelerar agent */
DELIMITER ++ ;

DROP PROCEDURE IF EXISTS temp_indice_agent_2016_01_29 ++
CREATE PROCEDURE temp_indice_agent_2016_01_29 ()
    READS SQL DATA
    MODIFIES SQL DATA
BEGIN
    DECLARE l_existe_indice tinyint(1);

    SET l_existe_indice = 0;

    /* Verificar existencia de índices que deben agregarse */
    SELECT COUNT(*) INTO l_existe_indice
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = 'call_center'
        AND TABLE_NAME = 'calls'
        AND INDEX_NAME = 'campaign_date_schedule';
    IF l_existe_indice = 0 THEN
        ALTER TABLE calls ADD KEY `campaign_date_schedule` (`id_campaign`, `date_init`, `date_end`, `time_init`, `time_end`);
    END IF;
END;
++
DELIMITER ; ++

CALL temp_indice_agent_2016_01_29();
DROP PROCEDURE IF EXISTS temp_indice_agent_2016_01_29;


/*!40000 ALTER TABLE `queue_call_entry` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

