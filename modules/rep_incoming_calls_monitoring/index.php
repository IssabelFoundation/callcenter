<?php
/*
  vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
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
  $Id: index.php,v 1.1 2007/01/09 23:49:36 alex Exp $
*/

require_once "libs/misc.lib.php";
require_once "libs/paloSantoGrid.class.php";

global $keylist;
$keylist = array('onqueue', 'abandoned', 'success', 'finished', 'losttrack', 'total');

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

function manejarMonitoreo_HTML_estiloestado($k)
{
    switch ($k) {
    case 'total':
        return 'font-weight: bold;';
    case 'abandoned':
        return 'color: #ff0000;';
    case 'success':
        return 'color: #008800;';
    default:
        return '';
    }
}

function manejarMonitoreo_HTML($module_name, $smarty, $sDirLocalPlantillas, $oPaloConsola)
{
    global $arrLang;
    global $keylist;

    $smarty->assign(array(
        'FRAMEWORK_TIENE_TITULO_MODULO' => existeSoporteTituloFramework(),
        'icon'                          => 'images/list.png',
        'title'                         =>  _tr('Incoming calls monitoring'),
    ));

    // Construcción del estado de monitoreo
    $estadoMonitor = obtenerEstadoMonitor($oPaloConsola);

    $jsonData = construirDatosJSON($estadoMonitor);

    $arrData = array();
    $tuplaTotal = array();
    foreach ($jsonData as $jsonKey => $jsonRow) {
        list($d1, $sQueue) = explode('-', $jsonKey);

        $tupla = array($sQueue);
        foreach ($keylist as $k) {
            $tupla[] = '<span style="'.manejarMonitoreo_HTML_estiloestado($k).'" id="'.$jsonKey.'-'.$k.'">'.$jsonRow[$k].'</span>';
            if (!isset($tuplaTotal[$k])) $tuplaTotal[$k] = 0;
            $tuplaTotal[$k] += $jsonRow[$k];
        }
        $arrData[] = $tupla;
    }
    $tupla = array('<b>'.strtoupper(_tr('Total')).'</b>');
    foreach ($keylist as $k) $tupla[] = '<b><span style="'.manejarMonitoreo_HTML_estiloestado($k).'" id="total-'.$k.'">'.$tuplaTotal[$k].'</span></b>';
    $arrData[] = $tupla;

    // Extraer la información que el navegador va a usar para actualizar
    $estadoCliente = $estadoMonitor;
    $estadoHash = generarEstadoHash($module_name, $estadoCliente);

    $oGrid  = new paloSantoGrid($smarty);
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
        'title'     =>  _tr('Incoming calls monitoring'),
        'icon'      =>  'images/list.png',
        'width'     =>  '99%',
        'start'     =>  1,
        'end'       =>  1,
        'total'     =>  1,
        'url'       =>  array('menu' => $module_name),
        'columns'   =>  array(
            array('name'    =>  _tr('Queue')),

            array('name'    =>  _tr('Waiting calls')),
            array('name'    =>  _tr('Abandoned')),
            array('name'    =>  _tr('Answered')),
            array('name'    =>  _tr('Finished')),
            array('name'    =>  _tr('Without monitoring')),
            array('name'    =>  _tr('Entered')),
        ),
    ), $arrData, $arrLang).
    $sJsonInitialize;
}

function obtenerEstadoMonitor($oPaloConsola)
{
    $sFechaHoy = date('Y-m-d');
    $estadoMonitor = array();
    $listaColas = $oPaloConsola->leerListaColasEntrantes();
    if (!is_array($listaColas)) return NULL;
    $listaCampanias = $oPaloConsola->leerListaCampanias();
    if (!is_array($listaCampanias)) return NULL;
    $listaCampaniasEntrantes = array();
    foreach ($listaCampanias as $tuplaCampania) if ($tuplaCampania['type'] == 'incoming') {
        agregarInformacionColaCampania($oPaloConsola, $sFechaHoy, $tuplaCampania, $estadoMonitor);
    }
    foreach ($listaColas as $tuplaCola) {
        agregarInformacionColaCampania($oPaloConsola, $sFechaHoy, array('type' => 'incomingqueue', 'id' => $tuplaCola['queue']), $estadoMonitor);
    }
    ksort($estadoMonitor);
    return $estadoMonitor;
}

function agregarInformacionColaCampania($oPaloConsola, $sFechaHoy, $tuplaCampania, &$infoColas)
{
    global $keylist;

    // Leer la cola para la campaña e iniciar fila de cola si no existe
    $infoCampania = $oPaloConsola->leerInfoCampania($tuplaCampania['type'], $tuplaCampania['id']);
    if (!isset($infoColas[$infoCampania['queue']])) {
        $infoColas[$infoCampania['queue']] = array();
        foreach ($keylist as $k) $infoColas[$infoCampania['queue']][$k] = 0;
    }

    // Leer estado real de campaña y sumarlo a la cola correspondiente
    $estadoCampania = $oPaloConsola->leerEstadoCampania($tuplaCampania['type'], $tuplaCampania['id'], $sFechaHoy);
    foreach ($keylist as $k) {
        $infoColas[$infoCampania['queue']][$k] += $estadoCampania['statuscount'][$k];
    }
}

function generarEstadoHash($module_name, $estadoCliente)
{
    $estadoHash = md5(serialize($estadoCliente));
    $_SESSION[$module_name]['estadoCliente'] = $estadoCliente;
    $_SESSION[$module_name]['estadoClienteHash'] = $estadoHash;

    return $estadoHash;
}

function construirDatosJSON(&$estadoMonitor)
{
    global $keylist;

    $jsonData = array();
    foreach ($estadoMonitor as $sQueue => $statusList)
    {
        $jsonKey = 'queue-'.$sQueue;
        foreach ($keylist as $k)
            $jsonData[$jsonKey][$k] = $statusList[$k];
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
    $estadoMonitor = obtenerEstadoMonitor($oPaloConsola);
    if (!is_array($estadoMonitor)) {
        $respuesta['error'] = $oPaloConsola->errMsg;
        jsonflush($bSSE, $respuesta);
    	$oPaloConsola->desconectarTodo();
        return;
    }

    // Acumular inmediatamente las filas que son distintas en estado
    $jsonData = construirDatosJSON($estadoMonitor);
    foreach ($jsonData as $jsonKey => $jsonRow) {
    	if (isset($estadoCliente[$jsonKey])) {
    	    if ($estadoCliente[$jsonKey] != $jsonRow) {
    	        $respuesta[$jsonKey] = $jsonRow;
    	    }
    	}
    }

    $oPaloConsola->escucharProgresoLlamada(TRUE);
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
                switch ($evento['event']) {
                case 'callprogress':
                    $sCallQueue = $evento['queue'];
                    if (isset($estadoMonitor[$sCallQueue])) {
                        $jsonKey = 'queue-'.$sCallQueue;
                        $bProcesado = TRUE;
                        switch ($evento['new_status']) {
                        case 'OnQueue':
                            $estadoMonitor[$sCallQueue]['total']++;
                            $estadoMonitor[$sCallQueue]['onqueue']++;
                            break;
                        case 'Abandoned':
                            if ($estadoMonitor[$sCallQueue]['onqueue'] > 0)
                                $estadoMonitor[$sCallQueue]['onqueue']--;
                            $estadoMonitor[$sCallQueue]['abandoned']++;
                            break;
                        default:
                            $bProcesado = FALSE;
                            break;
                        }

                        if ($bProcesado) {
                            // Estado en la estructura JSON
                            $jsonData[$jsonKey] = $estadoMonitor[$sCallQueue];

                            // Estado del cliente
                            $estadoCliente[$jsonKey] = $estadoMonitor[$sCallQueue];

                            // Estado a emitir al cliente
                            $respuesta[$jsonKey] = $estadoMonitor[$sCallQueue];
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

                    if (isset($estadoMonitor[$sCallQueue])) {
                        $jsonKey = 'queue-'.$sCallQueue;
                        if ($estadoMonitor[$sCallQueue]['onqueue'] > 0)
                            $estadoMonitor[$sCallQueue]['onqueue']--;
                        $estadoMonitor[$sCallQueue]['success']++;

                        // Estado en la estructura JSON
                        $jsonData[$jsonKey] = $estadoMonitor[$sCallQueue];

                        // Estado del cliente
                        $estadoCliente[$jsonKey] = $estadoMonitor[$sCallQueue];

                        // Estado a emitir al cliente
                        $respuesta[$jsonKey] = $estadoMonitor[$sCallQueue];
                    }
                    break;
                case 'agentunlinked':
                    // Averiguar la cola por la que entró la llamada nueva
                    $sCallQueue = $evento['queue'];

                    if (isset($estadoMonitor[$sCallQueue])) {
                        $jsonKey = 'queue-'.$sCallQueue;
                        if ($estadoMonitor[$sCallQueue]['success'] > 0)
                            $estadoMonitor[$sCallQueue]['success']--;
                        $estadoMonitor[$sCallQueue]['finished']++;

                        // Estado en la estructura JSON
                        $jsonData[$jsonKey] = $estadoMonitor[$sCallQueue];

                        // Estado del cliente
                        $estadoCliente[$jsonKey] = $estadoMonitor[$sCallQueue];

                        // Estado a emitir al cliente
                        $respuesta[$jsonKey] = $estadoMonitor[$sCallQueue];
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