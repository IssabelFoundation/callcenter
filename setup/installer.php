<?php
/* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Issabel version 0.5                                                  |
  | http://www.issabel.org                                               |
  +----------------------------------------------------------------------+
  | Copyright (c) 2006 Palosanto Solutions S. A.                         |
  +----------------------------------------------------------------------+
  | The contents of this file are subject to the General Public License  |
  | (GPL) Version 2 (the "License"); you may not use this file except in |
  | compliance with the License. You may obtain a copy of the License at |
  | http://www.opensource.org/licenses/gpl-license.php                   |
  |                                                                      |
  | Software distributed under the License is distributed on an "AS IS"  |
  | basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See  |
  | the License for the specific language governing rights and           |
  | limitations under the License.                                       |
  +----------------------------------------------------------------------+
  | The Initial Developer of the Original Code is PaloSanto Solutions    |
  +----------------------------------------------------------------------+
  $Id:  $ */
$DocumentRoot = "/var/www/html";

require_once("$DocumentRoot/libs/paloSantoInstaller.class.php");
require_once("$DocumentRoot/libs/paloSantoDB.class.php");

$tmpDir = '/tmp/new_module/callcenter';  # in this folder the load module extract the package content
#generar el archivo db de campañas
$return=1;
$path_script_db="$tmpDir/setup/call_center.sql";
$datos_conexion['user']     = "asterisk";
$datos_conexion['password'] = "asterisk";
$datos_conexion['locate']   = "";
$oInstaller = new Installer();

if (file_exists($path_script_db))
{
    //STEP 1: Create database call_center
    $return=0;
    $return=$oInstaller->createNewDatabaseMySQL($path_script_db,"call_center",$datos_conexion);

    $pDB = new paloDB ('mysql://root:'.MYSQL_ROOT_PASSWORD.'@localhost/call_center');
    modificarDatosCallAttribute($pDB);
    modificarCampoCalls($pDB);

    quitarColumnaSiExiste($pDB, 'call_center', 'agent', 'queue');

    crearColumnaSiNoExiste($pDB, 'call_center', 'calls',
        'dnc',
        "ADD COLUMN dnc int(1) NOT NULL DEFAULT '0'");
    crearColumnaSiNoExiste($pDB, 'call_center', 'call_entry',
        'id_campaign',
        "ADD COLUMN id_campaign  int unsigned, ADD FOREIGN KEY (id_campaign) REFERENCES campaign_entry (id)");
    crearColumnaSiNoExiste($pDB, 'call_center', 'calls',
        'date_init',
        "ADD COLUMN date_init  date, ADD COLUMN date_end  date, ADD COLUMN time_init  time, ADD COLUMN time_end  time");
    crearColumnaSiNoExiste($pDB, 'call_center', 'calls',
        'agent',
        "ADD COLUMN agent varchar(32)");
    crearColumnaSiNoExiste($pDB, 'call_center', 'call_entry',
        'trunk',
        "ADD COLUMN trunk varchar(20) NOT NULL");
    crearColumnaSiNoExiste($pDB, 'call_center', 'calls',
        'failure_cause',
        "ADD COLUMN failure_cause int(10) unsigned default null, ADD COLUMN failure_cause_txt varchar(32) default null");
    crearColumnaSiNoExiste($pDB, 'call_center', 'calls',
        'datetime_originate',
        "ADD COLUMN datetime_originate datetime default NULL");
    crearColumnaSiNoExiste($pDB, 'call_center', 'agent',
        'eccp_password',
        "ADD COLUMN eccp_password varchar(128) default NULL");
    crearColumnaSiNoExiste($pDB, 'call_center', 'campaign',
        'id_url',
        "ADD COLUMN id_url int unsigned, ADD FOREIGN KEY (id_url) REFERENCES campaign_external_url (id)");
    crearColumnaSiNoExiste($pDB, 'call_center', 'campaign',
        'callerid',
        "ADD COLUMN callerid varchar(15) default NULL");
    crearColumnaSiNoExiste($pDB, 'call_center', 'campaign_entry',
        'id_url',
        "ADD COLUMN id_url int unsigned, ADD FOREIGN KEY (id_url) REFERENCES campaign_external_url (id)");
    crearColumnaSiNoExiste($pDB, 'call_center', 'calls',
        'trunk',
        "ADD COLUMN trunk varchar(20) NOT NULL");
    crearColumnaSiNoExiste($pDB, 'call_center', 'agent',
        'type',
        "ADD COLUMN type enum('Agent','SIP','IAX2') DEFAULT 'Agent' NOT NULL AFTER id");
    crearColumnaSiNoExiste($pDB, 'call_center', 'calls',
        'scheduled',
        "ADD COLUMN scheduled BOOLEAN NOT NULL DEFAULT 0");
    crearColumnaSiNoExiste($pDB, 'call_center', 'calls',
        'callerid',
        "ADD COLUMN callerid varchar(15) default NULL");

    crearIndiceSiNoExiste($pDB, 'call_center', 'audit',
        'agent_break_datetime',
        "ADD KEY agent_break_datetime (id_agent, id_break, datetime_init)");
    crearIndiceSiNoExiste($pDB, 'call_center', 'calls',
        'datetime_init',
        "ADD KEY datetime_init (start_time)");
    crearIndiceSiNoExiste($pDB, 'call_center', 'calls',
        'datetime_entry_queue',
        "ADD KEY datetime_entry_queue (start_time)");
    crearIndiceSiNoExiste($pDB, 'call_center', 'call_entry',
        'datetime_init',
        "ADD KEY datetime_init (datetime_init)");
    crearIndiceSiNoExiste($pDB, 'call_center', 'call_entry',
        'datetime_entry_queue',
        "ADD KEY datetime_entry_queue (datetime_init)");
    crearIndiceSiNoExiste($pDB, 'call_center', 'dont_call',
        'callerid',
        "ADD KEY callerid (caller_id)");
    crearIndiceSiNoExiste($pDB, 'call_center', 'agent',
        'agent_type',
        "ADD KEY `agent_type` (`estatus`,`type`,`number`)");
    crearIndiceSiNoExiste($pDB, 'call_center', 'calls',
        'campaign_date_schedule',
        "ADD KEY `campaign_date_schedule` (`id_campaign`, `date_init`, `date_end`, `time_init`, `time_end`)");

    // Asegurarse de que todo agente tiene una contraseña de ECCP
    $pDB->genQuery('UPDATE agent SET eccp_password = SHA1(CONCAT(NOW(), RAND(), number)) WHERE eccp_password IS NULL');

    $pDB->disconnect();
}

instalarContextosEspeciales();

exit($return);

function quitarColumnaSiExiste($pDB, $sDatabase, $sTabla, $sColumna)
{
    $sPeticionSQL = <<<EXISTE_COLUMNA
SELECT COUNT(*)
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
EXISTE_COLUMNA;
    $r = $pDB->getFirstRowQuery($sPeticionSQL, FALSE, array($sDatabase, $sTabla, $sColumna));
    if (!is_array($r)) {
        fputs(STDERR, "ERR: al verificar tabla $sTabla.$sColumna - ".$pDB->errMsg."\n");
        return;
    }
    if ($r[0] > 0) {
        fputs(STDERR, "INFO: Se encuentra $sTabla.$sColumna en base de datos $sDatabase, se ejecuta:\n");
        $sql = "ALTER TABLE $sTabla DROP COLUMN $sColumna";
        fputs(STDERR, "\t$sql\n");
        $r = $pDB->genQuery($sql);
        if (!$r) fputs(STDERR, "ERR: ".$pDB->errMsg."\n");
    } else {
        fputs(STDERR, "INFO: No existe $sTabla.$sColumna en base de datos $sDatabase. No se hace nada.\n");
    }
}

function crearColumnaSiNoExiste($pDB, $sDatabase, $sTabla, $sColumna, $sColumnaDef)
{
    $sPeticionSQL = <<<EXISTE_COLUMNA
SELECT COUNT(*)
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
EXISTE_COLUMNA;
    $r = $pDB->getFirstRowQuery($sPeticionSQL, FALSE, array($sDatabase, $sTabla, $sColumna));
    if (!is_array($r)) {
        fputs(STDERR, "ERR: al verificar tabla $sTabla.$sColumna - ".$pDB->errMsg."\n");
        return;
    }
    if ($r[0] <= 0) {
        fputs(STDERR, "INFO: No se encuentra $sTabla.$sColumna en base de datos $sDatabase, se ejecuta:\n");
        $sql = "ALTER TABLE $sTabla $sColumnaDef";
        fputs(STDERR, "\t$sql\n");
        $r = $pDB->genQuery($sql);
        if (!$r) fputs(STDERR, "ERR: ".$pDB->errMsg."\n");
    } else {
        fputs(STDERR, "INFO: Ya existe $sTabla.$sColumna en base de datos $sDatabase.\n");
    }
}

function crearIndiceSiNoExiste($pDB, $sDatabase, $sTabla, $sIndice, $sIndiceDef)
{
    $sPeticionSQL = <<<EXISTE_INDICE
SELECT COUNT(*)
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
EXISTE_INDICE;
    $r = $pDB->getFirstRowQuery($sPeticionSQL, FALSE, array($sDatabase, $sTabla, $sIndice));
    if (!is_array($r)) {
        fputs(STDERR, "ERR: al verificar tabla $sTabla.$sIndice - ".$pDB->errMsg."\n");
        return;
    }
    if ($r[0] <= 0) {
        fputs(STDERR, "INFO: No se encuentra $sTabla.$sIndice en base de datos $sDatabase, se ejecuta:\n");
        $sql = "ALTER TABLE $sTabla $sIndiceDef";
        fputs(STDERR, "\t$sql\n");
        $r = $pDB->genQuery($sql);
        if (!$r) fputs(STDERR, "ERR: ".$pDB->errMsg."\n");
    } else {
        fputs(STDERR, "INFO: Ya existe $sTabla.$sIndice en base de datos $sDatabase.\n");
    }
}

function modificarDatosCallAttribute($pDB)
{
    $sPeticionSQL = <<<EXISTE_COLUMNA
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'call_center' AND TABLE_NAME = 'call_attribute' AND COLUMN_NAME = 'value'
EXISTE_COLUMNA;
    $r = $pDB->getFirstRowQuery($sPeticionSQL, FALSE);
    if (!is_array($r)) {
        fputs(STDERR, "ERR: al verificar tabla call_attribute.value - ".$pDB->errMsg."\n");
        return;
    }
    if ($r[0] > 0) {
        fputs(STDERR, "INFO: Se encuentra call_attribute.value en base de datos call_center, se ejecuta:\n");
        $sql = "RENAME TABLE `call_attribute` TO `call_attribute_old`";
        fputs(STDERR, "\t$sql\n");
        $r = $pDB->genQuery($sql);
        if (!$r) fputs(STDERR, "ERR: ".$pDB->errMsg."\n");
        fputs(STDERR, "INFO: Generando nueva tabla call_attribute, se ejecuta:\n");
        $sql = <<<QUERY_CREATE
        CREATE TABLE `call_attribute` (
          `id` int(10) unsigned NOT NULL auto_increment,
          `id_call` int(10) unsigned NOT NULL,
          `data` text NULL,
          PRIMARY KEY  (`id`),
          KEY `id_call` (`id_call`),
          CONSTRAINT `call_attribute_ibfk_1` FOREIGN KEY (`id_call`) REFERENCES `calls` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
QUERY_CREATE;
        fputs(STDERR, "\t$sql\n");
        $r = $pDB->genQuery($sql);
        if (!$r) fputs(STDERR, "ERR: ".$pDB->errMsg."\n");
        fputs(STDERR, "INFO: Copiando datos de tabla call_attribute_old, se ejecuta:\n");
        // Se puede tardar mucho tiempo en la inserción
        set_time_limit(0);
        $sql = <<<QUERY_SQL
        INSERT INTO call_attribute ( id_call, data )
        SELECT id_call, CONCAT("{",GROUP_CONCAT(CONCAT('"', REPLACE(columna,'"','\\"'), '":"', REPLACE(value,'"','\\"'), '"') ORDER BY column_number SEPARATOR ','),"}") AS DATA
        FROM call_attribute_old
        GROUP BY id_call;
QUERY_SQL;
        fputs(STDERR, "\t$sql\n");
        $r = $pDB->genQuery($sql);
        if (!$r) fputs(STDERR, "ERR: ".$pDB->errMsg."\n");
        fputs(STDERR, "INFO: Eliminando call_attribute_old, se ejecuta:\n");
        $sql = "DROP TABLE `call_attribute_old`;";
        fputs(STDERR, "\t$sql\n");
        $r = $pDB->genQuery($sql);
        if (!$r) fputs(STDERR, "ERR: ".$pDB->errMsg."\n");
    } else {
        fputs(STDERR, "INFO: Sin modificación a la tabla call_attribute.\n");
    }
}

function modificarCampoCalls($pDB)
{
    $sPeticionSQL = <<<EXISTE_COLUMNA
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'call_center' AND TABLE_NAME = 'calls' AND COLUMN_NAME = 'id_campaign'
EXISTE_COLUMNA;
    $r = $pDB->getFirstRowQuery($sPeticionSQL, FALSE);
    if (!is_array($r)) {
        fputs(STDERR, "ERR: al verificar tabla calla.id_campaign - ".$pDB->errMsg."\n");
        return;
    }
    if ($r[0] > 0) {
        fputs(STDERR, "INFO: Se encuentra calls.id_campaign en base de datos call_center, se ejecuta:\n");
        $sql = "ALTER TABLE `calls` DROP FOREIGN KEY `calls_ibfk_1`;";
        fputs(STDERR, "\t$sql\n");
        $r = $pDB->genQuery($sql);
        if (!$r) fputs(STDERR, "ERR: ".$pDB->errMsg."\n");
        
        $sql = "ALTER TABLE `calls` ALTER `id_campaign` DROP DEFAULT;";
        fputs(STDERR, "\t$sql\n");
        $r = $pDB->genQuery($sql);
        if (!$r) fputs(STDERR, "ERR: ".$pDB->errMsg."\n");

        $sql = "ALTER TABLE `calls` CHANGE COLUMN `id_campaign` `id_list` INT(10) UNSIGNED NOT NULL AFTER `id`;";
        fputs(STDERR, "\t$sql\n");
        $r = $pDB->genQuery($sql);
        if (!$r) fputs(STDERR, "ERR: ".$pDB->errMsg."\n");

        fputs(STDERR, "INFO: Por cada campaña, generar una lista inicial:\n");
        $sql = <<<QUERY_SQL
        SELECT DISTINCT id_list FROM calls ORDER BY calls.id_list ASC;
QUERY_SQL;
        fputs(STDERR, "\t$sql\n");
        $r = $pDB->fetchTable($sql, TRUE);
        if (!$r) fputs(STDERR, "ERR: ".$pDB->errMsg."\n");
        foreach ($r as $keyRow => $valueRow) {
            $sql = <<<QUERY_SQL
        INSERT INTO campaign_lists (id_campaign, `type`, name, upload, date_entered, `status`, total_calls, pending_calls, sent_calls, answered_calls, no_answer_calls, failed_calls, paused_calls, abandoned_calls, short_calls, is_recycled, id_parent_list, is_deleted)
        VALUES (? , 0, ?, '', NOW(), 2, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
QUERY_SQL;
        $r = $pDB->genQuery($sql, array($valueRow['id_list'],"Campaign List ".$valueRow['id_list']));
        if (!$r) fputs(STDERR, "ERR: ".$pDB->errMsg."\n");
        $id_list = $pDB->getLastInsertId();
        $sql = <<<QUERY_SQL
        UPDATE campaign_lists
        SET
            total_calls=(SELECT COUNT(id) FROM calls WHERE id_list=?)
        WHERE id=?
QUERY_SQL;
        $r = $pDB->genQuery($sql, array($valueRow['id_list'], $id_list));
        if (!$r) fputs(STDERR, "ERR: ".$pDB->errMsg."\n");
        $sql = <<<QUERY_SQL
        UPDATE calls
        SET
            id_list=?
        WHERE id_list=?
QUERY_SQL;
        $r = $pDB->genQuery($sql, array($id_list, $valueRow['id_list']));
        if (!$r) fputs(STDERR, "ERR: ".$pDB->errMsg."\n");
        $sql = <<<QUERY_SQL
        UPDATE calls
        SET
            status="Paused"
        WHERE id_list=? AND ISNULL(calls.`status`) 
QUERY_SQL;
        $r = $pDB->genQuery($sql, array($id_list));
        if (!$r) fputs(STDERR, "ERR: ".$pDB->errMsg."\n");
        }
    } else {
        fputs(STDERR, "INFO: Sin modificación a la tabla calls.\n");
    }
}

/**
 * Procedimiento que instala algunos contextos especiales requeridos para algunas
 * funcionalidades del CallCenter.
 */
function instalarContextosEspeciales()
{
	$sArchivo = '/etc/asterisk/extensions_custom.conf';
    $sInicioContenido = "; BEGIN ISSABEL CALL-CENTER CONTEXTS DO NOT REMOVE THIS LINE\n";
    $sFinalContenido =  "; END ISSABEL CALL-CENTER CONTEXTS DO NOT REMOVE THIS LINE\n";

    // Cargar el archivo, notando el inicio y el final del área de contextos de callcenter
    $bEncontradoInicio = $bEncontradoFinal = FALSE;
    $contenido = array();
    foreach (file($sArchivo) as $sLinea) {
    	if ($sLinea == $sInicioContenido) {
    		$bEncontradoInicio = TRUE;
        } elseif ($sLinea == $sFinalContenido) {
            $bEncontradoFinal = TRUE;
    	} elseif (!$bEncontradoInicio || $bEncontradoFinal) {
            if (substr($sLinea, strlen($sLinea) - 1) != "\n")
                $sLinea .= "\n";
    	    $contenido[] = $sLinea;
    	}
    }
    if ($bEncontradoInicio xor $bEncontradoFinal) {
    	fputs(STDERR, "ERR: no se puede localizar correctamente segmento de contextos de Call Center\n");
    } else {
    	$contenido[] = $sInicioContenido;
        $contenido[] =
'
[llamada_agendada]
exten => _X.,1,NoOP("Issabel CallCenter: AGENTCHANNEL=${AGENTCHANNEL}")
exten => _X.,n,NoOP("Issabel CallCenter: QUEUE_MONITOR_FORMAT=${QUEUE_MONITOR_FORMAT}")
exten => _X.,n,GotoIf($["${QUEUE_MONITOR_FORMAT}" = ""]?skiprecord)
exten => _X.,n,Set(CALLFILENAME=${STRFTIME(${EPOCH},,%Y%m%d-%H%M%S)}-${UNIQUEID})
exten => _X.,n,MixMonitor(${MIXMON_DIR}${CALLFILENAME}.${MIXMON_FORMAT},,${MIXMON_POST})
exten => _X.,n,Set(CDR(userfield)=audio:${CALLFILENAME}.${MIXMON_FORMAT})
exten => _X.,n(skiprecord),Dial(${AGENTCHANNEL},300,tw)
exten => h,1,Macro(hangupcall,)

';
        $contenido[] = $sFinalContenido;
        file_put_contents($sArchivo, $contenido);
        chown($sArchivo, 'asterisk'); chgrp($sArchivo, 'asterisk');
    }
}
?>
