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
require_once "libs/paloSantoDB.class.php";
require_once "libs/paloSantoGrid.class.php";
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
    load_language_module($module_name);

    //include module files
    require_once "modules/$module_name/configs/default.config.php";
    global $arrConf;

    // Se fusiona la configuración del módulo con la configuración global
    $arrConf = array_merge($arrConf, $arrConfModule);

    require_once "modules/$module_name/libs/paloSantoReportsCalls.class.php";
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

    // Parámetros por omisión
    $url = array('menu' => $module_name);
    $paramFiltroBase = $paramFiltro = array(
        'txt_fecha_init'    => date("d M Y"),
        'txt_fecha_end'     => date("d M Y"),
    );
    foreach (array_keys($paramFiltro) as $k) {
        if (!is_null(getParameter($k))){
            $paramFiltro[$k] = getParameter($k);
        }
    }
    
    $smarty->assign("btn_consultar",_tr('Find'));
    $smarty->assign("module_name",$module_name);

    $formCampos = array(
        "txt_fecha_init"  => array(
            "LABEL"                  => _tr("Date Init"),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "DATE",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "ereg",
            "VALIDATION_EXTRA_PARAM" => "^[[:digit:]]{1,2}[[:space:]]+[[:alnum:]]{3}[[:space:]]+[[:digit:]]{4}$"),
        "txt_fecha_end"    => array(
            "LABEL"                  => _tr("Date End"),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "DATE",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "ereg",
            "VALIDATION_EXTRA_PARAM" => "^[[:digit:]]{1,2}[[:space:]]+[[:alnum:]]{3}[[:space:]]+[[:digit:]]{4}$"),
    );
    $oFilterForm = new paloForm($smarty, $formCampos);
    $oGrid = new paloSantoGrid($smarty);
    $oGrid->enableExport();
    $oGrid->setTitle(_tr("List Calls"));
    $oGrid->pagingShow(FALSE);
    $oGrid->setNameFile_Export(_tr("List Calls"));
    $oGrid->setIcon('images/list.png');
    $oGrid->showFilter($oFilterForm->fetchForm("$local_templates_dir/form.tpl", "", $paramFiltro));

    $url = array_merge($url, $paramFiltro);
    $oGrid->setURL($url);
    $bExportando = $oGrid->isExportAction();
    
    $paramFiltro['txt_fecha_init'] = translateDate($paramFiltro['txt_fecha_init']);
    $paramFiltro['txt_fecha_end'] = translateDate($paramFiltro['txt_fecha_end']);

    $oReportsCalls = new paloSantoReportsCalls($pDB);
    
    $arrDatosReporte = $oReportsCalls->leerReporteLlamadas(
        $paramFiltro['txt_fecha_init'],
        $paramFiltro['txt_fecha_end']);
    if (!is_array($arrDatosReporte)) {
        $smarty->assign("mb_title", _tr("Error"));
        $smarty->assign("mb_message", $oReportsCalls->errMsg);
        return '';
    }

    $arrColumnas = array(_tr("Queue"),_tr("Successful"),_tr("Left"),_tr("Time Hopes"),_tr("Total Calls"));
    $oGrid->setColumns($arrColumnas);
    $arrData = array();
    $filaTotal = array_fill(0, 4, 0);
    foreach ($arrDatosReporte as $cola => $infoCola) {
        $filaCola = array($infoCola['success'], $infoCola['abandoned'], $infoCola['wait_sec'], $infoCola['total']);
        for ($i = 0; $i < count($filaCola); $i++)
            $filaTotal[$i] += $filaCola[$i];
        $filaCola[2] = format_time($filaCola[2]);
        array_unshift($filaCola, $cola);
        $arrData[] = $filaCola;
    }
    $filaTotal[2] = format_time($filaTotal[2]);
    array_unshift($filaTotal, _tr('Total'));
    $sTagInicio = (!$bExportando) ? '<b>' : '';
    $sTagFinal = ($sTagInicio != '') ? '</b>' : '';
    for ($i = 0; $i < count($filaTotal); $i++)
        $filaTotal[$i] = $sTagInicio.$filaTotal[$i].$sTagFinal;
    $arrData[] = $filaTotal;
    
    $oGrid->setData($arrData);
    return $oGrid->fetchGrid();
}

function format_time($iSec)
{
    $iMin = ($iSec - ($iSec % 60)) / 60; $iSec %= 60;
    $iHora =  ($iMin - ($iMin % 60)) / 60; $iMin %= 60;
    return sprintf('%02d:%02d:%02d', $iHora, $iMin, $iSec);
}

?>