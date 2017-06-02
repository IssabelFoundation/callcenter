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
  $Id: new_campaign.php $ */

require_once('libs/paloSantoDB.class.php');
require_once 'libs/paloSantoConfig.class.php';
include_once 'libs/paloSantoQueue.class.php';

class paloSantoLoginLogout
{
    private $_DB; // instancia de la clase paloDB
    var $errMsg;

    function paloSantoLoginLogout(&$pDB)
    {
        // Se recibe como parámetro una referencia a una conexión paloDB
        if (is_object($pDB)) {
            $this->_DB =& $pDB;
            $this->errMsg = $this->_DB->errMsg;
        } else {
            $dsn = (string)$pDB;
            $this->_DB = new paloDB($dsn);

            if (!$this->_DB->connStatus) {
                $this->errMsg = $this->_DB->errMsg;
                // debo llenar alguna variable de error
            } else {
                // debo llenar alguna variable de error
            }
        }
    }

    function leerColasEntrantesValidas()
    {
        //conexion resource
        $pConfig = new paloConfig("/etc", "amportal.conf", "=", "[[:space:]]*=[[:space:]]*");
        $arrConfig = $pConfig->leer_configuracion(false);
        $dsnAsteriskCdr = $arrConfig['AMPDBENGINE']['valor']."://".
                          $arrConfig['AMPDBUSER']['valor']. ":".
                          $arrConfig['AMPDBPASS']['valor']. "@".
                          $arrConfig['AMPDBHOST']['valor']."/asterisk";
        $pDB_asterisk = new paloDB($dsnAsteriskCdr);
        $oQueue  = new paloQueue($pDB_asterisk);
        $PBXQueues = $oQueue->getQueue();

        $arrQueue = array();
        if (is_array($PBXQueues)) {
            foreach($PBXQueues as $key => $value) {
                $result = $this->_DB->getFirstRowQuery(
                    'SELECT id, queue from queue_call_entry where queue = ?',
                    TRUE, array($value[0]));
                if (is_array($result) && count($result)>0) {
                    // La clave debe ser cadena para que in_array en paloForm funcione
                    $arrQueue[$result['id']] =  $result['queue'];
                }
            }
        }
        return $arrQueue;
    }
    
    function leerRegistrosLoginLogout($sTipo, $sFechaInicio, $sFechaFin, $idIncomingQueue = NULL)
    {
        if (!in_array($sTipo, array('D', 'G'))) {
            $this->errMsg = _tr('(internal) Invalid detail flag, must be D or G');
        	return NULL;
        }
        $sRegexp = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';
        if (!preg_match($sRegexp, $sFechaInicio)) {
            $this->errMsg = _tr('(internal) Invalid start date, must be yyyy-mm-dd hh:mm:ss');
        	return NULL;
        }
        if (!preg_match($sRegexp, $sFechaFin)) {
            $this->errMsg = _tr('(internal) Invalid end date, must be yyyy-mm-dd hh:mm:ss');
            return NULL;
        }
        if ($sFechaFin < $sFechaInicio) {
        	$t = $sFechaFin;
            $sFechaFin = $sFechaInicio;
            $sFechaInicio = $t;
        }
    	$sqlRegistros = <<<SQL_REGISTROS
SELECT
    agent.id,
    agent.number,
    agent.name,
    audit.datetime_init, 
    IF(audit.datetime_end IS NULL, NOW(), datetime_end) AS datetime_end,
    TIME_TO_SEC(IF(audit.duration IS NULL, TIMEDIFF(NOW(), audit.datetime_init), audit.duration)) AS duration,
    (
        SELECT SUM(duration) FROM call_entry
        WHERE   call_entry.id_agent = agent.id
            AND (? IS NULL OR call_entry.id_queue_call_entry = ?)
            AND call_entry.datetime_init BETWEEN audit.datetime_init AND IF(audit.datetime_end IS NULL, NOW(), audit.datetime_end)
    ) AS total_incoming,
    (
        SELECT SUM(duration) FROM calls
        WHERE   calls.id_agent = agent.id 
            AND calls.start_time BETWEEN audit.datetime_init AND IF(audit.datetime_end IS NULL, NOW(), audit.datetime_end)
    ) AS total_outgoing,
    IF(audit.datetime_end IS null, 'ONLINE', '') AS estado 
FROM audit, agent
WHERE   audit.datetime_init BETWEEN ? AND ?
    AND audit.id_agent = agent.id
    AND audit.id_break IS NULL
ORDER BY agent.name, audit.datetime_init
SQL_REGISTROS;
        $recordset = $this->_DB->fetchTable($sqlRegistros, TRUE,
            array($idIncomingQueue, $idIncomingQueue, $sFechaInicio, $sFechaFin));
        if (!is_array($recordset)) {
            $this->errMsg = $this->_DB->errMsg;
        	return NULL;
        }
        $ultimoOnline = array();
        foreach (array_keys($recordset) as $i) {
            if (is_null($recordset[$i]['total_incoming']))
                $recordset[$i]['total_incoming'] = 0;
            if (is_null($recordset[$i]['total_outgoing']))
                $recordset[$i]['total_outgoing'] = 0;
        
            /* Para el reporte detallado, cada agente debe cumplir la condición
             * de que todos los registros de auditoría deben estar cerrados, a
             * excepción de el registro más reciente, el cual puede estar 
             * abierto. Un registro abierto que no es el último está corrompido.
             */
            if (isset($ultimoOnline[$recordset[$i]['id']]) && 
                $recordset[$i]['datetime_init'] > $ultimoOnline[$recordset[$i]['id']]['datetime_init']) {

                // El registro anteriormente visto está corrompido
                $iPosCorrompido = $ultimoOnline[$recordset[$i]['id']]['index']; 
                $recordset[$iPosCorrompido]['estado'] = 'CORRUPTED';
                $recordset[$iPosCorrompido]['datetime_end'] = NULL;
                $recordset[$iPosCorrompido]['duration'] = NULL;
                $recordset[$iPosCorrompido]['total_incoming'] = 0;
                $recordset[$iPosCorrompido]['total_outgoing'] = 0;
                
                // No es necesario conservar la info si ya se marcó como corrupto
                unset($ultimoOnline[$recordset[$i]['id']]);
            }
            if ($recordset[$i]['estado'] == 'ONLINE') {
                $ultimoOnline[$recordset[$i]['id']] = array(
                    'index'         =>  $i,
                    'datetime_init' =>  $recordset[$i]['datetime_init'],
                );
            }
        }
        
        /* Incluso si los registros revisados parecen ser consistentes, pueden 
         * estar corruptos por registros futuros no consultados en el query de 
         * arriba. 
         */
        foreach ($ultimoOnline as $agentid => $agentOnline) {
        	$tuple = $this->_DB->getFirstRowQuery(
                'SELECT COUNT(*) AS N FROM audit '.
                'WHERE id_agent = ? AND id_break IS NULL AND datetime_init > ?',
                TRUE, array($agentid, $agentOnline['datetime_init']));
            if (!is_array($tuple)) {
                $this->errMsg = $this->_DB->errMsg;
                return NULL;
            }
            if ($tuple['N'] > 0) {
                // El registro anteriormente visto está corrompido
                $iPosCorrompido = $agentOnline['index']; 
                $recordset[$iPosCorrompido]['estado'] = 'CORRUPTED';
                $recordset[$iPosCorrompido]['datetime_end'] = NULL;
                $recordset[$iPosCorrompido]['duration'] = NULL;
                $recordset[$iPosCorrompido]['total_incoming'] = 0;
                $recordset[$iPosCorrompido]['total_outgoing'] = 0;
            }
        }
        
        // Para reporte detallado, ya se puede devolver recordset
        if ($sTipo == 'D') return $recordset;
        
        // Agrupar por ID de agente para reporte general
        $agrupacion = array();
        foreach ($recordset as $tupla) {
        	if (!isset($agrupacion[$tupla['id']])) {
        		$agrupacion[$tupla['id']] = $tupla;
        	} else {
        		foreach (array('duration', 'total_incoming', 'total_outgoing') as $k)
                    $agrupacion[$tupla['id']][$k] += $tupla[$k];
                if ($agrupacion[$tupla['id']]['datetime_init'] > $tupla['datetime_init'])
                    $agrupacion[$tupla['id']]['datetime_init'] = $tupla['datetime_init'];
                if ($agrupacion[$tupla['id']]['datetime_end'] < $tupla['datetime_end'])
                    $agrupacion[$tupla['id']]['datetime_end'] = $tupla['datetime_end'];
                $agrupacion[$tupla['id']]['estado'] = $tupla['estado'];
        	}
        }
        return $agrupacion;
    }
}
?>