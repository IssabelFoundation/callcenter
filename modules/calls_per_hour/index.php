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

require_once "libs/paloSantoForm.class.php";
require_once "libs/misc.lib.php";
require_once "libs/paloSantoConfig.class.php";
require_once "libs/paloSantoGrid.class.php";

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
    require_once "modules/$module_name/libs/paloSantoCallsHour.class.php";

    #incluir el archivo de idioma de acuerdo al que este seleccionado
    #si el archivo de idioma no existe incluir el idioma por defecto
    $lang=get_language();
    $script_dir=dirname($_SERVER['SCRIPT_FILENAME']);

    load_language_module($module_name);
    
    //include module files
    include_once "modules/$module_name/configs/default.conf.php";
    global $arrConf;

    // Se fusiona la configuración del módulo con la configuración global
    $arrConf = array_merge($arrConf, $arrConfModule);

    //folder path for custom templates
    $base_dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $templates_dir = (isset($arrConf['templates_dir']))?$arrConf['templates_dir']:'themes';
    $local_templates_dir = "$base_dir/modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];

    // Conexión a la base de datos CallCenter
    $pDB = new paloDB($arrConf['cadena_dsn']);

    // Mostrar pantalla correspondiente
    $contenidoModulo = '';
    $sAction = 'list_campaign';
    if (isset($_GET['action'])) $sAction = $_GET['action'];
    switch ($sAction) {
    case 'list_histogram':
    default:
        $contenidoModulo = listHistogram($pDB, $smarty, $module_name, $local_templates_dir);
        break;
    }

    return $contenidoModulo;
}

function sumar($a, $b) { return $a + $b; }

function listHistogram($pDB, $smarty, $module_name, $local_templates_dir)
{
    global $arrLang;

    // Tipo de llamada
    $comboTipos = array(
        "E" => _tr("Ingoing"),
        "S" => _tr("Outgoing")
    );
    $sTipoLlamada = 'E';
    if (isset($_GET['tipo'])) $sTipoLlamada = $_GET['tipo'];
    if (isset($_POST['tipo'])) $sTipoLlamada = $_POST['tipo'];
    if (!in_array($sTipoLlamada, array_keys($comboTipos))) $sTipoLlamada = 'E';
    $_POST['tipo'] = $sTipoLlamada; // Para llenar el formulario
    $smarty->assign('TIPO', $_POST['tipo']);
    
    // Estado de la llamada
    $comboEstados = array(
        'T' =>  _tr('All'),
        'E' =>  _tr('Completed'),
        'A' =>  _tr('Abandoned'),
    );
    if ($sTipoLlamada == 'S') $comboEstados['N'] = _tr('No answer/Short call');
    $sEstadoLlamada = 'T';
    if (isset($_GET['estado'])) $sEstadoLlamada = $_GET['estado'];
    if (isset($_POST['estado'])) $sEstadoLlamada = $_POST['estado'];
    if (!in_array($sEstadoLlamada, array_keys($comboEstados))) $sEstadoLlamada = 'E';
    $_POST['estado'] = $sEstadoLlamada; // Para llenar el formulario
    $smarty->assign('ESTADO', $_POST['estado']);
    
    // Rango de fechas
    $sFechaInicial = $sFechaFinal = date('Y-m-d');
    if (isset($_GET['fecha_ini'])) $sFechaInicial = date('Y-m-d', strtotime($_GET['fecha_ini']));
    if (isset($_POST['fecha_ini'])) $sFechaInicial = date('Y-m-d', strtotime($_POST['fecha_ini']));
    if (isset($_GET['fecha_fin'])) $sFechaFinal = date('Y-m-d', strtotime($_GET['fecha_fin']));
    if (isset($_POST['fecha_fin'])) $sFechaFinal = date('Y-m-d', strtotime($_POST['fecha_fin']));
    $_POST['fecha_ini'] = date('d M Y', strtotime($sFechaInicial));
    $_POST['fecha_fin'] = date('d M Y', strtotime($sFechaFinal));
    $smarty->assign('FECHA_INI', $sFechaInicial);
    $smarty->assign('FECHA_FIN', $sFechaFinal);

    // Recuperar la lista de llamadas
    $oCalls = new paloSantoCallsHour($pDB);
    $arrCalls = $oCalls->getCalls($sTipoLlamada, $sEstadoLlamada, $sFechaInicial, $sFechaFinal);

    // TODO: manejar error al obtener llamadas
    if (!is_array($arrCalls)) {
        $smarty->assign("mb_title", _tr("Validation Error"));
        $smarty->assign("mb_message", $oCalls->errMsg);
        $arrCalls = array();
    }

    // Lista de colas a elegir para gráfico. Sólo se elige de las colas devueltas 
    // por la lista de datos.
    $listaColas = array_keys($arrCalls);
    $comboColas = array(
        ''  =>  _tr('All'),
    );
    if (count($listaColas) > 0) 
        $comboColas += array_combine($listaColas, $listaColas);
    $sColaElegida = NULL;
    if (isset($_GET['queue'])) $sColaElegida = $_GET['queue'];
    if (isset($_POST['queue'])) $sColaElegida = $_POST['queue'];
    if (!in_array($sColaElegida, $listaColas)) $sColaElegida = '';
    $_POST['queue'] = $sColaElegida; // Para llenar el formulario
    $smarty->assign('QUEUE', $_POST['queue']);

    $url = construirURL(array(
        'menu'      =>  $module_name,
        'tipo'      =>  $sTipoLlamada,
        'estado'    =>  $sEstadoLlamada,
        'queue'     =>  $sColaElegida,
        'fecha_ini' =>  $sFechaInicial,
        'fecha_fin' =>  $sFechaFinal,
    ), array('nav', 'start'));
    $smarty->assign('url', $url);

    // Construir el arreglo como debe mostrarse en la tabla desglose
    $arrData = array();
    for ($i = 0; $i < 24; $i++) {
        $arrData[$i] = array(sprintf('%02d:00', $i));
    }
    $arrData[24] = array(_tr('Total Calls'));
    $arrCols = array(
        0   =>  array('name' => _tr('Hour')),
    );
    $arrTodos = array_fill(0, 24, 0);
    foreach ($arrCalls as $sQueue => $hist)    
    if (empty($sColaElegida) || $sColaElegida == $sQueue){
        $arrCols[] = array('name' => $sQueue);
        $iTotalCola = 0;
        foreach ($hist as $i => $iNumCalls) {
            $arrData[$i][] = $iNumCalls;
            $arrTodos[$i] += $iNumCalls;
            $iTotalCola += $iNumCalls;
        }
        $arrData[24][] = $iTotalCola;
    }
    $arrCols[] = array('name' => _tr('All'));
    $iTotalCola = 0;
    foreach ($arrTodos as $i => $iNumCalls) {
        $arrData[$i][] = $iNumCalls;
        $iTotalCola += $iNumCalls;
    }
    $arrData[24][] = $iTotalCola;

    $smarty->assign('MODULE_NAME', $module_name);
    $smarty->assign('LABEL_FIND', _tr('Find'));
    $formFilter = getFormFilter($comboTipos, $comboEstados, $comboColas);
    $oForm = new paloForm($smarty, $formFilter);

    //Llenamos las cabeceras
    $arrGrid = array("title"    => _tr("Calls per hour"),
        "url"      => $url,
        "icon"     => "images/list.png",
        "width"    => "99%",
        "start"    => 0,
        "end"      => 0,
        "total"    => 0,
        "columns"  => $arrCols);
    $oGrid = new paloSantoGrid($smarty);
    $oGrid->showFilter(
        $oForm->fetchForm(
            "$local_templates_dir/filter-calls.tpl", 
            NULL,
            $_POST)
    );
    $oGrid->enableExport();
    if (isset($_GET['exportcsv']) && $_GET['exportcsv'] == 'yes') {
        $fechaActual = date("Y-m-d");
        header("Cache-Control: private");
        header("Pragma: cache");
        header('Content-Type: text/csv; charset=UTF-8; header=present');
        $title = "\"calls-per-hour-".$fechaActual.".csv\"";
        header("Content-disposition: attachment; filename={$title}");
        return $oGrid->fetchGridCSV($arrGrid, $arrData);
    } else {
        $bExportando =
              ( (isset( $_GET['exportcsv'] ) && $_GET['exportcsv'] == 'yes') || 
                (isset( $_GET['exportspreadsheet'] ) && $_GET['exportspreadsheet'] == 'yes') || 
                (isset( $_GET['exportpdf'] ) && $_GET['exportpdf'] == 'yes')
              ) ;
        $sContenido = $oGrid->fetchGrid($arrGrid, $arrData, $arrLang);
        if (!$bExportando) {
            if (strpos($sContenido, '<form') === FALSE)
                $sContenido = "<form  method=\"POST\" style=\"margin-bottom:0;\" action=\"$url\">$sContenido</form>";
        }
        return $sContenido;
    }
}

function getFormFilter($arrDataTipo, $arrDataEstado, $arrDataQueues)
{
    $formCampos = array(
        "fecha_ini"       => array(
            "LABEL"                  => _tr("Date Init"),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "DATE",
            "INPUT_EXTRA_PARAM"      => array("TIME" => false, "FORMAT" => "%d %b %Y"),
            "VALIDATION_TYPE"        => 'ereg',
            "VALIDATION_EXTRA_PARAM" => '^[[:digit:]]{2}[[:space:]]+[[:alpha:]]{3}[[:space:]]+[[:digit:]]{4}$'
        ),
        "fecha_fin"       => array(
            "LABEL"                  => _tr("Date End"),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "DATE",
            "INPUT_EXTRA_PARAM"      => array("TIME" => false, "FORMAT" => "%d %b %Y"),
            "VALIDATION_TYPE"        => 'ereg',
            "VALIDATION_EXTRA_PARAM" => '^[[:digit:]]{2}[[:space:]]+[[:alpha:]]{3}[[:space:]]+[[:digit:]]{4}$'
        ),
        "tipo" => array(
            "LABEL"                  => _tr("Tipo"),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "SELECT",
            "INPUT_EXTRA_PARAM"      => $arrDataTipo,
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => ""
        ),
        "estado" => array(
            "LABEL"                  => _tr("Estado"),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "SELECT",
            "INPUT_EXTRA_PARAM"      => $arrDataEstado,
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => ""
        ),
        "queue" => array(
            "LABEL"                  => _tr("Cola"),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "SELECT",
            "INPUT_EXTRA_PARAM"      => $arrDataQueues,
            "VALIDATION_TYPE"        => "numeric",
            "VALIDATION_EXTRA_PARAM" => ""
        ),
    );

    return $formCampos;
}

?>
