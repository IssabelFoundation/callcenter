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
  $Id: paloSantoCampaignCC.class.php,v 1.2 2008/06/06 07:15:07 cbarcos Exp $ */

include_once("libs/paloSantoDB.class.php");

define('REGEXP_FECHA_VALIDA', '/^\d{4}-\d{2}-\d{2}$/');
define('REGEXP_HORA_VALIDA', '/^\d{2}:\d{2}$/');

/* Clase que implementa campaña (saliente por ahora) de CallCenter (CC) */
class paloSantoCampaignCC
{
    var $_DB; // instancia de la clase paloDB
    var $errMsg;

    function paloSantoCampaignCC(&$pDB)
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
     * Procedimiento para obtener el listado de los campañas existentes. Si
     * se especifica id, el listado contendrá únicamente la campaña
     * indicada por el valor. De otro modo, se listarán todas las campañas.
     *
     * @param int   $id_campaign    Si != NULL, indica el id de la campaña a recoger
     *
     * @return array    Listado de campañas en el siguiente formato, o FALSE en caso de error:
     *  array(
     *      //array(id,nombre,fecha_ini,hora_ini,prompt,llamadas_prog,llamadas_real,reintentos,llamadas_pend,detalles),
     *		array(id, name, start_time, retries, b_status, trunk),
     *      ...
     *  )
     */
    function getCampaigns($limit, $offset, $id_campaign = NULL,$estatus='all')
    {
        $this->errMsg = '';
        if (!is_null($id_campaign) && !ctype_digit("$id_campaign")) {
            $this->errMsg = _tr("Campaign ID is not valid");
            return FALSE;
        }
        $sPeticionSQL = <<<SQL_SELECT_CAMPAIGNS
SELECT id, name, trunk, context, queue, datetime_init, datetime_end, daytime_init,
    daytime_end, script, retries, promedio, num_completadas, estatus, max_canales,
    id_url
FROM campaign
SQL_SELECT_CAMPAIGNS;
        $paramWhere = array();
        $paramSQL = array();

        if (in_array($estatus, array('A', 'I', 'T'))) {
        	$paramWhere[] = 'estatus = ?';
            $paramSQL[] = $estatus;
        }
        if (!is_null($id_campaign)) {
        	$paramWhere[] = 'id = ?';
            $paramSQL[] = $id_campaign;
        }
        if (count($paramWhere) > 0) $sPeticionSQL .= ' WHERE '.implode(' AND ', $paramWhere);
        $sPeticionSQL .= ' ORDER BY datetime_init, daytime_init';
        if (!is_null($limit)) {
        	$sPeticionSQL .= ' LIMIT ? OFFSET ?';
            $paramSQL[] = $limit; $paramSQL[] = $offset;
        }
        $arr_result = $this->_DB->fetchTable($sPeticionSQL, TRUE, $paramSQL);
        if (!is_array($arr_result)) {
            $this->errMsg = $this->_DB->errMsg;
        	return FALSE;
        }
        return $arr_result;
    }

    /**
     * Procedimiento para crear una nueva campaña, vacía e inactiva. Esta campaña
     * debe luego llenarse con números de teléfono en sucesivas operaciones.
     *
     * @param   $sNombre            Nombre de la campaña
     * @param   $iMaxCanales        Número máximo de canales a usar simultáneamente por campaña
     * @param   $iRetries           Número de reintentos de la campaña, por omisión 5
     * @param   $sTrunk             troncal por donde se van a realizar las llamadas (p.ej. "Zap/g0")
     * @param   $sContext           Contexto asociado a la campaña (p.ej. 'from-internal')
     * @param   $sQueue             Número que identifica a la cola a conectar la campaña saliente (p.ej. '402')
     * @param   $sFechaInicio       Fecha YYYY-MM-DD en que inicia la campaña
     * @param   $sFechaFinal        Fecha YYYY-MM-DD en que finaliza la campaña
     * @param   $sHoraInicio        Hora del día (HH:MM militar) en que se puede iniciar llamadas
     * @param   $sHoraFinal         Hora del día (HH:MM militar) en que se debe dejar de hacer llamadas
     * @param   $script             Texto del script a recitar por el agente
     * @param   $id_url             NULL, o ID del URL externo a cargar
     *
     * @return  int    El ID de la campaña recién creada, o NULL en caso de error
     */
    function createEmptyCampaign($sNombre, $iMaxCanales, $iRetries, $sTrunk, $sContext, $sQueue,
        $sFechaInicial, $sFechaFinal, $sHoraInicio, $sHoraFinal, $script, $id_url)
    {
        $id_campaign = NULL;
        $bExito = FALSE;

        // Carga de colas entrantes activas
        $recordset = $this->_DB->fetchTable("SELECT queue FROM queue_call_entry WHERE estatus='A'");
        if (!is_array($recordset)) {
            $this->errMsg = _tr('(internal) Failed to query active incoming queues').
                ' - '.$this->_DB->errMsg;
        	return NULL;
        }
        $colasEntrantes = array();
        foreach ($recordset as $tupla) $colasEntrantes[] = $tupla[0];

        $sNombre = trim($sNombre);
        $iMaxCanales = trim($iMaxCanales);
        $iRetries = trim($iRetries);
        $sTrunk = trim($sTrunk);
        $sContext = trim($sContext);
        $sQueue = trim($sQueue);
        $sFechaInicial = trim($sFechaInicial);
        $sFechaFinal = trim($sFechaFinal);
        $sHoraInicio = trim($sHoraInicio);
        $sHoraFinal = trim($sHoraFinal);
        $script = trim($script);

        if ($sTrunk == '') $sTrunk = NULL;

        if ($sNombre == '') {
            $this->errMsg = _tr("Name Campaign can't be empty");//'Nombre de campaña no puede estar vacío';
        } elseif ($sContext == '') {
            $this->errMsg = _tr("Context can't be empty");//'Contexto no puede estar vacío';
        } elseif (!ctype_digit($iRetries)) {
            $this->errMsg = _tr('Retries must be numeric');//'Número de reintentos debe de ser numérico y entero';
        } elseif ($sQueue == '') {
            $this->errMsg = _tr("Queue can't be empty");//'Número de cola no puede estar vacío';
        } elseif (!ctype_digit($sQueue)) {
            $this->errMsg = _tr('Queue must be numeric');//'Número de cola debe de ser numérico y entero';
        } elseif (!preg_match(REGEXP_FECHA_VALIDA, $sFechaInicial)) {
            $this->errMsg = _tr('Invalid Start Date');//'Fecha de inicio no es válida (se espera yyyy-mm-dd)';
        } elseif (!preg_match(REGEXP_FECHA_VALIDA, $sFechaFinal)) {
            $this->errMsg = _tr('Invalid End Date');//'Fecha de final no es válida (se espera yyyy-mm-dd)';
        } elseif ($sFechaInicial > $sFechaFinal) {
            $this->errMsg = _tr('Start Date must be greater than End Date');//'Fecha de inicio debe ser anterior a la fecha final';
        } elseif (!preg_match(REGEXP_HORA_VALIDA, $sHoraInicio)) {
            $this->errMsg = _tr('Invalid Start Time');//'Hora de inicio no es válida (se espera hh:mm)';
        } elseif (!preg_match(REGEXP_HORA_VALIDA, $sHoraFinal)) {
            $this->errMsg = _tr('Invalid End Time');//'Hora de final no es válida (se espera hh:mm)';
        } elseif (strcmp($sFechaInicial,$sFechaFinal)==0 && strcmp ($sHoraInicio,$sHoraFinal)>=0) {
            $this->errMsg = _tr('Start Time must be greater than End Time');//'Hora de inicio debe ser anterior a la hora final';
        } elseif (!is_null($id_url) && !ctype_digit("$id_url")) {
            $this->errMsg = _tr('(internal) Invalid URL ID');
        } elseif (in_array($sQueue, $colasEntrantes)) {
             $this->errMsg =  _tr('Queue is being used, choose other one');//La cola ya está siendo usada, escoja otra
        } else {
            // Verificar que el nombre de la campaña es único
            $tupla = $this->_DB->getFirstRowQuery(
                'SELECT COUNT(*) AS N FROM campaign WHERE name = ?', TRUE, array($sNombre));
            if (is_array($tupla) && $tupla['N'] > 0) {
                // Ya existe una campaña duplicada
                $this->errMsg = _tr('Name Campaign already exists');//'Nombre de campaña indicado ya está en uso';
            	return NULL;
            }

            // Construir y ejecutar la orden de inserción SQL
            $sPeticionSQL = <<<SQL_INSERT_CAMPAIGN
INSERT INTO campaign (name, max_canales, retries, trunk, context, queue,
    datetime_init, datetime_end, daytime_init, daytime_end, script, id_url)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
SQL_INSERT_CAMPAIGN;
            $paramSQL = array($sNombre, $iMaxCanales, $iRetries, $sTrunk,
                $sContext, $sQueue, $sFechaInicial, $sFechaFinal, $sHoraInicio,
                $sHoraFinal, $script, $id_url);
            if ($this->_DB->genQuery($sPeticionSQL, $paramSQL)) {
            	// Leer el ID insertado por la operación
                $id_campaign = $this->_DB->getLastInsertId();
                if ($id_campaign === FALSE) {
                	$this->errMsg = $this->_DB->errMsg;
                    $id_campaign = NULL;
                }
            } else {
            	$this->errMsg = $this->_DB->errMsg;
            }
        }
        return $id_campaign;
    }

    /**
	 * Procedimiento para agregar los formularios a la campaña
	 *
     * @param	int		$id_campaign	ID de la campaña
     * @param	string		$formularios	los id de los formularios 1,2,.....,
     * @return	bool            true or false
    */
    function addCampaignForm($id_campania,$formularios)
    {
        if (!is_array($formularios)) {
            if ($formularios == '')
                $formularios = array();
            else $formularios = explode(',', $formularios);
        }
        foreach ($formularios as $id_form) {
        	$r = $this->_DB->genQuery(
                'INSERT INTO campaign_form (id_campaign, id_form) VALUES (?, ?)',
                array($id_campania, $id_form));
            if (!$r) {
                $this->errMsg = $this->_DB->errMsg;
            	return FALSE;
            }
        }
        return TRUE;
    }

    /**
	 * Procedimiento para actualizar los formularios a la campaña
	 *
     * @param	int		$id_campaign	ID de la campaña
     * @param	string		$formularios	los id de los formularios 1,2,.....,
     * @return	bool            true or false
    */
    function updateCampaignForm($id_campania, $formularios)
    {
        if (!$this->_DB->genQuery(
            'DELETE FROM campaign_form WHERE id_campaign = ?',
            array($id_campania))) {
            $this->errMsg = $this->_DB->errMsg;
            return FALSE;
        }
        return $this->addCampaignForm($id_campania, $formularios);
    }

    /**
	 * Procedimiento para obtener los formualarios de una campaña
	 *
     * @param	int		$id_campaign	ID de la campaña
     * @return	mixed	NULL en caso de error o los id formularios
    */
    function obtenerCampaignForm($id_campania)
    {
        $tupla = $this->_DB->fetchTable(
            'SELECT id_form FROM campaign_form WHERE id_campaign = ?',
            FALSE, array($id_campania));
        if (!is_array($tupla)) {
            $this->errMsg = $this->_DB->errMsg;
            return NULL;
        }
        $salida = array();
        foreach ($tupla as $value) $salida[] = $value[0];
        return $salida;
    }

    function getExternalUrls()
    {
        $tupla = $this->_DB->fetchTable('SELECT id, description FROM campaign_external_url WHERE active = 1');
        if (!is_array($tupla)) {
            $this->errMsg = $this->_DB->errMsg;
            return NULL;
        }
        $salida = array();
        foreach ($tupla as $value) $salida[$value[0]] = $value[1];
        return $salida;
    }

	/**
	 * Procedimiento para contar el número de teléfonos asignados a ser marcados
	 * en la campaña indicada por $idCampaign.
	 *
     * @param	int		$idCampaign	ID de la campaña a leer
     *
     * @return	mixed	NULL en caso de error o número de teléfonos total
	 */
    function countCampaignNumbers($idCampaign)
    {
    	$iNumTelefonos = NULL;

        if (!ctype_digit($idCampaign)) {
            $this->errMsg = _tr('Invalid Campaign ID'); //;'ID de campaña no es numérico';
            return NULL;
        }
        $tupla = $this->_DB->getFirstRowQuery(
            'SELECT COUNT(*) FROM calls WHERE id_campaign = ?',
            FALSE, array($idCampaign));
        if (!is_array($tupla)) {
            $this->errMsg = $this->_DB->errMsg;
            return NULL;
        }
        return (int)$tupla[0];
    }

    /**
     * Procedimiento para modificar las propiedades de una campaña existente
     *
     * @param   $idCampaign         ID de la campaña existente
     * @param   $sNombre            Nombre de la campaña
     * @param   $iMaxCanales        Número máximo de canales a usar simultáneamente por campaña
     * @param   $iRetries           Número de reintentos de la campaña, por omisión 5
     * @param   $sTrunk             troncal por donde se van a realizar las llamadas (p.ej. "Zap/g0")
     * @param   $sContext           Contexto asociado a la campaña (p.ej. 'from-internal')
     * @param   $sQueue             Número que identifica a la cola a conectar la campaña saliente (p.ej. '402')
     * @param   $sFechaInicio       Fecha YYYY-MM-DD en que inicia la campaña
     * @param   $sFechaFinal        Fecha YYYY-MM-DD en que finaliza la campaña
     * @param   $sHoraInicio        Hora del día (HH:MM militar) en que se puede iniciar llamadas
     * @param   $sHoraFinal         Hora del día (HH:MM militar) en que se debe dejar de hacer llamadas
     * @param   $script             Texto del script a recitar por el agente
     * @param   $id_url             NULL, o ID del URL externo a cargar
     *
     * @return  bool                VERDADERO si se actualiza correctamente, FALSO en error
     */
    function updateCampaign($idCampaign, $sNombre, $iMaxCanales, $iRetries, $sTrunk,
        $sContext, $sQueue, $sFechaInicial, $sFechaFinal, $sHoraInicio, $sHoraFinal,
        $script, $id_url)
    {

        $bExito = FALSE;

        $sNombre = trim($sNombre);
        $iMaxCanales = trim($iMaxCanales);
        $iRetries = trim($iRetries);
        $sTrunk = trim($sTrunk);
        $sContext = trim($sContext);
        $sQueue = trim($sQueue);
        $sFechaInicial = trim($sFechaInicial);
        $sFechaFinal = trim($sFechaFinal);
        $sHoraInicio = trim($sHoraInicio);
        $sHoraFinal = trim($sHoraFinal);
        $script = trim($script);

        if ($sTrunk == '') $sTrunk = NULL;

        if ($sNombre == '') {
            $this->errMsg = _tr("Name Campaign can't be empty");//'Nombre de campaña no puede estar vacío';
        } elseif ($sContext == '') {
            $this->errMsg = _tr("Context can't be empty");//'Contexto no puede estar vacío';
        } elseif (!ctype_digit($iRetries)) {
            $this->errMsg = _tr('Retries must be numeric');//'Número de reintentos debe de ser numérico y entero';
        } elseif ($sQueue == '') {
            $this->errMsg = _tr("Queue can't be empty");//'Número de cola no puede estar vacío';
        } elseif (!preg_match(REGEXP_FECHA_VALIDA, $sFechaInicial)) {
            $this->errMsg = _tr('Invalid Start Date');//'Fecha de inicio no es válida (se espera yyyy-mm-dd)';
        } elseif (!preg_match(REGEXP_FECHA_VALIDA, $sFechaFinal)) {
            $this->errMsg = _tr('Invalid End Date');//'Fecha de final no es válida (se espera yyyy-mm-dd)';
        } elseif ($sFechaInicial > $sFechaFinal) {
            $this->errMsg = _tr('Start Date must be greater than End Date');//'Fecha de inicio debe ser anterior a la fecha final';
        } elseif (!preg_match(REGEXP_HORA_VALIDA, $sHoraInicio)) {
            $this->errMsg = _tr('Invalid Start Time');//'Hora de inicio no es válida (se espera hh:mm)';
        } elseif (!preg_match(REGEXP_HORA_VALIDA, $sHoraFinal)) {
            $this->errMsg = _tr('Invalid End Time');//'Hora de final no es válida (se espera hh:mm)';
        } elseif (strcmp($sFechaInicial,$sFechaFinal)==0 && strcmp ($sHoraInicio,$sHoraFinal)>=0) {
            $this->errMsg = _tr('Start Time must be greater than End Time');//'Hora de inicio debe ser anterior a la hora final';
        } elseif (!is_null($id_url) && !ctype_digit("$id_url")) {
            $this->errMsg = _tr('(internal) Invalid URL ID');
        } else {

            // Construir y ejecutar la orden de update SQL
            $sPeticionSQL = <<<SQL_UPDATE_CAMPAIGN
UPDATE campaign SET
    name = ?, max_canales = ?, retries = ?, trunk = ?,
    context = ?, queue = ?, datetime_init = ?, datetime_end = ?,
    daytime_init = ?, daytime_end = ?, script = ?, id_url = ?
WHERE id = ?
SQL_UPDATE_CAMPAIGN;
            $paramSQL = array($sNombre, $iMaxCanales, $iRetries, $sTrunk,
                $sContext, $sQueue, $sFechaInicial, $sFechaFinal,
                $sHoraInicio, $sHoraFinal, $script, $id_url,
                $idCampaign);
            if ($this->_DB->genQuery($sPeticionSQL, $paramSQL)) return TRUE;
            $this->errMsg = $this->_DB->errMsg;
        }
        return false;
    }

    function activar_campaign($idCampaign, $activar)
    {
        if (!$this->_DB->genQuery(
            'UPDATE campaign SET estatus = ? WHERE id = ?',
            array($activar, $idCampaign))) {
        	$this->errMsg = $this->_DB->errMsg;
            return FALSE;
        }
        return TRUE;
    }

    function delete_campaign($idCampaign)
    {
        $listaSQL = array(
            'DELETE FROM campaign_form WHERE id_campaign = ?',
            'DELETE FROM call_recording WHERE id_call_outgoing IN (SELECT id from calls WHERE id_campaign = ?)',
            'DELETE FROM call_attribute WHERE id_call IN (SELECT id from calls WHERE id_campaign = ?)',
            'DELETE FROM form_data_recolected WHERE id_calls IN (SELECT id from calls WHERE id_campaign = ?)',
            'DELETE call_progress_log FROM call_progress_log, calls '.
                'WHERE call_progress_log.id_call_outgoing = calls.id AND calls.id_campaign = ?',
            'DELETE FROM calls WHERE id_campaign = ?',
            'DELETE FROM campaign WHERE id = ?',
        );

    	$this->_DB->beginTransaction();
        foreach ($listaSQL as $sql) {
        	$r = $this->_DB->genQuery($sql, array($idCampaign));
            if (!$r) {
            	$this->errMsg = $this->_DB->errMsg;
                $this->_DB->rollBack();
                return FALSE;
            }
        }
        $this->_DB->commit();
        return TRUE;
    }

    /**
     * Procedimiento para leer la totalidad de los datos de una campaña terminada,
     * incluyendo todos los datos recogidos en los diversos formularios asociados.
     *
     * @param   object  $pDB            Conexión paloDB a la base de datos call_center
     * @param   int     $id_campaign    ID de la campaña a recuperar
     * @param(out) string $errMsg       Mensaje de error
     *
     * @return  NULL en caso de error, o una estructura de la siguiente forma:
    array(
        BASE => array(
            LABEL   =>  array(
                "id_call",
                "Phone Customer"
                ...
            ),
            DATA    =>  array(
                array(...),
                array(...),
                ...
            ),
        ),
        FORMS => array(
            {id_form} => array(
                NAME    =>  'TestForm',
                LABEL   =>  array(
                    "Label A",
                    "Label B"
                    ...
                ),
                DATA    =>  array(
                    {id_call} => array(...),
                    {id_call} => array(...),
                    ...
                ),
            ),
            ...
        ),
    )
     */
    function & getCompletedCampaignData($id_campaign)
    {

        $this->errMsg = NULL;

        $sqlLlamadas = <<<SQL_LLAMADAS
SELECT
    c.id                AS id,
    c.phone             AS telefono,
    c.status            AS estado,
    a.number            AS number,
    c.start_time        AS fecha_hora,
    c.duration          AS duracion,
    c.uniqueid          AS uniqueid,
    c.failure_cause     AS failure_cause,
    c.failure_cause_txt AS failure_cause_txt
FROM calls c
LEFT JOIN agent a
    ON c.id_agent = a.id
WHERE
    c.id_campaign = ? AND
    (c.status='Success' OR c.status='Failure' OR c.status='ShortCall' OR c.status='NoAnswer' OR c.status='Abandoned')
ORDER BY
    telefono ASC
SQL_LLAMADAS;

        $datosCampania = NULL;
        $datosTelefonos = $this->_DB->fetchTable($sqlLlamadas, FALSE, array($id_campaign));
        if (!is_array($datosTelefonos)) {
            $this->errMsg = 'Unable to read campaign phone data - '.$this->_DB->errMsg;
            return $datosCampania;
        }
        $datosCampania = array(
            'BASE'  =>  array(
                'LABEL' =>  array(
                    'id_call',
                    _tr('Phone Customer'),
                    _tr('Status Call'),
                    "Agente",
                    _tr('Date & Time'),
                    _tr('Duration'),
                    'Uniqueid',
                    _tr('Failure Code'),
                    _tr('Failure Cause'),
                ),
                'DATA'  =>  $datosTelefonos,
            ),
            'FORMS' =>  array(),
        );
        $datosTelefonos = NULL;

        // Construir índice para obtener la posición de la llamada, dado su ID
        $datosCampania['BASE']['ID2POS'] = array();
        foreach ($datosCampania['BASE']['DATA'] as $pos => $tuplaTelefono) {
            $datosCampania['BASE']['ID2POS'][$tuplaTelefono[0]] = $pos;
        }

        // Leer los datos de los atributos de cada llamada
        $iOffsetAttr = count($datosCampania['BASE']['LABEL']);
        $sqlAtributos = <<<SQL_ATRIBUTOS
SELECT
    call_attribute.id_call          AS id_call,
    call_attribute.columna          AS etiqueta,
    call_attribute.value            AS valor,
    call_attribute.column_number    AS posicion
FROM calls, call_attribute
WHERE calls.id_campaign = ? AND calls.id = ? AND calls.id = call_attribute.id_call AND
    (calls.status='Success' OR calls.status='Failure' OR calls.status='ShortCall' OR calls.status='NoAnswer' OR calls.status='Abandoned')
ORDER BY calls.id, call_attribute.column_number
SQL_ATRIBUTOS;
        foreach ($datosCampania['BASE']['ID2POS'] as $id_call => $pos) {
            $datosAtributos = $this->_DB->fetchTable($sqlAtributos, TRUE, array($id_campaign, $id_call));
            if (!is_array($datosAtributos)) {
                $this->errMsg = 'Unable to read attribute data - '.$this->_DB->errMsg;
                $datosCampania = NULL;
                return $datosCampania;
            }
            foreach ($datosAtributos as $tuplaAtributo) {
                // Se asume que el valor posicion empieza desde 1
                $iPos = $iOffsetAttr + $tuplaAtributo['posicion'] - 1;
                $datosCampania['BASE']['LABEL'][$iPos] = $tuplaAtributo['etiqueta'];
                $datosCampania['BASE']['DATA'][$pos][$iPos] = $tuplaAtributo['valor'];
            }
        }

        // Leer los datos de los formularios asociados a esta campaña
        $sqlFormularios = <<<SQL_FORMULARIOS
(SELECT
    f.id        AS id_form,
    ff.id       AS id_form_field,
    ff.etiqueta AS campo_nombre,
    f.nombre    AS formulario_nombre,
    ff.orden    AS orden
FROM campaign_form cf, form f, form_field ff
WHERE cf.id_form = f.id AND f.id = ff.id_form AND ff.tipo <> 'LABEL' AND cf.id_campaign = ?)
UNION DISTINCT
(SELECT DISTINCT
    f.id        AS id_form,
    ff.id       AS id_form_field,
    ff.etiqueta AS campo_nombre,
    f.nombre    AS formulario_nombre,
    ff.orden    AS orden
FROM form f, form_field ff, form_data_recolected fdr, calls c
WHERE f.id = ff.id_form AND ff.tipo <> 'LABEL' AND fdr.id_form_field = ff.id AND fdr.id_calls = c.id AND c.id_campaign = ?)
ORDER BY id_form, orden ASC
SQL_FORMULARIOS;
        $datosFormularios = $this->_DB->fetchTable($sqlFormularios, FALSE, array($id_campaign, $id_campaign));
        if (!is_array($datosFormularios)) {
            $this->errMsg = 'Unable to read form data - '.$this->_DB->errMsg;
            $datosCampania = NULL;
            return $datosCampania;
        }
        foreach ($datosFormularios as $tuplaFormulario) {
            if (!isset($datosCampania['FORMS'][$tuplaFormulario[0]])) {
                $datosCampania['FORMS'][$tuplaFormulario[0]] = array(
                    'NAME'  =>  $tuplaFormulario[3],
                    'LABEL' =>  array(),
                    'DATA'  =>  array(),
                    'FF2POS'=>  array(),
                );
            }
            $datosCampania['FORMS'][$tuplaFormulario[0]]['LABEL'][] = $tuplaFormulario[2];

            // Construir índice para obtener posición/orden del campo de formulario, dado su ID.
            $datosCampania['FORMS'][$tuplaFormulario[0]]['FF2POS'][$tuplaFormulario[1]] = count($datosCampania['FORMS'][$tuplaFormulario[0]]['LABEL']) - 1;
        }
        $datosFormularios = NULL;

        // Leer los datos recolectados de los formularios
        $sqlDatosForm = <<<SQL_DATOS_FORM
SELECT
    c.id AS id_call,
    ff.id_form AS id_form,
    ff.id AS id_form_field,
    fdr.value AS campo_valor
FROM calls c, form_data_recolected fdr, form_field ff
WHERE fdr.id_calls = c.id AND fdr.id_form_field = ff.id AND c.id_campaign = ?
    AND ff.tipo <> 'LABEL'
    AND (c.status='Success' OR c.status='Failure' OR c.status='ShortCall' OR c.status='NoAnswer' OR c.status='Abandoned')
ORDER BY id_call, id_form, id_form_field
SQL_DATOS_FORM;
        $datosRecolectados = $this->_DB->fetchTable($sqlDatosForm, TRUE, array($id_campaign));
        if (!is_array($datosRecolectados)) {
            $this->errMsg = 'Unable to read form fill-out data - '.$this->_DB->errMsg;
            $datosCampania = NULL;
            return $datosCampania;
        }
        foreach ($datosRecolectados as $vr) {
            if (!isset($datosCampania['FORMS'][$vr['id_form']]['DATA'][$vr['id_call']])) {
                // No está asignada la tupla de valores para esta llamada. Se construye
                // una tupla de valores NULL que será llenada progresivamente.
                $tuplaVacia = array_fill(0, count($datosCampania['FORMS'][$vr['id_form']]['LABEL']), NULL);
                $datosCampania['FORMS'][$vr['id_form']]['DATA'][$vr['id_call']] = $tuplaVacia;
            }
            $iPos = $datosCampania['FORMS'][$vr['id_form']]['FF2POS'][$vr['id_form_field']];
            $datosCampania['FORMS'][$vr['id_form']]['DATA'][$vr['id_call']][$iPos] = $vr['campo_valor'];
        }
        $datosRecolectados = NULL;

        return $datosCampania;
    }
}
?>