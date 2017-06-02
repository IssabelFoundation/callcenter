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
  $Id: default.conf.php,v 1.1 2008-09-03 01:09:56 Alex Villacís Lasso Exp $
*/

require_once "libs/paloSantoDB.class.php";
require_once "modules/agent_console/libs/elastix2.lib.php";

function _moduleContent(&$smarty,$module_name)
{
	require_once "modules/$module_name/configs/default.config.php";
    require_once "modules/$module_name/libs/paloSantoColaEntrante.class.php";

    global $arrConf;
    
    // Se fusiona la configuración del módulo con la configuración global
    $arrConf = array_merge($arrConf, $arrConfModule);

    load_language_module($module_name);

    $base_dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $templates_dir = (isset($arrConf['templates_dir']))?$arrConf['templates_dir']:'themes';
    $local_templates_dir = "$base_dir/modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];
    $relative_dir_rich_text = "modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];
    $smarty->assign("relative_dir_rich_text", $relative_dir_rich_text);

    // Conexión a la base de datos CallCenter
    $pDB = new paloDB($arrConf['cadena_dsn']);

    // Mostrar pantalla correspondiente
    $contenidoModulo = '';
    $sAction = 'list_queues';
    if (isset($_GET['action'])) $sAction = $_GET['action'];
    switch ($sAction) {
    case 'new_queue':
        return crearCola($pDB, $smarty, $module_name, $local_templates_dir);
    case 'edit_queue':
        return modificarCola($pDB, $smarty, $module_name, $local_templates_dir);
    case 'list_queue':
    default:        
        return listarColas($pDB, $smarty, $module_name, $local_templates_dir);
    }
}

function listarColas($pDB, $smarty, $module_name, $local_templates_dir)
{
    require_once "libs/paloSantoGrid.class.php";
    global $arrLang;
    
    $oColas = new paloSantoColaEntrante($pDB);

    // Verificar si alguna cola debe activarse o inactivarse    
    if (isset($_POST['change_status']) && isset($_POST['status_queue_sel']) &&
        in_array($_POST['status_queue_sel'], array('activate', 'deactivate')) &&
        !is_null($id = getParameter('id'))) {
        $nstate = ($_POST['status_queue_sel'] == 'activate') ? 'A' : 'I';
        if (!$oColas->cambiarMonitoreoCola($id, $nstate)) {
            $smarty->assign("mb_title", _tr('Unable to change activation'));
            $smarty->assign("mb_message", $oColas->errMsg);
        }
    }

    // Estado indicado por el filtro
    $sEstado = 'A';
    $tmpEstado = getParameter('cbo_estado');
    $arrStatus = array('all' => _tr('all'), 'A' => _tr('active'), 'I' => _tr('inactive'));
    
    if (isset($tmpEstado) && isset($arrStatus[$tmpEstado])){ 
        $sEstado = $tmpEstado;
    }
    
    $total = $oColas->getNumColas(NULL, $sEstado);
    if($total===false){
        $total=0;
        $smarty->assign("mb_title", _tr('Unable to read queues'));
        $smarty->assign("mb_message", _tr('Cannot read queues').' - '.$oColas->errMsg);
    }
    
    $limit=50;
    $oGrid = new paloSantoGrid($smarty);
    $oGrid->setLimit($limit);
    $oGrid->setTotal($total);
    $offset = $oGrid->calculateOffset();
    $end    = ($offset+$limit)<=$total ? $offset+$limit : $total;
    $oGrid->setTitle(_tr('Queue List'));
    $oGrid->setWidth("99%");
    $oGrid->setStart(($total==0) ? 0 : $offset + 1);
    $oGrid->setEnd($end);
    $oGrid->setIcon("images/list.png");
    $oGrid->setURL(array('menu' => $module_name, 'cbo_estado' => $sEstado));
    $oGrid->setColumns(array('', _tr('Name Queue'), _tr('Status'), _tr('Options')));
    
    $arrDataQueues=array();
    if($total !=0 ){
        // Consulta de las colas
        $arrDataQueues = $oColas->leerColas(NULL, $sEstado, $limit, $offset);
        if (!is_array($arrDataQueues)) {
            $smarty->assign("mb_title", _tr('Unable to read queues'));
            $smarty->assign("mb_message", _tr('Cannot read queues').' - '.$oColas->errMsg);
            $arrDataQueues = array();
        }
    }
    
    $arrData = array();
    foreach ($arrDataQueues as $tuplaQueue) {
        $arrData[] = array(
            "<input type=\"radio\" name=\"id\" value=\"{$tuplaQueue['id']}\" />",
            $tuplaQueue['queue'],
            ($tuplaQueue['estatus'] == 'A') ? _tr('Active') : _tr('Inactive'),
            "<a href=\"?menu=$module_name&amp;action=edit_queue&amp;id_queue={$tuplaQueue['id']}\">[".htmlentities(_tr('Edit'), ENT_COMPAT, 'UTF-8')."]</a>",
        );
    }

    //addActions
    $oGrid->addNew("?menu=$module_name&action=new_queue", _tr('Select Queue'), TRUE);
    $oGrid->addComboAction('status_queue_sel', _tr("Change Status"), array(
        'activate'      =>  _tr('Activate'),
        'deactivate'    =>  _tr('Deactivate'),
    ), null, 'change_status');
    $oGrid->showFilter(
        '<table width="100%" border="0"><tr>' .
            '<td align="right"><b>'._tr('Status').'</b></td>'.
            '<td align="left"><select name="cbo_estado" onchange="submit();">'.combo($arrStatus, $sEstado).'</select></td>'.
        '</tr></table>'
    );
    $sContenido = $oGrid->fetchGrid(array(), $arrData, $arrLang);
    return $sContenido;
}

function crearCola($pDB, $smarty, $module_name, $local_templates_dir)
{
	return formularioModificarCola($pDB, $smarty, $module_name, $local_templates_dir, NULL);
}

function modificarCola($pDB, $smarty, $module_name, $local_templates_dir)
{
    $idCola = getParameter('id_queue');
    if (is_null($idCola)) {
        Header("Location: ?menu=$module_name");
        return '';
    }
    return formularioModificarCola($pDB, $smarty, $module_name, $local_templates_dir, $idCola);
}

function formularioModificarCola($pDB, $smarty, $module_name, $local_templates_dir, $idCola)
{
    require_once "libs/paloSantoForm.class.php";
    require_once "libs/paloSantoQueue.class.php";

    // Si se ha indicado cancelar, volver a listado sin hacer nada más
    if (isset($_POST['cancel'])) {
        Header("Location: ?menu=$module_name");
        return '';
    }

    $smarty->assign(array(
        'FRAMEWORK_TIENE_TITULO_MODULO' =>  existeSoporteTituloFramework(),
        'icon'                          =>  'images/kfaxview.png',
        'SAVE'                          =>  _tr('guardar'),
        'CANCEL'                        =>  _tr('cancelar'),
        'id_queue'                      =>  $idCola,
    ));

    // Leer todas las colas disponibles
    $dsnAsterisk = generarDSNSistema('asteriskuser', 'asterisk');
    $oDBAsterisk = new paloDB($dsnAsterisk);
    $oQueue = new paloQueue($oDBAsterisk);
    $arrQueues = $oQueue->getQueue();
    if (!is_array($arrQueues)) {
        $smarty->assign("mb_title", _tr('Unable to read queues'));
        $smarty->assign("mb_message", _tr('Cannot read queues').' - '.$oQueue->errMsg);
    	$arrQueues = array();
    }

    $oColas = new paloSantoColaEntrante($pDB);


    // Leer todos los datos de la cola entrante, si es necesario
    $arrColaEntrante = NULL;
    if (!is_null($idCola)) {
    	$arrColaEntrante = $oColas->leerColas($idCola);
        if (!is_array($arrColaEntrante) || count($arrColaEntrante) == 0) {
            $smarty->assign("mb_title", _tr('Unable to read incoming queue'));
            $smarty->assign("mb_message", _tr('Cannot read incoming queue').' - '.$oColas->errMsg);
            return '';
        }
    }
    
    /* Para nueva cola, se deben remover las colas ya usadas. Para cola 
     * modificada, sólo se muestra la cola que ya estaba asignada. */
    if (is_null($idCola)) {
        // Filtrar las colas que ya han sido usadas
        $arrFilterQueues = $oColas->filtrarColasUsadas($arrQueues);
    } else {
    	// Colocar sólo la información de la cola asignada
        $arrFilterQueues = array();
        foreach ($arrQueues as $tuplaQueue) {
        	if ($tuplaQueue[0] == $arrColaEntrante[0]['queue'])
                $arrFilterQueues[] = $tuplaQueue;
        }
    }
    $arrDataQueues = array();
    foreach ($arrFilterQueues as $tuplaQueue) {
        $arrDataQueues[$tuplaQueue[0]] = $tuplaQueue[1];
    }

    // Valores por omisión para primera carga
    if (is_null($idCola)) {
    	if (!isset($_POST['select_queue']) && count($arrFilterQueues) > 0) 
            $_POST['select_queue'] = $arrFilterQueues[0][0];
        if (!isset($_POST['rte_script'])) $_POST['rte_script'] = '';
    } else {
    	$_POST['select_queue'] = $arrColaEntrante[0]['queue'];
        if (!isset($_POST['rte_script'])) $_POST['rte_script'] = $arrColaEntrante[0]['script'];
    }
    
    // rte_script es un HTML complejo que debe de construirse con Javascript.
    $smarty->assign("rte_script",adaptar_formato_rte($_POST['rte_script']));
    
    // Generación del objeto de formulario
    $form_campos = array(
        "script" => array(
            "LABEL"                  => _tr('Script'),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "TEXT",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "",
            "VALIDATION_EXTRA_PARAM" => ""
        ),
        'select_queue' => array(
            "REQUIRED"               => "yes",
            "LABEL"                  => is_null($idCola) ? _tr('Select Queue').' :' : _tr('Queue'),
            "INPUT_TYPE"             => "SELECT",
            "INPUT_EXTRA_PARAM"      => $arrDataQueues,
            "VALIDATION_TYPE"        => "numeric",
            "VALIDATION_EXTRA_PARAM" => ""
        ),
    );
    $oForm = new paloForm($smarty, $form_campos);

    // Ejecutar el guardado de los cambios
    if (isset($_POST['save'])) {
        if(!$oForm->validateForm($_POST) || (!isset($_POST['rte_script']) || $_POST['rte_script']=='')) {
            // Falla la validación básica del formulario
            $smarty->assign("mb_title", _tr("Validation Error"));
            $arrErrores=$oForm->arrErroresValidacion;
            $strErrorMsg = "<b>"._tr('The following fields contain errors').":</b><br/>";
            if(is_array($arrErrores) && count($arrErrores) > 0){
                foreach($arrErrores as $k=>$v) {
                    $strErrorMsg .= "$k, ";
                }
            }
            if(!isset($_POST['rte_script']) || $_POST['rte_script']=='')
                $strErrorMsg .= _tr("Script");
            $strErrorMsg .= "";
            $smarty->assign("mb_message", $strErrorMsg);
        } else {
            $bExito = $oColas->iniciarMonitoreoCola($_POST['select_queue'], $_POST['rte_script']);
            if (!$bExito) {
                $smarty->assign("mb_title", _tr('Unable to save incoming queue'));
                $smarty->assign("mb_message", $oColas->errMsg);
            } else {
            	Header("Location: ?menu=$module_name");
            }
        }
    }

	return $oForm->fetchForm("$local_templates_dir/form.tpl", 
        is_null($idCola) ? _tr('Select Queue') : _tr('Edit Queue'), null);
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

function adaptar_formato_rte($strText)
{
    //returns safe code for preloading in the RTE
    $tmpString = $strText;
    
    //convert all types of single quotes
    $tmpString = str_replace(chr(145), chr(39), $tmpString);
    $tmpString = str_replace(chr(146), chr(39), $tmpString);
    $tmpString = str_replace("'", "&#39;", $tmpString);
    
    //convert all types of double quotes
    $tmpString = str_replace(chr(147), chr(34), $tmpString);
    $tmpString = str_replace(chr(148), chr(34), $tmpString);
    
    //replace carriage returns & line feeds
    $tmpString = str_replace(chr(10), " ", $tmpString);
    $tmpString = str_replace(chr(13), " ", $tmpString);
    
    //replace comillas dobles por una
    $tmpString = str_replace("\"", "'", $tmpString);
    
    return $tmpString;
}
?>
