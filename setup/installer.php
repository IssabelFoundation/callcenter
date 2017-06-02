<?php
/* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Elastix version 0.5                                                  |
  | http://www.elastix.com                                               |
  +----------------------------------------------------------------------+
  | Copyright (c) 2006 Palosanto Solutions S. A.                         |
  +----------------------------------------------------------------------+
  | Cdla. Nueva Kennedy Calle E 222 y 9na. Este                          |
  | Telfs. 2283-268, 2294-440, 2284-356                                  |
  | Guayaquil - Ecuador                                                  |
  | http://www.palosanto.com                                             |
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
  | The Original Code is: Elastix Open Source.                           |
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
        "ALTER TABLE calls ADD COLUMN scheduled BOOLEAN NOT NULL DEFAULT 0");

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

/**
 * Procedimiento que instala algunos contextos especiales requeridos para algunas
 * funcionalidades del CallCenter.
 */
function instalarContextosEspeciales()
{
	$sArchivo = '/etc/asterisk/extensions_custom.conf';
    $sInicioContenido = "; BEGIN ELASTIX CALL-CENTER CONTEXTS DO NOT REMOVE THIS LINE\n";
    $sFinalContenido =  "; END ELASTIX CALL-CENTER CONTEXTS DO NOT REMOVE THIS LINE\n";

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
exten => _X.,1,NoOP("Elastix CallCenter: AGENTCHANNEL=${AGENTCHANNEL}")
exten => _X.,n,NoOP("Elastix CallCenter: QUEUE_MONITOR_FORMAT=${QUEUE_MONITOR_FORMAT}")
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