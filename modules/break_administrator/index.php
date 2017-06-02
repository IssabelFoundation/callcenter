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
require_once "libs/paloSantoTrunk.class.php";
include_once "libs/paloSantoConfig.class.php";
include_once "libs/paloSantoGrid.class.php";

require_once "modules/agent_console/libs/elastix2.lib.php";

/*
  BASE CAMPAIGN
CREATE TABLE break (
    id             INTEGER PRIMARY KEY,
    name           VARCHAR(250) NOT NULL,
    description    VARCHAR(250)

, status varchar(1) Not NULL default 'A');

*/

function _moduleContent(&$smarty, $module_name)
{
    require_once "modules/$module_name/libs/PaloSantoBreaks.class.php";
    require_once "modules/$module_name/configs/default.conf.php";
    global $arrConf;

    // Se fusiona la configuración del módulo con la configuración global
    $arrConf = array_merge($arrConf, $arrConfModule);

    load_language_module($module_name);

    //folder path for custom templates
    $base_dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $templates_dir = (isset($arrConfig['templates_dir']))?$arrConfig['templates_dir']:'themes';
    $local_templates_dir = "$base_dir/modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];

    // se conecta a la base
    $pDB = new paloDB($arrConf["cadena_dsn"]);
    if(!empty($pDB->errMsg)) {
        $smarty->assign("mb_message", _tr("Error when connecting to database")."<br/>".$pDB->errMsg);
    }

    $sAccion = getParameter('action');
    $smarty->assign(array(
        'MODULE_NAME'   =>  $module_name,
        'ACTION'        =>  $sAccion,
        "CANCEL"        =>  _tr("Cancel"),
    ));

    switch ($sAccion) {
    case 'new':
        $contenidoModulo = nuevoBreak($smarty, $module_name, $pDB, $local_templates_dir);
        break;
    case 'edit':
        $contenidoModulo = editarBreak($smarty, $module_name, $pDB, $local_templates_dir);
        break;    
    case 'list':
    default:
        $contenidoModulo = listBreaks($smarty, $module_name, $pDB, $local_templates_dir);
        break;
    }
    return $contenidoModulo;
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

function listBreaks(&$smarty, $module_name, &$pDB, $local_templates_dir)
{
    $oBreaks = new PaloSantoBreaks($pDB);

    // Procesamiento de la activación/desactivación de breaks
    $r = TRUE;
    if (isset($_POST['activate']) && isset($_POST['id_break'])) {
        $r = $oBreaks->activateBreak($_POST['id_break'], 'A');
        if (!$r) {
            $smarty->assign("mb_title",_tr('Activate Error'));
            $smarty->assign("mb_message",_tr('Error when Activating the Break'));
        }
    } elseif (isset($_POST['deactivate']) && isset($_POST['id_break'])) {
        $r = $oBreaks->activateBreak($_POST['id_break'], 'I');
        if (!$r) {
            $respuesta->addAssign("mb_title","innerHTML",_tr("Desactivate Error")); 
            $respuesta->addAssign("mb_message","innerHTML",_tr("Error when desactivating the Break")); 
        }
    }

    // Procesamiento de la visualización de breaks
    $oGrid = new paloSantoGrid($smarty);
    $limit=30;
    $total=$oBreaks->countBreaks();
    if($total===false){
        $smarty->assign("mb_title", _tr("ERROR"));
        $smarty->assign("mb_message", _tr("Failed to fetch breaks")."<br/>".$pDB->errMsg);
        $total=0;
    }
    $oGrid->setLimit($limit);
    $oGrid->setTotal($total);
    $offset = $oGrid->calculateOffset();
    $oGrid->setTitle(_tr("Breaks List"));
    $oGrid->setWidth("99%");
    $oGrid->setIcon("images/list.png");
    $oGrid->setURL(array('menu' => $module_name), array('nav', 'start'));
    $oGrid->setColumns(array('', _tr("Name Break"), _tr("Description Break"), _tr("Status"), _tr("Options")));

    //obtenemos los breaks
    $arrBreaks = $oBreaks->getBreaks(null,'all',$limit,$offset); // Todos los breaks en todos los estados
    if (!is_array($arrBreaks)) {
        $smarty->assign("mb_title", _tr("ERROR"));
        $smarty->assign("mb_message", _tr("Failed to fetch breaks")."<br/>".$pDB->errMsg);
        $arrBreaks = array();
    }
    
    function listBreaks_formatHTML($break, $param)
    {
        return array(
            "<input class=\"input\" type=\"radio\" name=\"id_break\" value=\"{$break['id']}\"/>",
            htmlentities($break['name'], ENT_COMPAT, "UTF-8"),
            htmlentities($break['description'], ENT_COMPAT, "UTF-8").'&nbsp;',
            ($break['status'] == 'A') ? _tr('Active') : _tr('Inactive'),
            ($break['status'] == 'A')
                ? "<a href=\"?menu={$param['module_name']}&amp;action=edit&amp;id_break={$break['id']}\">["._tr('Edit Break').']</a>'
                : '&nbsp;',
        );
    }
    $arrData = array();
    if (count($arrBreaks) > 0)
        $arrData = array_map('listBreaks_formatHTML',
            $arrBreaks,
            array_fill(0, count($arrBreaks), array('module_name' => $module_name)));

    // Construcción de la rejilla de vista
    $oGrid->addNew("?menu=$module_name&action=new", _tr("Create New Break"), TRUE);
    
    $oGrid->addSubmitAction("activate",_tr("Activate"));
    $oGrid->addSubmitAction("deactivate",_tr("Desactivate"));
    
    return $oGrid->fetchGrid(array(), $arrData);    
}

function nuevoBreak(&$smarty, $module_name, $pDB, $local_templates_dir)
{
    return mostrarFormularioModificarBreak($smarty, $module_name, $pDB, $local_templates_dir, NULL);
}

function editarBreak(&$smarty, $module_name, $pDB, $local_templates_dir)
{
    $id_break = getParameter('id_break');
    if (is_null($id_break) || !preg_match('/^\d+$/', $id_break)) {
        Header("Location: ?menu=$module_name");
        return '';
    }
    return mostrarFormularioModificarBreak($smarty, $module_name, $pDB, $local_templates_dir, $id_break);
}

function mostrarFormularioModificarBreak(&$smarty, $module_name, $pDB, $local_templates_dir, $id_break)
{
    $bNuevoBreak = is_null($id_break);

    $smarty->assign(array(
        'SAVE'  =>  $bNuevoBreak ? _tr('Save') : _tr('Apply Changes'),
    ));

    if (isset($_POST['cancel'])) {
        Header("Location: ?menu=$module_name");
        return '';
    }

    $smarty->assign('FRAMEWORK_TIENE_TITULO_MODULO', existeSoporteTituloFramework());

    // Para modificación, se lee la información del break
    $oBreaks = new PaloSantoBreaks($pDB);
    if (!$bNuevoBreak) {
        $_POST['id_break'] = $id_break;
        $infoBreak = $oBreaks->getBreaks($id_break);
        if (!is_array($infoBreak)) {
            // No se puede recuperar información actual del break
            $smarty->assign("mb_title", _tr("ERROR"));
            $smarty->assign("mb_message", $pDB->errMsg);
        } elseif (count($infoBreak) <= 0) {
            // El break no se encuentra
            Header("Location: ?menu=$module_name");
            return '';
        } else {
            // Se asignan los valores a POST a menos que ya se encuentren valores
            if (!isset($_POST['nombre'])) $_POST['nombre'] = $infoBreak[0]['name'];
            if (!isset($_POST['descripcion'])) $_POST['descripcion'] = $infoBreak[0]['description'];
        }
    }
    $formCampos = array(
        "nombre"    =>    array(
                "LABEL"                  => _tr("Name Break"),
                "REQUIRED"               => "yes",
                "INPUT_TYPE"             => "TEXT",
                "INPUT_EXTRA_PARAM"      => array("size" => "40"),
                "VALIDATION_TYPE"        => "text",
                "VALIDATION_EXTRA_PARAM" => "",
        ),
        "descripcion" => array(
                "LABEL"                  => _tr("Description Break"),
                "REQUIRED"               => "yes",
                "INPUT_TYPE"             => "TEXTAREA",
                "INPUT_EXTRA_PARAM"      => "",
                "VALIDATION_TYPE"        => "text",
                "VALIDATION_EXTRA_PARAM" => "",
                "ROWS"                   => "2",
                "COLS"                   => "33"
        ),
        'id_break' => array(
                'LABEL'                 => 'id_break',
                'REQUIRED'              => 'no',
                'INPUT_TYPE'            => 'HIDDEN',
                "VALIDATION_TYPE"        => "ereg",
                "VALIDATION_EXTRA_PARAM" => "^[[:digit:]]+$",
        ),
    );
    $oForm = new paloForm($smarty, $formCampos);
    $oForm->setEditMode();

    // Procesar los cambios realizados
    if (isset($_POST['save'])) {
        if(!$oForm->validateForm($_POST)) {
            $smarty->assign("mb_title", _tr("Validation Error"));
            $arrErrores = $oForm->arrErroresValidacion;
            $strErrorMsg = "<b>"._tr('The following fields contain errors').":</b><br/>";
            if (is_array($arrErrores) && count($arrErrores) > 0) {
                $strErrorMsg .= implode(', ', array_keys($arrErrores));
            }
            $smarty->assign("mb_message", $strErrorMsg);
        } else {
            $exito  = $bNuevoBreak
                ? $oBreaks->createBreak($_POST['nombre'], $_POST['descripcion'])
                : $oBreaks->updateBreak($id_break, $_POST['nombre'], $_POST['descripcion']);

            if ($exito) {
                header("Location: ?menu=$module_name");
            } else {
                $smarty->assign("mb_title", _tr("Validation Error"));
                $smarty->assign("mb_message", $oBreaks->errMsg);
            } 
        }
    }

    // Mostrar el formulario con los valores
    $smarty->assign('icon', 'images/kfaxview.png');
    $contenidoModulo = $oForm->fetchForm(
        "$local_templates_dir/new.tpl",
        $bNuevoBreak ? _tr('New Break') : _tr('Edit Break'),
        $_POST);
    return $contenidoModulo;
}
?>
