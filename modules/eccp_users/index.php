<?php
/*
  vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Copyright (c) 1997-2003 Palosanto Solutions S. A.                    |
  +----------------------------------------------------------------------+
  | Cdla. Nueva Kennedy Calle E 222 y 9na. Este                          |
  | Telfs. 2283-268, 2294-440, 2284-356                                  |
  | Guayaquil - Ecuador                                                  |
  +----------------------------------------------------------------------+
  | Este archivo fuente está sujeto a las políticas de licenciamiento    |
  | de Palosanto Solutions S. A. y no está disponible públicamente.      |
  | El acceso a este documento está restringido según lo estipulado      |
  | en los acuerdos de confidencialidad los cuales son parte de las      |
  | políticas internas de Palosanto Solutions S. A.                      |
  | Si Ud. está viendo este archivo y no tiene autorización explícita    |
  | de hacerlo, comuníquese con nosotros, podría estar infringiendo      |
  | la ley sin saberlo.                                                  |
  +----------------------------------------------------------------------+
  | Autores: Alex Villacís Lasso <a_villacis@palosanto.com>              |
  +----------------------------------------------------------------------+
  $Id: index.php,v 1.1 2007/01/09 23:49:36 alex Exp $
*/

require_once "libs/paloSantoGrid.class.php";
require_once "libs/paloSantoDB.class.php";
require_once "libs/paloSantoForm.class.php";
require_once "libs/paloSantoConfig.class.php";

require_once 'libs/UsuariosECCP.class.php';

require_once "modules/agent_console/libs/elastix2.lib.php";

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

function _moduleContent(&$smarty, $module_name)
{
    //include module files
    include_once "modules/$module_name/configs/default.conf.php";
    global $arrConf;

    load_language_module($module_name);
    $arrConf = array_merge($arrConf, $arrConfig);

    //folder path for custom templates
    $base_dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $templates_dir = (isset($arrConfig['templates_dir']))?$arrConfig['templates_dir']:'themes';
    $local_templates_dir = "$base_dir/modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];

    // Conexión a la base de datos CallCenter
    $pDB = new paloDB($arrConf['cadena_dsn']);

    switch (getParameter('action')) {
    case 'new_user':
        return nuevoUsuario($pDB, $smarty, $module_name, $local_templates_dir);
    case 'edit_user':
        return editarUsuario($pDB, $smarty, $module_name, $local_templates_dir);
    case 'list_user':
    default:
        return listarUsuarios($pDB, $smarty, $module_name, $local_templates_dir);
    }
}

function listarUsuarios($pDB, $smarty, $module_name, $local_templates_dir)
{
    global $arrLang;
    $oUsuarios = new UsuariosECCP($pDB);
    
    $smarty->assign(array(
        'MODULE_NAME'           =>  $module_name,
    ));
    
    // Manejar posible borrado de agentes
    if (isset($_POST['delete']) && isset($_POST['id']) && ctype_digit($_POST['id'])) {
    	$bExito = $oUsuarios->borrarUsuario($_POST['id']);
        if (!$bExito) {
            $smarty->assign(array(
                'mb_title'      =>  _tr('Error when deleting user'),
                'mb_message'    =>  $oUsuarios->errMsg,
            ));
        }
    }
    
    // Listar todos los agentes
    $oGrid = new paloSantoGrid($smarty);
    $oGrid->setLimit(50);
    $oGrid->setTotal($oUsuarios->contarUsuarios());
    $offset = $oGrid->calculateOffset();
    $listaAgentes = $oUsuarios->listarUsuarios(NULL, $offset, $oGrid->getLimit());

    $arrData = array();
    foreach ($listaAgentes as $t) {
    	$arrData[] = array(
            '<input type="radio" name="id" value="'.$t['id'].'" />',
            htmlentities($t['username'], ENT_COMPAT, 'UTF-8'),
            '<a href="?menu='.$module_name.'&amp;action=edit_user&amp;id='.$t['id'].'">['._tr('Edit').']</a>',
        ); 
    }
    
    $url = construirURL(array('menu' => $module_name), array('nav', 'start'));
    $arrGrid = array("title"    => _tr('ECCP User List'),
                     "url"      => $url,
                     "icon"     => 'images/user.png',
                     "width"    => "99%",
                     "columns"  => array(
                                        0 => array("name"       => ''),
                                        1 => array("name"       => _tr('Name')),
                                        2 => array("name"       => _tr('Options')),
                                        )
                    );
    
    $oGrid->addNew("?menu=$module_name&action=new_user", _tr('New ECCP User'), true);
    $oGrid->deleteList('Are you sure to delete this user?', 'delete', _tr('Delete'));
    $sContenido = $oGrid->fetchGrid($arrGrid, $arrData,$arrLang);
    if (strpos($sContenido, '<form') === FALSE)
        $sContenido = "<form  method=\"POST\" style=\"margin-bottom:0;\" action=\"$url\">$sContenido</form>";
    return $sContenido;
}

function nuevoUsuario($pDB, $smarty, $module_name, $local_templates_dir)
{
	return formEditUser($pDB, $smarty, $module_name, $local_templates_dir, NULL);
}

function editarUsuario($pDB, $smarty, $module_name, $local_templates_dir)
{
    $id = NULL;
    if (isset($_GET['id']) && ctype_digit($_GET['id']))
        $id = $_GET['id'];
    if (is_null($id)) {
        Header("Location: ?menu=$module_name");
        return '';
    } else {
        return formEditUser($pDB, $smarty, $module_name, $local_templates_dir, $id);
    }
}

function formEditUser($pDB, $smarty, $module_name, $local_templates_dir, $id_user)
{
    // Si se ha indicado cancelar, volver a listado sin hacer nada más
    if (isset($_POST['cancel'])) {
        Header("Location: ?menu=$module_name");
        return '';
    }

    $smarty->assign('FRAMEWORK_TIENE_TITULO_MODULO', existeSoporteTituloFramework());

    // Leer los datos de la campaña, si es necesario
    $arrAgente = NULL;
    $oAgentes = new UsuariosECCP($pDB);
    if (!is_null($id_user)) {
        $arrAgente = $oAgentes->listarUsuarios($id_user);
        if (!is_array($arrAgente) || count($arrAgente) == 0) {
            $smarty->assign("mb_title", 'Unable to read agent');
            $smarty->assign("mb_message", 'Cannot read agent - '.$oAgentes->errMsg);
            return '';
        }
        $arrAgente = $arrAgente[0];
    }

    $arrFormElements = getFormUser($smarty);

    // Valores por omisión para primera carga
    if (is_null($id_user)) {
        // Creación de nuevo agente
        if (!isset($_POST['username']))     $_POST['username'] = '';
        if (!isset($_POST['password1']))    $_POST['password1'] = '';
        if (!isset($_POST['password2']))    $_POST['password2'] = '';
    } else {
        // Modificación de agente existente
        if (!isset($_POST['username']))     $_POST['username'] = $arrAgente['username'];
        if (!isset($_POST['password1']))    $_POST['password1'] = '';
        if (!isset($_POST['password2']))    $_POST['password2'] = '';
        
        // Volver opcional el cambio de clave de acceso
        $arrFormElements['password1']['REQUIRED'] = 'no';
        $arrFormElements['password2']['REQUIRED'] = 'no';
    }
    $oForm = new paloForm($smarty, $arrFormElements);
    if (!is_null($id_user)) {
        $oForm->setEditMode();
        $smarty->assign("id_user", $id_user);
    }

    $bDoCreate = isset($_POST['submit_save']);
    $bDoUpdate = isset($_POST['submit_apply_changes']);
    if ($bDoCreate || $bDoUpdate) {
        if(!$oForm->validateForm($_POST)) {
            // Falla la validación básica del formulario
            $smarty->assign("mb_title", _tr('Validation Error'));
            $arrErrores = $oForm->arrErroresValidacion;
            $strErrorMsg = "<b>"._tr('The following fields contain errors').":</b><br>";
            foreach($arrErrores as $k=>$v) {
                $strErrorMsg .= "$k, ";
            }
            $strErrorMsg .= "";
            $smarty->assign("mb_message", $strErrorMsg);
        } else {
            foreach (array('password1', 'password2', 'username') as $k)
                $_POST[$k] = trim($_POST[$k]);
            if ($_POST['password1'] != $_POST['password2'] || ($bDoCreate && $_POST['password1'] == '')) {
                $smarty->assign("mb_title", _tr('Validation Error'));
                $smarty->assign("mb_message", _tr('The passwords are empty or dont match'));
            } else {
                $bExito = TRUE;
                
                if ($bDoUpdate && $_POST['password1'] == '')
                    $_POST['password1'] = NULL;
                if ($bDoCreate) {
                    $bExito = $oAgentes->crearUsuario($_POST['username'], $_POST['password1']);
                    if (!$bExito) $smarty->assign("mb_message",
                        ""._tr('Error on user creation')." ".$oAgentes->errMsg);
                } elseif ($bDoUpdate) {
                    $bExito = $oAgentes->editarUsuario($id_user, $_POST['username'], $_POST['password1']);
                    if (!$bExito) $smarty->assign("mb_message",
                        ""._tr('Error on user update')." ".$oAgentes->errMsg);
                }
                if ($bExito) header("Location: ?menu=$module_name");
            }
        }
    }

    $smarty->assign('icon', 'images/user.png');
    $contenidoModulo = $oForm->fetchForm(
        "$local_templates_dir/edit-users.tpl", 
        is_null($id_user) ? _tr('New user') : _tr('Edit user').' "'.$_POST['username'].'"',
        $_POST);
    return $contenidoModulo;
}

function getFormUser(&$smarty)
{
    $smarty->assign("REQUIRED_FIELD", _tr('Required field'));
    $smarty->assign("CANCEL", _tr('Cancel'));
    $smarty->assign("APPLY_CHANGES", _tr('Apply changes'));
    $smarty->assign("SAVE", _tr('Save'));
    $smarty->assign("EDIT", _tr('Edit'));
    $smarty->assign("DELETE", _tr('Delete'));
    $smarty->assign("CONFIRM_CONTINUE", _tr('Are you sure you wish to continue?'));

    $arrFormElements = array(
        "username" => array(
            "LABEL"                  => ""._tr('User name')."",
            "EDITABLE"               => "yes",
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "TEXT",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => ""),
        "password1"   => array(
            "LABEL"                  => _tr('Password'),
            "EDITABLE"               => "yes",
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "PASSWORD",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => ""),
        "password2"   => array(
            "LABEL"                  => _tr('Retype password'),
            "EDITABLE"               => "yes",
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "PASSWORD",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => ""),
    );
    return $arrFormElements;
}

?>