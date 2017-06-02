<?php
/* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Elastix version 0.5                                                  |f
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

class paloSantoReportsBreak
{
    private $_DB;
    var $errMsg;
    
    function paloSantoReportsBreak(&$pDB)
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
            }
        }
    }

    /**
     * Esta funcion retorna un arreglo con el reporte de break tomados por cada agente, dada una fecha.
     *
     * @param   string  $fecha_init     Fecha de inicio de rango, en formato 'yyyy-mm-dd hh:mm:ss'
     * @param   string  $fecha_end      Fecha de final de rango, en formato 'yyyy-mm-dd hh:mm:ss'
     *
     * @result  mixed   Arreglo de reporte de breaks de agentes, o FALSE en caso de error
     */
    function getReportesBreak($fecha_init,$fecha_end)
    {
        if (!preg_match('/^[[:digit:]]{4}-[[:digit:]]{2}-[[:digit:]]{2}$/', $fecha_init)) {
            $this->errMsg = '(internal) Invalid start date, expected yyyy-mm-dd';
            return NULL;
        }
        if (!preg_match('/^[[:digit:]]{4}-[[:digit:]]{2}-[[:digit:]]{2}$/', $fecha_end)) {
            $this->errMsg = '(internal) Invalid end date, expected yyyy-mm-dd';
            return NULL;
        }
        
        $sPeticionSQL = <<<LEER_SUMARIO_BREAK
SELECT agent.id AS id_agente, agent.number, agent.name AS nombre_agente, audit.id_break, 
    break.name AS nombre_break, 
    SUM(UNIX_TIMESTAMP(IFNULL(audit.datetime_end, NOW())) - UNIX_TIMESTAMP(audit.datetime_init)) AS duracion 
FROM agent 
LEFT JOIN (audit, break) 
    ON (agent.id = audit.id_agent AND break.id = audit.id_break 
        AND audit.datetime_init >= ? AND audit.datetime_init <= ?)
WHERE agent.estatus = "A"
GROUP BY agent.id, audit.id_break
LEER_SUMARIO_BREAK;

        $recordset =& $this->_DB->fetchTable($sPeticionSQL, TRUE, array($fecha_init.' 00:00:00', $fecha_end.' 23:59:59'));
        if (!is_array($recordset)) {
            $this->errMsg = $this->_DB->errMsg;
            return NULL;
        }
        $reporte = array();
        foreach ($recordset as $tupla) {
            $idAgente = $tupla['id_agente'];
            if (!isset($reporte[$idAgente])) $reporte[$idAgente] = array(
                'id_agente' =>  $idAgente,
                'nombre_agente' =>  $tupla['nombre_agente'],
                'numero_agente' =>  $tupla['number'],
                'breaks'        =>  array(),
            );
            if (!is_null($tupla['id_break']))
                $reporte[$idAgente]['breaks'][$tupla['id_break']] = array(
                    'id_break'      =>  $tupla['id_break'],
                    'nombre_break'  =>  $tupla['nombre_break'],
                    'duracion'      =>  $tupla['duracion'],
                );
        }
        
        $recordset =& $this->_DB->fetchTable('SELECT id, name FROM break ORDER BY id', TRUE);
        if (!is_array($recordset)) {
            $this->errMsg = $this->_DB->errMsg;
            return NULL;
        }
        $listaBreaks = array();
        foreach ($recordset as $tupla) $listaBreaks[$tupla['id']] = $tupla['name'];
        return array('breaks' => $listaBreaks, 'reporte' => $reporte);
    }
}
?>
