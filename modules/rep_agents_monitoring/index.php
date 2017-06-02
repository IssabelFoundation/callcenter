<?php
  /* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Elastix version 1.5.2-3.1                                               |
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
  $Id: index.php,v 1.1.1.1 2009/07/27 09:10:19 dlopez Exp $ */

require_once 'libs/paloSantoGrid.class.php';

function _moduleContent(&$smarty, $module_name)
{
    global $arrConf;
    global $arrLang;

    require_once "modules/agent_console/libs/elastix2.lib.php";
    require_once "modules/agent_console/libs/paloSantoConsola.class.php";
    require_once "modules/agent_console/libs/JSON.php";
    require_once "modules/$module_name/configs/default.conf.php";

    // Directorio de este módulo
    $sDirScript = dirname($_SERVER['SCRIPT_FILENAME']);

    // Se fusiona la configuración del módulo con la configuración global
    $arrConf = array_merge($arrConf, $arrConfModule);

    /* Se pide el archivo de inglés, que se elige a menos que el sistema indique
       otro idioma a usar. Así se dispone al menos de la traducción al inglés
       si el idioma elegido carece de la cadena.
     */
    load_language_module($module_name);

    // Asignación de variables comunes y directorios de plantillas
    $sDirPlantillas = (isset($arrConf['templates_dir']))
        ? $arrConf['templates_dir'] : 'themes';
    $sDirLocalPlantillas = "$sDirScript/modules/$module_name/".$sDirPlantillas.'/'.$arrConf['theme'];
    $smarty->assign("MODULE_NAME", $module_name);

    $sAction = '';
    $sContenido = '';

    $sAction = getParameter('action');
    if (!in_array($sAction, array('', 'checkStatus')))
        $sAction = '';

    $oPaloConsola = new PaloSantoConsola();
    switch ($sAction) {
    case 'checkStatus':
        $sContenido = manejarMonitoreo_checkStatus($module_name, $smarty, $sDirLocalPlantillas, $oPaloConsola);
        break;
    case '':
    default:
        $sContenido = manejarMonitoreo_HTML($module_name, $smarty, $sDirLocalPlantillas, $oPaloConsola);
        break;
    }
    $oPaloConsola->desconectarTodo();

    return $sContenido;
}

function manejarMonitoreo_HTML($module_name, $smarty, $sDirLocalPlantillas, $oPaloConsola)
{
    global $arrLang;

    $smarty->assign(array(
        'FRAMEWORK_TIENE_TITULO_MODULO' => existeSoporteTituloFramework(),
        'icon'                          => 'modules/'.$module_name.'/images/call.png',
        'title'                         =>  _tr('Agent Monitoring'),
    ));

    /*
     * Un agente puede pertenecer a múltiples colas, y puede o no estar
     * atendiendo una llamada, la cual puede haber llegado de como máximo una
     * cola. Hay 3 cronómetros que se pueden actualizar:
     *
     * último estado:   el tiempo transcurrido desde el último cambio de estado
     * total de login:  el tiempo durante el cual el agente ha estado logoneado
     * total de llamadas: el tiempo que el agente pasa atendiendo llamadas
     *
     * Para el monitoreo de este módulo, los estados en que puede estar
     * una fila (que muestra un agente en una cola) pueden ser los siguientes:
     *
     * offline: el tiempo total de login y el tiempo de llamadas no se
     *  actualizan. Si el cliente estuvo en otro estado previamente
     *  (lastsessionend) entonces se actualiza regularmente el cronómetro de
     *  último estado. De otro modo el cronómetro de último estado está vacío.
     * online: se actualiza el tiempo total de login y el tiempo de último
     *  estado, y el tiempo total de llamadas no se actualiza. El cronómetro de
     *  último estado cuenta desde el inicio de sesión.
     * paused: igual que online, pero el cronómentro de último estado cuenta
     *  desde el inicio de la pausa.
     * oncall: se actualiza el tiempo total de login. El cronómetro de último
     *  estado cuenta desde el inicio de la llamada únicamente para la cola que
     *  proporcionó la llamada que atiende el agente actualmente. De otro modo
     *  el cronómetro no se actualiza. De manera similar, el total de tiempo de
     *  llamadas se actualiza únicamente para la cola que haya proporcionado la
     *  llamada que atiende el agente.
     *
     * El estado del cliente consiste en un arreglo de tantos elementos como
     * agentes haya pertenecientes a cada cola. Si un agente pertenece a más de
     * una cola, hay un elemento por cada pertenencia del mismo agente a cada
     * cola. Cada elemento es una estructura que contiene los siguientes
     * valores:
     *
     * status:          {offline|online|oncall|paused}
     * sec_laststatus:  integer|null
     * sec_calls:       integer
     * logintime:       integer
     * num_calls:       integer
     * oncallupdate:    boolean
     *
     * Cada elemento del arreglo se posiciona por 'queue-{NUM_COLA}-member-{NUM_AGENTE}'
     *
     * El estado enviado por el cliente para detectar cambios es también un
     * arreglo con el mismo número de elementos que el arreglo anterior,
     * posicionado de la misma manera. Cada elemento es una estructura que
     * contiene los siguientes valores:
     *
     * status:          {offline|online|oncall|paused}
     * oncallupdate:    boolean
     */
    $estadoMonitor = $oPaloConsola->listarEstadoMonitoreoAgentes();
    if (!is_array($estadoMonitor)) {
        $smarty->assign(array(
            'mb_title'  =>  'ERROR',
            'mb_message'    =>  $oPaloConsola->errMsg,
        ));
        return '';
    }
    ksort($estadoMonitor);

    $jsonData = construirDatosJSON($estadoMonitor);

    $arrData = array();
    $tuplaTotal = NULL;
    $sPrevQueue = NULL;
    foreach ($jsonData as $jsonKey => $jsonRow) {
        list($d1, $sQueue, $d2, $sTipoAgente, $sNumeroAgente) = explode('-', $jsonKey);

        $sEstadoTag = '(unimplemented)';
        switch ($jsonRow['status']) {
        case 'offline':
            $sEstadoTag = _tr('LOGOUT');
            break;
        case 'online':
            $sEstadoTag = '<img src="modules/'.$module_name.'/images/ready.png" border="0" alt="'._tr('READY').'"/>';
            break;
        case 'oncall':
            $sEstadoTag = '<img src="modules/'.$module_name.'/images/call.png" border="0" alt="'._tr('CALL').'"/>';
            break;
        case 'paused':
            $sEstadoTag = '<img src="modules/'.$module_name.'/images/break.png" border="0" alt="'._tr('BREAK').'"/>';
            if (!is_null($jsonRow['pausename']))
                $sEstadoTag .= '<span>'.htmlentities($jsonRow['pausename'], ENT_COMPAT, 'UTF-8').'</span>';
            break;
        }
        $sEstadoTag = '<span id="'.$jsonKey.'-statuslabel">'.$sEstadoTag.'</span>';
        $sEstadoTag .= '&nbsp;<span id="'.$jsonKey.'-sec_laststatus">';
        if (!is_null($jsonRow['sec_laststatus'])) {
        	$sEstadoTag .= timestamp_format($jsonRow['sec_laststatus']);
        }
        $sEstadoTag .= '</span>';

        // Estado a mostrar en HTML se deriva del estado JSON
        if ($sPrevQueue != $sQueue) {
            if (!is_null($tuplaTotal)) {
            	// Emitir fila de totales para la cola ANTERIOR
                $jsTotalKey = 'queue-'.$sPrevQueue;
                $arrData[] = array(
                    '<b>'._tr('TOTAL').'</b>',
                    '&nbsp;',
                    '<b>'._tr('Agents').': '.$tuplaTotal['num_agents'].'</b>',
                    '&nbsp;',
                    '<b><span id="'.$jsTotalKey.'-num_calls">'.$tuplaTotal['num_calls'].'</span></b>',
                    '<b><span id="'.$jsTotalKey.'-logintime">'.timestamp_format($tuplaTotal['logintime']).'</span></b>',
                    '<b><span id="'.$jsTotalKey.'-sec_calls">'.timestamp_format($tuplaTotal['sec_calls']).'</span></b>',
                );
            }

            // Reiniciar totales aquí
            $tuplaTotal = array(
                'num_agents'    =>  0,
                'logintime'     =>  0,
                'num_calls'     =>  0,
                'sec_calls'     =>  0,
            );
        }
        $tuplaTotal['num_agents']++;
        $tuplaTotal['logintime'] += $jsonRow['logintime'];
        $tuplaTotal['num_calls'] += $jsonRow['num_calls'];
        $tuplaTotal['sec_calls'] += $jsonRow['sec_calls'];
        $tupla = array(
            ($sPrevQueue == $sQueue) ? '' : $sQueue,
            $jsonRow['agentchannel'],
            htmlentities($jsonRow['agentname'], ENT_COMPAT, 'UTF-8'),
            $sEstadoTag,
            '<span id="'.$jsonKey.'-num_calls">'.$jsonRow['num_calls'].'</span>',
            '<span id="'.$jsonKey.'-logintime">'.timestamp_format($jsonRow['logintime']).'</span>',
            '<span id="'.$jsonKey.'-sec_calls">'.timestamp_format($jsonRow['sec_calls']).'</span>',
        );
        $arrData[] = $tupla;
        $sPrevQueue = $sQueue;
    }
    // Emitir fila de totales para la cola ÚLTIMA
    $jsTotalKey = 'queue-'.$sPrevQueue;
    $arrData[] = array(
        '<b>'._tr('TOTAL').'</b>',
        '&nbsp;',
        '<b>'._tr('Agents').': '.$tuplaTotal['num_agents'].'</b>',
        '&nbsp;',
        '<b><span id="'.$jsTotalKey.'-num_calls">'.$tuplaTotal['num_calls'].'</span></b>',
        '<b><span id="'.$jsTotalKey.'-logintime">'.timestamp_format($tuplaTotal['logintime']).'</span></b>',
        '<b><span id="'.$jsTotalKey.'-sec_calls">'.timestamp_format($tuplaTotal['sec_calls']).'</span></b>',
    );

    // No es necesario emitir el nombre del agente la inicialización JSON
    foreach (array_keys($jsonData) as $k) unset($jsonData[$k]['agentname']);

    // Extraer la información que el navegador va a usar para actualizar
    $estadoCliente = array();
    foreach (array_keys($jsonData) as $k) {
        $estadoCliente[$k] = array(
            'status'        =>  $jsonData[$k]['status'],
            'oncallupdate'  =>  $jsonData[$k]['oncallupdate'],
        );
    }
    $estadoHash = generarEstadoHash($module_name, $estadoCliente);

    $oGrid  = new paloSantoGrid($smarty);
    $oGrid->pagingShow(FALSE);
    $json = new Services_JSON();
    $INITIAL_CLIENT_STATE = $json->encode($jsonData);
    $sJsonInitialize = <<<JSON_INITIALIZE
<script type="text/javascript">
$(function() {
    initialize_client_state($INITIAL_CLIENT_STATE, '$estadoHash');
});
</script>
JSON_INITIALIZE;
    return $oGrid->fetchGrid(array(
            'title'     =>  _tr('Agents Monitoring'),
            'icon'      =>  _tr('images/list.png'),
            'width'     =>  '99%',
            'start'     =>  1,
            'end'       =>  1,
            'total'     =>  1,
            'url'       =>  array('menu' => $module_name),
            'columns'   =>  array(
                array('name'    =>  _tr('Queue')),
                array('name'    =>  _tr('Number')),
                array('name'    =>  _tr('Agent')),
                array('name'    =>  _tr('Current status')),
                array('name'    =>  _tr('Total calls')),
                array('name'    =>  _tr('Total login time')),
                array('name'    =>  _tr('Total talk time')),
            ),
        ), $arrData, $arrLang).
        $sJsonInitialize;
}

function generarEstadoHash($module_name, $estadoCliente)
{
    $estadoHash = md5(serialize($estadoCliente));
    $_SESSION[$module_name]['estadoCliente'] = $estadoCliente;
    $_SESSION[$module_name]['estadoClienteHash'] = $estadoHash;

    return $estadoHash;
}

function timestamp_format($i)
{
	return sprintf('%02d:%02d:%02d',
        ($i - ($i % 3600)) / 3600,
        (($i - ($i % 60)) / 60) % 60,
        $i % 60);
}

function construirDatosJSON(&$estadoMonitor)
{
    $iTimestampActual = time();
    $jsonData = array();
    foreach ($estadoMonitor as $sQueue => $agentList) {
        ksort($agentList);
        foreach ($agentList as $sAgentChannel => $infoAgente) {
            $iTimestampEstado = NULL;
            $jsonKey = 'queue-'.$sQueue.'-member-'.strtolower(str_replace('/', '-', $sAgentChannel));

            switch ($infoAgente['agentstatus']) {
            case 'offline':
                if (!is_null($infoAgente['lastsessionend']))
                    $iTimestampEstado = strtotime($infoAgente['lastsessionend']);
                break;
            case 'online':
                if (!is_null($infoAgente['lastsessionstart']))
                    $iTimestampEstado = strtotime($infoAgente['lastsessionstart']);
                break;
            case 'oncall':
                if (!is_null($infoAgente['linkstart']))
                    $iTimestampEstado = strtotime($infoAgente['linkstart']);
                break;
            case 'paused':
                if (!is_null($infoAgente['lastpausestart']))
                    $iTimestampEstado = strtotime($infoAgente['lastpausestart']);
                break;
            }

            // Preparar estado inicial JSON
            $jsonData[$jsonKey] = array(
                'agentchannel'      =>  $sAgentChannel,
                'agentname'         =>  $infoAgente['agentname'],
                'status'            =>  $infoAgente['agentstatus'],
                'sec_laststatus'    =>  is_null($iTimestampEstado) ? NULL : ($iTimestampActual - $iTimestampEstado),
                'sec_calls'         =>  $infoAgente['sec_calls'] +
                    (is_null($infoAgente['linkstart'])
                        ? 0
                        : $iTimestampActual - strtotime($infoAgente['linkstart'])),
                'logintime'         =>  $infoAgente['logintime'] + (
                    (is_null($infoAgente['lastsessionend']) && !is_null($infoAgente['lastsessionstart']))
                        ? $iTimestampActual - strtotime($infoAgente['lastsessionstart'])
                        : 0),
                'num_calls'         =>  $infoAgente['num_calls'],
                'oncallupdate'      =>  !is_null($infoAgente['linkstart']),
                'pausename'         =>  $infoAgente['pausename'],
            );
        }
    }
    return $jsonData;
}

function manejarMonitoreo_checkStatus($module_name, $smarty, $sDirLocalPlantillas, $oPaloConsola)
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
    foreach (array_keys($estadoCliente) as $k)
        $estadoCliente[$k]['oncallupdate'] = ($estadoCliente[$k]['oncallupdate'] == 'true');

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
        jsonflush($bSSE, $respuesta);
        $oPaloConsola->desconectarTodo();
        return;
    }

    // Estado del lado del servidor
    $estadoMonitor = $oPaloConsola->listarEstadoMonitoreoAgentes();
    if (!is_array($estadoMonitor)) {
        $respuesta['error'] = $oPaloConsola->errMsg;
        jsonflush($bSSE, $respuesta);
    	$oPaloConsola->desconectarTodo();
        return;
    }

    // Acumular inmediatamente las filas que son distintas en estado
    ksort($estadoMonitor);
    $jsonData = construirDatosJSON($estadoMonitor);
    foreach ($jsonData as $jsonKey => $jsonRow) {
    	if (isset($estadoCliente[$jsonKey])) {
    		if ($estadoCliente[$jsonKey]['status'] != $jsonRow['status'] ||
                $estadoCliente[$jsonKey]['oncallupdate'] != $jsonRow['oncallupdate']) {
                $respuesta[$jsonKey] = $jsonRow;
                $estadoCliente[$jsonKey]['status'] = $jsonRow['status'];
                $estadoCliente[$jsonKey]['oncallupdate'] = $jsonRow['oncallupdate'];
                unset($respuesta[$jsonKey]['agentname']);
            }
    	}
    }

    $iTimeoutPoll = $oPaloConsola->recomendarIntervaloEsperaAjax();
    do {
        $oPaloConsola->desconectarEspera();

        // Se inicia espera larga con el navegador...
        session_commit();
        $iTimestampInicio = time();

        while (connection_status() == CONNECTION_NORMAL && count($respuesta) <= 0
            && time() - $iTimestampInicio <  $iTimeoutPoll) {

            $listaEventos = $oPaloConsola->esperarEventoSesionActiva();
            if (is_null($listaEventos)) {
                $respuesta['error'] = $oPaloConsola->errMsg;
                jsonflush($bSSE, $respuesta);
                $oPaloConsola->desconectarTodo();
                return;
            }

            $iTimestampActual = time();
            foreach ($listaEventos as $evento) {
                $sNumeroAgente = $sCanalAgente = $evento['agent_number'];
                $sNumeroAgente = strtolower(str_replace('/', '-', $sCanalAgente));

            	switch ($evento['event']) {
            	case 'agentloggedin':
                    foreach (array_keys($estadoMonitor) as $sQueue) {
                        if (isset($estadoMonitor[$sQueue][$sCanalAgente])) {
                        	$jsonKey = 'queue-'.$sQueue.'-member-'.$sNumeroAgente;
                            if (isset($jsonData[$jsonKey]) && $jsonData[$jsonKey]['status'] == 'offline') {

                                // Estado en el estado de monitor
                                $estadoMonitor[$sQueue][$sCanalAgente]['agentstatus'] = 'online';
                                $estadoMonitor[$sQueue][$sCanalAgente]['lastsessionstart'] = date('Y-m-d H:i:s', $iTimestampActual);
                                $estadoMonitor[$sQueue][$sCanalAgente]['lastsessionend'] = NULL;
                                if (!is_null($estadoMonitor[$sQueue][$sCanalAgente]['lastpausestart']) &&
                                    is_null($estadoMonitor[$sQueue][$sCanalAgente]['lastpauseend'])) {
                                	$estadoMonitor[$sQueue][$sCanalAgente]['lastpauseend'] = date('Y-m-d H:i:s', $iTimestampActual);
                                }
                                $estadoMonitor[$sQueue][$sCanalAgente]['linkstart'] = NULL;

                                // Estado en la estructura JSON
                                $jsonData[$jsonKey]['status'] = $estadoMonitor[$sQueue][$sCanalAgente]['agentstatus'];
                                $jsonData[$jsonKey]['sec_laststatus'] = 0;
                                $jsonData[$jsonKey]['oncallupdate'] = FALSE;

                                // Estado del cliente
                                $estadoCliente[$jsonKey]['status'] = $jsonData[$jsonKey]['status'];
                                $estadoCliente[$jsonKey]['oncallupdate'] = $jsonData[$jsonKey]['oncallupdate'];

                                // Estado a emitir al cliente
                                $respuesta[$jsonKey] = $jsonData[$jsonKey];
                                unset($respuesta[$jsonKey]['agentname']);
                            }
                        }
                    }
                    break;
                case 'agentloggedout':
                    foreach (array_keys($estadoMonitor) as $sQueue) {
                        if (isset($estadoMonitor[$sQueue][$sCanalAgente])) {
                            $jsonKey = 'queue-'.$sQueue.'-member-'.$sNumeroAgente;
                            if (isset($jsonData[$jsonKey]) && $jsonData[$jsonKey]['status'] != 'offline') {

                                // Estado en el estado de monitor
                                $estadoMonitor[$sQueue][$sCanalAgente]['agentstatus'] = 'offline';
                                $estadoMonitor[$sQueue][$sCanalAgente]['lastsessionend'] = date('Y-m-d H:i:s', $iTimestampActual);
                                if (!is_null($estadoMonitor[$sQueue][$sCanalAgente]['lastpausestart']) &&
                                    is_null($estadoMonitor[$sQueue][$sCanalAgente]['lastpauseend'])) {
                                    $estadoMonitor[$sQueue][$sCanalAgente]['lastpauseend'] = date('Y-m-d H:i:s', $iTimestampActual);
                                }
                                $estadoMonitor[$sQueue][$sCanalAgente]['linkstart'] = NULL;
                                if (!is_null($estadoMonitor[$sQueue][$sCanalAgente]['lastsessionstart'])) {
                                    $iTimestampInicio = strtotime($estadoMonitor[$sQueue][$sCanalAgente]['lastsessionstart']);
                                    $iDuracionSesion =  $iTimestampActual - $iTimestampInicio;
                                    if ($iDuracionSesion >= 0) {
                                    	$estadoMonitor[$sQueue][$sCanalAgente]['logintime'] += $iDuracionSesion;
                                    }
                                }

                                // Estado en la estructura JSON
                                $jsonData[$jsonKey]['status'] = $estadoMonitor[$sQueue][$sCanalAgente]['agentstatus'];
                                $jsonData[$jsonKey]['sec_laststatus'] = 0;
                                $jsonData[$jsonKey]['oncallupdate'] = FALSE;
                                $jsonData[$jsonKey]['logintime'] = $estadoMonitor[$sQueue][$sCanalAgente]['logintime'];

                                // Estado del cliente
                                $estadoCliente[$jsonKey]['status'] = $jsonData[$jsonKey]['status'];
                                $estadoCliente[$jsonKey]['oncallupdate'] = $jsonData[$jsonKey]['oncallupdate'];

                                // Estado a emitir al cliente
                                $respuesta[$jsonKey] = $jsonData[$jsonKey];
                                unset($respuesta[$jsonKey]['agentname']);
                            }
                        }
                    }
                    break;
                case 'pausestart':
                    foreach (array_keys($estadoMonitor) as $sQueue) {
                        if (isset($estadoMonitor[$sQueue][$sCanalAgente])) {
                            $jsonKey = 'queue-'.$sQueue.'-member-'.$sNumeroAgente;
                            if (isset($jsonData[$jsonKey]) && $jsonData[$jsonKey]['status'] != 'offline') {

                                // Estado en el estado de monitor
                                if ($estadoMonitor[$sQueue][$sCanalAgente]['agentstatus'] != 'oncall')
                                    $estadoMonitor[$sQueue][$sCanalAgente]['agentstatus'] = 'paused';
                                $estadoMonitor[$sQueue][$sCanalAgente]['lastpausestart'] = date('Y-m-d H:i:s', $iTimestampActual);
                                $estadoMonitor[$sQueue][$sCanalAgente]['lastpauseend'] = NULL;

                                // Estado en la estructura JSON
                                $jsonData[$jsonKey]['status'] = $estadoMonitor[$sQueue][$sCanalAgente]['agentstatus'];
                                if ($jsonData[$jsonKey]['status'] == 'oncall') {
                                    if (!is_null($estadoMonitor[$sQueue][$sCanalAgente]['linkstart'])) {
                                        $iTimestampInicio = strtotime($estadoMonitor[$sQueue][$sCanalAgente]['linkstart']);
                                        $iDuracionLlamada = $iTimestampActual - $iTimestampInicio;
                                        if ($iDuracionLlamada >= 0) {
                                            $jsonData[$jsonKey]['sec_laststatus'] = $iDuracionLlamada;
                                            $jsonData[$jsonKey]['sec_calls'] =
                                                $estadoMonitor[$sQueue][$sCanalAgente]['sec_calls'] + $iDuracionLlamada;
                                        }
                                    }
                                } else {
                                    $jsonData[$jsonKey]['sec_laststatus'] = 0;
                                }
                                $jsonData[$jsonKey]['logintime'] = $estadoMonitor[$sQueue][$sCanalAgente]['logintime'];
                                if (!is_null($estadoMonitor[$sQueue][$sCanalAgente]['lastsessionstart'])) {
                                    $iTimestampInicio = strtotime($estadoMonitor[$sQueue][$sCanalAgente]['lastsessionstart']);
                                    $iDuracionSesion =  $iTimestampActual - $iTimestampInicio;
                                    if ($iDuracionSesion >= 0) {
                                        $jsonData[$jsonKey]['logintime'] += $iDuracionSesion;
                                    }
                                }

                                // Estado del cliente
                                $estadoCliente[$jsonKey]['status'] = $jsonData[$jsonKey]['status'];
                                $estadoCliente[$jsonKey]['oncallupdate'] = $jsonData[$jsonKey]['oncallupdate'];

                                // Nombre de la pausa
                                $jsonData[$jsonKey]['pausename'] = $evento['pause_name'];

                                // Estado a emitir al cliente
                                $respuesta[$jsonKey] = $jsonData[$jsonKey];
                                unset($respuesta[$jsonKey]['agentname']);
                            }
                        }
                    }
                    break;
                case 'pauseend':
                    foreach (array_keys($estadoMonitor) as $sQueue) {
                        if (isset($estadoMonitor[$sQueue][$sCanalAgente])) {
                            $jsonKey = 'queue-'.$sQueue.'-member-'.$sNumeroAgente;
                            if (isset($jsonData[$jsonKey]) && $jsonData[$jsonKey]['status'] != 'offline') {

                                // Estado en el estado de monitor
                                if ($estadoMonitor[$sQueue][$sCanalAgente]['agentstatus'] != 'oncall')
                                    $estadoMonitor[$sQueue][$sCanalAgente]['agentstatus'] = 'online';
                                $estadoMonitor[$sQueue][$sCanalAgente]['lastpauseend'] = date('Y-m-d H:i:s', $iTimestampActual);

                                // Estado en la estructura JSON
                                $jsonData[$jsonKey]['status'] = $estadoMonitor[$sQueue][$sCanalAgente]['agentstatus'];
                                if ($jsonData[$jsonKey]['status'] == 'oncall') {
                                    if (!is_null($estadoMonitor[$sQueue][$sCanalAgente]['linkstart'])) {
                                        $iTimestampInicio = strtotime($estadoMonitor[$sQueue][$sCanalAgente]['linkstart']);
                                        $iDuracionLlamada = $iTimestampActual - $iTimestampInicio;
                                        if ($iDuracionLlamada >= 0) {
                                            $jsonData[$jsonKey]['sec_laststatus'] = $iDuracionLlamada;
                                            $jsonData[$jsonKey]['sec_calls'] =
                                                $estadoMonitor[$sQueue][$sCanalAgente]['sec_calls'] + $iDuracionLlamada;
                                        }
                                    }
                                } else {
                                    $jsonData[$jsonKey]['sec_laststatus'] =
                                        $iTimestampActual - strtotime($estadoMonitor[$sQueue][$sCanalAgente]['lastsessionstart']);
                                }
                                $jsonData[$jsonKey]['logintime'] = $estadoMonitor[$sQueue][$sCanalAgente]['logintime'];
                                if (!is_null($estadoMonitor[$sQueue][$sCanalAgente]['lastsessionstart'])) {
                                    $iTimestampInicio = strtotime($estadoMonitor[$sQueue][$sCanalAgente]['lastsessionstart']);
                                    $iDuracionSesion =  $iTimestampActual - $iTimestampInicio;
                                    if ($iDuracionSesion >= 0) {
                                        $jsonData[$jsonKey]['logintime'] += $iDuracionSesion;
                                    }
                                }

                                // Estado del cliente
                                $estadoCliente[$jsonKey]['status'] = $jsonData[$jsonKey]['status'];
                                $estadoCliente[$jsonKey]['oncallupdate'] = $jsonData[$jsonKey]['oncallupdate'];

                                // Estado a emitir al cliente
                                $respuesta[$jsonKey] = $jsonData[$jsonKey];
                                unset($respuesta[$jsonKey]['agentname']);
                            }
                        }
                    }
                    break;
                case 'agentlinked':
                    // Averiguar la cola por la que entró la llamada nueva
                    $sCallQueue = $evento['queue'];
                    if (is_null($sCallQueue)) {
                    	$infoCampania = $oPaloConsola->leerInfoCampania(
                            $evento['call_type'],
                            $evento['campaign_id']);
                        if (!is_null($infoCampania)) $sCallQueue = $infoCampania['queue'];
                    }

                    foreach (array_keys($estadoMonitor) as $sQueue) {
                        if (isset($estadoMonitor[$sQueue][$sCanalAgente])) {
                            $jsonKey = 'queue-'.$sQueue.'-member-'.$sNumeroAgente;
                            if (isset($jsonData[$jsonKey]) && $jsonData[$jsonKey]['status'] != 'offline') {

                                // Estado en el estado de monitor
                                $estadoMonitor[$sQueue][$sCanalAgente]['agentstatus'] = 'oncall';
                                $estadoMonitor[$sQueue][$sCanalAgente]['linkstart'] = NULL;
                                if ($sCallQueue == $sQueue) {
                                    $estadoMonitor[$sQueue][$sCanalAgente]['num_calls']++;
                                    $estadoMonitor[$sQueue][$sCanalAgente]['linkstart'] = $evento['datetime_linkstart'];
                                }

                                // Estado en la estructura JSON
                                $jsonData[$jsonKey]['status'] = $estadoMonitor[$sQueue][$sCanalAgente]['agentstatus'];
                                $jsonData[$jsonKey]['sec_laststatus'] =
                                    is_null($estadoMonitor[$sQueue][$sCanalAgente]['linkstart'])
                                        ? NULL
                                        : $iTimestampActual - strtotime($estadoMonitor[$sQueue][$sCanalAgente]['linkstart']);
                                $jsonData[$jsonKey]['num_calls'] = $estadoMonitor[$sQueue][$sCanalAgente]['num_calls'];
                                $jsonData[$jsonKey]['sec_calls'] = $estadoMonitor[$sQueue][$sCanalAgente]['sec_calls'] +
                                    (is_null($jsonData[$jsonKey]['sec_laststatus'])
                                        ? 0
                                        : $jsonData[$jsonKey]['sec_laststatus']);
                                $jsonData[$jsonKey]['oncallupdate'] = !is_null($estadoMonitor[$sQueue][$sCanalAgente]['linkstart']);
                                $jsonData[$jsonKey]['logintime'] = $estadoMonitor[$sQueue][$sCanalAgente]['logintime'];
                                if (!is_null($estadoMonitor[$sQueue][$sCanalAgente]['lastsessionstart'])) {
                                    $iTimestampInicio = strtotime($estadoMonitor[$sQueue][$sCanalAgente]['lastsessionstart']);
                                    $iDuracionSesion =  $iTimestampActual - $iTimestampInicio;
                                    if ($iDuracionSesion >= 0) {
                                        $jsonData[$jsonKey]['logintime'] += $iDuracionSesion;
                                    }
                                }

                                // Estado del cliente
                                $estadoCliente[$jsonKey]['status'] = $jsonData[$jsonKey]['status'];
                                $estadoCliente[$jsonKey]['oncallupdate'] = $jsonData[$jsonKey]['oncallupdate'];

                                // Estado a emitir al cliente
                                $respuesta[$jsonKey] = $jsonData[$jsonKey];
                                unset($respuesta[$jsonKey]['agentname']);
                            }
                        }
                    }
                    break;
                case 'agentunlinked':
                    foreach (array_keys($estadoMonitor) as $sQueue) {
                        if (isset($estadoMonitor[$sQueue][$sCanalAgente])) {
                            $jsonKey = 'queue-'.$sQueue.'-member-'.$sNumeroAgente;
                            if (isset($jsonData[$jsonKey]) && $jsonData[$jsonKey]['status'] != 'offline') {

                                // Estado en el estado de monitor
                                $estadoMonitor[$sQueue][$sCanalAgente]['agentstatus'] =
                                    (!is_null($estadoMonitor[$sQueue][$sCanalAgente]['lastpausestart']) && is_null($estadoMonitor[$sQueue][$sCanalAgente]['lastpauseend']))
                                    ? 'paused' : 'online';
                                if (!is_null($estadoMonitor[$sQueue][$sCanalAgente]['linkstart'])) {
                                	$iTimestampInicio = strtotime($estadoMonitor[$sQueue][$sCanalAgente]['linkstart']);
                                    $iDuracionLlamada = $iTimestampActual - $iTimestampInicio;
                                    if ($iDuracionLlamada >= 0) {
                                    	$estadoMonitor[$sQueue][$sCanalAgente]['sec_calls'] += $iDuracionLlamada;
                                    }
                                }
                                $estadoMonitor[$sQueue][$sCanalAgente]['linkstart'] = NULL;

                                // Estado en la estructura JSON
                                $jsonData[$jsonKey]['status'] = $estadoMonitor[$sQueue][$sCanalAgente]['agentstatus'];
                                if ($jsonData[$jsonKey]['status'] == 'paused') {
                                    $jsonData[$jsonKey]['sec_laststatus'] =
                                        $iTimestampActual - strtotime($estadoMonitor[$sQueue][$sCanalAgente]['lastpausestart']);
                                } else {
                                    $jsonData[$jsonKey]['sec_laststatus'] =
                                        $iTimestampActual - strtotime($estadoMonitor[$sQueue][$sCanalAgente]['lastsessionstart']);
                                }
                                $jsonData[$jsonKey]['num_calls'] = $estadoMonitor[$sQueue][$sCanalAgente]['num_calls'];
                                $jsonData[$jsonKey]['sec_calls'] = $estadoMonitor[$sQueue][$sCanalAgente]['sec_calls'];
                                $jsonData[$jsonKey]['oncallupdate'] = FALSE;
                                $jsonData[$jsonKey]['logintime'] = $estadoMonitor[$sQueue][$sCanalAgente]['logintime'];
                                if (!is_null($estadoMonitor[$sQueue][$sCanalAgente]['lastsessionstart'])) {
                                    $iTimestampInicio = strtotime($estadoMonitor[$sQueue][$sCanalAgente]['lastsessionstart']);
                                    $iDuracionSesion =  $iTimestampActual - $iTimestampInicio;
                                    if ($iDuracionSesion >= 0) {
                                        $jsonData[$jsonKey]['logintime'] += $iDuracionSesion;
                                    }
                                }

                                // Estado del cliente
                                $estadoCliente[$jsonKey]['status'] = $jsonData[$jsonKey]['status'];
                                $estadoCliente[$jsonKey]['oncallupdate'] = $jsonData[$jsonKey]['oncallupdate'];

                                // Estado a emitir al cliente
                                $respuesta[$jsonKey] = $jsonData[$jsonKey];
                                unset($respuesta[$jsonKey]['agentname']);
                            }
                        }
                    }
                    break;
            	}
            }


        }
        if (count($respuesta) > 0) {
            @session_start();
            $estadoHash = generarEstadoHash($module_name, $estadoCliente);
            $respuesta['estadoClienteHash'] = $estadoHash;
            session_commit();
        }
        jsonflush($bSSE, $respuesta);

        $respuesta = array();

    } while ($bSSE && connection_status() == CONNECTION_NORMAL);
    $oPaloConsola->desconectarTodo();
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

?>