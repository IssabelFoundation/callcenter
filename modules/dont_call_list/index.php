<?php
/* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
 Codificación: UTF-8
+----------------------------------------------------------------------+
| Elastix version 0.8                                                  |
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
*/
require_once "libs/paloSantoForm.class.php";
require_once "libs/paloSantoGrid.class.php";

function _moduleContent(&$smarty, $module_name)
{
    //include module files
    require_once "modules/$module_name/configs/default.conf.php";
    require_once "modules/$module_name/libs/paloSantoDontCall.class.php";

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
        return agregarNumeros($pDB, $smarty, $module_name, $local_templates_dir);
    case 'list':
    default:
        return listarNumeros($pDB, $smarty, $module_name);
    }
}

function listarNumeros($pDB, $smarty, $module_name)
{
    $arrColumns = array('', _tr("Number Phone's"), _tr('Date Income'), _tr('Status'));
    $url = array('menu' => $module_name);
    $limit = 15;
    
    $oGrid = new paloSantoGrid($smarty);
    $oGrid->pagingShow(true);
    $oGrid->setLimit($limit);
    $oGrid->addNew("?menu=$module_name&action=add", _tr('Add'), TRUE);
    $oGrid->deleteList('Are you sure to remove selected DNC?', 'remove', _tr('Delete'));
    $oGrid->setColumns($arrColumns);
    $oGrid->setURL($url);
    $oGrid->setTitle(_tr('Phone List'));
    
    // Ejecutar operaciones indicadas en formularios
    $oDataForm = new paloSantoDontCall($pDB);
    if (isset($_POST['id']) && is_array($_POST['id']) && count($_POST['id']) > 0) {
        $bExito = TRUE;
        if (isset($_POST['remove'])) {
            $mb = array(
                'mb_title'  => _tr('Delete Error'), 
                'mb_message'=> _tr('Could not remove batch')
            );
            $bExito = $oDataForm->borrarDontCall($_POST['id']);
        }
        if (!$bExito) {
            $mb['mb_message'] .= ': '.$oDataForm->errMsg;
            $smarty->assign($mb);
        }
    }
    
    // Obtener listado de formularios
    $total = $oDataForm->contarDontCall();
    if ($total === FALSE) {
        $smarty->assign("mb_message", _tr("Error when connecting to database")." ".$oDataForm->errMsg);
        return '';
    }
    $oGrid->setTotal($total);
    $offset = $oGrid->calculateOffset();
    $arrDataNumbers = $oDataForm->listarDontCall($limit, $offset);
    if (!is_array($arrDataNumbers)) {
        $smarty->assign("mb_message", _tr("Error when connecting to database")." ".$oDataForm->errMsg);
        return '';
    }
    $arrData = array();
    foreach ($arrDataNumbers as $tuplaNumber) {
    	$arrData[] = array(
    	    '<input type="checkbox" name="id[]" value="'.$tuplaNumber['id'].'"/>',
            $tuplaNumber['caller_id'],
            $tuplaNumber['date_income'],
            ($tuplaNumber['status'] == 'I' ? _tr('Inactive') : _tr('Active')),
        );
    }

    $oGrid->setData($arrData);
    return $oGrid->fetchGrid();
}

function agregarNumeros($pDB, $smarty, $module_name, $local_templates_dir)
{
    if (isset($_POST['cancel'])) {
        Header('Location: ?menu='.$module_name);
        return '';
    }
    $oForm = new paloForm($smarty, array(
        'new_accion'           =>  array(
            'LABEL'             =>  '',
            'REQUIRED'          =>  'yes',
            'INPUT_TYPE'        =>  'RADIO',
            'INPUT_EXTRA_PARAM' =>  array(
                'file'  =>  _tr('Upload File'),
                'text'  =>  _tr('Add new Number')
            ),
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => "",
        ),
        'txt_new_number'       =>    array(
            "LABEL"                => _tr('Add new Number'),
            "REQUIRED"               => "no",
            "INPUT_TYPE"             => "TEXT",
            "INPUT_EXTRA_PARAM"      => array(
                "size"          => "15",
                'pattern'       => '^\d+$',
                'placeholder'   =>  '5551234'),
            "VALIDATION_TYPE"        => "numeric",
            "VALIDATION_EXTRA_PARAM" => "",
        ),
        'file_number'  =>    array(
            "LABEL"                => _tr('Load File'),
            "REQUIRED"               => "no",
            "INPUT_TYPE"             => "FILE",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => "",
        ),
    ));
    if (isset($_POST['apply_changes'])) {
        $mb = NULL;
        $oDataForm = new paloSantoDontCall($pDB);
        if (!$oForm->validateForm($_POST)) {
            $mb = array(
                'mb_title'      =>  _tr('Validation Error'),
                'mb_message'    =>  '<b>'._tr('The following fields contain errors').':</b><br/>'
                    .implode(', ', array_keys($oForm->arrErroresValidacion)),
            );
        } elseif ($_POST['new_accion'] == 'text') {
            if (!$oDataForm->insertarNumero($_POST['txt_new_number'])) {
                $mb = array(
                    'mb_title'      =>  _tr('Error'),
                    'mb_message'    =>  $oDataForm->errMsg,
                );
            } else {
                $mb = array(
                    'mb_title'      =>  _tr('Result'),
                    'mb_message'    =>  _tr('DNC inserted correctly'),
                );
                
            }
        } elseif ($_POST['new_accion'] == 'file') {
            if ($_FILES['file_number']['error'] != UPLOAD_ERR_OK) {
                $uperr = $_FILES['file_number']['error'];
                $msgmap = array(
                    UPLOAD_ERR_INI_SIZE     =>  _tr('Upload exceeds server side limit'),
                    UPLOAD_ERR_FORM_SIZE    =>  _tr('Upload exceeds client side limit'),
                    UPLOAD_ERR_PARTIAL      =>  _tr('Interrupted or partial upload'),
                    UPLOAD_ERR_NO_FILE      =>  _tr('No file uploaded'),
                    UPLOAD_ERR_NO_TMP_DIR   =>  _tr('No temp dir configured'),
                    UPLOAD_ERR_CANT_WRITE   =>  _tr('Failed to write upload to temp dir'),
                    UPLOAD_ERR_EXTENSION    =>  _tr('Upload terminated by extension'),
                );
                $msg = isset($msgmap[$uperr]) ? $msgmap[$uperr] : _tr('Unknown error');
                $mb = array(
                    'mb_title'      =>  _tr('Error'),
                    'mb_message'    =>  _tr('Error when is loading file').': '.$msg,
                );
            } else {
                ini_set('max_execution_time', 3600);    // Máximo de 1 hora para carga
                $loadReport = $oDataForm->cargarArchivo($_FILES['file_number']['tmp_name']);
                if (!is_array($loadReport)) {
                    $mb = array(
                        'mb_title'      =>  _tr('Error'),
                        'mb_message'    =>  _tr('Error when is loading file').': '.$oDataForm->errMsg,
                    );
                } else {
                    $mb = array(
                        'mb_title'      =>  _tr('Result'),
                        'mb_message'    =>  sprintf(_tr('Total records: %d Inserted: %d Rejected %d'),
                            $loadReport['total'], $loadReport['inserted'], $loadReport['rejected']),
                    );
                }
            }
        }
        if (!is_null($mb)) $smarty->assign($mb);
    }
    
    if (!isset($_POST['new_accion'])) $_POST['new_accion'] = 'file';
    $smarty->assign(array(
        'icon'                  =>  'images/list.png',
        'CANCEL'                =>  _tr('Cancel'),
        'SAVE'                  =>  _tr('Save'),
        'LABEL_MAX_FILESIZE'    =>  sprintf(_tr('Maximum upload size: %d Mb'), calcularMaxSubida() / 1048576),
    ));
    return $oForm->fetchForm("$local_templates_dir/new.tpl", _tr('Add Number'), $_POST);    
}

function calcularMaxSubida()
{
    $max_upload = NULL;
    
    foreach (array('upload_max_filesize', 'post_max_size') as $k) {
        $v = strtolower(trim(ini_get($k)));
        switch ($v[strlen($v) - 1]) {
        case 'g': $v *= 1024;
        case 'm': $v *= 1024;
        case 'k': $v *= 1024;
        }
        if (is_null($max_upload) || $max_upload > $v)
            $max_upload = $v;
    }
    return $max_upload;
}
?>