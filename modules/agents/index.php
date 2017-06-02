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
require_once "modules/agent_console/libs/JSON.php";

function _moduleContent(&$smarty, $module_name)
{
    load_language_module($module_name);

    //include module files
    include_once "modules/$module_name/configs/default.conf.php";
    include_once "modules/agent_console/configs/default.conf.php"; // For asterisk AMI credentials

    global $arrConf;

    // Se fusiona la configuración del módulo con la configuración global
    $arrConf = array_merge($arrConf, $arrConfModule);

    //folder path for custom templates
    $base_dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $templates_dir = (isset($arrConf['templates_dir']))?$arrConf['templates_dir']:'themes';
    $local_templates_dir = "$base_dir/modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];
    $relative_dir_rich_text = "modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];
    $smarty->assign("relative_dir_rich_text", $relative_dir_rich_text);

    // Conexión a la base de datos CallCenter
    $pDB = new paloDB($arrConf['cadena_dsn']);

    // Mostrar pantalla correspondiente
    $sAction = 'list_agents';
    if (isset($_REQUEST['action'])) $sAction = $_REQUEST['action'];
    switch ($sAction) {
    case 'new_agent':
        return newAgent($pDB, $smarty, $module_name, $local_templates_dir);
    case 'edit_agent':
        return editAgent($pDB, $smarty, $module_name, $local_templates_dir);
    case 'reparar_file':
        return repararAgente_file($pDB, $smarty, $module_name, $local_templates_dir);
    case 'reparar_db':
        return repararAgente_db($pDB, $smarty, $module_name, $local_templates_dir);
    case 'list_agents':
    default:
        return listAgent($pDB, $smarty, $module_name, $local_templates_dir);
    }
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
        $arrAgentes = array($_POST['agent_number']);
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
        "Repair"    =>  _tr("Repair"),
    );
    if (isset($_GET['cbo_estado'])) $sEstadoAgente = $_GET['cbo_estado'];
    if (isset($_POST['cbo_estado'])) $sEstadoAgente = $_POST['cbo_estado'];
    if (!in_array($sEstadoAgente, array_keys($listaEstados))) $sEstadoAgente = 'All';

    // Leer los agentes activos y comparar contra la lista de Asterisk
    $listaAgentesCallCenter = $oAgentes->getAgents();
    function get_agente_num($t) { return $t['number']; }
    $listaNumAgentesCallCenter = array_map('get_agente_num', $listaAgentesCallCenter);
    $listaNumAgentesAsterisk = $oAgentes->getAgentsFile();
    $listaNumSobrantes = array_diff($listaNumAgentesAsterisk, $listaNumAgentesCallCenter);
    $listaNumFaltantes = array_diff($listaNumAgentesCallCenter, $listaNumAgentesAsterisk);
    
    /* La variable $listaNumSobrantes tiene ahora todos los IDs de agente que 
       constan en Asterisk y no en la tabla call_center.agent como activos.
       La variable $listaNumFaltantes tiene los agentes que constan en 
       call_center.agent y no en Asterisk. El código posterior asume que el 
       archivo de agentes de Asterisk debería cambiarse para que refleje la
       tabla call_center.agent .
    */
    // Campo sync debe ser OK, o ASTERISK si consta en Asterisk pero no en 
    // CallCenter, o CC si consta en CallCenter pero no en Asterisk.
    foreach (array_keys($listaAgentesCallCenter) as $k) {
        $listaAgentesCallCenter[$k]['sync'] =
            in_array($listaAgentesCallCenter[$k]['number'], $listaNumFaltantes) 
                ? 'CC' : 'OK';
    }
    
    // Lista de todos los agentes conocidos, incluyendo los sobrantes.
    $listaAgentes = $listaAgentesCallCenter;
    foreach ($listaNumSobrantes as $idSobrante) {
        $listaAgentes[] = array(
            'id'        =>  NULL,
            'number'    =>  $oAgentes->arrAgents[$idSobrante][0],
            'name'      =>  $oAgentes->arrAgents[$idSobrante][2],
            'password'  =>  $oAgentes->arrAgents[$idSobrante][1],
            'estatus'   =>  NULL,
            'sync'      =>  'ASTERISK',
        );
    }

    // Listar todos los agentes que están conectados
    $listaOnline = $oAgentes->getOnlineAgents();
    if (is_array($listaOnline)) {
        foreach (array_keys($listaAgentes) as $k) {
            $listaAgentes[$k]['online'] = in_array($listaAgentes[$k]['number'], $listaOnline);
        }
    } else {
        $smarty->assign("mb_title", 'Unable to read agent');
        $smarty->assign("mb_message", 'Cannot read agent - '.$oAgentes->errMsg);
        foreach (array_keys($listaAgentes) as $k) 
            $listaAgentes[$k]['online'] = NULL;
    }
    
    // Filtrar los agentes conocidos según el estado que se requiera
    function estado_Online($t)  { return ($t['sync'] == 'OK' && $t['online']); }
    function estado_Offline($t) { return ($t['sync'] == 'OK' && !$t['online']); }
    function estado_Repair($t)  { return ($t['sync'] != 'OK'); }
    if ($sEstadoAgente != 'All') $listaAgentes = array_filter($listaAgentes, "estado_$sEstadoAgente");
    
    $arrData = array();
    $sImgVisto = "<img src='modules/$module_name/themes/images/visto.gif' border='0' />";
    $sImgErrorCC = "<img src='modules/$module_name/themes/images/error_small.png' border='0' title=\""._tr("Agent doesn't exist in configuration file")."\" />";
    $sImgErrorAst = "<img src='modules/$module_name/themes/images/error_small.png' border='0' title=\""._tr("Agent doesn't exist in database")."\" />";
    $smarty->assign(array(
        'PREGUNTA_BORRAR_AGENTE_CONF'   =>  _tr("To rapair is necesary delete agent from configuration file. Do you want to continue?"),
        'PREGUNTA_AGREGAR_AGENTE_CONF'  =>  _tr("To rapair is necesary add an agent in configuration file. Do you want to continue?"),
    ));
    foreach ($listaAgentes as $tuplaAgente) {
        $tuplaData = array(
            "<input class=\"button\" type=\"radio\" name=\"agent_number\" value=\"{$tuplaAgente["number"]}\" />",
            NULL,
            htmlentities($tuplaAgente['number'], ENT_COMPAT, 'UTF-8'),
            htmlentities($tuplaAgente['name'], ENT_COMPAT, 'UTF-8'),
            (($tuplaAgente['sync'] != 'CC') ? ($tuplaAgente['online'] ? _tr("Online") : _tr("Offline")) : '&nbsp;'),
            "<a href='?menu=agents&amp;action=edit_agent&amp;id_agent=" . $tuplaAgente["number"] . "'>["._tr("Edit")."]</a>",
        );
        switch ($tuplaAgente['sync']) {
        case 'OK':
            $tuplaData[1] = $sImgVisto;
            break;
        case 'ASTERISK':
            $tuplaData[1] = $sImgErrorAst.'&nbsp;<a href="#" class="reparar_file">'._tr('Repair').'</a>';
            $tuplaData[5] = '&nbsp;';   // No mostrar opción de editar agente que no está en DB
            break;
        case 'CC':
            $tuplaData[1] = $sImgErrorCC.'&nbsp;<a href="#" class="reparar_db">'._tr('Repair').'</a>';
            break;
        }
        $arrData[] = $tuplaData;
    }

    $url = construirURL(array('menu' => $module_name, 'cbo_estado' => $sEstadoAgente), array('nav', 'start'));

    $arrColumns = array('', _tr("Configure"), _tr("Number"), _tr("Name"), _tr("Status"), _tr("Options"));    
    $oGrid = new paloSantoGrid($smarty);
    $oGrid->pagingShow(true);
    $oGrid->setLimit(50);
    $oGrid->addNew("?menu=$module_name&action=new_agent", _tr('New agent'), TRUE);
    $oGrid->deleteList('Are you sure you wish to continue?', 'delete', _tr('Delete'));
    $oGrid->addSubmitAction('disconnect', _tr('Disconnect'));
    $oGrid->setColumns($arrColumns);
    $oGrid->setURL($url);
    $oGrid->setTitle(_tr('Agent List'));
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

function repararAgente_file($pDB, $smarty, $module_name, $local_templates_dir)
{
    $respuesta = array(
        'status'    =>  'success',
        'message'   =>  '(no message)',
    );
    
    if (!isset($_REQUEST['id_agent']) || !ctype_digit($_REQUEST['id_agent'])) {
        $respuesta = array(
            'status'    =>  'error',
            'message'   =>  'Invalid agent ID',
        );
    } else {
        $oAgentes = new Agentes($pDB);
        if (!$oAgentes->deleteAgentFile($_REQUEST['id_agent'])) {
            $respuesta = array(
                'status'    =>  'error',
                'message'   =>  _tr("Error when deleting agent in file").' - '.$oAgentes->errMsg,
            );
        }
    }

    $json = new Services_JSON();
    Header('Content-Type: application/json');
    return $json->encode($respuesta);
}

function repararAgente_db($pDB, $smarty, $module_name, $local_templates_dir)
{
    $respuesta = array(
        'status'    =>  'success',
        'message'   =>  '(no message)',
    );
    
    if (!isset($_REQUEST['id_agent']) || !ctype_digit($_REQUEST['id_agent'])) {
        $respuesta = array(
            'status'    =>  'error',
            'message'   =>  'Invalid agent ID',
        );
    } else {
        $oAgentes = new Agentes($pDB);

        // Hay que agregar el agente al archivo de configuración de Asterisk
        $infoAgente = $oAgentes->getAgents($_REQUEST['id_agent']);
        if (!is_array($infoAgente)) {
            $respuesta = array(
                'status'    =>  'error',
                'message'   =>  'DB Error - '.$oAgentes->errMsg,
            );
        } elseif (count($infoAgente) == 0) {
            // Agente no existe en DB, no se hace nada
        } elseif (!$oAgentes->addAgentFile(array(
            $infoAgente['number'],
            $infoAgente['password'],
            $infoAgente['name'],
            ))) {
            $respuesta = array(
                'status'    =>  'error',
                'message'   =>  _tr('Error saving agent in file').' - '.$oAgentes->errMsg,
            );
        }
    }

    $json = new Services_JSON();
    Header('Content-Type: application/json');
    return $json->encode($respuesta);
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

    $smarty->assign('FRAMEWORK_TIENE_TITULO_MODULO', existeSoporteTituloFramework());

    // Leer los datos de la campaña, si es necesario
    $arrAgente = NULL;
    $oAgentes = new Agentes($pDB);
    if (!is_null($id_agent)) {
        $arrAgente = $oAgentes->getAgents($id_agent);
        if (!is_array($arrAgente) || count($arrAgente) == 0) {
            $smarty->assign("mb_title", 'Unable to read agent');
            $smarty->assign("mb_message", 'Cannot read agent - '.$oAgentes->errMsg);
            return '';
        }
    }

    require_once("libs/paloSantoForm.class.php");
    $arrFormElements = getFormAgent($smarty, !is_null($id_agent));

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
        if (!isset($_POST['extension']))    $_POST['extension'] = $arrAgente['number'];
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

    $bDoCreate = isset($_POST['submit_save_agent']);
    $bDoUpdate = isset($_POST['submit_apply_changes']);
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
            } elseif (!preg_match('/^[[:digit:]]+$/', $_POST['extension'])) {
                $smarty->assign("mb_title", _tr("Validation Error"));
                $smarty->assign("mb_message", _tr("Error Agent Number"));
            } else {
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
        is_null($id_agent) ? _tr("New agent") : _tr('Edit agent').' "'.$_POST['description'].'"',
        $_POST);
    return $contenidoModulo;
}

function getFormAgent(&$smarty, $bEdit)
{
    $smarty->assign("REQUIRED_FIELD", _tr("Required field"));
    $smarty->assign("CANCEL", _tr("Cancel"));
    $smarty->assign("APPLY_CHANGES", _tr("Apply changes"));
    $smarty->assign("SAVE", _tr("Save"));
    $smarty->assign("EDIT", _tr("Edit"));
    $smarty->assign("DELETE", _tr("Delete"));
    $smarty->assign("CONFIRM_CONTINUE", _tr("Are you sure you wish to continue?"));

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
            "LABEL"                  => ""._tr("Agent Number")."",
            "EDITABLE"               => "yes",
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "TEXT",
            'EDITABLE'              => $bEdit ? 'no' : 'yes',
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "numeric",
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
