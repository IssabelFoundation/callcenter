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

include_once("libs/paloSantoDB.class.php");

/* Clase que implementa campaña (saliente por ahora) de CallCenter (CC) */
class paloSantoCallsHour
{
    var $_DB; // instancia de la clase paloDB
    var $errMsg;

    function paloSantoCallsHour(&$pDB)
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

    /**
     * Procedimiento para obtener el número de llamadas por hora y por cola.
     * El tiempo se cuenta desde que la llamada fue contestada por un agente, a
     * excepción de las llamadas abandonadas entrantes, para las cuales se toma
     * el tiempo en que la llamada entró a la cola.
     *
     * @param   enum    $sTipo  'E' para entrantes, 'S' para salientes
     * @param   enum    $sTipo  'T' para todas, 'E' para terminada, 'N' para no respuesta o llamada corta, 'A' para abandonada
     * @param   string  $sFechaInicio   Fecha de inicio para agrupación de llamadas
     * @param   string  $sFechaInicio   Fecha de fin para agrupación de llamadas
     *
     * @return  NULL en caso de error, o una estructura de la siguiente forma:
     * array(
     *     {cola} => array(0 => 1, 1 => 4, ..., 23 => 9), // <-- horas enumeradas
     *     ...
     * )
     */
    function getCalls($sTipo, $sEstado, $sFechaInicio, $sFechaFin)
    {
        if (!in_array($sTipo, array('E', 'S'))) {
            $this->errMsg = '(internal) Invalid call type, must be E or S';
            return NULL;
        }
        if (!in_array($sEstado, array('T', 'E', 'N', 'A'))) {
            $this->errMsg = '(internal) Invalid call status, must be one of A,E,N,T';
            return NULL;
        }
        if ($sTipo == 'E' && $sEstado == 'N') {
            $this->errMsg = '(internal) Invalid call status N for incoming calls';
            return NULL;
        }
        if (!preg_match('/^[[:digit:]]{4}-[[:digit:]]{2}-[[:digit:]]{2}$/', $sFechaInicio) ||
            !preg_match('/^[[:digit:]]{4}-[[:digit:]]{2}-[[:digit:]]{2}$/', $sFechaFin)) {
            $this->errMsg = '(internal) Invalid date format, must be YYYY-MM-DD';
            return NULL;
        }
        
        // Elegir sentencia SQL adecuada para la selección
        switch ($sTipo) {
        case 'S':   // Llamadas salientes
            $sqlParams = array($sFechaInicio.' 00:00:00', $sFechaFin.' 23:59:59');
            switch ($sEstado) {
            case 'T':   // Todas
                $sEstadoSQL = ' ';
                break;
            case 'E':   // Terminadas
                $sEstadoSQL = " AND status = 'Success' ";
                break;
            case 'N':   // No contestadas
                $sEstadoSQL = " AND (status = 'NoAnswer' OR status = 'ShortCall') ";
                break;
            case 'A':   // Abandonadas
                $sEstadoSQL = " AND status = 'Abandoned' ";
                break;
            }
            $sqlLlamadas = 
                'SELECT camp.queue AS queue, HOUR(c.start_time) AS hora, COUNT(*) AS N '.
                'FROM calls c, campaign camp '.
                'WHERE start_time >= ? AND end_time <= ? AND c.id_campaign = camp.id AND c.status IS NOT NULL'.
                $sEstadoSQL.
                'GROUP BY queue, hora ORDER BY queue, hora';
            break;
        case 'E':   // Llamadas entrantes
            $sqlParams = array($sFechaInicio.' 00:00:00', $sFechaInicio.' 00:00:00', $sFechaFin.' 23:59:59');
            switch ($sEstado) {
            case 'T':
                $sEstadoSQL = ' ';
                break;
            case 'E':
                $sEstadoSQL = " AND status = 'terminada' ";
                break;
            case 'A':
                $sEstadoSQL = " AND status = 'abandonada' ";
                break;
            }
            $sqlLlamadas = 
                "SELECT queue_ce.queue AS queue, ".
                    "HOUR(IF(status = 'abandonada', datetime_entry_queue, datetime_init)) AS hora, ".
                    "COUNT(*) AS N ".
                "FROM call_entry call_e, queue_call_entry queue_ce ".
                "WHERE call_e.id_queue_call_entry = queue_ce.id ".
                    "AND ((status = 'abandonada' AND datetime_entry_queue >= ?) OR (status <> 'abandonada' AND datetime_init >= ?) ) ".
                    "AND datetime_end <= ? ".
                $sEstadoSQL.
                "GROUP BY queue, hora ORDER BY queue, hora";
            break;
        }
        /* La salida esperada del recordset:
        +-------+------+----+
        | queue | hora | N  |
        +-------+------+----+
        | 900   |    7 |  1 | 
        | 900   |    9 |  2 | 
        | 900   |   10 |  5 | 
        | 900   |   11 |  5 | 
        | 900   |   12 |  3 | 
        | 900   |   13 |  5 | 
        | 900   |   14 |  3 | 
        | 900   |   15 | 15 | 
        | 900   |   16 |  1 | 
        | 900   |   17 |  2 | 
        +-------+------+----+
        10 rows in set (0.00 sec)
        */
        $recordset =& $this->_DB->fetchTable($sqlLlamadas, TRUE, $sqlParams);
        if (!is_array($recordset)) {
            $this->errMsg = 'Unable to read call information - '.$this->_DB->errMsg;
            return NULL;
        }
        
        // Construir el arreglo requerido
        $histograma = array();
        foreach ($recordset as $tupla) {
            $sCola = $tupla['queue'];
            if (!isset($histograma[$sCola]))
                $histograma[$sCola] = array_fill(0, 24, 0);
            $histograma[$sCola][$tupla['hora']] = $tupla['N'];
        }
        return $histograma;
    }
}

?>
