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
  $Id: index.php,v 1.1.1.1 2009/07/27 09:10:19 dlopez Exp $ */

include_once "libs/paloSantoGrid.class.php";
include_once "libs/paloSantoDB.class.php";
include_once "libs/paloSantoForm.class.php";
//include_once "libs/paloSantoConfig.class.php";
require_once "libs/misc.lib.php";

if (!function_exists('_tr')) {
    function _tr($s)
    {
        global $arrLang;
        return isset($arrLang[$s]) ? $arrLang[$s] : $s;
    }
}
if (!function_exists('load_language_module')) {
    function load_language_module($module_id, $ruta_base='')
    {
        $lang = get_language($ruta_base);
        include_once $ruta_base."modules/$module_id/lang/en.lang";
        $lang_file_module = $ruta_base."modules/$module_id/lang/$lang.lang";
        if ($lang != 'en' && file_exists("$lang_file_module")) {
            $arrLangEN = $arrLangModule;
            include_once "$lang_file_module";
            $arrLangModule = array_merge($arrLangEN, $arrLangModule);
        }

        global $arrLang;
        global $arrLangModule;
        $arrLang = array_merge($arrLang,$arrLangModule);
    }
}
function _moduleContent(&$smarty, $module_name)
{
    global $arrConf;
    global $arrConfModule;

     //include module files
    include_once "modules/$module_name/configs/default.conf.php";
    include_once "modules/$module_name/libs/paloSantoTiempoConexiondeAgentes.class.php";
    include_once "libs/paloSantoConfig.class.php";
    $arrConf = array_merge($arrConf,$arrConfModule);

    // Obtengo la ruta del template a utilizar para generar el filtro.
    $base_dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $templates_dir = (isset($arrConf['templates_dir']))?$arrConf['templates_dir']:'themes';
    $local_templates_dir = "$base_dir/modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];

    load_language_module($module_name);

    //conexion resource
    $pConfig = new paloConfig("/etc", "amportal.conf", "=", "[[:space:]]*=[[:space:]]*");
    $arrConfig = $pConfig->leer_configuracion(false);
    $dsnAsteriskCdr = $arrConfig['AMPDBENGINE']['valor']."://".
                      $arrConfig['AMPDBUSER']['valor']. ":".
                      $arrConfig['AMPDBPASS']['valor']. "@".
                      $arrConfig['AMPDBHOST']['valor']."/asterisk";

    $pDB = new paloDB($arrConf['dsn_conn_database']);
    $pDB_asterisk = new paloDB($dsnAsteriskCdr);
    $oCallsAgent = new paloSantoTiempoConexiondeAgentes($pDB);

    // Variables estáticas asignadas vía Smarty
    $smarty->assign(array(
        "Filter"    =>  _tr('Show'),
    ));

    $bElastixNuevo = method_exists('paloSantoGrid','setURL');
    $oGrid = new paloSantoGrid($smarty);
    $bExportando = $bElastixNuevo
        ? $oGrid->isExportAction()
        : (isset( $_GET['exportcsv'] ) && $_GET['exportcsv'] == 'yes');

    // Estas son las colas entrantes disponibles en el sistema
    $arrQueue = leerColasEntrantes($pDB, $pDB_asterisk);
    $t = array_keys($arrQueue);

    if (count($t) <= 0) {
        // TODO: internacionalizar y poner en plantilla
        return <<<NO_QUEUE_END
<p><b>No queues have been defined for incoming calls.</b></p>
<p>For proper operation and reporting, it is necessary to configure at least one queue. You can add queues <a href="?menu=pbxconfig&amp;display=queues">here</a>. 
In addition, the queue must be registered for use by incoming calls by clicking <a href="?menu=queues">here</a>.</p>
NO_QUEUE_END;
    }

    $sColaOmision = $t[0];

    //Esto es para validar cuando recien se entra al modulo, para q aparezca seteado un numero de agente en el textbox
    // TODO: reemplazar con lista desplegable de agentes en cola elegida
    $sAgenteOmision = $oCallsAgent->obtener_agente();

    $arrFormElements = createFieldFilter($arrQueue);
    $oFilterForm = new paloForm($smarty, $arrFormElements);

    // Valores iniciales de las variables
    $paramConsulta = array(
        'date_start'    =>  date('d M Y'),
        'date_end'      =>  date('d M Y'),
        'queue'         =>  $sColaOmision,
        'agent'         =>  $sAgenteOmision,
    );
    foreach (array_keys($paramConsulta) as $k) {
        if (isset($_GET[$k])) $paramConsulta[$k] = $_GET[$k];
        if (isset($_POST[$k])) $paramConsulta[$k] = $_POST[$k];
    }
    if($oFilterForm->validateForm($paramConsulta)) {
        // Exito, puedo procesar los datos ahora.
    } else {
        // Error
        $smarty->assign("mb_title", _tr("Validation Error"));
        $arrErrores = $oFilterForm->arrErroresValidacion;
        $strErrorMsg = "<b>"._tr('Required field').":</b><br/>";
        foreach($arrErrores as $k=>$v) {
            $strErrorMsg .= "$k, ";
        }
        $strErrorMsg .= "";
        $smarty->assign("mb_message", $strErrorMsg);
        $paramConsulta = array(
            'date_start'    =>  date('d M Y'),
            'date_end'      =>  date('d M Y'),
            'queue'         =>  $sColaOmision,
            'agent'         =>  $sAgenteOmision,
        );
    }

    // Se genera el filtro con las variables ya validadas
    $htmlFilter = $oFilterForm->fetchForm(
        "$local_templates_dir/filter.tpl", 
        "", 
        $paramConsulta);

    // Consultar los datos y generar la matriz del reporte
    $sFechaInicial = translateDate($paramConsulta['date_start']);
    $sFechaFinal = translateDate($paramConsulta['date_end']);
    $r = $oCallsAgent->reportarBreaksAgente($paramConsulta['agent'], $paramConsulta['queue'], $sFechaInicial, $sFechaFinal);
    $b = $bExportando ? array('','') : array('<b>','</b>');
    $ub = $bExportando ? array('','') : array('<u><b>','</b></u>');

    $arrData = array();
    if (is_array($r) && count($r) > 0) {
        $tempTiempos = array(
            'monitoreadas'      =>  0,  // número de llamadas monitoreadas (estatus 'terminada')
            'llamadas_por_hora' =>  0,  // número de llamadas por hora (de todos los estados)
            'duracion_llamadas' =>  0,  // duración de todas las llamadas entrantes (cualquier estado)
            'promedio_duracion' =>  0,  // promedio de duración (estatus 'terminada')
            'total_llamadas'    =>  0,  // número de llamadas (todos los estados)
        );
        foreach ($r['tiempos_llamadas'] as $tupla) {
            $tempTiempos['llamadas_por_hora'] = $tempTiempos['total_llamadas'] += $tupla['N'];
            $tempTiempos['duracion_llamadas'] += $tupla['tiempo_llamadas_entrantes'];
            if ($tupla['status'] == 'terminada') {
                $tempTiempos['monitoreadas'] = $tupla['N'];
                $tempTiempos['promedio_duracion'] = $tupla['promedio_sobre_monitoreadas'];
            }
        }
        if ($r['tiempo_conexion'] > 0)
            $tempTiempos['llamadas_por_hora'] /= $r['tiempo_conexion'] / 3600;

        $sFormatoMonitoreadas = sprintf(
            '%d %s(s) (%d %s, %d %s)',
            $tempTiempos['total_llamadas'],
            _tr('Call'),
            $tempTiempos['monitoreadas'],
            _tr('Monitored'),
            $tempTiempos['total_llamadas'] - $tempTiempos['monitoreadas'],
            _tr('Unmonitored'));
        $arrData = array(
            array($b[0].strtoupper(_tr('Agent name')).$b[1], $r['name'],"",""),
            array($b[0].strtoupper(_tr('Conecction Data')).$b[1],"","",""),
            array(_tr('First Conecction'), $r['primera_conexion'],"",""),
            array(_tr('Last Conecction'), $r['ultima_conexion'],"",""),
            array(_tr('Time Conecction'), formatoSegundos($r['tiempo_conexion']),"",""),
            array(_tr('Count Conecction'), $r['conteo_conexion'],"",""),
            array($b[0].strtoupper(_tr('Calls Entry')).$b[1],"","",""),
            array(_tr('Count Calls Entry'), $sFormatoMonitoreadas,"",""),
            array(_tr('Calls/h'), number_format($tempTiempos['llamadas_por_hora'], 2),"",""),
            array(_tr('Time Call Entry'), formatoSegundos($tempTiempos['duracion_llamadas']),"",""),
            array(_tr('Average Calls Entry'), $tempTiempos['promedio_duracion']."    ("._tr('Monitored only').')',"",""),
            array($b[0].strtoupper(_tr('Reason No Ready')).$b[1],"","",""),
            array($ub[0].(_tr('Break')).$ub[1], $ub[0].(_tr('Count')).$ub[1], $ub[0].(_tr('Hour')).$ub[1], $ub[0].(_tr('Porcent compare whit time not ready')).$ub[1]),
        );

        $tempBreaks = array();
        $iTotalSeg = 0;
        foreach ($r['tiempos_breaks'] as $tupla) {
            $tempBreaks[] = array(
                $tupla['name'],
                $tupla['N'],
                formatoSegundos($tupla['total_break']),
                $tupla['total_break'],
            );
            $iTotalSeg += $tupla['total_break'];
        }
        for ($i = 0; $i < count($tempBreaks); $i++) {
            $tempBreaks[$i][3] = number_format(100.0 * ($tempBreaks[$i][3] / $iTotalSeg), 2).' %';
            $arrData[] = $tempBreaks[$i];
        }

    } else {
        if (!is_array($r)) {
            $smarty->assign("mb_title", _tr("Database Error"));
            $smarty->assign("mb_message", $oCallsAgent->errMsg);
        }
        $arrData[] = array(
            $b[0]._tr("There aren't records to show").$b[1],
            '',
            '',
            '',
        );
    }

    // Creo objeto de grid
    $oGrid->enableExport();
    $oGrid->showFilter($htmlFilter);

    // La definición del grid
    $paramConsulta['menu'] = $module_name;
    if($bElastixNuevo){
        $oGrid->setURL(construirURL($paramConsulta));
        $oGrid->setData($arrData);
        $arrColumnas = array("","","","");
        $oGrid->setColumns($arrColumnas);
        $oGrid->setTitle(_tr("Agent Information"));
        $oGrid->pagingShow(false); 
        $oGrid->setNameFile_Export(_tr("Agent Information"));
     
        $smarty->assign("SHOW", _tr("Show"));
        return $oGrid->fetchGrid();
    } else {
        global $arrLang;

        $total = $end = count($arrData);
        $offset = 0;
        $url = construirUrl($paramConsulta);
        $arrGrid = array(
            "title"    => _tr("Time conecction of agents"),
            "icon"     => "images/list.png",
            "width"    => "99%",
            "start"    => ($total==0) ? 0 : $offset + 1,
            "end"      => $end,
            "total"    => $total,
            "url"      => $url,
            "columns"  => array(
                            0 => array("name"      => "",
                                        "property" => ""),
                            1 => array("name"      => "",
                                        "property" => ""),
                            2 => array("name"      => "",
                                        "property" => ""),
                            3 => array("name"      => "",
                                        "property" => ""),
                            )
        );
        if($bExportando){
            $fechaActual = date("d M Y");
            header("Cache-Control: private");
            header("Pragma: cache");
            $title = $fechaActual;
            header('Content-Type: text/csv; charset=utf-8; header=present');
            header("Content-disposition: attachment; filename=\"".$title.".csv\"");
        }
        if ($bExportando)
            return $oGrid->fetchGridCSV($arrGrid, $arrData);
        $sContenido = $oGrid->fetchGrid($arrGrid, $arrData, $arrLang);
        if (strpos($sContenido, '<form') === FALSE)
            $sContenido = "<form  method=\"POST\" style=\"margin-bottom:0;\" action=\"$url\">$sContenido</form>";
        return $sContenido;
    }        
}

function leerColasEntrantes($pDB, $pDB_asterisk)
{
    include_once "libs/paloSantoQueue.class.php";

    $arrQueue = array();
    $oQueue = new paloQueue($pDB_asterisk);
    $PBXQueues = $oQueue->getQueue();
    if (is_array($PBXQueues)) {
        foreach($PBXQueues as $key => $value) {
            $query = "SELECT id, queue from queue_call_entry WHERE queue = ?";
            $result = $pDB->getFirstRowQuery($query, true, array($value[0]));
            if (is_array($result) && count($result)>0) {
                $arrQueue[$result['queue']] =  $result['queue'];
            }
        }
    }
    return $arrQueue;
}

function formatoSegundos($s)
{
    $sec = $s % 60; $s = ($s - $sec) / 60;
    $min = $s % 60; $hora = ($s - $min) / 60;
    return sprintf('%02d:%02d:%02d', $hora, $min, $sec);
}

function createFieldFilter($arrQueue)
{
    $arrFormElements = array(
        "date_start"  => array(
            "LABEL"                  => _tr('Start Date'),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "DATE",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "ereg",
            "VALIDATION_EXTRA_PARAM" => "^[[:digit:]]{1,2}[[:space:]]+[[:alnum:]]{3}[[:space:]]+[[:digit:]]{4}$"),
        "date_end"    => array(
            "LABEL"                  => _tr("End Date"),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "DATE",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "ereg",
            "VALIDATION_EXTRA_PARAM" => "^[[:digit:]]{1,2}[[:space:]]+[[:alnum:]]{3}[[:space:]]+[[:digit:]]{4}$"),
        //COLA
        "queue" => array(
            "LABEL"                  => _tr("Queue"),
            "REQUIRED"               => "no",
            "INPUT_TYPE"             => "SELECT",
            "INPUT_EXTRA_PARAM"      => $arrQueue,
            "VALIDATION_TYPE"        => "ereg",
            "VALIDATION_EXTRA_PARAM" => "^[[:digit:]]+$"),
        "agent" => array(
            "LABEL"                  => _tr("No.Agent"),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "TEXT",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "ereg",
            "VALIDATION_EXTRA_PARAM" => "^[[:digit:]]+$"),
         );
    return $arrFormElements;
}

?>
