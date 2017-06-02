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
  $Id: index.php,v 1.2 2008/06/07 06:28:13 cbarcos Exp $ */

require_once("libs/paloSantoGrid.class.php");
require_once("libs/Agentes.class.php");

require_once "modules/agent_console/libs/elastix2.lib.php";

function _moduleContent(&$smarty, $module_name)
{
    load_language_module($module_name);

    //include module files
    include_once "modules/$module_name/configs/default.conf.php";
    include_once "modules/agent_console/configs/default.conf.php"; // For asterisk AMI credentials

    global $arrConf;
    global $arrConfig;

    //folder path for custom templates
    $base_dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $templates_dir = (isset($arrConfig['templates_dir']))?$arrConfig['templates_dir']:'themes';
    $local_templates_dir = "$base_dir/modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];
    $relative_dir_rich_text = "modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];
    $smarty->assign("relative_dir_rich_text", $relative_dir_rich_text);

    // Conexión a la base de datos CallCenter y Asterisk (se utiliza root para pruebas)
    $pDB = new paloDB($cadena_dsn); // $cadena_dsn está ubicado en configs/default.conf.php
    if ($pDB->connStatus) return "ERR: failed to connect to database: ".$pDB->errMsg;

    // Mostrar pantalla correspondiente
    $contenidoModulo = '';
    $sAction = 'list_agents';
    if (isset($_GET['action'])) $sAction = $_GET['action'];

    switch ($sAction) {
    case 'new_agent':
        $contenidoModulo = newAgent($pDB, $smarty, $module_name, $local_templates_dir);
        break;
    case 'edit_agent':
        $contenidoModulo = editAgent($pDB, $smarty, $module_name, $local_templates_dir);
        break;
    case 'list_agents':
    default:
        $contenidoModulo = listAgent($pDB, $smarty, $module_name, $local_templates_dir);
        break;
    }

    return $contenidoModulo;
}

function listAgent($pDB, $smarty, $module_name, $local_templates_dir)
{
    global $arrLang;

    $oAgentes = new Agentes($pDB);

    // Operaciones de manipulación de agentes
    if (isset($_POST['delete']) && isset($_POST['agent_number']) && preg_match('/^[[:digit:]]+$/', $_POST['agent_number'])) {
        // Borrar el agente indicado de la base de datos, y del archivo
        if (!$oAgentes->deleteAgent($_POST['agent_number'])) {
            $smarty->assign(array(
                'mb_title'      =>  _tr("Error Delete Agent"),
                'mb_message'    =>  $oAgentes->errMsg,
            ));
        }
    } elseif (isset($_POST['disconnect']) && isset($_POST['agent_number']) && preg_match('/^[[:digit:]]+$/', $_POST['agent_number'])) {
        // Desconectar agentes. El código en Agentes.class.php puede desconectar
        // varios agentes a la vez, pero aquí sólo se desconecta uno.
        $infoAgent = $oAgentes->getAgents($_POST['agent_number']);
        $arrAgentes = array($infoAgent['type'].'/'.$infoAgent['number']);
        if (!$oAgentes->desconectarAgentes($arrAgentes)) {
            $smarty->assign(array(
                'mb_title'      =>  'Unable to disconnect agent',
                'mb_message'    =>  $oAgentes->errMsg,
            ));
        }
    }

    // Estados posibles del agente
    $sEstadoAgente = 'All';
    $listaEstados = array(
        "All"       =>  _tr("All"),
        "Online"    =>  _tr("Online"),
        "Offline"   =>  _tr("Offline"),
    );
    $tmpEstado = getParameter('cbo_estado');
    if (isset($listaEstados[$tmpEstado])) {
        $sEstadoAgente = $tmpEstado;
    }

    $listaAgentes = $oAgentes->getAgents();

    // Listar todos los agentes que están conectados
    $listaOnline = $oAgentes->getOnlineAgents();
    if (is_array($listaOnline)) {
        foreach (array_keys($listaAgentes) as $k) {
            $listaAgentes[$k]['online'] = in_array($listaAgentes[$k]['type'].'/'.$listaAgentes[$k]['number'], $listaOnline);
        }
    } else {
        $smarty->assign("mb_title", 'Unable to read agent');
        $smarty->assign("mb_message", 'Cannot read agent - '.$oAgentes->errMsg);
        foreach (array_keys($listaAgentes) as $k)
            $listaAgentes[$k]['online'] = NULL;
    }

    // Filtrar los agentes conocidos según el estado que se requiera
    function estado_Online($t)  { return ($t['online']); }
    function estado_Offline($t) { return (!$t['online']); }
    if ($sEstadoAgente != 'All') $listaAgentes = array_filter($listaAgentes, "estado_$sEstadoAgente");

    $arrData = array();
    $smarty->assign(array(
        'PREGUNTA_BORRAR_AGENTE_CONF'   =>  _tr("To rapair is necesary delete agent from configuration file. Do you want to continue?"),
        'PREGUNTA_AGREGAR_AGENTE_CONF'  =>  _tr("To rapair is necesary add an agent in configuration file. Do you want to continue?"),
    ));
    foreach ($listaAgentes as $tuplaAgente) {
        $tuplaData = array(
            "<input class=\"button\" type=\"radio\" name=\"agent_number\" value=\"{$tuplaAgente["number"]}\" />",
            htmlentities($tuplaAgente['number'], ENT_COMPAT, 'UTF-8'),
            htmlentities($tuplaAgente['name'], ENT_COMPAT, 'UTF-8'),
            ($tuplaAgente['online'] ? _tr("Online") : _tr("Offline")),
            "<a href='?menu=$module_name&amp;action=edit_agent&amp;id_agent=" . $tuplaAgente["number"] . "'>["._tr("Edit")."]</a>",
        );
        $arrData[] = $tuplaData;
    }

    $url = construirURL(array('menu' => $module_name, 'cbo_estado' => $sEstadoAgente), array('nav', 'start'));

    $arrColumns = array('', _tr("Number"), _tr("Name"), _tr("Status"), _tr("Options"));
    $oGrid = new paloSantoGrid($smarty);
    $oGrid->pagingShow(true);
    $oGrid->setLimit(50);
    $oGrid->addNew("?menu=$module_name&action=new_agent", _tr('New callback extension'), TRUE);
    $oGrid->deleteList('Are you sure you wish to continue?', 'delete', _tr('Delete'));
    $oGrid->addSubmitAction('disconnect', _tr('Disconnect'));
    $oGrid->setColumns($arrColumns);
    $oGrid->setURL($url);
    $oGrid->setTitle(_tr('Callback Extensions'));
    $oGrid->setIcon('images/user.png');

    $_REQUEST['cbo_estado'] = $sEstadoAgente;
    $oGrid->addFilterControl(_tr("Filter applied ")._tr("Status")." = ".$listaEstados[$sEstadoAgente], $_REQUEST, array("cbo_estado" =>'A'),true);

    $oGrid->setTotal(count($arrData));
    $offset = $oGrid->calculateOffset();
    $arrData = array_slice($arrData, $offset, $oGrid->getLimit());
    $oGrid->setData($arrData);
    $smarty->assign(array(
        'LABEL_STATE'           =>  _tr('Status'),
        'estados'               =>  $listaEstados,
        'estado_sel'            =>  $sEstadoAgente,
    ));
    $oGrid->showFilter($smarty->fetch("$local_templates_dir/filter-list-agents.tpl"));
    return $oGrid->fetchGrid();
}

function newAgent($pDB, $smarty, $module_name, $local_templates_dir)
{
    return formEditAgent($pDB, $smarty, $module_name, $local_templates_dir, NULL);
}

function editAgent($pDB, $smarty, $module_name, $local_templates_dir)
{
    $id_agent = NULL;
    if (isset($_GET['id_agent']) && preg_match('/^[[:digit:]]+$/', $_GET['id_agent']))
        $id_agent = $_GET['id_agent'];
    if (isset($_POST['id_campaign']) && preg_match('/^[[:digit:]]+$/', $_POST['id_agent']))
        $id_agent = $_POST['id_agent'];
    if (is_null($id_agent)) {
        Header("Location: ?menu=$module_name");
        return '';
    } else {
        return formEditAgent($pDB, $smarty, $module_name, $local_templates_dir, $id_agent);
    }
}

function formEditAgent($pDB, $smarty, $module_name, $local_templates_dir, $id_agent)
{
    // Si se ha indicado cancelar, volver a listado sin hacer nada más
    if (isset($_POST['cancel'])) {
        Header("Location: ?menu=$module_name");
        return '';
    }

    $bDoCreate = isset($_POST['submit_save_agent']);
    $bDoUpdate = isset($_POST['submit_apply_changes']);
    $oAgentes = new Agentes($pDB);

    $smarty->assign('FRAMEWORK_TIENE_TITULO_MODULO', existeSoporteTituloFramework());

    // Leer los datos de la campaña, si es necesario
    $arrAgente = NULL;
    if (!is_null($id_agent)) {
        $arrAgente = $oAgentes->getAgents($id_agent);
        if (!is_array($arrAgente) || count($arrAgente) == 0) {
            $smarty->assign("mb_title", 'Unable to read agent');
            $smarty->assign("mb_message", 'Cannot read agent - '.$oAgentes->errMsg);
            return '';
        }
    }

    require_once("libs/paloSantoForm.class.php");
    $arrFormElements = getFormAgent($smarty, $oAgentes, $arrAgente, !is_null($id_agent));

    // Valores por omisión para primera carga
    if (is_null($id_agent)) {
        // Creación de nuevo agente
        if (!isset($_POST['extension']))    $_POST['extension'] = '';
        if (!isset($_POST['description']))  $_POST['description'] = '';
        if (!isset($_POST['password1']))    $_POST['password1'] = '';
        if (!isset($_POST['password2']))    $_POST['password2'] = '';
        if (!isset($_POST['eccpwd1']))      $_POST['eccpwd1'] = '';
        if (!isset($_POST['eccpwd2']))      $_POST['eccpwd2'] = '';
    } else {
        // Modificación de agente existente
        if (!isset($_POST['extension']))    $_POST['extension'] = $arrAgente['type'].'/'.$arrAgente['number'];
        if (!isset($_POST['description']))  $_POST['description'] = $arrAgente['name'];
        if (!isset($_POST['password1']))    $_POST['password1'] = $arrAgente['password'];
        if (!isset($_POST['password2']))    $_POST['password2'] = $arrAgente['password'];
        if (!isset($_POST['eccpwd1']))      $_POST['eccpwd1'] = $arrAgente['eccp_password'];
        if (!isset($_POST['eccpwd2']))      $_POST['eccpwd2'] = $arrAgente['eccp_password'];

        // Volver opcional el cambio de clave de acceso
        $arrFormElements['password1']['REQUIRED'] = 'no';
        $arrFormElements['password2']['REQUIRED'] = 'no';
    }
    $oForm = new paloForm($smarty, $arrFormElements);
    if (!is_null($id_agent)) {
        $oForm->setEditMode();
        $smarty->assign("id_agent", $id_agent);
    }

    if ($bDoCreate || $bDoUpdate) {
        if(!$oForm->validateForm($_POST)) {

            // Falla la validación básica del formulario
            $smarty->assign("mb_title", _tr("Validation Error"));
            $arrErrores = $oForm->arrErroresValidacion;

            $strErrorMsg = "<b>"._tr('The following fields contain errors').":</b><br>";
            foreach($arrErrores as $k=>$v) {
                $strErrorMsg .= "$k, ";
            }
            $strErrorMsg .= "";
            $smarty->assign("mb_message", $strErrorMsg);
        } else {
            foreach (array('extension', 'password1', 'password2', 'description', 'eccpwd1', 'eccpwd2') as $k)
                $_POST[$k] = trim($_POST[$k]);
            if ($_POST['password1'] != $_POST['password2'] || ($bDoCreate && $_POST['password1'] == '')) {
                $smarty->assign("mb_title", _tr("Validation Error"));
                $smarty->assign("mb_message", _tr("The passwords are empty or don't match"));
            } elseif ($_POST['eccpwd1'] != $_POST['eccpwd2']) {
                $smarty->assign("mb_title", _tr("Validation Error"));
                $smarty->assign("mb_message", _tr("ECCP passwords don't match"));
            } elseif (!preg_match('/^[[:digit:]]+$/', $_POST['password1'])) {
                $smarty->assign("mb_title", _tr("Validation Error"));
                $smarty->assign("mb_message", _tr("The passwords aren't numeric values"));
            }
	      /* Se asume que esta validación no es necesaria.
		elseif (!preg_match('/^[[:digit:]]+$/', $_POST['extension'])) {

                $smarty->assign("mb_title", _tr("Validation Error"));
                $smarty->assign("mb_message", _tr("Error Agent Number"));
            } */
	      else {
                $bExito = TRUE;

                if ($bDoUpdate && $_POST['password1'] == '')
                    $_POST['password1'] = $arrAgente['password'];
                $agente = array(
                    0 => $_POST['extension'],
                    1 => $_POST['password1'],
                    2 => $_POST['description'],
                    3 => $_POST['eccpwd1'],
                );

                if ($bDoCreate) {
                    $bExito = $oAgentes->addAgent($agente);
                    if (!$bExito) $smarty->assign("mb_message",
                        ""._tr("Error Insert Agent")." ".$oAgentes->errMsg);
                } elseif ($bDoUpdate) {
                    $bExito = $oAgentes->editAgent($agente);
                    if (!$bExito) $smarty->assign("mb_message",
                        ""._tr("Error Update Agent")." ".$oAgentes->errMsg);
                }
                if ($bExito) header("Location: ?menu=$module_name");
            }
        }
    }

    $smarty->assign('icon', 'images/user.png');
    $contenidoModulo = $oForm->fetchForm(
        "$local_templates_dir/new.tpl",
        is_null($id_agent) ? _tr("New callback extension") : _tr('Edit agent').' "'.$_POST['description'].'"',
        $_POST);
    return $contenidoModulo;
}

function getFormAgent(&$smarty, $oAgentes, $arrAgente, $bEdit)
{
    $smarty->assign("REQUIRED_FIELD", _tr("Required field"));
    $smarty->assign("CANCEL", _tr("Cancel"));
    $smarty->assign("APPLY_CHANGES", _tr("Apply changes"));
    $smarty->assign("SAVE", _tr("Save"));
    $smarty->assign("EDIT", _tr("Edit"));
    $smarty->assign("DELETE", _tr("Delete"));
    $smarty->assign("CONFIRM_CONTINUE", _tr("Are you sure you wish to continue?"));

    $arrExtensions = $oAgentes->getUnusedExtensions();
    if (!is_array($arrExtensions)) {
    	$smarty->assign(array(
            'mb_title' => 'Fallo al leer extensiones',
            'mb_message' => $oAgentes->errMsg));
        $arrExtensions = array('Unavailable extensions.');
    }
    if (!is_null($arrAgente)) {
    	$sChannel = $arrAgente['type'].'/'.$arrAgente['number'];
        $arrExtensions[$sChannel] = $sChannel;
        asort($arrExtensions);
    }

    $arrFormElements = array(
        "description" => array(
            "LABEL"                  => ""._tr('Name')."",
            "EDITABLE"               => "yes",
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "TEXT",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => ""),

        "extension"   => array(
            "LABEL"                  => ""._tr("Callback extension")."",
            "EDITABLE"               => "yes",
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "SELECT",
            'EDITABLE'              => $bEdit ? 'no' : 'yes',
            "INPUT_EXTRA_PARAM"      => $arrExtensions,
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => ""),
        "password1"   => array(
            "LABEL"                  => _tr("Password"),
            "EDITABLE"               => "yes",
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "PASSWORD",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => ""),
        "password2"   => array(
            "LABEL"                  => _tr("Retype password"),
            "EDITABLE"               => "yes",
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "PASSWORD",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => ""),
        "eccpwd1"   => array(
            "LABEL"                  => _tr("ECCP Password"),
            "EDITABLE"               => "yes",
            "REQUIRED"               => "no",
            "INPUT_TYPE"             => "PASSWORD",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => ""),
        "eccpwd2"   => array(
            "LABEL"                  => _tr("Retype ECCP password"),
            "EDITABLE"               => "yes",
            "REQUIRED"               => "no",
            "INPUT_TYPE"             => "PASSWORD",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => ""),
    );
    return $arrFormElements;
}

?>
