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
require_once "libs/paloSantoForm.class.php";
require_once "libs/paloSantoGrid.class.php";
require_once "libs/paloSantoConfig.class.php";
require_once 'libs/paloSantoIncomingCampaign.class.php';

require_once "modules/agent_console/libs/elastix2.lib.php";

function _moduleContent(&$smarty, $module_name)
{
    $script_dir=dirname($_SERVER['SCRIPT_FILENAME']);

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
    $relative_dir_rich_text = "modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];
    $smarty->assign("relative_dir_rich_text", $relative_dir_rich_text);

    // Conexión a la base de datos CallCenter
    $pDB = new paloDB($arrConf['cadena_dsn']);

    // Mostrar pantalla correspondiente
    $contenidoModulo = '';
    $sAction = 'list_campaign';
    if (isset($_GET['action'])) $sAction = $_GET['action'];
    switch ($sAction) {
    case 'new_campaign':
        $contenidoModulo = newCampaign($pDB, $smarty, $module_name, $local_templates_dir);
        break;
    case 'edit_campaign':
        $contenidoModulo = editCampaign($pDB, $smarty, $module_name, $local_templates_dir);
        break;
    case 'csv_data':
        $contenidoModulo = displayCampaignCSV($pDB, $smarty, $module_name, $local_templates_dir);
        break;
    case 'list_campaign':
    default:
        $contenidoModulo = listCampaign($pDB, $smarty, $module_name, $local_templates_dir);
        break;
    }

    return $contenidoModulo;
}

function listCampaign($pDB, $smarty, $module_name, $local_templates_dir)
{
    global $arrLang;
    $arrData = '';
    $oCampaign = new paloSantoIncomingCampaign($pDB);

    // Recoger ID de campaña para operación
    $id_campaign = NULL;
    if (isset($_POST['id_campaign']) && preg_match('/^[[:digit:]]+$/', $_POST['id_campaign']))
        $id_campaign = $_POST['id_campaign'];

    // Revisar si se debe de borrar una campaña elegida
    if (isset($_POST['delete']) && !is_null($id_campaign)) {
        if($oCampaign->delete_campaign($id_campaign)) {
            if ($oCampaign->errMsg!="") {
                $smarty->assign("mb_title",_tr('Validation Error'));
                $smarty->assign("mb_message", $oCampaign->errMsg);
            } else {
            }
        } else {
            $msg_error = ($oCampaign->errMsg!="") ? "<br/>".$oCampaign->errMsg:"";
            $smarty->assign("mb_title", _tr('Delete Error'));
            $smarty->assign("mb_message", _tr('Error when deleting the Campaign').$msg_error);
        }
    }

    // Activar o desactivar campañas elegidas
    if (isset($_POST['change_status']) && !is_null($id_campaign)){
        if($_POST['status_campaing_sel']=='activate'){
            if(!$oCampaign->activar_campaign($id_campaign, 'A')) {
                $smarty->assign("mb_title", _tr('Activate Error'));
                $smarty->assign("mb_message", _tr('Error when Activating the Campaign').': '.$oCampaign->errMsg);
            }
        }elseif($_POST['status_campaing_sel']=='deactivate'){
            if(!$oCampaign->activar_campaign($id_campaign, 'I')) {
                $smarty->assign("mb_title", _tr('Deactivate Error'));
                $smarty->assign("mb_message", _tr('Error when deactivating the Campaign').': '.$oCampaign->errMsg);
            }
        }
    }

    // Validar el filtro por estado de actividad de la campaña
    $estados = array(
        "all" => _tr('All'),
        "A" => _tr('Active'),
        "I" => _tr('Inactive')
    );
    $sEstado = 'A';
    if (isset($_GET['cbo_estado']) && isset($estados[$_GET['cbo_estado']])) {
        $sEstado = $_GET['cbo_estado'];
    }
    if (isset($_POST['cbo_estado']) && isset($estados[$_POST['cbo_estado']])) {
        $sEstado = $_POST['cbo_estado'];
    }

    // para el pagineo
    $oGrid = new paloSantoGrid($smarty);
    $oGrid->setLimit(50);
    $total = $oCampaign->countCampaigns($sEstado);
    if (is_null($total)) {
        $smarty->assign("mb_title", _tr('Read Error'));
        $smarty->assign("mb_message", _tr('Unable to count campaigns').' - '.$arrCampaign->errMsg);
        $total = 0;
    } else {
        $oGrid->setTotal($total);
        $offset = $oGrid->calculateOffset();
        $arrCampaign = $oCampaign->getCampaigns($oGrid->getLimit(), $offset, NULL, $sEstado);
        if (is_null($arrCampaign)) {
            $smarty->assign("mb_title", _tr('Read Error'));
            $smarty->assign("mb_message", _tr('Unable to read campaigns').' - '.$arrCampaign->errMsg);
        }
    }

    $url = construirURL(
        array('menu' => $module_name, 'cbo_estado' => $sEstado),
        array('nav', 'start'));

    if (is_array($arrCampaign)) {
        foreach($arrCampaign as $campaign) {
            $arrTmp    = array();
            $arrTmp[0] = "<input class=\"button\" type=\"radio\" name=\"id_campaign\" value=\"$campaign[id]\" />";
            $arrTmp[1] = "<a href='?menu=$module_name&amp;action=edit_campaign&amp;id_campaign=".$campaign['id']."'>".
                htmlentities($campaign['name'], ENT_COMPAT, 'UTF-8').'</a>';
            $arrTmp[2] = $campaign['datetime_init'].' - '.$campaign['datetime_end'];
            $arrTmp[3] = $campaign['daytime_init'].' - '.$campaign['daytime_end'];
            $arrTmp[4] = $campaign['queue'];
            $arrTmp[5] = ($campaign['num_completadas']!="") ? $campaign['num_completadas'] : "N/A";
            $arrTmp[6] = ($campaign['promedio']!="") ? number_format($campaign['promedio'],0) : "N/A";

            if($campaign['estatus']=='I'){
                $arrTmp[7] = _tr('Inactive');
            } elseif($campaign['estatus']=='A'){
                $arrTmp[7] = _tr('Active');
            }
            $arrTmp[8] = "<a href='?menu=$module_name&amp;action=csv_data&amp;id_campaign=".$campaign['id']."&amp;rawmode=yes'>["._tr('CSV Data')."]</a>";
            $arrData[] = $arrTmp;
        }
    }

    // Definición de la tabla de las campañas
    $oGrid->setTitle(_tr("Campaigns List"));
    $oGrid->setWidth("99%");
    $oGrid->setIcon("images/list.png");
    $oGrid->setURL($url);
    $oGrid->setColumns(array('', _tr('Campaign Name'), _tr('Range Date'),
        _tr('Schedule per Day'), _tr('Queue'), _tr('Completed Calls'),
        _tr('Average Time'), _tr('Status'), _tr('Options')));
    $_POST['cbo_estado']=$sEstado;
    $oGrid->addFilterControl(_tr("Filter applied ")._tr("Status")." = ".$estados[$sEstado], $_POST, array("cbo_estado" =>'A'),true);
    $smarty->assign(array(
        'MODULE_NAME'                   =>  $module_name,
        'LABEL_CAMPAIGN_STATE'          =>  _tr('Campaign state'),
        'estados'                       =>  $estados,
        'estado_sel'                    =>  $sEstado,
    ));

    $oGrid->addNew("?menu=$module_name&action=new_campaign", _tr('Create New Campaign'), TRUE);
    $oGrid->addComboAction('status_campaing_sel', _tr("Change Status"), array(
        'activate'      =>  _tr('Activate'),
        'deactivate'    =>  _tr('Deactivate'),
    ), null, 'change_status');
    $oGrid->deleteList('Are you sure you wish to delete campaign?', 'delete', _tr('Delete'));
    $oGrid->setData($arrData);
    $oGrid->showFilter($smarty->fetch("$local_templates_dir/filter-list-campaign.tpl"));
    return $oGrid->fetchGrid();
}

function newCampaign($pDB, $smarty, $module_name, $local_templates_dir)
{
    return formEditCampaign($pDB, $smarty, $module_name, $local_templates_dir, NULL);
}

function editCampaign($pDB, $smarty, $module_name, $local_templates_dir)
{
    $id_campaign = NULL;
    if (isset($_GET['id_campaign']) && ctype_digit($_GET['id_campaign']))
        $id_campaign = $_GET['id_campaign'];
    if (isset($_POST['id_campaign']) && ctype_digit($_POST['id_campaign']))
        $id_campaign = $_POST['id_campaign'];
    if (is_null($id_campaign)) {
        Header("Location: ?menu=$module_name");
        return '';
    } else {
        return formEditCampaign($pDB, $smarty, $module_name, $local_templates_dir, $id_campaign);
    }
}

function formEditCampaign($pDB, $smarty, $module_name, $local_templates_dir, $id_campaign = NULL)
{
    include_once "libs/paloSantoQueue.class.php";
    include_once "modules/form_designer/libs/paloSantoDataForm.class.php";

    // Si se ha indicado cancelar, volver a listado sin hacer nada más
    if (isset($_POST['cancel'])) {
        Header("Location: ?menu=$module_name");
        return '';
    }

    $smarty->assign('FRAMEWORK_TIENE_TITULO_MODULO', existeSoporteTituloFramework());

    // Leer los datos de la campaña, si es necesario
    $arrCampaign = NULL;
    $oCamp = new paloSantoIncomingCampaign($pDB);
    if (!is_null($id_campaign)) {
        $arrCampaign = $oCamp->getCampaigns(null, null, $id_campaign);
        if (!is_array($arrCampaign) || count($arrCampaign) == 0) {
            $smarty->assign("mb_title", 'Unable to read campaign');
            $smarty->assign("mb_message", 'Cannot read campaign - '.$oCamp->errMsg);
            return '';
        }
    }

    // Obtener y conectarse a base de datos de FreePBX
    $pConfig = new paloConfig("/etc", "amportal.conf", "=", "[[:space:]]*=[[:space:]]*");
    $arrConfig = $pConfig->leer_configuracion(false);
    $dsn = $arrConfig['AMPDBENGINE']['valor'] . "://" .
        $arrConfig['AMPDBUSER']['valor'] . ":" .
        $arrConfig['AMPDBPASS']['valor'] . "@" .
        $arrConfig['AMPDBHOST']['valor'] . "/asterisk";
    $oDB = new paloDB($dsn);

    // Leer las colas que se han definido en FreePBX, y quitar las usadas
    // en campañas entrantes.
    $arrDataQueues = array();
    $oQueue = new paloQueue($oDB);
    $arrQueues = $oQueue->getQueue();   // Todas las colas, entrantes y salientes
    if (is_array($arrQueues)) {
        $query_call_entry = "SELECT queue FROM queue_call_entry WHERE estatus = 'A'";
        $arr_call_entry = $pDB->fetchTable($query_call_entry); // Las colas entrantes
        $colasEntrantes = array();
        foreach ($arr_call_entry as $row) $colasEntrantes[] = $row[0];
        foreach($arrQueues as $rowQueue) {
            if (in_array($rowQueue[0], $colasEntrantes))
                $arrDataQueues[$rowQueue[0]] = $rowQueue[1];
        }
        ksort($arrDataQueues);
    }

    $arrUrlsExternos = array(
        ''  =>  _tr('(No external URL)'),
    ) + $oCamp->getExternalUrls();

    // Cargar la información de todos los formularios creados y activos
    $oDataForm = new paloSantoDataForm($pDB);
    $arrDataForm = $oDataForm->getFormularios(NULL,'A');

    // Impedir mostrar el formulario si no se han definido colas o no
    // quedan colas libres para usar en campañas salientes.
    if (count($arrQueues) <= 0) {
        $formCampos = getFormCampaign($arrDataQueues, NULL, NULL, NULL);
        $oForm = new paloForm($smarty, $formCampos);
        $smarty->assign('no_queues', 1);
    } elseif (count($arrDataQueues) <= 0) {
        $formCampos = getFormCampaign($arrDataQueues, NULL, NULL, NULL);
        $oForm = new paloForm($smarty, $formCampos);
        $smarty->assign('no_incoming_queues', 1);
    } elseif (count($arrDataForm) <= 0) {
        $formCampos = getFormCampaign($arrDataQueues, NULL, NULL, NULL);
        $oForm = new paloForm($smarty, $formCampos);
        $smarty->assign('no_forms', 1);
    } else {
        $smarty->assign('label_manage_trunks', _tr('Manage Trunks'));
        $smarty->assign('label_manage_queues', _tr('Manage Queues'));
        $smarty->assign('label_manage_forms',  _tr('Manage Forms'));
        $smarty->assign('label_manage_external_url', _tr('Manage External URLs'));

        // Definición del formulario de nueva campaña
        $smarty->assign("REQUIRED_FIELD", _tr('Required field'));
        $smarty->assign("CANCEL", _tr('Cancel'));
        $smarty->assign("SAVE", _tr('Save'));
        $smarty->assign("APPLY_CHANGES", _tr('Apply changes'));
        $smarty->assign('LABEL_CALL_FILE', _tr('Call File'));

        // Valores por omisión para primera carga
        $arrNoElegidos = array();   // Lista de selección de formularios elegibles
        $arrElegidos = array();     // Lista de selección de formularios ya elegidos
        $values_form = NULL;        // Selección hecha en el formulario
        if (is_null($id_campaign)) {
            if (!isset($_POST['nombre'])) $_POST['nombre']='';
            if (!isset($_POST['rte_script'])) $_POST['rte_script'] = '';
            if (!isset($_POST['values_form'])) $_POST['values_form'] = '';
            //$_POST['formulario']= explode(",", $_POST['values_form']);
            $values_form = explode(",", $_POST['values_form']);

        } else {
            if (!isset($_POST['nombre']))       $_POST['nombre']       = $arrCampaign[0]['name'];
            if (!isset($_POST['fecha_ini']))    $_POST['fecha_ini']    = date('d M Y',strtotime($arrCampaign[0]['datetime_init']));
            if (!isset($_POST['fecha_fin']))    $_POST['fecha_fin']    = date('d M Y',strtotime($arrCampaign[0]['datetime_end']));
            $arrDateTimeInit = explode(":",$arrCampaign[0]['daytime_init']);
            $arrDateTimeEnd  = explode(":",$arrCampaign[0]['daytime_end']);
            if (!isset($_POST['hora_ini_HH']))  $_POST['hora_ini_HH']  = isset($arrDateTimeInit[0])?$arrDateTimeInit[0]:"00";
            if (!isset($_POST['hora_ini_MM']))  $_POST['hora_ini_MM']  = isset($arrDateTimeInit[1])?$arrDateTimeInit[1]:"00";
            if (!isset($_POST['hora_fin_HH']))  $_POST['hora_fin_HH']  = isset($arrDateTimeEnd[0])?$arrDateTimeEnd[0]:"00";
            if (!isset($_POST['hora_fin_MM']))  $_POST['hora_fin_MM']  = isset($arrDateTimeEnd[1])?$arrDateTimeEnd[1]:"00";
            if (!isset($_POST['queue']))        $_POST['queue']        = $arrCampaign[0]['queue'];
            //$_POST['script'] = "";
            if (!isset($_POST['rte_script']))   $_POST['rte_script'] = $arrCampaign[0]['script'];
            //if (!isset($_POST['formulario']))           $_POST['formulario'] = "";
            //if (!isset($_POST['formularios_elegidos'])) $_POST['formularios_elegidos'] = "";
            if (!isset($_POST['values_form'])) {
                $values_form = $oCamp->obtenerCampaignForm($id_campaign);
                if (!is_null($arrCampaign[0]['id_form']) && !in_array($arrCampaign[0]['id_form'], $values_form))
                    $values_form[] = $arrCampaign[0]['id_form'];
            } else {
                $values_form = explode(",", $_POST['values_form']);
            }
            if (!isset($_POST['external_url']))        $_POST['external_url']        = $arrCampaign[0]['id_url'];
        }

        // rte_script es un HTML complejo que debe de construirse con Javascript.
        $smarty->assign("rte_script", adaptar_formato_rte($_POST['rte_script']));

        // Clasificar los formularios elegidos y no elegidos
        foreach ($arrDataForm as $key => $form) {
            if (in_array($form['id'], $values_form))
                $arrElegidos[$form['id']] = $form['nombre'];
            else
                $arrNoElegidos[$form['id']] = $form['nombre'];
        }

        // Generación del objeto de formulario
        $formCampos = getFormCampaign($arrDataQueues, $arrNoElegidos, $arrElegidos, $arrUrlsExternos);
        $oForm = new paloForm($smarty, $formCampos);
        if (!is_null($id_campaign)) {
            $oForm->setEditMode();
            $smarty->assign('id_campaign', $id_campaign);
        }


        // En esta implementación el formulario trabaja exclusivamente en modo 'input'
        // y por lo tanto proporciona el botón 'save'
        $bDoCreate = isset($_POST['save']);
        $bDoUpdate = isset($_POST['apply_changes']);
        if ($bDoCreate || $bDoUpdate) {
            if(!$oForm->validateForm($_POST) || (!isset($_POST['rte_script']) || $_POST['rte_script']=='')) {
                // Falla la validación básica del formulario
                $smarty->assign("mb_title", _tr('Validation Error'));
                $arrErrores=$oForm->arrErroresValidacion;
                $strErrorMsg = "<b>"._tr('The following fields contain errors').":</b><br/>";
                if(is_array($arrErrores) && count($arrErrores) > 0){
                    foreach($arrErrores as $k=>$v) {
                        $strErrorMsg .= "$k, ";
                    }
                }
                if(!isset($_POST['rte_script']) || $_POST['rte_script']=='')
                    $strErrorMsg .= _tr('Script');
                $strErrorMsg .= "";
                $smarty->assign("mb_message", $strErrorMsg);
            } else {
                $time_ini = $_POST['hora_ini_HH'].":".$_POST['hora_ini_MM'];
                $time_fin = $_POST['hora_fin_HH'].":".$_POST['hora_fin_MM'];
                $iFechaIni = strtotime($_POST['fecha_ini']);
                $iFechaFin = strtotime($_POST['fecha_fin']);
                $iHoraIni =  strtotime($time_ini);
                $iHoraFin =  strtotime($time_fin);
                if ($iFechaIni == -1 || $iFechaIni === FALSE) {
                    $smarty->assign("mb_title", _tr('Validation Error'));
                    $smarty->assign("mb_message", _tr('Unable to parse start date specification'));
                } elseif ($iFechaFin == -1 || $iFechaFin === FALSE) {
                    $smarty->assign("mb_title", _tr('Validation Error'));
                    $smarty->assign("mb_message", _tr('Unable to parse end date specification'));
                } elseif ($iHoraIni == -1 || $iHoraIni === FALSE) {
                    $smarty->assign("mb_title", _tr('Validation Error'));
                    $smarty->assign("mb_message", _tr('Unable to parse start time specification'));
                } elseif ($iHoraFin == -1 || $iHoraFin === FALSE) {
                    $smarty->assign("mb_title", _tr('Validation Error'));
                    $smarty->assign("mb_message", _tr('Unable to parse end time specification'));
                } else {

                    if(!$pDB->genQuery("SET AUTOCOMMIT=0")) {
                        $smarty->assign("mb_message", $pDB->errMsg);
                    } else {
                        $bExito = TRUE;
                        if ($bDoCreate) {
                            $id_campaign = $oCamp->createEmptyCampaign(
                                            $_POST['nombre'],
                                            $_POST['queue'],
                                            date('Y-m-d', $iFechaIni),
                                            date('Y-m-d', $iFechaFin),
                                            $time_ini,
                                            $time_fin,
                                            $_POST['rte_script'],
                                            NULL,
                                            ($_POST['external_url'] == '') ? NULL : (int)$_POST['external_url']);
                            if (is_null($id_campaign)) $bExito = FALSE;
                        } elseif ($bDoUpdate) {
                            $bExito = $oCamp->updateCampaign(
                                            $id_campaign,
                                            $_POST['nombre'],
                                            $_POST['queue'],
                                            date('Y-m-d', $iFechaIni),
                                            date('Y-m-d', $iFechaFin),
                                            $time_ini,
                                            $time_fin,
                                            $_POST['rte_script'],
                                            NULL,
                                            ($_POST['external_url'] == '') ? NULL : (int)$_POST['external_url']);
                        }

                        // Introducir o actualizar formularios
                        if ($bExito) {
                            if ($bDoCreate) {
                                $bExito = $oCamp->addCampaignForm($id_campaign, $values_form);
                            } elseif ($bDoUpdate) {
                                $bExito = $oCamp->updateCampaignForm($id_campaign, $values_form);
                            }
                        }

                        // Confirmar o deshacer la transacción según sea apropiado
                        if ($bExito) {
                            $pDB->genQuery("COMMIT");
                            header("Location: ?menu=$module_name");
                        } else {
                            $pDB->genQuery("ROLLBACK");
                            $smarty->assign("mb_title", _tr('Validation Error'));
                            $smarty->assign("mb_message", $oCamp->errMsg);
                        }
                    }
                    $pDB->genQuery("SET AUTOCOMMIT=1");
                }
            }
        }
    }

    $smarty->assign('icon', 'images/kfaxview.png');
    $contenidoModulo = $oForm->fetchForm(
        "$local_templates_dir/new.tpl",
        is_null($id_campaign) ? _tr('New Campaign') : _tr('Edit Campaign').' "'.$_POST['nombre'].'"',
        $_POST);
    return $contenidoModulo;
}

function getFormCampaign($arrDataQueues, $arrSelectForm, $arrSelectFormElegidos,
    $arrUrlsExternos)
{
    $horas = array();
    $i = 0;
    for( $i=-1;$i<24;$i++)
    {
        if($i == -1)     $horas["HH"] = "HH";
        else if($i < 10) $horas["0$i"] = '0'.$i;
        else             $horas[$i] = $i;
    }

    $minutos = array();
    $i = 0;
    for( $i=-1;$i<60;$i++)
    {
        if($i == -1)     $minutos["MM"] = "MM";
        else if($i < 10) $minutos["0$i"] = '0'.$i;
        else             $minutos[$i] = $i;
    }

    $formCampos = array(
        'nombre'    =>    array(
            "LABEL"                => _tr('Name Campaign'),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "TEXT",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => "",
        ),

        "fecha_str"       => array(
            "LABEL"                  => _tr('Range Date'),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => '',
            "VALIDATION_EXTRA_PARAM" => ''
        ),
        "fecha_ini"       => array(
            "LABEL"                  => _tr('Start'),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "DATE",
            "INPUT_EXTRA_PARAM"      => array("TIME" => false, "FORMAT" => "%d %b %Y"),
            "VALIDATION_TYPE"        => 'ereg',
            "VALIDATION_EXTRA_PARAM" => '^[[:digit:]]{2}[[:space:]]+[[:alpha:]]{3}[[:space:]]+[[:digit:]]{4}$'
        ),
        "fecha_fin"       => array(
            "LABEL"                  => _tr('End'),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "DATE",
            "INPUT_EXTRA_PARAM"      => array("TIME" => false, "FORMAT" => "%d %b %Y"),
            "VALIDATION_TYPE"        => 'ereg',
            "VALIDATION_EXTRA_PARAM" => '^[[:digit:]]{2}[[:space:]]+[[:alpha:]]{3}[[:space:]]+[[:digit:]]{4}$'
        ),
        "hora_str"       => array(
            "LABEL"                  => _tr('Schedule per Day'),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "",
            "INPUT_EXTRA_PARAM"      => "",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => '',
            "VALIDATION_EXTRA_PARAM" => ''
        ),
        "hora_ini_HH"   => array(
            "LABEL"                  => _tr('Start time'),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "SELECT",
            "INPUT_EXTRA_PARAM"      => $horas,
            "VALIDATION_TYPE"        => 'numeric',
            "VALIDATION_EXTRA_PARAM" => '',
         ),
        "hora_ini_MM"   => array(
            "LABEL"                  => _tr('Start time'),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "SELECT",
            "INPUT_EXTRA_PARAM"      => $minutos,
            "VALIDATION_TYPE"        => 'numeric',
            "VALIDATION_EXTRA_PARAM" => '',
         ),
         "hora_fin_HH"   => array(
            "LABEL"                  => _tr('End time'),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "SELECT",
            "INPUT_EXTRA_PARAM"      => $horas,
            "VALIDATION_TYPE"        => 'numeric',
            "VALIDATION_EXTRA_PARAM" => '',
         ),
         "hora_fin_MM"   => array(
            "LABEL"                  => _tr('End time'),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "SELECT",
            "INPUT_EXTRA_PARAM"      => $minutos,
            "VALIDATION_TYPE"        => 'numeric',
            "VALIDATION_EXTRA_PARAM" => '',
         ),
         'formulario'       => array(
            "LABEL"                  => _tr('Form'),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "SELECT",
            "INPUT_EXTRA_PARAM"      => $arrSelectForm,
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => "",
            "MULTIPLE"               => true,
            "SIZE"                   => "5"
        ),
        'formularios_elegidos'       => array(
            "LABEL"                  => _tr('Form'),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "SELECT",
            "INPUT_EXTRA_PARAM"      => $arrSelectFormElegidos,
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => "",
            "MULTIPLE"               => true,
            "SIZE"                   => "5"
        ),
        "queue" => array(
            "LABEL"                  => _tr('Queue'),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "SELECT",
            "INPUT_EXTRA_PARAM"      => $arrDataQueues,
            "VALIDATION_TYPE"        => "numeric",
            "VALIDATION_EXTRA_PARAM" => ""
        ),
        "script" => array(
            "LABEL"                  => _tr('Script'),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "TEXT",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => ""
        ),
        'external_url'       => array(
            "LABEL"                  => _tr("External URLs"),
            "REQUIRED"               => "no",
            "INPUT_TYPE"             => "SELECT",
            "INPUT_EXTRA_PARAM"      => $arrUrlsExternos,
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => "",
        ),
    );

    return $formCampos;
}

// TODO: validar esta funcion para verificar para qué es necesario escapar.
function adaptar_formato_rte($strText) {
    //returns safe code for preloading in the RTE
    $tmpString = $strText;

    //convert all types of single quotes
    $tmpString = str_replace(chr(145), chr(39), $tmpString);
    $tmpString = str_replace(chr(146), chr(39), $tmpString);
    $tmpString = str_replace("'", "&#39;", $tmpString);

    //convert all types of double quotes
    $tmpString = str_replace(chr(147), chr(34), $tmpString);
    $tmpString = str_replace(chr(148), chr(34), $tmpString);
//  $tmpString = str_replace("\"", "\"", $tmpString);

    //replace carriage returns & line feeds
    $tmpString = str_replace(chr(10), " ", $tmpString);
    $tmpString = str_replace(chr(13), " ", $tmpString);

        //replace comillas dobles por una
        $tmpString = str_replace("\"", "'", $tmpString);

    return $tmpString;
}

function csv_replace($s)
{
    return ($s == '') ? '""' : '"'.str_replace('"',"'", $s).'"';
}

function displayCampaignCSV($pDB, $smarty, $module_name, $local_templates_dir)
{
    $sDatosCSV = '';

    $id_campaign = NULL;
    if (isset($_GET['id_campaign']) && preg_match('/^[[:digit:]]+$/', $_GET['id_campaign']))
        $id_campaign = $_GET['id_campaign'];
    if (is_null($id_campaign)) {
        Header("Location: ?menu=$module_name");
        return '';
    }

    // Se puede tardar mucho tiempo en la descarga
    ini_set('max_execution_time', 3600);

    // Leer los datos de la campaña, si es necesario
    $oCamp = new paloSantoIncomingCampaign($pDB);
    $arrCampaign = $oCamp->getCampaigns(null, null, $id_campaign);
    if (!is_array($arrCampaign) || count($arrCampaign) == 0) {
        $smarty->assign("mb_title", 'Unable to read campaign');
        print 'Cannot read campaign - '.$oCamp->errMsg;
        return '';
    }

    $errMsg = NULL;
    $datosCampania =& $oCamp->getCompletedCampaignData($id_campaign);
    if (is_null($datosCampania)) {
        print $oCamp->errMsg;
    } else {
        header("Cache-Control: private");
        header("Pragma: cache");
        header('Content-Type: text/csv; charset=UTF-8; header=present');
        header("Content-disposition: attachment; filename=\"".$arrCampaign[0]['name'].".csv\"");

        if (count($datosCampania['BASE']['DATA']) <= 0) {
            $sDatosCSV = "No Data Found\r\n";
        } else {
            // Cabeceras del archivo CSV. Se omite la primera etiqueta 'id_call'
            $lineaCSV = array();
            $lineaEspaciador = array();
            $baseLabels = $datosCampania['BASE']['LABEL'];
            array_shift($baseLabels);
            $lineaCSV = array_merge($lineaCSV, array_map('csv_replace', $baseLabels));
            $lineaEspaciador = array_fill(0, count($baseLabels), '""');
            foreach (array_keys($datosCampania['FORMS']) as $id_form) {
                $lineaCSV = array_merge($lineaCSV, array_map('csv_replace', $datosCampania['FORMS'][$id_form]['LABEL']));
                $lineaEspaciador = array_merge(
                    $lineaEspaciador,
                    array_fill(0, count($datosCampania['FORMS'][$id_form]['LABEL']), '"FORMULARIO"')); // TODO: internacionalizar
            }
            $sDatosCSV .= join(',', $lineaEspaciador)."\r\n";
            $sDatosCSV .= join(',', $lineaCSV)."\r\n";

            // Datos del archivo CSV
            foreach ($datosCampania['BASE']['DATA'] as $tuplaDatos) {
                $lineaCSV = array();

                // Datos base de la campaña. Se recoge el primer elemento para id.
                $id_call = array_shift($tuplaDatos);
                $lineaCSV = array_merge($lineaCSV, array_map('csv_replace', $tuplaDatos));

                // Datos de los formularios de la campaña
                foreach (array_keys($datosCampania['FORMS']) as $id_form) {
                    $dataList = NULL;
                    if (isset($datosCampania['FORMS'][$id_form]['DATA'][$id_call])) {
                        $dataList = $datosCampania['FORMS'][$id_form]['DATA'][$id_call];
                    } else {
                        $dataList = array_fill(0, count($datosCampania['FORMS'][$id_form]['LABEL']), NULL);
                    }
                    $lineaCSV = array_merge($lineaCSV, array_map('csv_replace', $dataList));
                }

                $sDatosCSV .= join(',', $lineaCSV)."\r\n";
            }
        }
    }

    return $sDatosCSV;
}

?>