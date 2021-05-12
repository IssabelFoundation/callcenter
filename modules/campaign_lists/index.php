<?php
  /* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Issabel version {ISSBEL_VERSION}                                               |
  | http://www.issabel.org                                               |
  +----------------------------------------------------------------------+
  | Copyright (c) 2017 Issabel Foundation                                |
  | Copyright (c) 2006 Palosanto Solutions S. A.                         |
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
  | The Initial Developer of the Original Code is PaloSanto Solutions    |
  +----------------------------------------------------------------------+
  $Id: index.php,v 1.1 2018-09-25 02:09:20 Nestor Islas nestor_islas@outlook.com Exp $ */
//include issabel framework
include_once "libs/paloSantoGrid.class.php";
include_once "libs/paloSantoForm.class.php";

require_once "modules/agent_console/libs/issabel2.lib.php";
require_once "modules/agent_console/libs/JSON.php";

function _moduleContent(&$smarty, $module_name)
{
    //include module files
    include_once "modules/$module_name/configs/default.conf.php";
    include_once "modules/$module_name/libs/paloSantoCampaign_Lists.class.php";

    //include file language agree to issabel configuration
    //if file language not exists, then include language by default (en)
    $lang=get_language();
    $base_dir=dirname($_SERVER['SCRIPT_FILENAME']);
    $lang_file="modules/$module_name/lang/$lang.lang";
    if (file_exists("$base_dir/$lang_file")) include_once "$lang_file";
    else include_once "modules/$module_name/lang/en.lang";

    //global variables
    global $arrConf;
    global $arrConfModule;
    global $arrLang;
    global $arrLangModule;
    $arrConf = array_merge($arrConf,$arrConfModule);
    $arrLang = array_merge($arrLang,$arrLangModule);

    //folder path for custom templates
    $templates_dir=(isset($arrConf['templates_dir']))?$arrConf['templates_dir']:'themes';
    $local_templates_dir="$base_dir/modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];

    //conexion resource
    $pDB = new paloDB($arrConf['dsn_conn_database']);


    //actions
    $action = getAction();
    $content = "";

    switch($action){
        case 'create':
            $content = loadListContacts($smarty, $module_name, $local_templates_dir, $pDB, $arrConf);
        break;
        case 'view':
            $content = viewList($smarty, $module_name, $local_templates_dir, $pDB, $arrConf);
        break;
		case 'getLists':
            $content = manejarMonitoreo_getList($smarty, $module_name, $local_templates_dir, $pDB, $arrConf);
        break;
		default:
            $content = reportLists($smarty, $module_name, $local_templates_dir, $pDB, $arrConf);
            break;
    }
    return $content;
}

function reportLists($smarty, $module_name, $local_templates_dir, &$pDB, $arrConf)
{
    $pCampaign_Lists = new paloSantoCampaign_Lists($pDB);
    $filter_field = getParameter("filter_field");
    $filter_value = getParameter("filter_value");

    // Recoger ID de lista para operación
    $id_list = NULL;
    if (isset($_POST['id_list']) && ctype_digit($_POST['id_list']))
        $id_list = $_POST['id_list'];

    // Revisar si se debe de borrar una lista elegida
    if (isset($_POST['delete']) && !is_null($id_list)) {
        if($pCampaign_Lists->delete_list($id_list)) {
            $smarty->assign("mb_title",_tr('success_title_deleted'));
            $smarty->assign("mb_message", _tr('success_message_deleted'));
        } else {
            $msg_error = ($pCampaign_Lists->errMsg!="") ? "<br/>".$pCampaign_Lists->errMsg:"";
            $smarty->assign("mb_title", _tr('error_title_deleted'));
            $smarty->assign("mb_message", _tr('error_message_deleted').$msg_error);
        }
    }

    if (isset($_POST['change_status']) && !is_null($id_list)){
        if($_POST['status_list_sel']=='activate'){
            if(!$pCampaign_Lists->changeStatusList($id_list, 1)) {
                $smarty->assign("mb_title", _tr('error_title_activate_status'));
                $smarty->assign("mb_message", _tr('error_message_activate_status').': '.$pCampaign_Lists->errMsg);
            }
            else
            {
                $smarty->assign("mb_title", _tr('success_title_activate_status'));
                $smarty->assign("mb_message", _tr('success_message_activate_status').': '.$id_list);
            }
        }elseif($_POST['status_list_sel']=='deactivate'){
            if(!$pCampaign_Lists->changeStatusList($id_list, 2)) {
                $smarty->assign("mb_title", _tr('error_title_deactivate_status'));
                $smarty->assign("mb_message", _tr('error_message_deactivate_status').': '.$pCampaign_Lists->errMsg);
            }
            else
            {
                $smarty->assign("mb_title", _tr('success_title_deactivate_status'));
                $smarty->assign("mb_message", _tr('success_message_deactivate_status').': '.$id_list);
            }
        }
    }

    $oGrid  = new paloSantoGrid($smarty);
    $oGrid->setTitle(_tr("lists_grid_title"));
    $oGrid->pagingShow(true);

    $url = array(
        "menu"         =>  $module_name,
        "filter_field" =>  $filter_field,
        "filter_value" =>  $filter_value);
    $oGrid->setURL($url);

    $arrColumns = array("", _tr("grid_column_id"), _tr("grid_column_name"), _tr("grid_column_type"), _tr("grid_column_campaign"), _tr("grid_column_file"), _tr("grid_column_total_calls"), _tr("grid_column_pending_calls"), _tr("grid_column_status"), _tr("grid_column_date"), "");
    $oGrid->setColumns($arrColumns);

    $total   = $pCampaign_Lists->getNumCampaign_Lists($filter_field, $filter_value);
    $arrData = null;
    if($oGrid->isExportAction()){
        $limit  = $total; // max number of rows.
        $offset = 0;      // since the start.
    }
    else{
        $limit  = 20;
        $oGrid->setLimit($limit);
        $oGrid->setTotal($total);
        $offset = $oGrid->calculateOffset();
    }

    $arrResult =$pCampaign_Lists->getCampaign_Lists($limit, $offset, $filter_field, $filter_value);

    if(is_array($arrResult) && $total>0){
		$label_status = '';
		foreach($arrResult as $key => $value){
            $campaignName = $pCampaign_Lists->getCampaign_Name($value['id_campaign'], $value['type']);
            $arrTmp[0] = ($value['status'] != 3)?"<input class=\"input\" type=\"radio\" name=\"id_list\" value=\"{$value['id']}\"/>": '&nbsp;';
            $arrTmp[1] = $value['id'];
            $arrTmp[2] = "<b><a href=\"?menu={$module_name}&amp;action=view&amp;id_list={$value['id']}\">[".htmlentities(utf8_encode($value['name']), ENT_COMPAT, "UTF-8").']</a></b>';
            $arrTmp[3] = $value['sType'];
            $arrTmp[4] = "<a href='?menu=campaign_out&amp;action=edit_campaign&amp;id_campaign=".$value['id_campaign']."'>".htmlentities($campaignName, ENT_COMPAT, 'UTF-8').'</a>';
            $arrTmp[5] = htmlentities(utf8_encode($value['upload']), ENT_COMPAT, "UTF-8").'&nbsp;';
            $arrTmp[6] = '<span id="total_calls_'.$value['id'].'">'.htmlentities($value['total_calls'], ENT_COMPAT, "UTF-8").'</span>';;
            $arrTmp[7] = '<span id="pending_calls_'.$value['id'].'">'.htmlentities($value['pending_calls'], ENT_COMPAT, "UTF-8").'</span>';
            switch ($value['status']) {
                case 1:
                    $label_status = '<span class="label label-success">'.htmlentities($value['sStatus'], ENT_COMPAT, "UTF-8").'</span>';
                    break;
                case 2:
                    $label_status = '<span class="label label-info">'.htmlentities($value['sStatus'], ENT_COMPAT, "UTF-8").'</span>';
                    break;
                case 3:
                    $label_status = '<span class="label label-default">'.htmlentities($value['sStatus'], ENT_COMPAT, "UTF-8").'</span>';
                    break;
                default:
                    $label_status = htmlentities($value['sStatus'], ENT_COMPAT, "UTF-8").'&nbsp;';
                    break;
            }
            $arrTmp[8] = $label_status;
            $arrTmp[9] = htmlentities($value['date_entered'], ENT_COMPAT, "UTF-8").'&nbsp;';
            $arrTmp[10] = ($value['status'] == 3)
                ? "<a href=\"?menu={$module_name}&amp;action=recycle&amp;id_list={$value['id']}\">["._tr('label_recycle_list').']</a>'
                : '&nbsp;';
            $arrData[] = $arrTmp;
        }
    }
    $oGrid->setData($arrData);

    $oGrid->addNew("?menu=$module_name&action=new_list", _tr('label_new_list'), TRUE);
    $oGrid->deleteList(_tr('label_delete_message'), 'delete', _tr('label_delete_list'));
    $oGrid->addComboAction('status_list_sel', _tr("label_change_status_cb"), array(
        'activate'      =>  _tr('label_cb_activate'),
        'deactivate'    =>  _tr('label_cb_deactivate'),
    ), null, 'change_status');
    $content = $oGrid->fetchGrid();
    //end grid parameters

    return $content;
}

function viewList($smarty, $module_name, $local_templates_dir, &$pDB, $arrConf)
{
    $pCampaign_Lists = new paloSantoCampaign_Lists($pDB);
    $id_list = getParameter('id_list');

    $pCampaign_Lists->updateListStats($id_list);
    $infoList = $pCampaign_Lists->getCampaign_ListsById($id_list);
    if (!is_array($infoList)) {
            $smarty->assign("mb_title", _tr("error_title_view_list"));
            $smarty->assign("mb_message", $pDB->errMsg);
        } 

    $smarty->assign('FRAMEWORK_TIENE_TITULO_MODULO', existeSoporteTituloFramework());
    $smarty->assign('title', _tr("view_list_title")." ".$infoList['id']);

    $smarty->assign('id_list', $infoList['id']);
    $smarty->assign('name_list', utf8_encode($infoList['name']));
    $smarty->assign('file_list', utf8_encode($infoList['upload']));
    $smarty->assign('date_list', $infoList['date_entered']);
    $smarty->assign('status_list', $infoList['sStatus']);
    $smarty->assign('total_calls', $infoList['total_calls']);

    $smarty->assign('pending_calls', ($infoList['status'] == 1)?$infoList['pending_calls']:$infoList['paused_calls']);
    $smarty->assign('sent_calls', $infoList['sent_calls']);

    $labelDays = array('monday' => _tr('label_monday'), 'tuesday' => _tr('label_tuesday'), 'wednesday' => _tr('label_wednesday'), 'thursday' => _tr('label_thursday'), 'friday' => _tr('label_friday'), 'saturday' => _tr('label_saturday'), 'sunday' => _tr('label_sunday'));
    $dataWeek = $pCampaign_Lists->getListStatsByWeek($id_list);
    
    $smarty->assign('dataWeek', $dataWeek);
    $smarty->assign('labelWeek', $labelDays);
    $smarty->assign('label_week', _tr('label_successful_week_calls'));
    
    $labelDetailBar = array('pending_calls' => _tr('label_pending_calls'), 'paused_calls' => _tr('label_paused_calls'), 'sent_calls' => _tr('label_sent_calls'), 'answered_calls' => _tr('label_answered_calls'), 'no_answer_calls' => _tr('label_no_answer_calls'), 'failed_calls' => _tr('label_failed_calls'), 'abandoned_calls' => _tr('label_abandoned_calls'), 'short_calls' => _tr('label_short_calls'));
    $smarty->assign('paused_calls', $infoList['paused_calls']);
    $smarty->assign('sent_calls', $infoList['sent_calls']);
    $smarty->assign('answered_calls', $infoList['answered_calls']);
    $smarty->assign('no_answer_calls', $infoList['no_answer_calls']);
    $smarty->assign('failed_calls', $infoList['failed_calls']);
    $smarty->assign('abandoned_calls', $infoList['abandoned_calls']);
    $smarty->assign('short_calls', $infoList['short_calls']);
    $smarty->assign('labelDetailBar', $labelDetailBar);
    $smarty->assign('label_bar_chart', _tr('label_bar_chart'));

    $contentView = $smarty->fetch("$local_templates_dir/view_list.tpl");

    return $contentView;
}

function loadListContacts($smarty, $module_name, $local_templates_dir, &$pDB, $arrConf)
{
    require_once "modules/campaign_out/libs/paloSantoCampaignCC.class.php";
    require_once "modules/$module_name/libs/paloContactInsert.class.php";

    $id_campaign = (isset($_REQUEST['id_campaign']) && ctype_digit($_REQUEST['id_campaign']))
        ? (int)$_REQUEST['id_campaign'] : NULL;
    if (!is_null($id_campaign)) {
        $_POST['id_campaign'] = $id_campaign;
    }

    // Si se ha indicado cancelar, volver a listado sin hacer nada más
    if (isset($_POST['cancel'])) {
        Header("Location: ?menu=$module_name");
        return '';
    }

    $pCampaign_Lists = new paloSantoCampaign_Lists($pDB);

    $arrCampaigns = $pCampaign_Lists->getCampaignsOutgoing();

    if (!is_array($arrCampaigns)) {
        $smarty->assign(array(
            'mb_title' => 'Fallo al leer campañas', 
            'mb_message' => $pCampaign_Lists->errMsg));
        $arrCampaigns = array('Unavailable campaigns.');
    }

    $smarty->assign(array(
        'FRAMEWORK_TIENE_TITULO_MODULO' =>  existeSoporteTituloFramework(),
        'REQUIRED_FIELD'                =>  _tr("label_required_field"),
        'CANCEL'                        =>  _tr("label_cancel"),
        'SAVE'                          =>  _tr("label_save"),
        'LBL_OPTIONS_UPLOADER'          =>  _tr('label_options').': ',
        'LBL_UPLOADERS'                 =>  _tr('label_available_uploaders'),
        'LBL_CAMPAIGN'                 =>  _tr('label_sel_campaign'),
        'icon'                          =>  'images/kfaxview.png',
    ));

    // Construir lista de todos los cargadores conocidos
    $listuploaders = array();
    $uploadersdir = "modules/$module_name/uploaders";
    foreach (scandir("$uploadersdir/") as $uploader) {
        if ($uploader != '.' && $uploader != '..' && is_dir("$uploadersdir/$uploader")) {
            $listuploaders[$uploader] = $uploader;
        }
    }

    // Carga de todas las funciones auxiliares de los diálogos
    foreach ($listuploaders as $uploader) {
        if (file_exists("modules/$module_name/uploaders/$uploader/index.php")) {
            if (file_exists("modules/$module_name/uploaders/$uploader/lang/en.lang"))
                load_language_module("$module_name/uploaders/$uploader");
            require_once "modules/$module_name/uploaders/$uploader/index.php";
        }
    }

    $oForm = new paloForm($smarty, array(
        'uploader'          =>  array(
            'LABEL'                     =>  _tr('label_available_uploaders'),
            'REQUIRED'                  =>  'yes',
            'INPUT_TYPE'                =>  'SELECT',
            'INPUT_EXTRA_PARAM'         =>  $listuploaders,
            'VALIDATION_TYPE'           =>  'text',
            'VALIDATION_EXTRA_PARAM'    =>  '',
            'ONCHANGE'                  =>  'submit();',
        ),
        "list_name"    =>    array(
            "LABEL"                  => _tr("label_list_name"),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "TEXT",
            "INPUT_EXTRA_PARAM"      => array("size" => "40"),
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => "",
        ),
        "id_campaign"   => array(
            "LABEL"                  => _tr("label_sel_campaign"),
            "EDITABLE"               => "yes",
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "SELECT",
            "INPUT_EXTRA_PARAM"      => $arrCampaigns,            
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => ""
        )
    ));

    $selected_uploader = isset($_REQUEST['uploader']) ? $_REQUEST['uploader'] : 'CSV';
    if (!in_array($selected_uploader, $listuploaders)) $selected_uploader = 'CSV';

    $classname = 'Uploader_'.$selected_uploader;
    $method = (isset($_REQUEST['uploader_action']) && method_exists($classname, 'handleJSON_'.$_REQUEST['uploader_action']))
        ? 'handleJSON_'.$_REQUEST['uploader_action'] : 'main';
    $h = array($classname, $method);
    $r = call_user_func($h, $module_name, $smarty, realpath($local_templates_dir.'/../../uploaders/'.$selected_uploader.'/tpl'), $pDB);
    if ($method != 'main') return $r;

    $smarty->assign('CONTENT_UPLOADER', $r);
    $smarty->assign('LBL_OPTIONS_UPLOADER', _tr('label_options').': '.htmlentities($selected_uploader, ENT_COMPAT, 'UTF-8'));
    return $oForm->fetchForm(
        "$local_templates_dir/load_contacts.tpl",
        _tr("label_load_contacts"),$_POST);
}

function createFieldFilter(){
    $arrFilter = array(
	    "" => _tr(""),
                    );

    $arrFormElements = array(
            "filter_field" => array("LABEL"                  => _tr("label_search"),
                                    "REQUIRED"               => "no",
                                    "INPUT_TYPE"             => "SELECT",
                                    "INPUT_EXTRA_PARAM"      => $arrFilter,
                                    "VALIDATION_TYPE"        => "text",
                                    "VALIDATION_EXTRA_PARAM" => ""),
            "filter_value" => array("LABEL"                  => "",
                                    "REQUIRED"               => "no",
                                    "INPUT_TYPE"             => "TEXT",
                                    "INPUT_EXTRA_PARAM"      => "",
                                    "VALIDATION_TYPE"        => "text",
                                    "VALIDATION_EXTRA_PARAM" => ""),
                    );
    return $arrFormElements;
}

function manejarMonitoreo_getList($smarty, $module_name, $local_templates_dir, &$pDB, $arrConf)
{
    $respuesta = array(
        'status'    =>  'success',
        'message'   =>  '(no message)',
    );

    $pCampaign_Lists = new paloSantoCampaign_Lists($pDB);
    $arrResult =$pCampaign_Lists->getCampaign_Lists_Stats();

    if (!is_array($arrResult)) {
    	$respuesta['status'] = 'error';
        $respuesta['message'] = $oPaloConsola->errMsg;
    }
    else
    	$respuesta['lists'] = $arrResult;

    $json = new Services_JSON();
    Header('Content-Type: application/json');
    return $json->encode($respuesta);
}

function getAction()
{
    if(getParameter("save_new")) //Get parameter by POST (submit)
        return "save_new";
    else if(getParameter("save_edit"))
        return "save_edit";
    else if(getParameter("delete")) 
        return "delete";
    else if(getParameter("action")=="new_list")      //Get parameter by GET (command pattern, links)
        return "create";
    else if(getParameter("action")=="view")      //Get parameter by GET (command pattern, links)
        return "view";
    else if(getParameter("action")=="edit")
        return "update";
	else if(getParameter("action")=="getLists")
        return "getLists";
    else
        return "report"; //cancel
}
?>