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

require_once 'libs/misc.lib.php';
require_once 'libs/paloSantoForm.class.php';
require_once 'libs/paloSantoGrid.class.php';

define ('LIMITE_PAGINA', 50);

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

    require_once "modules/$module_name/libs/paloSantoLoginLogout.class.php";

    //folder path for custom templates
    $base_dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $templates_dir = (isset($arrConf['templates_dir']))?$arrConf['templates_dir']:'themes';
    $local_templates_dir = "$base_dir/modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];

    $pDB = new paloDB($arrConf["cadena_dsn"]);
    if (!is_object($pDB->conn) || $pDB->errMsg!="") {
        $smarty->assign('mb_message', _tr('Error when connecting to database')." ".$pDB->errMsg);
        return NULL;
    }

    return listadoLoginLogout($pDB, $smarty, $module_name, $local_templates_dir);
}

function listadoLoginLogout($pDB, $smarty, $module_name, $local_templates_dir)
{
    $oCalls = new paloSantoLoginLogout($pDB);
    $smarty->assign(array(
        'SHOW'      =>  _tr('Show'),
        'Filter'    =>  _tr('Find'),
    ));
    
    $arrFormElements = array(
        'date_start'  => array(
            'LABEL'                  => _tr('Date Init'),
            'REQUIRED'               => 'yes',
            'INPUT_TYPE'             => 'DATE',
            'INPUT_EXTRA_PARAM'      => '',
            'VALIDATION_TYPE'        => 'ereg',
            'VALIDATION_EXTRA_PARAM' => '^[[:digit:]]{1,2}[[:space:]]+[[:alnum:]]{3}[[:space:]]+[[:digit:]]{4}$'),
        'date_end'    => array(
            'LABEL'                  => _tr('Date End'),
            'REQUIRED'               => 'yes',
            'INPUT_TYPE'             => 'DATE',
            'INPUT_EXTRA_PARAM'      => '',
            'VALIDATION_TYPE'        => 'ereg',
            'VALIDATION_EXTRA_PARAM' => '^[[:digit:]]{1,2}[[:space:]]+[[:alnum:]]{3}[[:space:]]+[[:digit:]]{4}$'),
        'detailtype'  => array(
            'LABEL'                  => _tr('Tipo'),
            'REQUIRED'               => 'no',
            'INPUT_TYPE'             => 'SELECT',
            'INPUT_EXTRA_PARAM'      => array(
                'D' => _tr('Detallado'),
                'G' => _tr('General')),
            'VALIDATION_TYPE'        => 'text',
            'VALIDATION_EXTRA_PARAM' => ''),
        'queue'  => array(
            'LABEL'                  => _tr('Incoming Queue'),
            'REQUIRED'               => 'no',
            'INPUT_TYPE'             => 'SELECT',
            'INPUT_EXTRA_PARAM'      => generarComboColasEntrantes($oCalls),
            'VALIDATION_TYPE'        => 'text',
            'VALIDATION_EXTRA_PARAM' => '^\d+$'),
    );
    $oFilterForm = new paloForm($smarty, $arrFormElements);

    // Parámetros base y validación de parámetros
    $url = array('menu' => $module_name);
    $paramFiltroBase = $paramFiltro = array(
        'date_start'    =>  date('d M Y'), 
        'date_end'      =>  date('d M Y'),
        'detailtype'    =>  'D',
        'queue'         =>  '',
    );
    foreach (array_keys($paramFiltro) as $k) {
        if (!is_null(getParameter($k))){
            $paramFiltro[$k] = getParameter($k);
        }
    }

    $htmlFilter = $oFilterForm->fetchForm("$local_templates_dir/filter.tpl", "", $paramFiltro);
    if (!$oFilterForm->validateForm($paramFiltro)) {
        $smarty->assign(array(
            'mb_title'      =>  _tr('Validation Error'),
            'mb_message'    =>  '<b>'._tr('The following fields contain errors').':</b><br/>'.
                                implode(', ', array_keys($oFilterForm->arrErroresValidacion)),
        ));
        $paramFiltro = $paramFiltroBase;
    }

    // Tradudir fechas a formato ISO para comparación y para API de CDRs.
    $url = array_merge($url, $paramFiltro);
    $paramFiltro['date_start'] = translateDate($paramFiltro['date_start']).' 00:00:00';
    $paramFiltro['date_end'] = translateDate($paramFiltro['date_end']).' 23:59:59';

    // Consulta y recorte de registros
    $recordset = $oCalls->leerRegistrosLoginLogout($paramFiltro['detailtype'],
        $paramFiltro['date_start'], $paramFiltro['date_end'],
        ((trim($paramFiltro['queue']) == '') ? NULL : $paramFiltro['queue']));
    if (!is_array($recordset)) {
        $smarty->assign(array(
            'mb_title'      =>  _tr('Query Error'),
            'mb_message'    =>  $oCalls->errMsg,
        ));
    	$recordset = array();
    }
    
    $oGrid = new paloSantoGrid($smarty);
    $bExportando = $oGrid->isExportAction();
    $oGrid->setLimit(LIMITE_PAGINA);
    $oGrid->setTotal(count($recordset));
    $offset = $oGrid->calculateOffset();

    // Formato del arreglo de datos a mostrar
    $arrData = array();
    $sTagInicio = (!$bExportando) ? '<b>' : '';
    $sTagFinal = ($sTagInicio != '') ? '</b>' : '';
    $recordSlice = $bExportando
        ? $recordset 
        : array_slice($recordset, $offset, LIMITE_PAGINA);
    foreach ($recordSlice as $tupla) {
    	$arrData[] = array(
            $tupla['number'],
            $tupla['name'],
            $tupla['datetime_init'],
            ($tupla['estado'] == 'ONLINE') 
                ? $sTagInicio.$tupla['datetime_end'].$sTagFinal
                : $tupla['datetime_end'],
            format_time($tupla['duration']),
            format_time($tupla['total_incoming']),
            format_time($tupla['total_outgoing']),
            format_time($tupla['total_incoming'] + $tupla['total_outgoing']),
            number_format(100.0 * (($tupla['duration'] <= 0) 
                ? 0 
                : (($tupla['total_incoming'] + $tupla['total_outgoing']) / $tupla['duration'])), 2),
            _tr($tupla['estado']),
        );
    }

    // Calcular totales de pie de página
    $ktotales = array('duration', 'total_incoming', 'total_outgoing');
    $totales = array_combine($ktotales, array_fill(0, count($ktotales), 0));
    foreach ($recordset as $tupla) {
    	foreach ($ktotales as $k) $totales[$k] += $tupla[$k];
    }
    $arrData[] = array(
        $sTagInicio._tr('Total').$sTagFinal,
        '', '', '',
        $sTagInicio.format_time($totales['duration']).$sTagFinal,
        $sTagInicio.format_time($totales['total_incoming']).$sTagFinal,
        $sTagInicio.format_time($totales['total_outgoing']).$sTagFinal,
        $sTagInicio.format_time($totales['total_incoming'] + $totales['total_outgoing']).$sTagFinal,
        '', ''
    );

    $oGrid->enableExport();

    $oGrid->setURL($url);
    $oGrid->setData($arrData);
    $arrColumnas = array(
        _tr('Agente'),
        _tr('Nombre'),
        _tr('Date Init'),
        _tr('Date End'),
        _tr('Total Login'),
        _tr('Llamadas entrantes'),
        _tr('Llamadas salientes'),
        _tr('Tiempo en Llamadas'),
        _tr('Service(%)'),
        _tr('Estado'));
    $oGrid->setColumns($arrColumnas);
    $oGrid->setTitle(_tr('Login Logout'));
    $oGrid->pagingShow(true);
    $oGrid->setNameFile_Export(_tr('Login Logout'));
 
    $oGrid->showFilter($htmlFilter);
    return $oGrid->fetchGrid();
}

function generarComboColasEntrantes($oCalls)
{
    $comboColas = array(
        ''  =>  _tr('All'),
    );
	return $comboColas + $oCalls->leerColasEntrantesValidas();
}

function format_time($iSec)
{
    $iMin = ($iSec - ($iSec % 60)) / 60; $iSec %= 60;
    $iHora =  ($iMin - ($iMin % 60)) / 60; $iMin %= 60;
    return sprintf('%02d:%02d:%02d', $iHora, $iMin, $iSec);
}
?>