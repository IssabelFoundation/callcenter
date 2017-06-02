<?php
  /* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Elastix version 1.2-2                                                |
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
  $Id: default.conf.php,v 1.1 2008-09-03 01:09:56 Alex Villacís Lasso Exp $ */

require_once "modules/agent_console/libs/elastix2.lib.php";

function _moduleContent(&$smarty, $module_name)
{
    global $arrConfig;

    //include elastix framework
    include_once "libs/paloSantoGrid.class.php";
    include_once "libs/paloSantoForm.class.php";

    //include module files
    include_once "modules/$module_name/libs/paloSantoConfiguration.class.php";
    include_once "modules/$module_name/configs/default.conf.php";

    load_language_module($module_name);

    global $arrConf;

    $oDB = new paloDB($arrConfig['cadena_dsn']);

    //folder path for custom templates
    $base_dir=dirname($_SERVER['SCRIPT_FILENAME']);
    $templates_dir=(isset($arrConfig['templates_dir']))?$arrConfig['templates_dir']:'themes';
    $local_templates_dir="$base_dir/modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];

    $accion = getAction();

    $content = "";
    $content .= form_Service($oDB, $smarty, $module_name, $local_templates_dir, $arrConfig['pid_dialer']);
    $content .= form_Configuration($oDB, $smarty, $module_name, $local_templates_dir);

    return $content;
}

function form_Configuration(&$oDB, $smarty, $module_name, $local_templates_dir)
{
    global $arrConfig;
    $smarty->assign('FRAMEWORK_TIENE_TITULO_MODULO', existeSoporteTituloFramework());

    $arrFormConference = createFieldForm();
    $oForm = new paloForm($smarty,$arrFormConference);

    $smarty->assign("SAVE", _tr("Save"));
    $smarty->assign("REQUIRED_FIELD", _tr("Required field"));
    $smarty->assign("icon", "images/list.png");

    $objConfig =& new PaloSantoConfiguration($oDB);
    $listaConf = $objConfig->ObtainConfiguration();
    
//    print_r($listaConf);
    $camposConocidos = array(
        'asterisk.asthost' => 'asterisk_asthost',
        'asterisk.astuser' => 'asterisk_astuser',
        'asterisk.astpass' => 'asterisk_astpass_1',
        'asterisk.duracion_sesion' => 'asterisk_duracion_sesion',
        'dialer.llamada_corta' => 'dialer_llamada_corta',
        'dialer.tiempo_contestar' => 'dialer_tiempo_contestar',
        'dialer.debug' => 'dialer_debug',
        'dialer.allevents' => 'dialer_allevents',
        'dialer.overcommit' => 'dialer_overcommit',
        'dialer.qos' => 'dialer_qos',
        'dialer.predictivo' => 'dialer_predictivo',
        'dialer.timeout_originate' => 'dialer_timeout_originate',
        'dialer.timeout_inactivity' => 'dialer_timeout_inactivity',
    );
    $valoresForm = array(
        'asterisk_asthost' => '127.0.0.1',
        'asterisk_astuser' => '',
        'asterisk_astpass_1' => '',
        'asterisk_astpass_2' => '',
        'asterisk_duracion_sesion' => '0',
        'dialer_llamada_corta' => '10',
        'dialer_tiempo_contestar' => '8',
        'dialer_debug' => 'off',
        'dialer_allevents' => 'off',
        'dialer_overcommit' => 'off',
        'dialer_qos' => '0.97',
        'dialer_predictivo' => 'on',
        'dialer_timeout_originate' => '0',
        'dialer_timeout_inactivity' => '15',
    );
    foreach ($camposConocidos as $dbfield => $formfield) {
        if (isset($listaConf[$dbfield])) {
            if (in_array($dbfield, array('dialer.debug', 'dialer.allevents', 
                'dialer.overcommit', 'dialer.predictivo')) )
            {
                $valoresForm[$formfield] = $listaConf[$dbfield] ? 'on' : 'off';
            } else $valoresForm[$formfield] = $listaConf[$dbfield];
        } else {
        }
    }
    if (count($_POST) > 0) {
        if (!isset($_POST['asterisk_astuser']) || trim($_POST['asterisk_astuser']) == '') {
            $_POST['asterisk_astuser'] = '';
            $_POST['asterisk_astpass_1'] = '';
            $_POST['asterisk_astpass_2'] = '';
        }
        foreach ($camposConocidos as $dbfield => $formfield) if (isset($_POST[$formfield])) {
            if (in_array($dbfield, array('dialer.debug', 'dialer.allevents', 
                'dialer.overcommit', 'dialer.predictivo')))
            {
               $valoresForm[$formfield] = ($_POST[$formfield] == 'on') ? 'on' : 'off';
            } else $valoresForm[$formfield] = $_POST[$formfield];
        }

        $action = getAction();
        if ($action == 'save') {
            if (!$oForm->validateForm($_POST)) {
                $smarty->assign("mb_title", _tr("Validation Error"));
                $arrErrores=$oForm->arrErroresValidacion;
                $strErrorMsg = "<b>"._tr('The following fields contain errors').":</b><br/>";
                if(is_array($arrErrores) && count($arrErrores) > 0){
                    foreach($arrErrores as $k=>$v) {
                        $strErrorMsg .= "$k, ";
                    }
                }
                $smarty->assign("mb_message", $strErrorMsg);
            } elseif ($_POST['dialer_qos'] < 0 || $_POST['dialer_qos'] >= 100) {
                $smarty->assign("mb_title", _tr("Validation Error"));
                $arrErrores=array('Service Percent' => 'Not in range 1..99');
                $strErrorMsg = "<b>"._tr('The following fields contain errors').":</b><br/>";
                if(is_array($arrErrores) && count($arrErrores) > 0){
                    foreach($arrErrores as $k=>$v) {
                        $strErrorMsg .= "$k, ";
                    }
                }
                $smarty->assign("mb_message", $strErrorMsg);
            } elseif ($_POST['asterisk_astpass_1'] != $_POST['asterisk_astpass_2']) {
                $smarty->assign("mb_title", _tr("Validation Error"));
                $strErrorMsg = _tr('Password and confirmation do not match.');
                $smarty->assign("mb_message", $strErrorMsg);
            } else {
                // Esto asume implementación PDO
                $oDB->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $oDB->conn->beginTransaction();
                $bContinuar = TRUE;
                $strErrorMsg = '';
                $config = array();
                foreach ($camposConocidos as $dbfield => $formfield) {
                    if ($dbfield == 'asterisk.astpass' && $_POST[$formfield] == '') continue;
                    
                    if (in_array($dbfield, array('dialer.debug', 'dialer.allevents', 
                            'dialer.overcommit', 'dialer.predictivo'))) {
                        $config[$dbfield] = ($_POST[$formfield] == 'on') ? 1 : 0;
                    } else {
                        $config[$dbfield] = $_POST[$formfield];
                    }
                }
                if (!isset($config['asterisk.astuser']) || $config['asterisk.astuser'] == '')
                    $config['asterisk.astpass'] = '';
                $bContinuar = $objConfig->SaveConfiguration($config);
                if (!$bContinuar) {
                    $strErrorMsg = $objConfig->errMsg;
                    $smarty->assign("mb_title", _tr('Internal DB error'));
                    $strErrorMsg = _tr('Could not save changes!').' '.$strErrorMsg;
                    $smarty->assign("mb_message", $strErrorMsg);
                }
                if ($bContinuar) {
                    $bContinuar = $oDB->conn->commit();
                    if (!$bContinuar) {
                        $smarty->assign("mb_title", _tr('Internal DB error'));
                        $strErrorMsg = _tr('Could not commit changes!');
                        $smarty->assign("mb_message", $strErrorMsg);
                    }
                }
                if (!$bContinuar) $oDB->conn->rollBack();
            }
        }
    }
    unset($valoresForm['asterisk_astpass_1']);
    unset($valoresForm['asterisk_astpass_2']);

    $htmlForm = $oForm->fetchForm("$local_templates_dir/form.tpl", _tr("Configuration"), $valoresForm);

    $contenidoModulo = "<form  method='POST' style='margin-bottom:0;' action='?menu=$module_name'>".$htmlForm."</form>";

    return $contenidoModulo;
}

function form_Service(&$oDB, $smarty, $module_name, $local_templates_dir, $pd)
{
    global $arrConfig;

    $objConfig = new paloSantoConfiguration($oDB);
    if (isset($_POST['dialer_action'])) {
        $objConfig->setStatusDialer(($_POST['dialer_action'] == _tr('Start')) ? 1 : 0);
    }
    $bDialerActivo = $objConfig->getStatusDialer($pd);

    $smarty->assign('ASTERISK_CONNECT_PARAM',_tr('Asterisk Connection'));
    $smarty->assign('DIALER_PARAM',_tr('Dialer Parameters'));
    $smarty->assign('DIALER_STATUS_MESG',_tr('Dialer Status'));
    $smarty->assign('CURRENT_STATUS',_tr('Current Status'));
    $smarty->assign('DIALER_STATUS', $bDialerActivo 
        ? _tr('Running') 
        : _tr('Stopped'));
    $smarty->assign('DIALER_ACTION', $bDialerActivo 
        ? _tr('Stop') 
        : _tr('Start'));
}

function createFieldForm()
{
    return array(
        'asterisk_asthost'  =>      array(
            'LABEL'                     =>  _tr('Asterisk Server'),
            'REQUIRED'                  =>  'yes',
            'INPUT_TYPE'                =>  'TEXT',
            'VALIDATION_TYPE'           =>  'text',
            'INPUT_EXTRA_PARAM'         =>  '',
            'VALIDATION_EXTRA_PARAM'    =>  '',
        ),
        'asterisk_astuser'  =>      array(
            'LABEL'                     =>  _tr('Asterisk Login'),
            'REQUIRED'                  =>  'no',
            'INPUT_TYPE'                =>  'TEXT',
            'VALIDATION_TYPE'           =>  'text',
            'INPUT_EXTRA_PARAM'         =>  '',
            'VALIDATION_EXTRA_PARAM'    =>  '',
        ),
        'asterisk_astpass_1'  =>      array(
            'LABEL'                     =>  _tr('Asterisk Password'),
            'REQUIRED'                  =>  'no',
            'INPUT_TYPE'                =>  'PASSWORD',
            'VALIDATION_TYPE'           =>  'text',
            'INPUT_EXTRA_PARAM'         =>  '',
            'VALIDATION_EXTRA_PARAM'    =>  '',
        ),
        'asterisk_astpass_2'  =>      array(
            'LABEL'                     =>  _tr('Asterisk Password (confirm)'),
            'REQUIRED'                  =>  'no',
            'INPUT_TYPE'                =>  'PASSWORD',
            'VALIDATION_TYPE'           =>  'text',
            'INPUT_EXTRA_PARAM'         =>  '',
            'VALIDATION_EXTRA_PARAM'    =>  '',
        ),
        'asterisk_duracion_sesion'  =>  array(
            'LABEL'                     =>  _tr('AMI Session Duration'),
            'REQUIRED'                  =>  'yes',
            'INPUT_TYPE'                =>  'TEXT',
            'VALIDATION_TYPE'           =>  'numeric',
            'INPUT_EXTRA_PARAM'         =>  '',
            //'VALIDATION_EXTRA_PARAM'    =>  '^[[:digit:]]+$',
            'VALIDATION_EXTRA_PARAM'    =>  '',            
        ),
        'dialer_llamada_corta'  =>  array(
            'LABEL'                     =>  _tr('Short Call Threshold'),
            'REQUIRED'                  =>  'yes',
            'INPUT_TYPE'                =>  'TEXT',
            'VALIDATION_TYPE'           =>  'ereg',
            'INPUT_EXTRA_PARAM'         =>  '',
            'VALIDATION_EXTRA_PARAM'    =>  '^[[:digit:]]+$',
        ),
        'dialer_tiempo_contestar'=> array(
            'LABEL'                     =>  _tr('Answering delay'),
            'REQUIRED'                  =>  'yes',
            'INPUT_TYPE'                =>  'TEXT',
            'VALIDATION_TYPE'           =>  'ereg',
            'INPUT_EXTRA_PARAM'         =>  '',
            'VALIDATION_EXTRA_PARAM'    =>  '^[[:digit:]]+$',
        ),
        'dialer_debug'  =>          array(
            'LABEL'                     =>  _tr('Enable dialer debug'),
            'REQUIRED'                  =>  'yes',
            'INPUT_TYPE'                =>  'CHECKBOX',
            'VALIDATION_TYPE'           =>  'text',
            'INPUT_EXTRA_PARAM'         =>  '',
            'VALIDATION_EXTRA_PARAM'    =>  '',
        ),
        'dialer_allevents'  =>      array(
            'LABEL'                     =>  _tr('Dump all received Asterisk events'),
            'REQUIRED'                  =>  'yes',
            'INPUT_TYPE'                =>  'CHECKBOX',
            'VALIDATION_TYPE'           =>  'text',
            'INPUT_EXTRA_PARAM'         =>  '',
            'VALIDATION_EXTRA_PARAM'    =>  '',
        ),
        'dialer_overcommit'  =>      array(
            'LABEL'                     =>  _tr('Enable overcommit of outgoing calls'),
            'REQUIRED'                  =>  'yes',
            'INPUT_TYPE'                =>  'CHECKBOX',
            'VALIDATION_TYPE'           =>  'text',
            'INPUT_EXTRA_PARAM'         =>  '',
            'VALIDATION_EXTRA_PARAM'    =>  '',
        ),
        'dialer_qos'=> array(
            'LABEL'                     =>  _tr('Service percent'),
            'REQUIRED'                  =>  'yes',
            'INPUT_TYPE'                =>  'TEXT',
            'VALIDATION_TYPE'           =>  'float',
            'INPUT_EXTRA_PARAM'         =>  '',
            'VALIDATION_EXTRA_PARAM'    =>  '^[[:digit:]]+$',
        ),
        'dialer_predictivo'  =>      array(
            'LABEL'                     =>  _tr('Enable predictive dialer behavior'),
            'REQUIRED'                  =>  'yes',
            'INPUT_TYPE'                =>  'CHECKBOX',
            'VALIDATION_TYPE'           =>  'text',
            'INPUT_EXTRA_PARAM'         =>  '',
            'VALIDATION_EXTRA_PARAM'    =>  '',
        ),
        'dialer_timeout_originate'=> array(
            'LABEL'                     =>  _tr('Per-call dial timeout'),
            'REQUIRED'                  =>  'yes',
            'INPUT_TYPE'                =>  'TEXT',
            'VALIDATION_TYPE'           =>  'ereg',
            'INPUT_EXTRA_PARAM'         =>  '',
            'VALIDATION_EXTRA_PARAM'    =>  '^[[:digit:]]+$',
        ),
        'dialer_timeout_inactivity'=> array(
            'LABEL'                     =>  _tr('Agent inactivity timeout'),
            'REQUIRED'                  =>  'yes',
            'INPUT_TYPE'                =>  'TEXT',
            'VALIDATION_TYPE'           =>  'ereg',
            'INPUT_EXTRA_PARAM'         =>  '',
            'VALIDATION_EXTRA_PARAM'    =>  '^[[:digit:]]+$',
        ),
    );
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

function getAction()
{
    if(getParameter("show")) //Get parameter by POST (submit)
        return "show";
    if(getParameter("save"))
        return "save";
    else if(getParameter("new"))
        return "new";
    else if(getParameter("action")=="show") //Get parameter by GET (command pattern, links)
        return "show";
    else
        return "report";
}?>
