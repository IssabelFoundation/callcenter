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
  $Id: data_fom $ */

require_once "libs/paloSantoForm.class.php";
require_once "libs/paloSantoGrid.class.php";
require_once "libs/misc.lib.php";

require_once "modules/agent_console/libs/elastix2.lib.php";
require_once "modules/agent_console/libs/JSON.php";

function _moduleContent(&$smarty, $module_name)
{
    //include module files
    require_once "modules/$module_name/configs/default.conf.php";
    require_once "modules/$module_name/libs/paloSantoDataForm.class.php";
    
    global $arrConf;

    // Se fusiona la configuración del módulo con la configuración global
    $arrConf = array_merge($arrConf, $arrConfModule);

    load_language_module($module_name);

    //folder path for custom templates
    $base_dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $templates_dir = (isset($arrConf['templates_dir'])) ? $arrConf['templates_dir'] : 'themes';
    $local_templates_dir = "$base_dir/modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];

    $smarty->assign("MODULE_NAME", $module_name);

    $pDB = new paloDB($arrConf['cadena_dsn']);
    if (!is_object($pDB->conn) || $pDB->errMsg!="") {
        $smarty->assign("mb_message", _tr('Error when connecting to database')." ".$pDB->errMsg);
    }
    
    switch (getParameter('action')) {
    case 'add':
    case 'edit':
        return modificarFormulario($pDB, $smarty, $module_name, $local_templates_dir);
    case 'save':
        return guardarFormulario($pDB, $smarty, $module_name, $local_templates_dir);
    case 'list':
    default:
        return listarFormularios($pDB, $smarty, $module_name, $local_templates_dir);
    }
}

function listarFormularios($pDB, $smarty, $module_name, $local_templates_dir)
{
    $arrColumns = array('', _tr('Form Name'), _tr('Form Description'), _tr('Status'), _tr('Options'));
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

    $oGrid = new paloSantoGrid($smarty);
    $oGrid->pagingShow(true);
    $oGrid->setLimit($limit);
    $oGrid->addNew("?menu=$module_name&action=add", _tr('Create New Form'), TRUE);
    $oGrid->addSubmitAction('activate', _tr('Activate'));
    $oGrid->addSubmitAction('deactivate', _tr('Desactivate'));
    $oGrid->deleteList('Are you sure you wish to delete form?', 'remove', _tr('Delete'));
    $oGrid->setColumns($arrColumns);
    $oGrid->setURL($url);
    $oGrid->setTitle(_tr('Form List'));
    
    // Ejecutar operaciones indicadas en formularios
    $oDataForm = new paloSantoDataForm($pDB);
    if (isset($_POST['id'])) {
        $bExito = TRUE;
        if (isset($_POST['activate'])) {
            $mb = array(
                'mb_title'  => _tr('Activate Error'), 
                'mb_message'=> _tr('Error when Activating the form')
            );
            $bExito = $oDataForm->activacionFormulario($_POST['id'], TRUE);
        } elseif (isset($_POST['deactivate'])) {
            $mb = array(
                'mb_title'  => _tr('Desactivate Error'), 
                'mb_message'=> _tr('Error when eliminating the form')
            );
            $bExito = $oDataForm->activacionFormulario($_POST['id'], FALSE);
        } elseif (isset($_POST['remove'])) {
            $mb = array(
                'mb_title'  => _tr('Delete Error'), 
                'mb_message'=> _tr('Error when deleting the Form')
            );
            $bExito = $oDataForm->eliminarFormulario($_POST['id']);
        }
        if (!$bExito) {
            $mb['mb_message'] .= ': '.$oDataForm->errMsg;
            $smarty->assign($mb);
        }
    }
    
    // Obtener listado de formularios
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
    	    '<input type="radio" name="id" value="'.$tuplaForm['id'].'"/>',
            htmlentities($tuplaForm['nombre'], ENT_COMPAT, 'UTF-8'),
            (empty($tuplaForm['descripcion']) ? '&nbsp;' : htmlentities($tuplaForm['descripcion'], ENT_COMPAT, 'UTF-8')),
            ($tuplaForm['estatus'] == 'I' ? _tr('Inactive') : _tr('Active')),
    	    ($tuplaForm['estatus'] == 'I'
    	        ? '&nbsp;'
                : "<a href='".construirURL(array('menu' => $module_name, 'action' => 'edit', 'id' => $tuplaForm['id']))."'>"._tr('Edit')."</a>"),
        );
    }

    $oFilterForm = new paloForm($smarty, array(
        'cbo_estado'    =>    array(
            "LABEL"                 => _tr('Status'),
            "REQUIRED"              => "no",
            "INPUT_TYPE"            => "SELECT",
            "INPUT_EXTRA_PARAM"     => $cbo_estados,
            "VALIDATION_TYPE"       => "text",
            "VALIDATION_EXTRA_PARAM"=> "",
            "ONCHANGE"              => 'submit();',
        ),
    ));
    $oGrid->addFilterControl(
        _tr("Filter applied ")._tr("Status")." = ".$cbo_estados[$cbo_estado],
        $paramFiltro,
        array("cbo_estado" =>'A'),
        true);
    $oGrid->showFilter($oFilterForm->fetchForm("$local_templates_dir/filter.tpl", "", $paramFiltro));
    $oGrid->setData($arrData);
    return $oGrid->fetchGrid();
}

function modificarFormulario($pDB, $smarty, $module_name, $local_templates_dir)
{
    if (isset($_POST['cancel'])) {
        Header('Location: ?menu='.$module_name);
        return;
    }
    
    $oForm = new paloForm($smarty, array(
        'form_nombre'    =>    array(
            "LABEL"                => _tr('Form Name'),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "TEXT",
            "INPUT_EXTRA_PARAM"      => array("size" => "60"),
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => "",
        ),
        'form_description'    =>    array(
            "LABEL"                => _tr('Form Description'),
            "REQUIRED"               => "no",
            "INPUT_TYPE"             => "TEXTAREA",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => "",
            "COLS"                   => "33",
            "ROWS"                   => "2",
        ),
        'id_formulario'     =>  array(
            'LABEL'                 =>  '',
            'INPUT_TYPE'            =>  'HIDDEN',
            'REQUIRED'              =>  'no',
            'VALIDATION_TYPE'       =>  'numeric',
        ),
    ));
    $valoresForm = array();
    $camposForm = array();
    if (isset($_REQUEST['id'])) {
        $oDataForm = new paloSantoDataForm($pDB);
        $tupla = $oDataForm->leerFormulario($_REQUEST['id']);
        if (!is_array($tupla) || count($tupla) <= 0) {
            Header('Location: ?menu='.$module_name);
            return;
        } 
        $camposForm = $oDataForm->leerCamposFormulario($_REQUEST['id']);
        if (!is_array($camposForm)) {
            Header('Location: ?menu='.$module_name);
            return;
        }
        $sTitulo = _tr('Edit Form').' "'.$tupla['nombre'].'"';
        $smarty->assign(array(
            'id_formulario' =>  $_REQUEST['id'],
        ));
        $valoresForm = array(
            'id_formulario'     =>  $tupla['id'],
            'form_nombre'       =>  $tupla['nombre'],
            'form_description'  =>  $tupla['descripcion'],
        );
    } else {
        $sTitulo = _tr('New Form');
    }
    
    $json = new Services_JSON();
    $smarty->assign(array(
        'icon'              =>  'images/kfaxview.png',
        'TOOLTIP_DRAGDROP'  =>  _tr('Drag and drop to reorder fields'),
        'CANCEL'            =>  _tr('Cancel'),
        'SAVE'              =>  _tr('Save'),
        'LABEL_DELETE'      =>  _tr('Delete'),
        'LABEL_ORDER'       =>  _tr('Order'),
        'LABEL_NAME'        =>  _tr('Field Name'),
        'LABEL_TYPE'        =>  _tr('Type'),
        'LABEL_ENUMVAL'     =>  _tr('Values Field'),
        'LABEL_FFADD'       =>  '+',
        'LABEL_FFDEL'       =>  '-',
        'LABEL_NEWFIELD'    =>  _tr('new field'),
        'CMB_TIPO'          =>  combo(array(
            'TEXT'      =>  _tr('Type Text'),
            'LIST'      =>  _tr('Type List'),
            'DATE'      =>  _tr('Type Date'),
            'TEXTAREA'  =>  _tr('Type Text Area'),
            'LABEL'     =>  _tr('Type Label'),
        ), 'TEXT'),
        'CAMPOS_FORM'       =>  $json->encode($camposForm),

        // Estos campos sólo se asignan para hacer aparecer el widget de mensajes
        // con el propósito de manipularlo
        'mb_title'      =>  '<span class="mb_title" id="mb_title">mb_title</span>',
        'mb_message'    =>  '<span class="mb_message" id="mb_message">mb_message</span>',
    ));
    return $oForm->fetchForm("$local_templates_dir/form.tpl", $sTitulo, $valoresForm);
}

function guardarFormulario($pDB, $smarty, $module_name, $local_templates_dir)
{
    Header('Content-Type: application/json');
    $json = new Services_JSON();
    $respuesta = array(
        'action'    =>  'saved',
    );
    $oDataForm = new paloSantoDataForm($pDB);
    if (!$oDataForm->guardarFormulario(
        isset($_POST['id']) ? $_POST['id'] : NULL,
        isset($_POST['nombre']) ? $_POST['nombre'] : '',
        isset($_POST['descripcion']) ? $_POST['descripcion'] : '',
        isset($_POST['formfields']) ? $_POST['formfields'] : array()
    )) {
        $respuesta['action'] = 'error';
        $respuesta['message']['title'] = _tr('Form could not be updated');
        $respuesta['message']['message'] = $oDataForm->errMsg;
    }
    return $json->encode($respuesta);
}
?>