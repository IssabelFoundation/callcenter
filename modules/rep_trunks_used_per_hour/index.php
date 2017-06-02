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
  $Id: index.php,v 1.2 2009/07/27 13:10:24 dlopez Exp $ */
//include elastix framework
include_once "libs/paloSantoGrid.class.php";
include_once "libs/paloSantoForm.class.php";
include_once "libs/paloSantoTrunk.class.php";//Trunks

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
    //include module files
    include_once "modules/$module_name/configs/default.conf.php";
    include_once "modules/$module_name/libs/paloSantoReportedeTroncalesusadasporHoraeneldia.class.php";
    include_once "libs/paloSantoConfig.class.php";

    $base_dir=dirname($_SERVER['SCRIPT_FILENAME']);

    load_language_module($module_name);

    //global variables
    global $arrConf;
    global $arrConfModule;
    $arrConf = array_merge($arrConf,$arrConfModule);

    //folder path for custom templates
    $templates_dir=(isset($arrConf['templates_dir']))?$arrConf['templates_dir']:'themes';
    $local_templates_dir="$base_dir/modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];

    //conexion resource
    $pConfig = new paloConfig("/etc", "amportal.conf", "=", "[[:space:]]*=[[:space:]]*");
    $arrConfig = $pConfig->leer_configuracion(false);
    $dsnAsteriskCdr = $arrConfig['AMPDBENGINE']['valor']."://".
                      $arrConfig['AMPDBUSER']['valor']. ":".
                      $arrConfig['AMPDBPASS']['valor']. "@".
                      $arrConfig['AMPDBHOST']['valor']."/asterisk";

    $pDB = new paloDB($arrConf['dsn_conn_database']);
    $pDB_asterisk = new paloDB($dsnAsteriskCdr);

    //actions
    $accion = getAction();
    $content = "";

    switch($accion){
        default:
            $content = reportReportedeTroncalesusadasporHoraeneldia($smarty, $module_name, $local_templates_dir, $pDB, $arrConf, $pDB_asterisk);
            break;
    }
    return $content;
}

function reportReportedeTroncalesusadasporHoraeneldia($smarty, $module_name, $local_templates_dir, &$pDB, $arrConf, &$pDB_asterisk)
{
    $pReportedeTroncalesusadasporHoraeneldia = new paloSantoReportedeTroncalesusadasporHoraeneldia($pDB);

    // PS se obtiene el arreglo con las trunks para mostrarlas en el filtro
    //$arrTrunk1 = getTrunk($pDB, $pDB_asterisk);//Trunks
     //diana
    //llamamos  funcion nueva
    $arrTrunk = obtener_nuevas_trunks($pDB, $pDB_asterisk);

    // valores del filtro
    $filter_field = getParameter("filter_field");
    $filter_value = getParameter("filter_value");
    $date_from = getParameter("date_from");
    $date_to = getParameter("date_to");

    // si la fecha no está seteada en el filtro
    $_POST["date_from"] = isset($date_from)?$date_from:date("d M Y");
    $_POST["date_to"] = isset($date_to)?$date_to:date("d M Y");
    $date_from = isset($date_from)?date('Y-m-d',strtotime($date_from)):date("Y-m-d");
    $date_to = isset($date_to)?date('Y-m-d',strtotime($date_to)):date("Y-m-d");

    // para setear la trunk la primera vez
    $filter_value = getParameter("filter_value");
    if (!isset($filter_value)) {
        $trunk = array_shift(array_keys($arrTrunk));//Trunks
        $_POST["filter_value"] = $trunk;
        $filter_value = $trunk;
    }
    //validacion para que los filtros se queden seteados con el valor correcto, correccion de bug que se estaba dando en caso de pagineo
    $_POST["filter_value"] = $filter_value;

    $bElastixNuevo = method_exists('paloSantoGrid','setURL');
    // begin grid parameters
    $oGrid  = new paloSantoGrid($smarty);
    $oGrid->enableExport();
    $bExportando = $bElastixNuevo
        ? $oGrid->isExportAction()
        : (isset( $_GET['exportcsv'] ) && $_GET['exportcsv'] == 'yes');

    $limit  = 50;
    $offset = 0;

   // se obtienen los datos que se van a mostrar
    $arrData = null;
    $filter_value = trim($filter_value);
    $recordset = $pReportedeTroncalesusadasporHoraeneldia->listarTraficoLlamadasHora(
        $date_from, $date_to, empty($filter_value) ? NULL: $filter_value);
    if (!is_array($recordset)) {
        $smarty->assign(array(
            'mb_title'      =>  _tr('Query Error'),
            'mb_message'    =>  $oCalls->errMsg,
        ));
        $recordset = array();
    }

    $total = count($recordset);
    $oGrid->setLimit($limit);
    $oGrid->setTotal($total);
    
    if($bElastixNuevo)
        $offset = $oGrid->calculateOffset();
    else{
        $action = getParameter("nav");
        $start = getParameter("start");
        $oGrid->calculatePagination($action,$start);
        $end    = $oGrid->getEnd();
    }

    $url = array(
            "menu"          => $module_name,
            "filter_field"  => $filter_field,
            "filter_value"  => $filter_value,
            "date_from"     => $date_from,
            "date_to"       => $date_to);
    

    // se guarda la data en un arreglo que luego es enviado como parámetro para crear el reporte
    if(is_array($recordset)){
        $arrData = array();
        $total = array(
            'entered'       =>  0,
            'terminada'     =>  0,
            'abandonada'    =>  0,
            'en-cola'       =>  0,
            'fin-monitoreo' =>  0,
        );
        foreach ($recordset as $iHora => $tupla) {
        	$arrData[] = array(
                sprintf('%02d:00:00 - %02d:00:00', $iHora, $iHora + 1),
                $tupla['entered'],
                $tupla['terminada'],
                $tupla['abandonada'],
                $tupla['en-cola'],
                $tupla['fin-monitoreo'],
            );
            foreach (array_keys($total) as $k) $total[$k] += $tupla[$k];
        }
        $sTagInicio = (!$bExportando) ? '<b>' : '';
        $sTagFinal = ($sTagInicio != '') ? '</b>' : '';
        $arrData[] = array(
            $sTagInicio._tr('TOTAL').$sTagFinal,
            $sTagInicio.$total['entered'].$sTagFinal,
            $sTagInicio.$total['terminada'].$sTagFinal,
            $sTagInicio.$total['abandonada'].$sTagFinal,
            $sTagInicio.$total['en-cola'].$sTagFinal,
            $sTagInicio.$total['fin-monitoreo'].$sTagFinal,
        );
    }

    //begin section filter
    $arrFormFilterReportedeTroncalesusadasporHoraeneldia = createFieldFilter($arrTrunk);
    $smarty->assign("SHOW", _tr("Show"));
    $oFilterForm = new paloForm($smarty, $arrFormFilterReportedeTroncalesusadasporHoraeneldia);

    $htmlFilter = $oFilterForm->fetchForm("$local_templates_dir/filter.tpl","",$_POST, $_GET);
    //end section filter
    $oGrid->showFilter($htmlFilter);
    if($bElastixNuevo){
        $oGrid->setURL($url);
        $oGrid->setData($arrData);
        $arrColumnas = array(_tr("Time Period "), _tr("Entered"), _tr("Answered"), _tr("Abandoned"),_tr("In queue"),_tr("Without monitoring "));
        $oGrid->setColumns($arrColumnas);
        $oGrid->setTitle(_tr("Reporte de Troncales usadas por Hora en el dia"));
        $oGrid->pagingShow(true); 
        $oGrid->setNameFile_Export(_tr("Reporte de Troncales usadas por Hora en el dia"));
     

        return $oGrid->fetchGrid();
     } else {
            global $arrLang;

            $url = construirURL($url, array('nav', 'start'));
            $offset = 0;
            $limit = $total + 1;
            // se crea el grid
            $arrGrid = array("title"    => _tr("Reporte de Troncales usadas por Hora en el dia"),
                        "url"      => $url,
                        "icon"     => "images/list.png",
                        "width"    => "99%",
                        "start"    => ($total==0) ? 0 : $offset + 1,
                        "end"      => $end,
                        "total"    => $total,
                        "columns"  => array(
			0 => array("name"      => _tr("Time Period "),
                                   "property1" => ""),
			1 => array("name"      => _tr("Entered"),
                                   "property1" => ""),
			2 => array("name"      => _tr("Answered"),
                                   "property1" => ""),
			3 => array("name"      => _tr("Abandoned"),
                                   "property1" => ""),
			4 => array("name"      => _tr("In queue"),
                                   "property1" => ""),
			5 => array("name"      => _tr("Without monitoring "),
                                   "property1" => ""),
                                        )
                    );
            if($bExportando){
                 $fechaActual = date("d M Y");
                 header("Cache-Control: private");
                 header("Pragma: cache");
                 header('Content-Type: application/octec-stream');
                 $title = "\"".$fechaActual.".csv\"";
                 header("Content-disposition: inline; filename={$title}");
                 header('Content-Type: application/force-download');
            }
            if ($bExportando)
                return $oGrid->fetchGridCSV($arrGrid, $arrData);
            $sContenido = $oGrid->fetchGrid($arrGrid, $arrData, $arrLang);
            if (strpos($sContenido, '<form') === FALSE)
                $sContenido = "<form  method=\"POST\" style=\"margin-bottom:0;\" action=\"$url\">$sContenido</form>";
            return $sContenido;
    }
}
    
function createFieldFilter($arrTrunk){

    $arrFormElements = array(
            "filter_field" => array("LABEL"                  => _tr("Trunk"),
                                    "REQUIRED"               => "no",
                                    "INPUT_TYPE"             => "text",
                                    "INPUT_EXTRA_PARAM"      => "no",
                                    "VALIDATION_TYPE"        => "text",
                                    "VALIDATION_EXTRA_PARAM" => ""),

            "filter_value" => array("LABEL"                  => "",
                                    "REQUIRED"               => "no",
                                    "INPUT_TYPE"             => "SELECT",
                                    "INPUT_EXTRA_PARAM"      => $arrTrunk,
                                    "VALIDATION_TYPE"        => "",
                                    "VALIDATION_EXTRA_PARAM" => ""),

            "date_from"    => array("LABEL"                  => _tr("Start date"),
                                    "REQUIRED"               => "yes",
                                    "INPUT_TYPE"             => "DATE",
                                    "INPUT_EXTRA_PARAM"      => "",
                                    "VALIDATION_TYPE"        => "ereg",
                                    "VALIDATION_EXTRA_PARAM" => "^[[:digit:]]{1,2}[[:space:]]+[[:alnum:]]{3}[[:space:]]+[[:digit:]]{4}$"),

            "date_to"      => array("LABEL"                  => _tr("End date"),
                                    "REQUIRED"               => "no",
                                    "INPUT_TYPE"             => "DATE",
                                    "INPUT_EXTRA_PARAM"      => "",
                                    "VALIDATION_TYPE"        => "ereg",
                                    "VALIDATION_EXTRA_PARAM" => "^[[:digit:]]{1,2}[[:space:]]+[[:alnum:]]{3}[[:space:]]+[[:digit:]]{4}$"),
                    );
    return $arrFormElements;
}

function obtener_nuevas_trunks($pDB, $pDB_asterisk)
{
    $listaTrunks = array('' => _tr('(All)'));
    $trunks = getTrunks($pDB_asterisk);    

    foreach ($trunks as $tuplaTrunk) {
        $listaTrunks[$tuplaTrunk[1]] = $tuplaTrunk[1];
    }
    return $listaTrunks;
}

if (!function_exists('getParameter')) {
function getParameter($parameter)
{
    if(isset($_POST[$parameter]))
        return $_POST[$parameter];
    else if(isset($_GET[$parameter]))
        return $_GET[$parameter];
    else
        return null;
}
}

function getAction()
{
    if(getParameter("show")) //Get parameter by POST (submit)
        return "show";
    else if(getParameter("new"))
        return "new";
    else if(getParameter("action")=="show") //Get parameter by GET (command pattern, links)
        return "show";
    else
        return "report";
}
?>