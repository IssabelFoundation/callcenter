<?php
/* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Elastix version 0.8                                                  |
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
  $Id: default.conf.php,v 1.1.1.1 2007/03/23 00:13:58 elandivar Exp $ */


require_once "modules/agent_console/libs/elastix2.lib.php";
require_once "modules/agent_console/libs/JSON.php";
require_once "modules/agent_console/libs/paloSantoConsola.class.php";

function _moduleContent(&$smarty, $module_name)
{
    global $arrConf;
    global $arrLang;
    global $arrConfig;

    //include module files
    include_once "modules/$module_name/configs/default.conf.php";

    // Se fusiona la configuración del módulo con la configuración global
    $arrConf = array_merge($arrConf, $arrConfModule);

    load_language_module($module_name);

    //folder path for custom templates
    $base_dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $templates_dir = (isset($arrConfig['templates_dir']))?$arrConfig['templates_dir']:'themes';
    $local_templates_dir = "$base_dir/modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];

    // Ember.js requiere jQuery 1.7.2 o superior.
    modificarReferenciasLibreriasJS($smarty);

    $sContenido = '';

    // Procesar los eventos AJAX.
    switch (getParameter('action')) {
    case 'getCampaigns':
        $sContenido = manejarMonitoreo_getCampaigns($module_name, $smarty, $local_templates_dir);
        break;
    case 'getCampaignDetail':
        $sContenido = manejarMonitoreo_getCampaignDetail($module_name, $smarty, $local_templates_dir);
        break;
    case 'checkStatus':
        $sContenido = manejarMonitoreo_checkStatus($module_name, $smarty, $local_templates_dir);
        break;
    case 'loadPreviousLogEntries':
        $sContenido = manejarMonitoreo_loadPreviousLogEntries($module_name, $smarty, $local_templates_dir);
        break;
    default:
        // Página principal con plantilla
        $sContenido = manejarMonitoreo_HTML($module_name, $smarty, $local_templates_dir);
    }
    return $sContenido;
}

function manejarMonitoreo_HTML($module_name, $smarty, $sDirLocalPlantillas)
{
    $smarty->assign("MODULE_NAME", $module_name);
    $smarty->assign(array(
        'title'                         =>  _tr('Campaign Monitoring'),
        'icon'                          => '/images/list.png',
        'ETIQUETA_CAMPANIA'             =>  _tr('Campaign'),
        'ETIQUETA_FECHA_INICIO'         =>  _tr('Start date'),
        'ETIQUETA_FECHA_FINAL'          =>  _tr('End date'),
        'ETIQUETA_HORARIO'              =>  _tr('Schedule'),
        'ETIQUETA_COLA'                 =>  _tr('Queue'),
        'ETIQUETA_INTENTOS'             =>  _tr('Retries'),
        'ETIQUETA_TOTAL_LLAMADAS'       =>  _tr('Total calls'),
        'ETIQUETA_LLAMADAS_PENDIENTES'  =>  _tr('Pending calls'),
        'ETIQUETA_LLAMADAS_FALLIDAS'    =>  _tr('Failed calls'),
        'ETIQUETA_LLAMADAS_CORTAS'      =>  _tr('Short calls'),
        'ETIQUETA_LLAMADAS_EXITO'       =>  _tr('Connected calls'),
        'ETIQUETA_LLAMADAS_MARCANDO'    =>  _tr('Placing calls'),
        'ETIQUETA_LLAMADAS_COLA'        =>  _tr('Queued calls'),
        'ETIQUETA_LLAMADAS_TIMBRANDO'   =>  _tr('Ringing calls'),
        'ETIQUETA_LLAMADAS_ABANDONADAS' =>  _tr('Abandoned calls'),
        'ETIQUETA_LLAMADAS_NOCONTESTA'  =>  _tr('Unanswered calls'),
        'ETIQUETA_LLAMADAS_TERMINADAS'  =>  _tr('Finished calls'),
        'ETIQUETA_LLAMADAS_SINRASTRO'   =>  _tr('Lost track'),
        'ETIQUETA_AGENTES'              =>  _tr('Agents'),
        'ETIQUETA_NUMERO_TELEFONO'      =>  _tr('Phone Number'),
        'ETIQUETA_TRONCAL'              =>  _tr('Trunk'),
        'ETIQUETA_ESTADO'               =>  _tr('Status'),
        'ETIQUETA_DESDE'                =>  _tr('Since'),
        'ETIQUETA_AGENTE'               =>  _tr('Agent'),
        'ETIQUETA_REGISTRO'             =>  _tr('View campaign log'),
        'PREVIOUS_N'                    =>  _tr('Previous 100 entries'),
        'ETIQUETA_MAX_DURAC_LLAM'       =>  _tr('Maximum Call Duration'),
        'ETIQUETA_PROMEDIO_DURAC_LLAM'  =>  _tr('Average Call Duration'),
    ));

    return $smarty->fetch("file:$sDirLocalPlantillas/informacion_campania.tpl");
}

function manejarMonitoreo_getCampaigns($module_name, $smarty, $sDirLocalPlantillas)
{
    $respuesta = array(
        'status'    =>  'success',
        'message'   =>  '(no message)',
    );

    $oPaloConsola = new PaloSantoConsola();
    $listaCampanias = $oPaloConsola->leerListaCampanias();
    if (!is_array($listaCampanias)) {
    	$respuesta['status'] = 'error';
        $respuesta['message'] = $oPaloConsola->errMsg;
    }
    $listaColas = $oPaloConsola->leerListaColasEntrantes();
    if (!is_array($listaColas)) {
        $respuesta['status'] = 'error';
        $respuesta['message'] = $oPaloConsola->errMsg;
    }
    if (is_array($listaCampanias) && is_array($listaColas)) {
        foreach ($listaColas as $q) {
        	$listaCampanias[] = array(
                'id'        =>  $q['queue'],
                'type'      =>  'incomingqueue',
                'name'      =>  $q['queue'],
                'status'    =>  $q['status'],
            );
        }


        /* Para la visualización se requiere que primero se muestren las campañas
         * activas, con el ID mayor primero (probablemente la campaña más reciente)
         * seguido de las campañas inactivas, y luego las terminadas */
        if (!function_exists('manejarMonitoreo_getCampaigns_sort')) {
            function manejarMonitoreo_getCampaigns_sort($a, $b)
            {
            	if ($a['type'] != $b['type']) {
            		if ($a['type'] == 'incomingqueue') return 1;
                    if ($b['type'] == 'incomingqueue') return -1;
            	}
                if ($a['status'] != $b['status'])
                    return strcmp($a['status'], $b['status']);
                return $b['id'] - $a['id'];
            }
        }
        usort($listaCampanias, 'manejarMonitoreo_getCampaigns_sort');
        $respuesta['campaigns'] = array();
        foreach ($listaCampanias as $c) {
            $respuesta['campaigns'][] = array(
                'id_campaign'   => $c['id'],
                'desc_campaign' => '('._tr($c['type']).') '.$c['name'],
                'type'          =>  $c['type'],
                'status'        =>  $c['status'],
            );
        }
    }
    $json = new Services_JSON();
    Header('Content-Type: application/json');
    return $json->encode($respuesta);
}

function manejarMonitoreo_getCampaignDetail($module_name, $smarty, $sDirLocalPlantillas)
{
    $respuesta = array(
        'status'    =>  'success',
        'message'   =>  '(no message)',
    );
    $estadoCliente = array();

    $sTipoCampania = getParameter('campaigntype');
    $sIdCampania = getParameter('campaignid');
    if (is_null($sTipoCampania) || !in_array($sTipoCampania, array('incoming', 'outgoing', 'incomingqueue'))) {
        $respuesta['status'] = 'error';
        $respuesta['message'] = _tr('Invalid campaign type');
    } elseif (is_null($sIdCampania) || !ctype_digit($sIdCampania)) {
        $respuesta['status'] = 'error';
        $respuesta['message'] = _tr('Invalid campaign ID');
    } else {
        $oPaloConsola = new PaloSantoConsola();
        if ($respuesta['status'] == 'success') {
        	$infoCampania = $oPaloConsola->leerInfoCampania($sTipoCampania, $sIdCampania);
            if (!is_array($infoCampania)) {
            	$respuesta['status'] = 'error';
                $respuesta['message'] = $oPaloConsola->errMsg;
            }
        }
        if ($respuesta['status'] == 'success') {
            $estadoCampania = $oPaloConsola->leerEstadoCampania($sTipoCampania, $sIdCampania);
            if (!is_array($estadoCampania)) {
                $respuesta['status'] = 'error';
                $respuesta['message'] = $oPaloConsola->errMsg;
            }
        }
        if ($respuesta['status'] == 'success') {
        	//$logCampania = $oPaloConsola->leerLogCampania($sTipoCampania, $sIdCampania);
            $logCampania = array();
            if (!is_array($logCampania)) {
                $respuesta['status'] = 'error';
                $respuesta['message'] = $oPaloConsola->errMsg;
            }
        }
    }
    if ($respuesta['status'] == 'success') {
    	$respuesta['campaigndata'] = array(
            'startdate'                 =>
                is_null($infoCampania['startdate'])
                ? _tr('N/A') : $infoCampania['startdate'],
            'enddate'                   =>
                is_null($infoCampania['enddate'])
                ? _tr('N/A') : $infoCampania['enddate'],
            'working_time_starttime'    =>
                is_null($infoCampania['working_time_starttime'])
                ? _tr('N/A') : $infoCampania['working_time_starttime'],
            'working_time_endtime'      =>
                is_null($infoCampania['working_time_endtime'])
                ? _tr('N/A') : $infoCampania['working_time_endtime'],
            'queue'                     =>  $infoCampania['queue'],
            'retries'                   =>
                is_null($infoCampania['retries'])
                ? _tr('N/A') : (int)$infoCampania['retries'],
        );

        // Traducción de estado de las llamadas no conectadas
        $estadoCampaniaLlamadas = array();
        foreach ($estadoCampania['activecalls'] as $activecall) {
            $estadoCampaniaLlamadas[] = formatoLlamadaNoConectada($activecall);
        }

        // Traducción de estado de los agentes
        $estadoCampaniaAgentes = array();
        foreach ($estadoCampania['agents'] as $agent) {
            $estadoCampaniaAgentes[] = formatoAgente($agent);
        }

        // Traducción de log de la campaña
        $logFinalCampania = array();
        foreach ($logCampania as $entradaLog) {
        	$logFinalCampania[] = formatoLogCampania($entradaLog);
        }

        // Se arma la respuesta JSON y el estado final del cliente
        $respuesta = array_merge($respuesta, crearRespuestaVacia());
        $respuesta['statuscount']['update'] = $estadoCampania['statuscount'];
        $respuesta['stats']['update'] = $estadoCampania['stats'];
        $respuesta['activecalls']['add'] = $estadoCampaniaLlamadas;
        $respuesta['agents']['add'] = $estadoCampaniaAgentes;
        $respuesta['log'] = $logFinalCampania;
        $estadoCliente = array(
            'campaignid'    =>  $sIdCampania,
            'campaigntype'  =>  $sTipoCampania,
            'queue'         =>  $infoCampania['queue'],
            'statuscount'   =>  $estadoCampania['statuscount'],
            'activecalls'   =>  $estadoCampania['activecalls'],
            'agents'        =>  $estadoCampania['agents'],
            'stats'         =>  $estadoCampania['stats'],
        );

        $respuesta['estadoClienteHash'] = generarEstadoHash($module_name, $estadoCliente);
    }

    $json = new Services_JSON();
    Header('Content-Type: application/json');
    return $json->encode($respuesta);
}

function manejarMonitoreo_loadPreviousLogEntries($module_name, $smarty, $sDirLocalPlantillas)
{
    $respuesta = array(
        'status'    =>  'success',
        'message'   =>  '(no message)',
    );

    $sTipoCampania = getParameter('campaigntype');
    $sIdCampania = getParameter('campaignid');
    $idBefore = getParameter('beforeid');
    if (is_null($idBefore) || !ctype_digit($idBefore))
        $idBefore = NULL;
    if (is_null($sTipoCampania) || !in_array($sTipoCampania, array('incoming', 'outgoing', 'incomingqueue'))) {
        $respuesta['status'] = 'error';
        $respuesta['message'] = _tr('Invalid campaign type');
    } elseif (is_null($sIdCampania) || !ctype_digit($sIdCampania)) {
        $respuesta['status'] = 'error';
        $respuesta['message'] = _tr('Invalid campaign ID');
    } else {
        $oPaloConsola = new PaloSantoConsola();
        $logCampania = $oPaloConsola->leerLogCampania($sTipoCampania, $sIdCampania, 100, $idBefore);
        if (!is_array($logCampania)) {
            $respuesta['status'] = 'error';
            $respuesta['message'] = $oPaloConsola->errMsg;
        } else {
            // Traducción de log de la campaña
            $logFinalCampania = array();
            foreach ($logCampania as $entradaLog) {
                $logFinalCampania[] = formatoLogCampania($entradaLog);
            }

            $respuesta['log'] = $logFinalCampania;
        }
    }

    $json = new Services_JSON();
    Header('Content-Type: application/json');
    return $json->encode($respuesta);
}

function manejarMonitoreo_checkStatus($module_name, $smarty, $sDirLocalPlantillas)
{
    $respuesta = array();

    ignore_user_abort(true);
    set_time_limit(0);

    // Estado del lado del cliente
    $estadoHash = getParameter('clientstatehash');
    if (!is_null($estadoHash)) {
        $estadoCliente = isset($_SESSION[$module_name]['estadoCliente'])
            ? $_SESSION[$module_name]['estadoCliente']
            : array();
    } else {
        $estadoCliente = getParameter('clientstate');
        if (!is_array($estadoCliente)) return;
    }

    // Modo a funcionar: Long-Polling, o Server-sent Events
    $sModoEventos = getParameter('serverevents');
    $bSSE = (!is_null($sModoEventos) && $sModoEventos);
    if ($bSSE) {
        Header('Content-Type: text/event-stream');
        printflush("retry: 5000\n");
    } else {
        Header('Content-Type: application/json');
    }

    // Verificar hash correcto
    if (!is_null($estadoHash) && $estadoHash != $_SESSION[$module_name]['estadoClienteHash']) {
        $respuesta['estadoClienteHash'] = 'mismatch';
        $respuesta['hashRecibido'] = $estadoHash;
        jsonflush($bSSE, $respuesta);
        return;
    }

    $oPaloConsola = new PaloSantoConsola();

    // Estado del lado del servidor
    $estadoCampania = $oPaloConsola->leerEstadoCampania($estadoCliente['campaigntype'], $estadoCliente['campaignid']);
    if (!is_array($estadoCampania)) {
        $respuesta['error'] = $oPaloConsola->errMsg;
        jsonflush($bSSE, $respuesta);
        $oPaloConsola->desconectarTodo();
        return;
    }

    // Acumular inmediatamente las filas que son distintas en estado
    $respuesta = crearRespuestaVacia();

    // Cuenta de estados
    foreach (array_keys($estadoCliente['statuscount']) as $k) {
        // Actualización de valores de contadores
    	if ($estadoCliente['statuscount'][$k] != $estadoCampania['statuscount'][$k]) {
    		$respuesta['statuscount']['update'][$k] = $estadoCampania['statuscount'][$k];
            $estadoCliente['statuscount'][$k] = $estadoCampania['statuscount'][$k];
    	}
    }

    // Estado de llamadas no conectadas
    foreach (array_keys($estadoCliente['activecalls']) as $k) {
    	// Llamadas que cambiaron de estado o ya no están sin agente
        if (!isset($estadoCampania['activecalls'][$k])) {
        	// Llamada ya no está esperando agente
            $respuesta['activecalls']['remove'][] = array('callid' => $estadoCliente['activecalls'][$k]['callid']);
            unset($estadoCliente['activecalls'][$k]);
        } elseif ($estadoCliente['activecalls'][$k]['callstatus'] != $estadoCampania['activecalls'][$k]['callstatus']) {
        	// Llamada ha cambiado de estado
            $respuesta['activecalls']['update'][] = formatoLlamadaNoConectada($estadoCampania['activecalls'][$k]);
            $estadoCliente['activecalls'][$k] = $estadoCampania['activecalls'][$k];
        }
    }
    foreach (array_keys($estadoCampania['activecalls']) as $k) {
    	// Llamadas nuevas
        if (!isset($estadoCliente['activecalls'][$k])) {
            $respuesta['activecalls']['add'][] = formatoLlamadaNoConectada($estadoCampania['activecalls'][$k]);
            $estadoCliente['activecalls'][$k] = $estadoCampania['activecalls'][$k];
        }
    }

    // Estado de agentes de campaña
    foreach (array_keys($estadoCliente['agents']) as $k) {
    	// Agentes que cambiaron de estado o desaparecieron (???)
        if (!isset($estadoCampania['agents'][$k])) {
        	// Agente ya no aparece (???)
            $respuesta['agents']['remove'][] = array('agent' => $estadoCliente['agents'][$k]['agentchannel']);
            unset($estadoCliente['agents'][$k]);
        } elseif ($estadoCliente['agents'][$k] != $estadoCampania['agents'][$k]) {
        	// Agente ha cambiado de estado
            $respuesta['agents']['update'][] = formatoAgente($estadoCampania['agents'][$k]);
            $estadoCliente['agents'][$k] = $estadoCampania['agents'][$k];
        }
    }
    foreach (array_keys($estadoCampania['agents']) as $k) {
        // Agentes nuevos (???)
        if (!isset($estadoCliente['agents'][$k])) {
            $respuesta['agents']['add'][] = formatoAgente($estadoCampania['agents'][$k]);
            $estadoCliente['agents'][$k] = $estadoCampania['agents'][$k];
        }
    }

    unset($estadoCampania);

    $oPaloConsola->escucharProgresoLlamada(TRUE);
    $iTimeoutPoll = $oPaloConsola->recomendarIntervaloEsperaAjax();
    do {
        $oPaloConsola->desconectarEspera();

        // Se inicia espera larga con el navegador...
        $iTimestampInicio = time();

        while (connection_status() == CONNECTION_NORMAL && esRespuestaVacia($respuesta)
            && time() - $iTimestampInicio <  $iTimeoutPoll) {

            session_commit();
            $listaEventos = $oPaloConsola->esperarEventoSesionActiva();
            if (is_null($listaEventos)) {
                $respuesta['error'] = $oPaloConsola->errMsg;
                jsonflush($bSSE, $respuesta);
                $oPaloConsola->desconectarTodo();
                return;
            }
            @session_start();

            /* Si el navegador elige otra campaña mientras se espera la primera
             * campaña, entonces esta espera es inválida, y el navegador ya ha
             * iniciado otra sesión comet. */
            if (isset($_SESSION[$module_name]) &&
                !($estadoCliente['campaigntype'] === $_SESSION[$module_name]['estadoCliente']['campaigntype'] &&
                 $estadoCliente['campaignid'] === $_SESSION[$module_name]['estadoCliente']['campaignid'])) {
                $respuesta['estadoClienteHash'] = 'invalidated';
                jsonflush($bSSE, $respuesta);
                $oPaloConsola->desconectarTodo();
                return;
            }

            $iTimestampActual = time();
            foreach ($listaEventos as $evento) {
                $sCanalAgente = isset($evento['agent_number']) ? $evento['agent_number'] : NULL;
                switch ($evento['event']) {
                case 'agentloggedin':
                    if (isset($estadoCliente['agents'][$sCanalAgente])) {
                    	/* Se ha logoneado agente que atiende a esta campaña.
                         * ATENCIÓN: sólo se setean suficientes campos para la
                         * visualización. Otros campos quedan con sus valores
                         * antiguos, si tenían */
                        $estadoCliente['agents'][$sCanalAgente]['status'] = 'online';
                        $estadoCliente['agents'][$sCanalAgente]['pauseinfo'] = NULL;
                        $estadoCliente['agents'][$sCanalAgente]['callinfo'] = NULL;

                        $respuesta['agents']['update'][] = formatoAgente($estadoCliente['agents'][$sCanalAgente]);
                    }
                    break;
                case 'agentloggedout':
                    if (isset($estadoCliente['agents'][$sCanalAgente])) {
                        /* Se ha deslogoneado agente que atiende a esta campaña.
                         * ATENCIÓN: sólo se setean suficientes campos para la
                         * visualización. Otros campos quedan con sus valores
                         * antiguos, si tenían */
                        $estadoCliente['agents'][$sCanalAgente]['status'] = 'offline';
                        $estadoCliente['agents'][$sCanalAgente]['pauseinfo'] = NULL;
                        $estadoCliente['agents'][$sCanalAgente]['callinfo'] = NULL;

                        $respuesta['agents']['update'][] = formatoAgente($estadoCliente['agents'][$sCanalAgente]);
                    }
                    break;
                case 'callprogress':
                    if (esEventoParaCampania($estadoCliente, $evento)) {
                    	// Llamada corresponde a cola monitoreada
                        $callid = $evento['call_id'];

                        // Para llamadas entrantes, cada llamada en cola aumenta el total
                        if ($evento['call_type'] == 'incoming' && $evento['new_status'] == 'OnQueue') {
                        	agregarContadorLlamada('total', $estadoCliente, $respuesta);
                        }

                        if (in_array($evento['new_status'], array('Failure', 'Abandoned', 'NoAnswer'))) {
                            if (isset($estadoCliente['activecalls'][$callid])) {
                                restarContadorLlamada($estadoCliente['activecalls'][$callid]['callstatus'], $estadoCliente, $respuesta);
                                agregarContadorLlamada($evento['new_status'], $estadoCliente, $respuesta);

                                // Quitar de las llamadas que esperan un agente
                                $respuesta['activecalls']['remove'][] = array('callid' => $callid);
                                unset($estadoCliente['activecalls'][$callid]);
                            }
                        } elseif (in_array($evento['new_status'], array('OnHold', 'OffHold'))) {
                        	// Se supone que una llamada en hold ya fue asignada a un agente
                        } else {
                            if (isset($estadoCliente['activecalls'][$callid])) {
                                restarContadorLlamada($estadoCliente['activecalls'][$callid]['callstatus'], $estadoCliente, $respuesta);

                                $estadoCliente['activecalls'][$callid]['callstatus'] = $evento['new_status'];
                                $estadoCliente['activecalls'][$callid]['trunk'] = $evento['trunk'];
                                if ($evento['new_status'] == 'OnQueue')
                                    $estadoCliente['activecalls'][$callid]['queuestart'] = $evento['datetime_entry'];
                                $respuesta['activecalls']['update'][] =
                                    formatoLlamadaNoConectada($estadoCliente['activecalls'][$callid]);
                            } else {
                            	// Valores sólo para satisfacer formato
                                $estadoCliente['activecalls'][$callid] = array(
                                    'callid'        =>  $callid,
                                    'callnumber'    =>  $evento['phone'],
                                    'callstatus'    =>  $evento['new_status'],
                                    'dialstart'     =>  $evento['datetime_entry'],
                                    'dialend'       =>  NULL,
                                    'queuestart'    =>  $evento['datetime_entry'],
                                    'trunk'         =>  $evento['trunk'],
                                );
                                $respuesta['activecalls']['add'][] =
                                    formatoLlamadaNoConectada($estadoCliente['activecalls'][$callid]);
                            }

                            agregarContadorLlamada($evento['new_status'], $estadoCliente, $respuesta);
                        }

                        $respuesta['log'][] = formatoLogCampania(array(
                            'id'                =>  $evento['id'],
                            'new_status'        =>  $evento['new_status'],
                            'datetime_entry'    =>  $evento['datetime_entry'],
                            'campaign_type'     =>  $evento['call_type'],
                            'campaign_id'       =>  $evento['campaign_id'],
                            'call_id'           =>  $evento['call_id'],
                            'retry'             =>  $evento['retry'],
                            'uniqueid'          =>  $evento['uniqueid'],
                            'trunk'             =>  $evento['trunk'],
                            'phone'             =>  $evento['phone'],
                            'queue'             =>  $evento['queue'],
                            'agentchannel'      =>  $sCanalAgente,
                            'duration'          =>  NULL,
                        ));
                    }
                    break;
                case 'pausestart':
                    if (isset($estadoCliente['agents'][$sCanalAgente])) {
                        switch ($evento['pause_class']) {
                        case 'break':
                            $estadoCliente['agents'][$sCanalAgente]['pauseinfo'] = array(
                                'pauseid'   =>  $evento['pause_type'],
                                'pausename' =>  $evento['pause_name'],
                                'pausestart'=>  $evento['pause_start'],
                            );
                            break;
                        case 'hold':
                            $estadoCliente['agents'][$sCanalAgente]['onhold'] = TRUE;
                            // TODO: desde cuándo empieza la pausa hold?
                            break;
                        // TODO: pausa de llamada agendada
                        }
                        if ($estadoCliente['agents'][$sCanalAgente]['status'] != 'oncall')
                            $estadoCliente['agents'][$sCanalAgente]['status'] = 'paused';
                        $respuesta['agents']['update'][] = formatoAgente($estadoCliente['agents'][$sCanalAgente]);
                    }
                    break;
                case 'pauseend':
                    if (isset($estadoCliente['agents'][$sCanalAgente])) {
                        switch ($evento['pause_class']) {
                        case 'break':
                            $estadoCliente['agents'][$sCanalAgente]['pauseinfo'] = NULL;
                            break;
                        case 'hold':
                            $estadoCliente['agents'][$sCanalAgente]['onhold'] = FALSE;
                            // TODO: anular inicio de pausa hold
                            break;
                        }
                        if ($estadoCliente['agents'][$sCanalAgente]['status'] != 'oncall') {
                            $estadoCliente['agents'][$sCanalAgente]['status'] = (
                                !is_null($estadoCliente['agents'][$sCanalAgente]['pauseinfo']) ||
                                $estadoCliente['agents'][$sCanalAgente]['onhold'])
                            ? 'paused' : 'online';
                        }

                        $respuesta['agents']['update'][] = formatoAgente($estadoCliente['agents'][$sCanalAgente]);
                    }
                    break;
                case 'agentlinked':
                    // Si la llamada estaba en lista activa, quitarla
                    $callid = $evento['call_id'];
                    if (isset($estadoCliente['activecalls'][$callid])) {
                        restarContadorLlamada($estadoCliente['activecalls'][$callid]['callstatus'], $estadoCliente, $respuesta);
                        $respuesta['activecalls']['remove'][] = array('callid' => $estadoCliente['activecalls'][$callid]['callid']);
                        unset($estadoCliente['activecalls'][$callid]);
                    }

                    // Si el agente es uno de los de la campaña, modificar
                    if (isset($estadoCliente['agents'][$sCanalAgente])) {
                        $estadoCliente['agents'][$sCanalAgente]['status'] = 'oncall';
                        $estadoCliente['agents'][$sCanalAgente]['callinfo'] = array(
                            'callnumber'    =>  $evento['phone'],
                            'linkstart'     =>  $evento['datetime_linkstart'],
                            'trunk'         =>  $evento['trunk'],
                            'callid'        =>  $evento['call_id'],

                            // Campos que (todavía) no se usan
                            'calltype'      =>  $evento['call_type'],
                            'campaign_id'   =>  $evento['campaign_id'],
                            'queuenumber'   =>  $evento['queue'],
                            'remote_channel'=>  $evento['remote_channel'],
                            'status'        =>  $evento['status'],
                            'queuestart'    =>  (is_null($evento['datetime_join']) || $evento['datetime_join'] == '') ? NULL : $evento['datetime_join'],
                            'dialstart'     =>  $evento['datetime_originate'],
                            'dialend'       =>  $evento['datetime_originateresponse'],
                        );

                        $respuesta['agents']['update'][] = formatoAgente($estadoCliente['agents'][$sCanalAgente]);
                    }

                    if (esEventoParaCampania($estadoCliente, $evento)) {
                        $respuesta['log'][] = formatoLogCampania(array(
                            'id'                =>  $evento['campaignlog_id'],
                            'new_status'        =>  'Success',
                            'datetime_entry'    =>  $evento['datetime_linkstart'],
                            'campaign_type'     =>  $evento['call_type'],
                            'campaign_id'       =>  $evento['campaign_id'],
                            'call_id'           =>  $evento['call_id'],
                            'retry'             =>  $evento['retries'],
                            'uniqueid'          =>  $evento['uniqueid'],
                            'trunk'             =>  $evento['trunk'],
                            'phone'             =>  $evento['phone'],
                            'queue'             =>  $evento['queue'],
                            'agentchannel'      =>  $sCanalAgente,
                            'duration'          =>  NULL,
                        ));

                        agregarContadorLlamada('Success', $estadoCliente, $respuesta);
                    }
                    break;
                case 'agentunlinked':
                    // Si el agente es uno de los de la campaña, modificar
                    if (isset($estadoCliente['agents'][$sCanalAgente])) {
                        /* Es posible que se reciba un evento agentunlinked luego
                         * del evento agentloggedout si el agente se desconecta con
                         * una llamada activa. */
                        if ($estadoCliente['agents'][$sCanalAgente]['status'] != 'offline') {
                            $estadoCliente['agents'][$sCanalAgente]['status'] = (
                                !is_null($estadoCliente['agents'][$sCanalAgente]['pauseinfo']) ||
                                $estadoCliente['agents'][$sCanalAgente]['onhold'])
                            ? 'paused' : 'online';
                        }
                        $estadoCliente['agents'][$sCanalAgente]['callinfo'] = NULL;

                        $respuesta['agents']['update'][] = formatoAgente($estadoCliente['agents'][$sCanalAgente]);
                    }

                    if (esEventoParaCampania($estadoCliente, $evento)) {
                        $respuesta['log'][] = formatoLogCampania(array(
                            'id'                =>  $evento['campaignlog_id'],
                            'new_status'        =>  $evento['shortcall'] ? 'ShortCall' : 'Hangup',
                            'datetime_entry'    =>  $evento['datetime_linkend'],
                            'campaign_type'     =>  $evento['call_type'],
                            'campaign_id'       =>  $evento['campaign_id'],
                            'call_id'           =>  $evento['call_id'],
                            'retry'             =>  NULL,
                            'uniqueid'          =>  NULL,
                            'trunk'             =>  NULL,
                            'phone'             =>  $evento['phone'],
                            'queue'             =>  NULL,
                            'agentchannel'      =>  $sCanalAgente,
                            'duration'          =>  $evento['duration'],
                        ));

                        if ($evento['call_type'] == 'incoming') {
                        	restarContadorLlamada('Success', $estadoCliente, $respuesta);
                            agregarContadorLlamada('Finished', $estadoCliente, $respuesta);
                            agregarContadorLlamada('Total', $estadoCliente, $respuesta);
                            $respuesta['duration'] = $evento['duration'];
                        } else {
                        	if ($evento['shortcall']) {
                        		restarContadorLlamada('Success', $estadoCliente, $respuesta);
                                agregarContadorLlamada('ShortCall', $estadoCliente, $respuesta);
                        	} else {
                        		// Se actualiza Finished para actualizar estadísticas
                                agregarContadorLlamada('Finished', $estadoCliente, $respuesta);
                                $respuesta['duration'] = $evento['duration'];
                        	}
                        }
                        if (isset($respuesta['duration'])) {
                        	$estadoCliente['stats']['total_sec'] += $respuesta['duration'];
                            if ($estadoCliente['stats']['max_duration'] < $respuesta['duration'])
                                $estadoCliente['stats']['max_duration'] = $respuesta['duration'];
                        }
                    }
                    break;
                case 'queuemembership':
                    if (in_array($estadoCliente['queue'], $evento['queues']) &&
                        !isset($estadoCliente['agents'][$sCanalAgente])) {
                        // Este nuevo agente acaba de ingresar a la cola de campañas
                        $estadoCliente['agents'][$sCanalAgente] = array_merge(
                            array('agentchannel' => $sCanalAgente), $evento);
                        unset($estadoCliente['agents'][$sCanalAgente]['queues']);

                        $respuesta['agents']['add'][] = formatoAgente($estadoCliente['agents'][$sCanalAgente]);
                    } elseif (!in_array($estadoCliente['queue'], $evento['queues']) &&
                        isset($estadoCliente['agents'][$sCanalAgente])) {

                        // El agente mencionado deja de pertenecer a la cola de campañas
                        $respuesta['agents']['remove'][] = array('agent' => $estadoCliente['agents'][$sCanalAgente]['agentchannel']);
                        unset($estadoCliente['agents'][$sCanalAgente]);
                    }
                    break;
                }
            }
        }

        $estadoHash = generarEstadoHash($module_name, $estadoCliente);
        $respuesta['estadoClienteHash'] = $estadoHash;
        jsonflush($bSSE, $respuesta);

        $respuesta = crearRespuestaVacia();

    } while ($bSSE && connection_status() == CONNECTION_NORMAL);
    $oPaloConsola->desconectarTodo();
}

function esEventoParaCampania(&$estadoCliente, &$evento)
{
    return ($estadoCliente['campaigntype'] == 'incomingqueue')
        ? ( $estadoCliente['campaignid'] == $evento['queue'] &&
            is_null($evento['campaign_id']))
        : ( $estadoCliente['campaignid'] == $evento['campaign_id'] &&
            $estadoCliente['campaigntype'] == $evento['call_type']);
}

function crearRespuestaVacia()
{
    return array(
        'statuscount'   =>  array('update' => array()),
        'activecalls'   =>  array('add' => array(), 'update' => array(), 'remove' => array()),
        'agents'        =>  array('add' => array(), 'update' => array(), 'remove' => array()),
        'log'           =>  array(),
    );
}

function esRespuestaVacia(&$respuesta)
{
	return count($respuesta['statuscount']['update']) == 0
        && count($respuesta['activecalls']['add']) == 0
        && count($respuesta['activecalls']['update']) == 0
        && count($respuesta['activecalls']['remove']) == 0
        && count($respuesta['agents']['add']) == 0
        && count($respuesta['agents']['update']) == 0
        && count($respuesta['agents']['remove']) == 0
        && count($respuesta['log']) == 0;
}

// Restar del contador Placing/Dialing/Ringing/OnQueue según corresponda
function restarContadorLlamada($old_status, &$estadoCliente, &$respuesta)
{
    $k = strtolower($old_status);
    if ($k == 'dialing') $k = 'placing';
    if (isset($estadoCliente['statuscount'][$k]) && $estadoCliente['statuscount'][$k] > 0) {
        $estadoCliente['statuscount'][$k]--;
        $respuesta['statuscount']['update'][$k] = $estadoCliente['statuscount'][$k];
    }
}

// Agregar al contador correspondiente de progreso
function agregarContadorLlamada($new_status, &$estadoCliente, &$respuesta)
{
    $k = strtolower($new_status);
    if ($k == 'dialing') $k = 'placing';
    if (isset($estadoCliente['statuscount'][$k])) {
        $estadoCliente['statuscount'][$k]++;
        $respuesta['statuscount']['update'][$k] = $estadoCliente['statuscount'][$k];
    }
}

function formatoLlamadaNoConectada($activecall)
{
    $sFechaHoy = date('Y-m-d');
    $sDesde = (!is_null($activecall['queuestart']))
        ? $activecall['queuestart'] : $activecall['dialstart'];
    if (strpos($sDesde, $sFechaHoy) === 0)
        $sDesde = substr($sDesde, strlen($sFechaHoy) + 1);
    $sEstado = ($activecall['callstatus'] == 'placing' && !is_null($activecall['trunk']))
        ? _tr('dialing') : _tr($activecall['callstatus']);
    return array(
        'callid'        =>  $activecall['callid'],
        'callnumber'    =>  $activecall['callnumber'],
        'trunk'         =>  $activecall['trunk'],
        'callstatus'    =>  $sEstado,
        'desde'         =>  $sDesde,
    );
}

function formatoAgente($agent)
{
    $sEtiquetaStatus = _tr($agent['status']);
    $sFechaHoy = date('Y-m-d');
    $sDesde = '-';
    switch ($agent['status']) {
    case 'paused':
        // Prioridad de pausa: hold, break, agendada
        if ($agent['onhold']) {
            $sEtiquetaStatus = _tr('Hold');
            // TODO: desde cuándo está en hold?
        } elseif (!is_null($agent['pauseinfo'])) {
            $sDesde = $agent['pauseinfo']['pausestart'];
            $sEtiquetaStatus .= ': '.$agent['pauseinfo']['pausename'];
        }
        // TODO: exponer pausa de agendamiento
        break;
    case 'oncall':
        $sDesde = $agent['callinfo']['linkstart'];
        break;
    }
    if (strpos($sDesde, $sFechaHoy) === 0)
        $sDesde = substr($sDesde, strlen($sFechaHoy) + 1);
    return array(
        'agent'         =>  $agent['agentchannel'],
        'status'        =>  $sEtiquetaStatus,
        'callnumber'    =>  is_null($agent['callinfo']['callnumber']) ? '-' : $agent['callinfo']['callnumber'],
        'trunk'         =>  is_null($agent['callinfo']['trunk']) ? '-' : $agent['callinfo']['trunk'],
        'desde'         =>  $sDesde,
    );
}

function formatoLogCampania($entradaLog)
{
    $listaMsg = array(
        'Placing'   =>  _tr('LOG_FMT_PLACING'),
        'Dialing'   =>  _tr('LOG_FMT_DIALING'),
        'Ringing'   =>  _tr('LOG_FMT_RINGING'),
        'OnQueue'   =>  _tr('LOG_FMT_ONQUEUE'),
        'Success'   =>  _tr('LOG_FMT_SUCCESS'),
        'Hangup'    =>  _tr('LOG_FMT_HANGUP'),
        'OnHold'    =>  _tr('LOG_FMT_ONHOLD'),
        'OffHold'   =>  _tr('LOG_FMT_OFFHOLD'),
        'Failure'   =>  _tr('LOG_FMT_FAILURE'),
        'Abandoned' =>  _tr('LOG_FMT_ABANDONED'),
        'ShortCall' =>  _tr('LOG_FMT_SHORTCALL'),
        'NoAnswer'  =>  _tr('LOG_FMT_NOANSWER'),
    );
    $sMensaje = $listaMsg[$entradaLog['new_status']];
    foreach ($entradaLog as $k => $v) {
        if ($k == 'duration') $v = sprintf('%02d:%02d:%02d',
                ($v - ($v % 3600)) / 3600,
                (($v - ($v % 60)) / 60) % 60,
                $v % 60);
        $sMensaje = str_replace('{'.$k.'}', $v, $sMensaje);
    }

    return array(
        'id'        =>  $entradaLog['id'],
        'timestamp' =>  $entradaLog['datetime_entry'],
        'mensaje'   =>  $sMensaje,
    );
}

function modificarReferenciasLibreriasJS($smarty)
{
    $listaLibsJS_framework = explode("\n", $smarty->get_template_vars('HEADER_LIBS_JQUERY'));
    $listaLibsJS_modulo = explode("\n", $smarty->get_template_vars('HEADER_MODULES'));

    /* Las referencias a Ember.js y Handlebars se reordenan para que Handlebars
     * aparezca antes que Ember.js.
     */
    $sEmberRef = $sHandleBarsRef = NULL;
    foreach (array_keys($listaLibsJS_modulo) as $k) {
    	if (strpos($listaLibsJS_modulo[$k], 'themes/default/js/handlebars-') !== FALSE) {
            $sHandleBarsRef = $listaLibsJS_modulo[$k];
            unset($listaLibsJS_modulo[$k]);
        } elseif (strpos($listaLibsJS_modulo[$k], 'themes/default/js/ember-') !== FALSE) {
            $sEmberRef = $listaLibsJS_modulo[$k];
            unset($listaLibsJS_modulo[$k]);
        }
    }
    array_unshift($listaLibsJS_modulo, $sEmberRef);
    array_unshift($listaLibsJS_modulo, $sHandleBarsRef);
    $smarty->assign('HEADER_MODULES', implode("\n", $listaLibsJS_modulo));
    $smarty->assign('HEADER_LIBS_JQUERY', implode("\n", $listaLibsJS_framework));
}

function jsonflush($bSSE, $respuesta)
{
    $json = new Services_JSON();
    $r = $json->encode($respuesta);
    if ($bSSE)
        printflush("data: $r\n\n");
    else printflush($r);
}

function printflush($s)
{
    print $s;
    ob_flush();
    flush();
}

function generarEstadoHash($module_name, $estadoCliente)
{
    $estadoHash = md5(serialize($estadoCliente));
    $_SESSION[$module_name]['estadoCliente'] = $estadoCliente;
    $_SESSION[$module_name]['estadoClienteHash'] = $estadoHash;

    return $estadoHash;
}

?>
