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
  $Id: paloSantoCampaignCC.class.php,v 1.2 2008/06/06 07:15:07 cbarcos Exp $ */

require_once "libs/paloSantoForm.class.php";
require_once "libs/misc.lib.php";
include_once "libs/paloSantoConfig.class.php";
include_once "libs/paloSantoGrid.class.php";

require_once "modules/agent_console/libs/elastix2.lib.php";

function _moduleContent(&$smarty, $module_name)
{
    require_once "modules/$module_name/libs/externalUrl.class.php";

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
    $sAction = 'list_urls';
    if (isset($_GET['action'])) $sAction = $_GET['action'];
    switch ($sAction) {
    case 'new_url':
        $contenidoModulo = newURL($pDB, $smarty, $module_name, $local_templates_dir);
        break;
    case 'edit_url':
        $contenidoModulo = editURL($pDB, $smarty, $module_name, $local_templates_dir);
        break;
    case 'list_urls':
    default:
        $contenidoModulo = listURL($pDB, $smarty, $module_name, $local_templates_dir);
        break;
    }

    return $contenidoModulo;
}

function descOpenType()
{
	return array(
        'window'    =>  _tr('New window'),
        'iframe'    =>  _tr('Embedded frame'),
        'jsonp'     =>  _tr('JSONP'),
    );
}

function listURL($pDB, $smarty, $module_name, $local_templates_dir)
{
	$urls = new externalUrl($pDB);
    $grid = new paloSantoGrid($smarty);
    $dtypes = descOpenType();

    // para el pagineo
    $url = array('menu' => $module_name);
    $grid->setURL($url);
    $grid->setLimit(15);
    $grid->setTotal($urls->countURLs());
    $offset = $grid->calculateOffset();
    $listaUrls = $urls->getURLs($grid->getLimit(), $grid->getOffsetValue());
    $data = array();
    foreach ($listaUrls as $tuplaUrl) {
    	$data[] = array(
            $tuplaUrl['active'] ? _tr('Yes') : _tr('No'),
            $dtypes[$tuplaUrl['opentype']],
            htmlentities($tuplaUrl['urltemplate'], ENT_COMPAT, 'UTF-8'),
            htmlentities($tuplaUrl['description'], ENT_COMPAT, 'UTF-8'),
            "<a href='?menu=$module_name&amp;action=edit_url&amp;id_url=".$tuplaUrl['id']."'>["._tr('Edit')."]</a>",
        );
    }
    $grid->addNew("?menu=$module_name&action=new_url", _tr('New URL'), true);
    $grid->setTitle(_tr('External URLs'));
    $grid->setColumns(array(_tr('Active'), _tr('Opens in'), _tr('URL Template'), _tr('Description'), _tr('Options')));
    $grid->setData($data);
    $grid->setIcon('images/application_link.png');
    return $grid->fetchGrid();
}

function newURL($pDB, $smarty, $module_name, $local_templates_dir)
{
    return formEditURL($pDB, $smarty, $module_name, $local_templates_dir, NULL);
}

function editURL($pDB, $smarty, $module_name, $local_templates_dir)
{
    $id_url = NULL;
    if (isset($_GET['id_url']) && ctype_digit($_GET['id_url']))
        $id_url = (int)$_GET['id_url'];
    if (isset($_POST['id_url']) && ctype_digit($_GET['id_url']))
        $id_url = $_POST['id_url'];
    if (is_null($id_url)) {
        Header("Location: ?menu=$module_name");
        return '';
    } else {
        return formEditURL($pDB, $smarty, $module_name, $local_templates_dir, $id_url);
    }
}

function formEditURL($pDB, $smarty, $module_name, $local_templates_dir, $id_url)
{
    // Si se ha indicado cancelar, volver a listado sin hacer nada más
    if (isset($_POST['cancel'])) {
        Header("Location: ?menu=$module_name");
        return '';
    }
    $smarty->assign('FRAMEWORK_TIENE_TITULO_MODULO', existeSoporteTituloFramework());
    $smarty->assign("icon", "images/application_link.png");

    $urls = new externalUrl($pDB);
    $tuplaURL = NULL;
    if (!is_null($id_url)) {
        $tuplaURL = $urls->getURL($id_url);
        if (!is_array($tuplaURL) || count($tuplaURL) == 0) {
            $smarty->assign("mb_title", _tr('Unable to read URL'));
            $smarty->assign("mb_message", _tr('Cannot read URL').' - '.$urls->errMsg);
            return '';
        }
    }

    $formCampos = array(
        'description'   =>  array(
            "LABEL"                => _tr('URL Description'),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "TEXTAREA",
            "INPUT_EXTRA_PARAM"      => "",
            'ROWS'                   => 6,
            'COLS'                   => 50,
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => "",
        ),
        'urltemplate'   =>  array(
            "LABEL"                => _tr('URL Template'),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "TEXT",
            "INPUT_EXTRA_PARAM"      => array('size' => 64, 'title' => _tr('TEMPLATE_DESC')),
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => "",
        ),
        'active'   =>  array(
            "LABEL"                => _tr('Enable use of this template'),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "CHECKBOX",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => "",
        ),
        'opentype'   =>  array(
            "LABEL"                => _tr('Open URL in'),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "SELECT",
            "INPUT_EXTRA_PARAM"      => descOpenType(),
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => "",
        ),
    );
    $oForm = new paloForm($smarty, $formCampos);
    if (!is_null($id_url)) {
        $oForm->setEditMode();
        $smarty->assign('id_url', $id_url);
    }

    if (!is_null($tuplaURL)) {
    	if (!isset($_POST['description'])) $_POST['description'] = $tuplaURL['description'];
        if (!isset($_POST['urltemplate'])) $_POST['urltemplate'] = $tuplaURL['urltemplate'];
        if (!isset($_POST['opentype'])) $_POST['opentype'] = $tuplaURL['opentype'];
        if (!isset($_POST['active'])) $_POST['active'] = $tuplaURL['active'] ? 'on' : 'off';
    } else {
    	if (!isset($_POST['active'])) $_POST['active'] = 'on';
    }

    // En esta implementación el formulario trabaja exclusivamente en modo 'input'
    // y por lo tanto proporciona el botón 'save'
    $bDoCreate = isset($_POST['save']);
    $bDoUpdate = isset($_POST['apply_changes']);
    if ($bDoCreate || $bDoUpdate) {
        if (!$oForm->validateForm($_POST)) {
            // Falla la validación básica del formulario
            $smarty->assign("mb_title", _tr("Validation Error"));
            $arrErrores=$oForm->arrErroresValidacion;
            $strErrorMsg = "<b>"._tr('The following fields contain errors').":</b><br/>";
            if(is_array($arrErrores) && count($arrErrores) > 0){
                foreach($arrErrores as $k=>$v) {
                    $strErrorMsg .= "$k, ";
                }
            }
            $smarty->assign("mb_message", $strErrorMsg);
        } else {
        	if ($bDoCreate) {
        		$bExito = $urls->createURL($_POST['urltemplate'], $_POST['description'], $_POST['opentype']);
        	} elseif ($bDoUpdate) {
        		$urls->enableURL($id_url, ($_POST['active'] != 'off'));
                $bExito = $urls->updateURL($id_url, $_POST['urltemplate'], $_POST['description'], $_POST['opentype']);
        	}
            if ($bExito) {
                header("Location: ?menu=$module_name");
            } else {
                $smarty->assign("mb_title", _tr("Validation Error"));
                $smarty->assign("mb_message", $urls->errMsg);
            }
        }

    }

    $smarty->assign(array(
        'SAVE'          =>  _tr('Save'),
        'CANCEL'        =>  _tr('Cancel'),
        'APPLY_CHANGES' =>  _tr('Apply Changes'),
    ));
    return $oForm->fetchForm(
        "$local_templates_dir/new.tpl",
        is_null($id_url) ? _tr("New URL") : _tr("Edit URL"),
        $_POST);
}
?>