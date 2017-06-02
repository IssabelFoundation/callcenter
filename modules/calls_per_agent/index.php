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
  $Id: index.php,v 1.1.1.1 2007/07/06 21:31:21 gcarrillo Exp $ */

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

require_once "libs/paloSantoGrid.class.php";
require_once "libs/paloSantoDB.class.php";
require_once "libs/paloSantoForm.class.php";
require_once "libs/paloSantoConfig.class.php";
require_once "libs/misc.lib.php";
    
function _moduleContent(&$smarty, $module_name)
{
    //Incluir librería de lenguaje
    load_language_module($module_name);
    
    //include module files
    require_once "modules/$module_name/configs/default.conf.php";
    require_once "modules/$module_name/libs/paloSantoCallPerAgent.class.php";
    global $arrConf;
    
    //folder path for custom templates
    $base_dir=dirname($_SERVER['SCRIPT_FILENAME']);
    $templates_dir=(isset($arrConfig['templates_dir']))?$arrConfig['templates_dir']:'themes';
    $local_templates_dir="$base_dir/modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];

    $urlVars = array('menu' => $module_name);
    
    $smarty->assign(array(
        'menu'      =>  $module_name,
        'Filter'    =>  _tr('Query'),
    ));
    
    // Construcción del formulario de filtro
    $comboTipos = array(
        ''      =>  _tr('All'),
        'IN'    =>  _tr("Ingoing"),
        'OUT'   =>  _tr("Outgoing"),
    );
    $arrFormElements = createFieldFilter($comboTipos);
    $oFilterForm = new paloForm($smarty, $arrFormElements);
    
    // Variables a usar para el URL, consulta, y POST
    $defaultExtraVars = array(
        'date_start'    =>  date('d M Y'),
        'date_end'      =>  date('d M Y'),
        'number'        =>  '',
        'queue'         =>  '',
        'type'          =>  '',
    );
    $arrFilterExtraVars = $defaultExtraVars;
    foreach (array_keys($arrFilterExtraVars) as $k) {
        $v = trim(getParameter($k));
        $arrFilterExtraVars[$k] = (is_null($v) || $v == '') ? $arrFilterExtraVars[$k] : $v;
    }
    
    // Validación del formulario
    if (!$oFilterForm->validateForm($arrFilterExtraVars)) {
        $arrFilterExtraVars = $defaultExtraVars;
        $smarty->assign(array(
            'mb_title'      =>  _tr('Validation Error'),
            'mb_message'    =>  '<b>'._tr('The following fields contain errors').':</b><br/>'.
                                implode(', ', array_keys($oFilterForm->arrErroresValidacion)),
        ));
    }
    
    // Traducción de valores y petición real
    $pDB = new paloDB($cadena_dsn);
    $oCallsAgent = new paloSantoCallsAgent($pDB);
    $fieldPat = array(
        'number'    =>  array(),
        'queue'     =>  array(),
        'type'      =>  array(),
    );
    foreach (array_keys($fieldPat) as $k) {
        if (isset($arrFilterExtraVars[$k]) && $arrFilterExtraVars[$k] != '')
            $fieldPat[$k][] = $arrFilterExtraVars[$k];
    }
    if (count($fieldPat['type']) <= 0) $fieldPat['type'] = array('IN', 'OUT');
    $arrCallsAgentTmp = $oCallsAgent->obtenerCallsAgent(
        translateDate($arrFilterExtraVars['date_start']).' 00:00:00',
        translateDate($arrFilterExtraVars['date_end']).' 23:59:59',
        $fieldPat);
    if (!is_array($arrCallsAgentTmp)) {
        $smarty->assign(array(
            'mb_title'      =>  _tr('ERROR'),
            'mb_message'    =>  $oCallsAgent->errMsg,
        ));
    	$arrCallsAgentTmp = array();
    }
    $totalCallsAgents = count($arrCallsAgentTmp);
    
    // Construcción del reporte final
    $oGrid = new paloSantoGrid($smarty);
    $oGrid->enableExport();   // enable export.
    $oGrid->showFilter($oFilterForm->fetchForm("$local_templates_dir/filter.tpl", '', $arrFilterExtraVars));
    $oGrid->setLimit($totalCallsAgents);
    $oGrid->setTotal($totalCallsAgents + 1);
    $offset = $oGrid->calculateOffset();
    
    // Bloque comun
    $arrData = array();
    $sumCallAnswered = $sumDuration = $timeMayor = 0;
    foreach($arrCallsAgentTmp as $cdr) {
        $arrData[] = array(
            $cdr['agent_number'],
            htmlspecialchars($cdr['agent_name'], ENT_COMPAT, 'UTF-8'),
            $cdr['type'],
            $cdr['queue'],
            $cdr['num_answered'],
            formatoSegundos($cdr['sum_duration']),
            formatoSegundos($cdr['avg_duration']),
            formatoSegundos($cdr['max_duration']),
        );
    
        $sumCallAnswered += $cdr['num_answered'];   // Total de llamadas contestadas
        $sumDuration += $cdr['sum_duration'];       // Total de segundos en llamadas
        $timeMayor = ($timeMayor < $cdr['max_duration']) ? $cdr['max_duration'] : $timeMayor;
    }
    $sTagInicio = (!$oGrid->isExportAction()) ? '<b>' : '';
    $sTagFinal = ($sTagInicio != '') ? '</b>' : '';
    $arrData[] = array(
        $sTagInicio._tr('Total').$sTagFinal,
        '', '', '',
        $sTagInicio.$sumCallAnswered.$sTagFinal,
        $sTagInicio.formatoSegundos($sumDuration).$sTagFinal,
        $sTagInicio.formatoSegundos(($sumCallAnswered > 0) ? ($sumDuration / $sumCallAnswered) : 0).$sTagFinal,
        $sTagInicio.formatoSegundos($timeMayor).$sTagFinal,
    );
    
    // Construyo el URL base
    if(isset($arrFilterExtraVars) && is_array($arrFilterExtraVars) && count($arrFilterExtraVars)>0) {
        $urlVars = array_merge($urlVars, $arrFilterExtraVars);
    }
        
    $oGrid->setURL(construirURL($urlVars, array("nav", "start")));
    $oGrid->setData($arrData);
    $arrColumnas = array(_tr("No.Agent"), _tr("Agent"), _tr("Type"), _tr("Queue"),
        _tr("Calls answered"),_tr("Duration"),_tr("Average"),_tr("Call longest"));
    $oGrid->setColumns($arrColumnas);
    $oGrid->setTitle(_tr("Calls per Agent"));
    $oGrid->pagingShow(false);
    $oGrid->setNameFile_Export(_tr("Calls per Agent"));
     
    $smarty->assign("SHOW", _tr("Show"));
    return $oGrid->fetchGrid();    
}

function formatoSegundos($iSeg)
{
    $iSeg = (int)$iSeg;
    $iHora = $iMinutos = $iSegundos = 0;
    $iSegundos = $iSeg % 60; $iSeg = ($iSeg - $iSegundos) / 60;
    $iMinutos = $iSeg % 60; $iSeg = ($iSeg - $iMinutos) / 60;
    $iHora = $iSeg;
    return sprintf('%02d:%02d:%02d', $iHora, $iMinutos, $iSegundos);
}

function createFieldFilter($arrDataTipo)
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
        "type" => array(
            "LABEL"                  => _tr("Tipo"),
            "REQUIRED"               => "no",
            "INPUT_TYPE"             => "SELECT",
            "INPUT_EXTRA_PARAM"      => $arrDataTipo,
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => "^(IN|OUT)$"),
        "queue" => array(
            "LABEL"                  => _tr("Queue"),
            "REQUIRED"               => "no",
            "INPUT_TYPE"             => "TEXT",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "ereg",
            "VALIDATION_EXTRA_PARAM" => "^[[:digit:]]+$"),
        "number" => array(
            "LABEL"                  => _tr("No.Agent"),
            "REQUIRED"               => "no",
            "INPUT_TYPE"             => "TEXT",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "ereg",
            "VALIDATION_EXTRA_PARAM" => "^[[:digit:]]+$"),
         );
    return $arrFormElements;
}
?>