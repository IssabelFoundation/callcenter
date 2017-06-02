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
include_once "libs/paloSantoGrid.class.php";

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
    load_language_module($module_name);

    //include module files
    include_once "modules/$module_name/configs/default.conf.php";
    global $arrConf;

    // Se fusiona la configuración del módulo con la configuración global
    $arrConf = array_merge($arrConf, $arrConfModule);

    require_once "modules/$module_name/libs/paloSantoHoldTime.class.php";
    //folder path for custom templates
    $base_dir=dirname($_SERVER['SCRIPT_FILENAME']);
    $templates_dir=(isset($arrConf['templates_dir']))?$arrConf['templates_dir']:'themes';
    $local_templates_dir="$base_dir/modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];
	
    // se conecta a la base
    $pDB = new paloDB($arrConf["cadena_dsn"]);
    if (!is_object($pDB->conn) || $pDB->errMsg!="") {
        $smarty->assign("mb_message", _tr("Error when connecting to database")." ".$pDB->errMsg);
        return '';
    }

    $smarty->assign('LABEL_FIND', _tr('Find'));

    // Parámetros por omisión
    $url = array('menu' => $module_name);
    $paramFiltroBase = $paramFiltro = array(
        'date_start'    => date("d M Y"), 
        'date_end'      => date("d M Y"),
        'call_type'     => 'incoming',
        'call_state'    => 'any',
    );
    foreach (array_keys($paramFiltro) as $k) {
        if (!is_null(getParameter($k))){
            $paramFiltro[$k] = getParameter($k);
        }
    }

    if (!isset($paramFiltro['call_type']))
        $paramFiltro['call_type'] = 'incoming';
    if (!in_array($paramFiltro['call_type'], array('incoming', 'outgoing')))
        $paramFiltro['call_type'] = 'incoming';
    $callStates = array(
        "any"  => _tr("Todas"),
        "Success"  => _tr("Exitosas"),
        "NoAnswer"  => _tr("No Realizadas"),
        "Abandoned"  => _tr("Abandonadas")
    );
    if ($paramFiltro['call_type'] == 'incoming') unset($callStates['NoAnswer']);
    $formCampos = array(
        "date_start"  => array(
            "LABEL"                  => _tr("Date Init"),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "DATE",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "ereg",
            "VALIDATION_EXTRA_PARAM" => "^[[:digit:]]{1,2}[[:space:]]+[[:alnum:]]{3}[[:space:]]+[[:digit:]]{4}$"),
        "date_end"    => array(
            "LABEL"                  => _tr("Date End"),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "DATE",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "ereg",
            "VALIDATION_EXTRA_PARAM" => "^[[:digit:]]{1,2}[[:space:]]+[[:alnum:]]{3}[[:space:]]+[[:digit:]]{4}$"),
        "call_type"  => array(
            "LABEL"                  => _tr("Tipo"),
            "REQUIRED"               => "no",
            "INPUT_TYPE"             => "SELECT",
            "INPUT_EXTRA_PARAM"      => array(
                "incoming"  => _tr("Ingoing"),
                "outgoing"  => _tr("Outgoing")),
            "VALIDATION_TYPE"        => "ereg",
            "VALIDATION_EXTRA_PARAM" => "^(incoming|outgoing)$",
            "ONCHANGE"               => "submit();"),
        "call_state"  => array(
            "LABEL"                  => _tr("Estado"),
            "REQUIRED"               => "no",
            "INPUT_TYPE"             => "SELECT",
            "INPUT_EXTRA_PARAM"      => $callStates,
            "VALIDATION_TYPE"        => "ereg",
            "VALIDATION_EXTRA_PARAM" => "^(".implode('|', array_keys($callStates)).")$"),
    );
    $oFilterForm = new paloForm($smarty, $formCampos);
    $oGrid = new paloSantoGrid($smarty);
    $oGrid->enableExport();
    $oGrid->setTitle(_tr("Hold Time"));
    $oGrid->pagingShow(FALSE); 
    $oGrid->setNameFile_Export(_tr("Hold Time"));
    $oGrid->showFilter($oFilterForm->fetchForm("$local_templates_dir/filter.tpl", "", $paramFiltro));

    $url = array_merge($url, $paramFiltro);
    $oGrid->setURL($url);

    if ($paramFiltro['call_state'] == 'any') $paramFiltro['call_state'] = NULL;
    
    $paramFiltro['date_start'] = translateDate($paramFiltro['date_start']);
    $paramFiltro['date_end'] = translateDate($paramFiltro['date_end']);
    
    $oCalls = new paloSantoHoldTime($pDB);
    $arrCalls = $oCalls->leerHistogramaEsperaCola(
        $paramFiltro['call_type'],
        $paramFiltro['call_state'],
        $paramFiltro['date_start'],
        $paramFiltro['date_end']);
    if (!is_array($arrCalls)) {
        $smarty->assign("mb_title", _tr("Error"));
        $smarty->assign("mb_message", $oCalls->errMsg);
    	return '';
    }

    $bExportando = $oGrid->isExportAction();

    $arrColumnas = array(_tr("Cola"),"0 - 10","11 - 20","21 - 30","31 - 40","41 - 50","51 - 60","61 >",
        _tr("Tiempo Promedio Espera(Seg)"),_tr("Espera Mayor(seg)"),_tr("Total Calls"));
    $oGrid->setColumns($arrColumnas);
    $arrData = array();
    $histTotal = array_fill(0, 7, 0);
    $iMaxWait = $iTotalCalls = $iTotalWait = 0;
    foreach ($arrCalls as $cola => $histdata) {
    	$arrTmp = $histdata['hist'];
        array_unshift($arrTmp, $cola);
        $arrTmp[] = number_format(($histdata['total_calls'] > 0) ? ($histdata['total_wait'] / $histdata['total_calls']) : 0, 0);
        $arrTmp[] = $histdata['max_wait'];
        $arrTmp[] = $histdata['total_calls'];
        $arrData[] = $arrTmp;
        
        if ($iMaxWait < $histdata['max_wait']) $iMaxWait = $histdata['max_wait'];
        $iTotalCalls += $histdata['total_calls'];
        $iTotalWait += $histdata['total_wait'];
        for ($i = 0; $i < count($histdata['hist']); $i++)
            $histTotal[$i] += $histdata['hist'][$i];
    }
    array_unshift($histTotal, _tr("Total"));
    $histTotal[] = number_format(($iTotalCalls > 0) ? ($iTotalWait / $iTotalCalls) : 0, 0);
    $histTotal[] = $iMaxWait;
    $histTotal[] = $iTotalCalls;
    $sTagInicio = (!$bExportando) ? '<b>' : '';
    $sTagFinal = ($sTagInicio != '') ? '</b>' : '';
    for ($i = 0; $i < count($histTotal); $i++)
        $histTotal[$i] = $sTagInicio.$histTotal[$i].$sTagFinal;
    $arrData[] = $histTotal;

    $oGrid->setData($arrData);
    return $oGrid->fetchGrid();
}
?>