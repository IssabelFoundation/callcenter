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
*/
    
require_once "libs/paloSantoForm.class.php";
require_once "modules/agent_console/libs/elastix2.lib.php";

function _moduleContent(&$smarty,$module_name)
{
    include_once "modules/$module_name/configs/config.php";
    require_once "modules/$module_name/libs/paloSantoUploadFile.class.php";

    // Obtengo la ruta del template a utilizar para generar el filtro.
    $base_dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $templates_dir = (isset($config['templates_dir']))?$config['templates_dir']:'themes';
    $local_templates_dir = "$base_dir/modules/$module_name/".$templates_dir.'/'.$arrConfig['theme'];

    load_language_module($module_name);

    $smarty->assign('FRAMEWORK_TIENE_TITULO_MODULO', existeSoporteTituloFramework());
    $smarty->assign('icon', 'images/list.png');
    $smarty->assign("MODULE_NAME", $module_name);
    $smarty->assign("LABEL_MESSAGE", _tr('Select file upload'));
    $smarty->assign("Format_File", _tr('Format File'));
    $smarty->assign("File", _tr('File'));
    $smarty->assign('ETIQUETA_SUBMIT', _tr('Upload'));
    $smarty->assign('ETIQUETA_DOWNLOAD', _tr('Download contacts'));
    $smarty->assign('Format_Content', _tr('"Phone","Identification Card","Name","Last Name"'));

    $form_campos = array(
        'file'    =>    array(
            "LABEL"                  => _tr('File'),
            "REQUIRED"               => "yes",
            "INPUT_TYPE"             => "FILE",
            "INPUT_EXTRA_PARAM"      => "",
            "VALIDATION_TYPE"        => "text",
            "VALIDATION_EXTRA_PARAM" => "",
        ),
    );

    $oForm = new paloForm($smarty,$form_campos);
    $fContenido = $oForm->fetchForm("$local_templates_dir/form.tpl", _tr('Load File') ,$_POST);

    if (isset($_POST['cargar_datos'])) {
        $infoArchivo = $_FILES['fileCRM'];
        if ($infoArchivo['error'] != 0) {
            $smarty->assign("mb_title", _tr('Error'));
            $smarty->assign("mb_message", _tr('Error while loading file'));
        } else {
            $sNombreTemp = $infoArchivo['tmp_name'];
            $pDB = new paloDB($arrConfig['cadena_dsn']);
            $oCarga = new paloSantoUploadFile($pDB);
            $sEncoding = NULL;
            $bExito = $oCarga->addCampaignNumbersFromFile($sNombreTemp, $sEncoding);
            if (!$bExito) {
                $smarty->assign("mb_title", _tr('Error'));
                $smarty->assign("mb_message", _tr('Error while loading file').': '.$oCarga->errMsg);
            } else {
                $r = $oCarga->obtenerContadores();
                $smarty->assign("mb_title", _tr('Result'));
                $smarty->assign("mb_message", 
                    _tr('Inserted records').': '.$r[0].'<br/>'.
                    _tr('Updated records').': '.$r[1].'<br/>'.
                    _tr('Detected charset').': '.$sEncoding);
            }
        }
    } elseif (isset($_GET['action']) && $_GET['action'] == 'csvdownload') {
        $pDB = new paloDB($arrConfig['cadena_dsn']);
        $oCarga = new paloSantoUploadFile($pDB);
        $r = $oCarga->leerContactos();
        if (!is_array($r)) {
            $smarty->assign("mb_title", _tr('Error'));
            $smarty->assign("mb_message", $oCarga->errMsg);
            return $oCarga->errMsg;
        } else {
            header("Cache-Control: private");
            header("Pragma: cache");
            header('Content-Type: text/csv; charset=UTF-8; header=present');
            header("Content-disposition: attachment; filename=\"contacts.csv\"");

            $fContenido = '';
            foreach ($r as $tuplaDatos) {
                $fContenido .= join(',', array_map('csv_replace', $tuplaDatos))."\r\n";
            }
        }
    }
    return $fContenido;
}

function csv_replace($s)
{
    return ($s == '') ? '""' : '"'.str_replace('"',"'", $s).'"';
}

?>
