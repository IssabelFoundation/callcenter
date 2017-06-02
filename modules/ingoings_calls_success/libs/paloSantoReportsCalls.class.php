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

require_once("libs/paloSantoDB.class.php");

class paloSantoReportsCalls
{
    var $_DB;
    var $errMsg = '';

    function paloSantoReportsCalls(&$pDB)
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

    function leerReporteLlamadas($sFechaInicio, $sFechaFinal)
    {
        $sRegexpFecha = '/^\d{4}-\d{2}-\d{2}$/';
        if (!(preg_match($sRegexpFecha, $sFechaInicio) && preg_match($sRegexpFecha, $sFechaFinal))) {
            $this->errMsg = _tr('Invalid start or end date');
            return NULL;
        }
        if ($sFechaInicio > $sFechaFinal) { $t = $sFechaInicio; $sFechaInicio = $sFechaFinal; $sFechaFinal = $t; }
        
        $sFechaInicio .= ' 00:00:00';
        $sFechaFinal .= ' 23:59:59';
        
        $sPeticionSQL = <<<SQL_REPORT_CALLS
SELECT queue_call_entry.queue AS queue,
    COUNT(call_entry.id) AS N,
    SUM(call_entry.duration_wait) AS sum_duration_wait,
    call_entry.status
FROM queue_call_entry, call_entry
WHERE queue_call_entry.id = call_entry.id_queue_call_entry
    AND call_entry.status IN ('terminada', 'abandonada')
    AND call_entry.datetime_entry_queue BETWEEN ? AND ?
GROUP BY queue_call_entry.queue, call_entry.status 
SQL_REPORT_CALLS;
        $paramSQL = array($sFechaInicio, $sFechaFinal);
        
        $recordset = $this->_DB->fetchTable($sPeticionSQL, TRUE, $paramSQL);
        if (!is_array($recordset)) {
            $this->errMsg = $this->_DB->errMsg;
            return NULL;
        }
        
        $result = array();
        foreach ($recordset as $tupla) {
            if (!isset($result[$tupla['queue']])) {
                $result[$tupla['queue']] = array('success' => 0, 'abandoned' => 0, 'wait_sec' => 0, 'total' => 0);
            }
            if ($tupla['status'] == 'terminada')
                $result[$tupla['queue']]['success'] += $tupla['N'];
            if ($tupla['status'] == 'abandonada')
                $result[$tupla['queue']]['abandoned'] += $tupla['N'];
            $result[$tupla['queue']]['total'] += $tupla['N'];
            $result[$tupla['queue']]['wait_sec'] += $tupla['sum_duration_wait'];
        }
        return $result;
    }
}    
?>