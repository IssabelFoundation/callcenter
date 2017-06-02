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
*/

require_once "libs/paloSantoGrid.class.php";
require_once "libs/paloSantoForm.class.php";
require_once "libs/misc.lib.php";

require_once "modules/agent_console/libs/elastix2.lib.php";

function _moduleContent(&$smarty, $module_name)
{
    require_once "modules/$module_name/configs/default.conf.php";
    require_once "modules/$module_name/libs/paloSantoDataFormList.class.php";

    load_language_module($module_name);

    //folder path for custom templates
    $base_dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $templates_dir = (isset($arrConfig['templates_dir']))?$arrConfig['templates_dir']:'themes';
    $local_templates_dir = "$base_dir/modules/$module_name/".$templates_dir.'/'.$arrConfig['theme'];

    $pDB = new paloDB($arrConfig['cadena_dsn']);
    if (!is_object($pDB->conn) || $pDB->errMsg!="") {
        $smarty->assign("mb_message", _tr("Error when connecting to database")." ".$pDB->errMsg);
        return '';
    }
    
    switch (getParameter('action')) {
    case 'preview':
        return vistaPreviaFormulario($pDB, $smarty, $module_name, $local_templates_dir);
        break;
    case 'list':
    default:
        return listarFormularios($pDB, $smarty, $module_name, $local_templates_dir);
    }
}

function listarFormularios($pDB, $smarty, $module_name, $local_templates_dir)
{
    $arrColumns = array(_tr('Form Name'), _tr('Form Description'), _tr('Status'), _tr('Options'));
    $cbo_estados = array('all' => _tr('All'), 'A' => _tr('Active'), 'I' => _tr('Inactive'));
    $url = array('menu' => $module_name);
    $limit = 20;
    
    // Validar estado de formulario elegido
    $cbo_estado = getParameter('cbo_estado');
    if (!isset($cbo_estado) || !in_array($cbo_estado, array_keys($cbo_estados))) {
        $cbo_estado = 'A';
    }
    $paramFiltro = array(
        'cbo_estado'    =>  $cbo_estado,
    );
    $url = array_merge($url, $paramFiltro);

    // Preparar grilla
    $oGrid = new paloSantoGrid($smarty);
    $oGrid->pagingShow(true);
    $oGrid->setLimit($limit);
    $oGrid->setColumns($arrColumns);
    $oGrid->setURL($url);
    $oGrid->setTitle(_tr('Form List'));
    
    // Obtener listado de formularios
    $oDataForm = new paloSantoDataFormList($pDB);
    $total = $oDataForm->contarFormularios($cbo_estado);
    if ($total === FALSE) {
        $smarty->assign("mb_message", _tr("Error when connecting to database")." ".$oDataForm->errMsg);
    	return '';
    }
    $oGrid->setTotal($total);
    $offset = $oGrid->calculateOffset();
    $arrDataForm = $oDataForm->listarFormularios($cbo_estado, $limit, $offset);
    if (!is_array($arrDataForm)) {
        $smarty->assign("mb_message", _tr("Error when connecting to database")." ".$oDataForm->errMsg);
        return '';
    }
    $arrData = array();
    foreach ($arrDataForm as $tuplaForm) {
    	$arrData[] = array(
            htmlentities($tuplaForm['nombre'], ENT_COMPAT, 'UTF-8'),
            (empty($tuplaForm['descripcion']) ? '&nbsp;' : htmlentities($tuplaForm['descripcion'], ENT_COMPAT, 'UTF-8')),
            ($tuplaForm['estatus'] == 'I' ? _tr('Inactive') : _tr('Active')),
            "<a href='".construirURL(array('menu' => $module_name, 'action' => 'preview', 'id' => $tuplaForm['id']))."'>"._tr('Preview')."</a>",
        );
    }

    //FILTER
    $_POST['cbo_estado'] = $cbo_estado;
    $oGrid->addFilterControl(_tr("Filter applied ")._tr("Status")." = ".$cbo_estados[$cbo_estado], $_POST, array("cbo_estado" =>'all'),true);

    $arrFormFilter = formFilter($cbo_estados);
    $oFilterForm = new paloForm($smarty, $arrFormFilter);
    $htmlFilter = $oFilterForm->fetchForm("$local_templates_dir/filter.tpl", "", $_POST);
    $oGrid->showFilter(trim($htmlFilter));

    $oGrid->setData($arrData);
    return $oGrid->fetchGrid();
}

function formFilter($estados)
{
    $arrFilter = array( 
            'cbo_estado'    =>    array(
                "LABEL"                => _tr('Status'),
                "REQUIRED"               => "no",
                "INPUT_TYPE"             => "SELECT",
                "INPUT_EXTRA_PARAM"      => $estados,
                "VALIDATION_TYPE"        => "text",
                "VALIDATION_EXTRA_PARAM" => "",
                "ONCHANGE"               => 'submit();',
        ),
    );
    return $arrFilter;
}

function vistaPreviaFormulario($pDB, $smarty, $module_name, $local_templates_dir)
{
    $smarty->assign('FRAMEWORK_TIENE_TITULO_MODULO', existeSoporteTituloFramework());    
    
    $oDataForm = new paloSantoDataFormList($pDB);
    $formdata = $oDataForm->generarFormulario(getParameter('id'));
    if (!is_array($formdata) || count($formdata) <= 0) {
        Header("Location: ?menu=$module_name");
    	return;
    }

    /* Esta invocación de paloForm no produce salida. Sólo se la realiza para
     * recoger las variables asignadas con get_template_vars() */ 
    $oForm = new paloForm($smarty, $formdata['campos']);
    $oForm->fetchForm("$local_templates_dir/preview.tpl", '', array());
    $listacampos = array();
    foreach (array_keys($formdata['campos']) as $k) {
    	$listacampos[] = $smarty->get_template_vars($k);
    }

    $oForm = new paloForm($smarty, array(
        'form_nombre'    =>    array(
            "LABEL"                => _tr("Form Name"),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "TEXT",
            "INPUT_EXTRA_PARAM"      => array("size" => "40"),
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => "",
        ),
        'form_description'    =>    array(
            "LABEL"                => _tr("Form Description"),
            "REQUIRED"               => "no",
            "INPUT_TYPE"             => "TEXTAREA",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => "",
            "COLS"                   => "33",
            "ROWS"                   => "2",
        ),
    ));
    $oForm->setViewMode(); // Esto es para activar el modo "preview"
    $smarty->assign('listacampos', $listacampos);
    $template = (count($formdata['campos']) > 0) ? 'listacampos.tpl' : 'vacio.tpl';
    $smarty->assign('formulario', $smarty->fetch("$local_templates_dir/$template"));
    $smarty->assign('icon', 'images/kfaxview.png');
    return $oForm->fetchForm("$local_templates_dir/preview.tpl",
        _tr('Form'),
        array(
            'form_nombre'       => $formdata['nombre'],
            'form_description'  =>  $formdata['descripcion'],
        ));
}
?>