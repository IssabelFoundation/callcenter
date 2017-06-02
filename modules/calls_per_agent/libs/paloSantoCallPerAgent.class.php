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
  $Id: paloSantoCDR.class.php,v 1.1.1.1 2007/07/06 21:31:55 gcarrillo Exp $ */

class paloSantoCallsAgent {

    function paloSantoCallsAgent(&$pDB)
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

    private function _construirWhere($sTipo, $date_start, $date_end, $fieldPat)
    {
        $condSQL = array();
        $paramSQL = array();
        
        $campoLlamadas = array(
            'incoming' => 'call_entry.datetime_entry_queue',
            'outgoing' => 'calls.start_time'
        );
        $tablaCola = array(
            'incoming' => 'queue_call_entry',
            'outgoing' => 'campaign'
        );
        $sTablaCola = $tablaCola[$sTipo]; 
        $sCampoLlamada = $campoLlamadas[$sTipo];

        // Fechas de inicio y de fin
        if (!is_null($date_start)) {
        	$condSQL[] = "$sCampoLlamada >= ?";
            $paramSQL[] = $date_start;
        }
        if (!is_null($date_end)) {
            $condSQL[] = "$sCampoLlamada <= ?";
            $paramSQL[] = $date_end;
        }
        
        if (!function_exists('_construirWhere_mapLike')) {
        	function _construirWhere_mapLike($s) { return "%{$s}%"; }
        }
        
        // Colas a buscar
        if (isset($fieldPat['queue']) && count($fieldPat['queue']) > 0) {
            $condSQL[] = '('.implode(' OR ', array_fill(0, count($fieldPat['queue']), "$sTablaCola.queue LIKE ?")).')';
            $paramSQL = array_merge($paramSQL, array_map('_construirWhere_mapLike', $fieldPat['queue']));            
        }

        // Agentes a buscar
        if (isset($fieldPat['number']) && count($fieldPat['number']) > 0) {
            $condSQL[] = '('.implode(' OR ', array_fill(0, count($fieldPat['number']), 'agent.number LIKE ?')).')';
            $paramSQL = array_merge($paramSQL, array_map('_construirWhere_mapLike', $fieldPat['number']));            
        }

        // Construir fragmento completo de sentencia SQL
        $where = array(implode(' AND ', $condSQL), $paramSQL);
        if ($where[0] != '') $where[0] = ' AND '.$where[0];
        return $where;
    }

    /**
     * Procedimiento para listar los totales de llamadas de los agentes
     * 
     * @param   string  $date_start Fecha en formato yyyy-mm-dd hh:mm:ss 
     * @param   string  $date_end   Fecha en formato yyyy-mm-dd hh:mm:ss
     * @param   array   $fieldPat   Lista de elementos a buscar
     *          number  Número de agente
     *          queue   Cola usada para recibir la llamada
     *          type    IN,INBOUND,OUT,OUTBOUND
     * 
     * @return  mixed   NULL en caso de error, o tupla (Data, NumRecords)  
     */
    function obtenerCallsAgent($date_start = NULL, $date_end = NULL, $fieldPat = array())
    {
    	$sPeticion_incoming = <<<SQL_INCOMING
SELECT agent.number AS agent_number, agent.name AS agent_name,
    'Inbound' AS type, queue_call_entry.queue AS queue,
    COUNT(*) AS num_answered, SUM(call_entry.duration) AS sum_duration,
    AVG(call_entry.duration) AS avg_duration, MAX(call_entry.duration) AS max_duration 
FROM (call_entry, queue_call_entry)
LEFT JOIN agent
    ON agent.id = call_entry.id_agent
WHERE queue_call_entry.id = call_entry.id_queue_call_entry AND call_entry.status = 'terminada'
SQL_INCOMING;
        list($sWhere_incoming, $param_incoming) = $this->_construirWhere(
            'incoming', $date_start, $date_end, $fieldPat);
        $sPeticion_incoming .= $sWhere_incoming.' GROUP BY agent.number, queue';

        $sPeticion_outgoing = <<<SQL_OUTGOING
SELECT agent.number AS agent_number, agent.name AS agent_name,
    'Outbound' AS type, campaign.queue AS queue, COUNT(*) AS num_answered,
    SUM(calls.duration) AS sum_duration, AVG(calls.duration) AS avg_duration,
    MAX(calls.duration) AS max_duration
FROM (calls, campaign)
LEFT JOIN agent
    ON agent.id = calls.id_agent
WHERE calls.duration IS NOT NULL AND campaign.id = calls.id_campaign AND calls.status = 'Success'
SQL_OUTGOING;
        list($sWhere_outgoing, $param_outgoing) = $this->_construirWhere(
            'outgoing', $date_start, $date_end, $fieldPat);
        $sPeticion_outgoing .= $sWhere_outgoing.' GROUP BY agent.number, queue';

        // Construir la unión SQL en caso necesario
        if (!isset($fieldPat['type'])) $fieldPat['type'] = array('IN', 'OUT');
        $sPeticionSQL = NULL; $paramSQL = NULL;
        if (!in_array('IN', $fieldPat['type']) && !in_array('INBOUND', $fieldPat['type'])) {
        	// Sólo llamadas salientes
            $sPeticionSQL = $sPeticion_outgoing;
            $paramSQL = $param_outgoing;
        } elseif (!in_array('OUT', $fieldPat['type']) && !in_array('OUTBOUND', $fieldPat['type'])) {
        	// Sólo llamadas entrantes
            $sPeticionSQL = $sPeticion_incoming;
            $paramSQL = $param_incoming;
        } else {
        	// Todas las llamadas
            $sPeticionSQL = "($sPeticion_incoming) UNION ($sPeticion_outgoing)";
            $paramSQL = array_merge($param_incoming, $param_outgoing);
        }
        $sPeticionSQL .= ' ORDER BY agent_number, queue';

        // Ejecutar la petición SQL para todos los datos
        //print "<pre>$sPeticionSQL</pre>"; print_r($paramSQL);
        $recordset = $this->_DB->fetchTable($sPeticionSQL, TRUE, $paramSQL);
        if (!is_array($recordset)) {
            $this->errMsg = '(internal) Failed to count calls - '.$this->_DB->errMsg;
            return NULL;
        }
        return $recordset;
    }
}
?>