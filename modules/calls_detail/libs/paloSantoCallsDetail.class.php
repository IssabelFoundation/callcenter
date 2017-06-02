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

class paloSantoCallsDetail
{
    private $_DB;   // Conexión a la base de datos
    var $errMsg;    // Último mensaje de error

    function paloSantoCallsDetail(&$pDB)
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

    // Construir condición WHERE común a llamadas entrantes y salientes
    private function _construirWhere($param)
    {
        $condSQL = array();
        $paramSQL = array();

        // Selección del agente que atendió la llamada
        if (isset($param['agent']) && preg_match('/^\d+$/', $param['agent'])) {
            $condSQL[] = 'agent.number = ?';
            $paramSQL[] = $param['agent'];
        }

        return array($condSQL, $paramSQL);
    }

    private function _construirWhere_incoming($param)
    {
        list($condSQL, $paramSQL) = $this->_construirWhere($param);

        // Selección de la cola por la que pasó la llamada
        if (isset($param['queue']) && preg_match('/^\d+$/', $param['queue'])) {
            $condSQL[] = 'queue_call_entry.queue = ?';
            $paramSQL[] = $param['queue'];
        }

        // Filtrar por patrón de número telefónico de la llamada
        if (isset($param['phone']) && preg_match('/^\d+$/', $param['phone'])) {
            $condSQL[] = 'IF(contact.telefono IS NULL, call_entry.callerid, contact.telefono) LIKE ?';
            $paramSQL[] = '%'.$param['phone'].'%';
        }

        // Filtrar por ID de campaña entrante
        if (isset($param['id_campaign_in']) && preg_match('/^\d+$/', $param['id_campaign_in'])) {
            $condSQL[] = 'campaign_entry.id = ?';
            $paramSQL[] = (int)$param['id_campaign_in'];
        }

        // Fecha y hora de inicio y final del rango
        $sRegFecha = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';
        if (isset($param['date_start']) && preg_match($sRegFecha, $param['date_start'])) {
            $condSQL[] = 'call_entry.datetime_entry_queue >= ?';
            $paramSQL[] = $param['date_start'];
        }
        if (isset($param['date_end']) && preg_match($sRegFecha, $param['date_end'])) {
            $condSQL[] = 'call_entry.datetime_entry_queue <= ?';
            $paramSQL[] = $param['date_end'];
        }

        // Construir fragmento completo de sentencia SQL
        $where = array(implode(' AND ', $condSQL), $paramSQL);
        if ($where[0] != '') $where[0] = ' AND '.$where[0];
        return $where;
    }

    private function _construirWhere_outgoing($param)
    {
        list($condSQL, $paramSQL) = $this->_construirWhere($param);

        // Selección de la cola por la que pasó la llamada
        if (isset($param['queue']) && preg_match('/^\d+$/', $param['queue'])) {
            $condSQL[] = 'campaign.queue = ?';
            $paramSQL[] = $param['queue'];
        }

        // Filtrar por patrón de número telefónico de la llamada
        if (isset($param['phone']) && preg_match('/^\d+$/', $param['phone'])) {
            $condSQL[] = 'calls.phone LIKE ?';
            $paramSQL[] = '%'.$param['phone'].'%';
        }

        // Filtrar por ID de campaña saliente
        if (isset($param['id_campaign_out']) && preg_match('/^\d+$/', $param['id_campaign_out'])) {
            $condSQL[] = 'campaign.id = ?';
            $paramSQL[] = (int)$param['id_campaign_out'];
        }

        // Fecha y hora de inicio y final del rango
        $sRegFecha = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';
        if (isset($param['date_start']) && preg_match($sRegFecha, $param['date_start'])) {
            $condSQL[] = 'calls.fecha_llamada >= ?';
            $paramSQL[] = $param['date_start'];
        }
        if (isset($param['date_end']) && preg_match($sRegFecha, $param['date_end'])) {
            $condSQL[] = 'calls.fecha_llamada <= ?';
            $paramSQL[] = $param['date_end'];
        }

        // Construir fragmento completo de sentencia SQL
        $where = array(implode(' AND ', $condSQL), $paramSQL);
        if ($where[0] != '') $where[0] = ' AND '.$where[0];
        return $where;
    }

    /**
     * Procedimiento para recuperar el detalle de llamadas realizadas a través
     * del CallCenter.
     *
     * @param   mixed   $param  Lista de parámetros de filtrado:
     *  date_start      Fecha y hora minima de la llamada, en formato
     *                  yyyy-mm-dd hh:mm:ss. Si se omite, se lista desde la
     *                  primera llamada.
     *  date_end        Fecha y hora máxima de la llamada, en formato
     *                  yyyy-mm-dd hh:mm:ss. Si se omite, se lista hasta la
     *                  última llamada.
     *  calltype        Tipo de llamada. Se puede indicar "incoming" o "outgoing".
     *                  Si se omite, se recuperan llamadas de los dos tipos.
     *  agent           Filtrar por número de agente a recuperar (9000 para
     *                  Agent/9000). Si no se especifica, se recuperan llamadas
     *                  de todos los agentes.
     *  queue           Filtrar por número de cola. Si no se especifica, se
     *                  recuperan llamadas mandadas por todas las colas.
     *  phone           Filtrar por número telefónico que contenga el patrón
     *                  numérico indicado. El patron 123 elige los números
     *                  44123887, 123847693, 999999123, etc. Si no se especifica,
     *                  se recuperan detalles sin importar el número conectado.
     * @param   mixed   $limit  Máximo número de CDRs a leer, o NULL para todos
     * @param   mixed   $offset Inicio de lista de CDRs, si se especifica $limit
     *
     * @return  mixed   Arreglo de tuplas con los siguientes campos, en el
     *                  siguiente orden, o NULL si falla la petición:
     *      0   número del agente que atendió la llamada
     *      1   nombre del agente que atendió la llamada
     *      2   fecha de inicio de la llamada, en formato yyyy-mm-dd hh:mm:ss
     *      3   fecha de final de la llamada, en formato yyyy-mm-dd hh:mm:ss
     *      4   duración de la llamada, en segundos
     *      5   duración que la llamada estuvo en espera en la cola, en segundos
     *      6   cola a través de la cual se atendió la llamada
     *      7   tipo de llamada Inbound o Outbound
     *      8   teléfono marcado o atendido en llamada
     *      9   transferencia
     *     10   estado final de la llamada
     */
    function & leerDetalleLlamadas($param, $limit = NULL, $offset = 0)
    {
        if (!is_array($param)) {
            $this->errMsg = '(internal) Invalid parameter array';
            return NULL;
        }

        $sPeticion_incoming = <<<SQL_INCOMING
SELECT agent.number, agent.name, call_entry.datetime_init AS start_date,
    call_entry.datetime_end AS end_date, call_entry.duration,
    call_entry.duration_wait, queue_call_entry.queue, 'Inbound' AS type,
    IF(contact.telefono IS NULL, call_entry.callerid, contact.telefono) AS telefono,
    call_entry.transfer, call_entry.status, call_entry.id AS idx
FROM (call_entry, queue_call_entry)
LEFT JOIN contact
    ON contact.id = call_entry.id_contact
LEFT JOIN agent
    ON agent.id = call_entry.id_agent
LEFT JOIN campaign_entry
    ON campaign_entry.id = call_entry.id_campaign
WHERE call_entry.id_queue_call_entry = queue_call_entry.id
SQL_INCOMING;
        list($sWhere_incoming, $param_incoming) = $this->_construirWhere_incoming($param);
        $sPeticion_incoming .= $sWhere_incoming;

        $sPeticion_outgoing = <<<SQL_OUTGOING
SELECT agent.number, agent.name, calls.start_time AS start_date,
    calls.end_time AS end_date, calls.duration,
    calls.duration_wait, campaign.queue, 'Outbound' AS type,
    calls.phone AS telefono,
    calls.transfer, calls.status, calls.id AS idx
FROM (calls, campaign)
LEFT JOIN agent
    ON agent.id = calls.id_agent
WHERE campaign.id = calls.id_campaign
SQL_OUTGOING;
        list($sWhere_outgoing, $param_outgoing) = $this->_construirWhere_outgoing($param);
        $sPeticion_outgoing .= $sWhere_outgoing;

        // Construir la unión SQL en caso necesario
        $sPeticionSQL = NULL; $paramSQL = NULL;
        if (!isset($param['calltype']) || !in_array($param['calltype'], array('incoming', 'outgoing')))
            $param['calltype'] = 'any';
        switch ($param['calltype']) {
        case 'incoming':
            $sPeticionSQL = $sPeticion_incoming;
            $paramSQL = $param_incoming;
            break;
        case 'outgoing':
            $sPeticionSQL = $sPeticion_outgoing;
            $paramSQL = $param_outgoing;
            break;
        default:
            $sPeticionSQL = "($sPeticion_incoming) UNION ($sPeticion_outgoing)";
            $paramSQL = array_merge($param_incoming, $param_outgoing);
            break;
        }
        $sPeticionSQL .= ' ORDER BY start_date DESC, telefono';
        if (!empty($limit)) {
            $sPeticionSQL .= " LIMIT ? OFFSET ?";
            array_push($paramSQL, $limit, $offset);
        }

        // Ejecutar la petición SQL para todos los datos
        //print "<pre>$sPeticionSQL</pre>";
        $recordset = $this->_DB->fetchTable($sPeticionSQL, FALSE, $paramSQL);
        if (!is_array($recordset)) {
            $this->errMsg = '(internal) Failed to fetch CDRs - '.$this->_DB->errMsg;
            $recordset = NULL;
        }

        /* Buscar grabaciones para las llamadas leídas. No se usa un LEFT JOIN
         * en el query principal porque pueden haber múltiples grabaciones por
         * registro (múltiples intentos en caso outgoing) y la cuenta de
         * registros no considera esta duplicidad. */
        $sqlfield = array(
            'Inbound'   =>  'id_call_incoming',
            'Outbound'  =>  'id_call_outgoing',
        );
        foreach (array_keys($recordset) as $i) {
            /* Se asume que el tipo de llamada está en la columna 7 y el ID del
             * intento de llamada en la columna 11. */
            $sql = 'SELECT id, datetime_entry FROM call_recording WHERE '.
                $sqlfield[$recordset[$i][7]].' = ? ORDER BY datetime_entry DESC';
            $r2 = $this->_DB->fetchTable($sql, TRUE, array($recordset[$i][11]));
            if (!is_array($r2)) {
                $this->errMsg = '(internal) Failed to fetch recordings for CDRs - '.$this->_DB->errMsg;
                $recordset = NULL;
                break;
            }
            $recordset[$i][] = $r2;
        }

        return $recordset;
    }

    /**
     * Procedimiento para contar el total de registros en el detalle de llamadas
     * realizadas a través del CallCenter.
     *
     * @param   mixed   $param  Lista de parámetros de filtrado. Idéntico a
     *                          leerDetalleLlamadas.
     *
     * @return  mixed   NULL en caso de error, o cuenta de registros.
     */
    function contarDetalleLlamadas($param)
    {
        if (!is_array($param)) {
            $this->errMsg = '(internal) Invalid parameter array';
            return NULL;
        }

        $sPeticion_incoming = <<<SQL_INCOMING
SELECT COUNT(*)
FROM (call_entry, queue_call_entry)
LEFT JOIN contact
    ON contact.id = call_entry.id_contact
LEFT JOIN agent
    ON agent.id = call_entry.id_agent
LEFT JOIN campaign_entry
    ON campaign_entry.id = call_entry.id_campaign
WHERE call_entry.id_queue_call_entry = queue_call_entry.id
SQL_INCOMING;
        list($sWhere_incoming, $param_incoming) = $this->_construirWhere_incoming($param);
        $sPeticion_incoming .= $sWhere_incoming;

        $sPeticion_outgoing = <<<SQL_OUTGOING
SELECT COUNT(*)
FROM (calls, campaign)
LEFT JOIN agent
    ON agent.id = calls.id_agent
WHERE campaign.id = calls.id_campaign
SQL_OUTGOING;
        list($sWhere_outgoing, $param_outgoing) = $this->_construirWhere_outgoing($param);
        $sPeticion_outgoing .= $sWhere_outgoing;

        // Sumar las cuentas de ambas tablas en caso necesario
        $iNumRegistros = 0;
        if (!isset($param['calltype']) || !in_array($param['calltype'], array('incoming', 'outgoing')))
            $param['calltype'] = 'any';
        if (in_array($param['calltype'], array('any', 'outgoing'))) {
            // Agregar suma de llamadas salientes
            $tupla = $this->_DB->getFirstRowQuery($sPeticion_outgoing, FALSE, $param_outgoing);
            if (is_array($tupla) && count($tupla) > 0) {
                $iNumRegistros += $tupla[0];
            } elseif (!is_array($tupla)) {
                $this->errMsg = '(internal) Failed to count CDRs (outgoing) - '.$this->_DB->errMsg;
                return NULL;
            }
        }
        if (in_array($param['calltype'], array('any', 'incoming'))) {
            // Agregar suma de llamadas entrantes
            $tupla = $this->_DB->getFirstRowQuery($sPeticion_incoming, FALSE, $param_incoming);
            if (is_array($tupla) && count($tupla) > 0) {
                $iNumRegistros += $tupla[0];
            } elseif (!is_array($tupla)) {
                $this->errMsg = '(internal) Failed to count CDRs (incoming) - '.$this->_DB->errMsg;
                return NULL;
            }
        }

        return $iNumRegistros;
    }

    /**
     * Procedimiento para obtener los agentes de CallCenter. A diferencia del
     * método en modules/agents/Agentes.class.php, este método lista también los
     * agentes inactivos, junto con su estado.
     *
     * @return  mixed   NULL en caso de error, o lista de agentes
     */
    function getAgents()
    {
        $recordset = $this->_DB->fetchTable(
            'SELECT id, number, name, estatus FROM agent ORDER BY estatus, number',
            TRUE);
        if (!is_array($recordset)) {
            $this->errMsg = '(internal) Failed to fetch agents - '.$this->_DB->errMsg;
            $recordset = NULL;
        }
        return $recordset;
    }

    /**
     * Procedimiento para leer la lista de campañas del CallCenter. Las campañas
     * se listan primero las activas, luego inactivas, luego terminadas, y luego
     * por fecha de creación descendiente.
     *
     * @param unknown $type
     */
    function getCampaigns($type)
    {
        $recordset = $this->_DB->fetchTable(
            'SELECT id, name, estatus '.
            'FROM '.(($type == 'incoming') ? 'campaign_entry' : 'campaign').' '.
            'ORDER BY estatus, datetime_init DESC',
            TRUE);
        if (!is_array($recordset)) {
            $this->errMsg = '(internal) Failed to fetch campaigns - '.$this->_DB->errMsg;
            $recordset = NULL;
        }
        return $recordset;
    }

    function getRecordingFilePath($id)
    {
        $tupla = $this->_DB->getFirstRowQuery(
            'SELECT recordingfile FROM call_recording WHERE id = ?',
            TRUE, array($id));
        if (!is_array($tupla)) {
            $this->errMsg = '(internal) Failed to fetch recording filename - '.$this->_DB->errMsg;
            return NULL;
        }
        if (count($tupla) <= 0) return NULL;

        // TODO: volver configurable
        $recordingpath = '/var/spool/asterisk/monitor';
        if ($tupla['recordingfile']{0} != '/')
            $tupla['recordingfile'] = $recordingpath.'/'.$tupla['recordingfile'];
        return array(
            $tupla['recordingfile'],            // Ruta de archivo real
            basename($tupla['recordingfile'])   // TODO: renombrar según convención campaña
        );
    }
}
?>
