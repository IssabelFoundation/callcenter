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
 $Id: new_campaign.php $ */

class Uploader_CSV
{
    static function main($module_name, $smarty, $local_templates_dir, $pDB)
    {
        $oForm = new paloForm($smarty, array(
            'encoding'          =>  array(
                'LABEL'                     =>  _tr('Call File Encoding'),
                'REQUIRED'                  =>  'yes',
                'INPUT_TYPE'                =>  'SELECT',
                'INPUT_EXTRA_PARAM'         =>  self::_listarCodificaciones(),
                'VALIDATION_TYPE'           =>  'text',
                'VALIDATION_EXTRA_PARAM'    =>  '',
            ),
            'phonefile'          =>  array(
                'LABEL'                     =>  _tr('Call File'),
                'REQUIRED'                  =>  'yes',
                'INPUT_TYPE'                =>  'FILE',
                'INPUT_EXTRA_PARAM'         =>  '',
                'VALIDATION_TYPE'           =>  'text',
                'VALIDATION_EXTRA_PARAM'    =>  '',
            ),
        ));

        if (isset($_POST['save'])) {
            if (!in_array($_POST['encoding'], mb_list_encodings())) {
                $smarty->assign("mb_title", _tr('Validation Error'));
                $smarty->assign("mb_message", _tr('Invalid character encoding'));
            } elseif (empty($_FILES['phonefile']['tmp_name'])) {
                $smarty->assign("mb_title", _tr('Validation Error'));
                $smarty->assign("mb_message", _tr('Call file not specified or failed to be uploaded'));
            } else {
                $pDB->beginTransaction();

                // Se puede tardar mucho tiempo en la inserción
                set_time_limit(0);
                list($bExito, $errMsg) = self::addCampaignNumbersFromFile(
                    $pDB,
                    $_REQUEST['id_campaign'],
                    $_FILES['phonefile']['tmp_name'],
                    $_POST['encoding']);

                // Confirmar o deshacer la transacción según sea apropiado
                if ($bExito) {
                    $pDB->commit();
                    header("Location: ?menu=$module_name");
                    return '';
                } else {
                    $pDB->rollBack();
                    $smarty->assign("mb_title", _tr("Validation Error"));
                    $smarty->assign("mb_message", $errMsg);
                }
            }
        }
        return $oForm->fetchForm(
            $local_templates_dir.'/load_contacts_csv.tpl',
            '', $_POST);
    }

    /**
     * Procedimiento para agregar los números de teléfono indicados por la
     * ruta de archivo indicada a la campaña. No se hace intento alguno por
     * eliminar números existentes de la campaña (véase clearCampaignNumbers()), ni
     * tampoco para verificar si los números existentes se encuentran en el
     * listado nuevo definido.
     *
     * @param   int     $idCampaign ID de la campaña a modificar
     * @param   string  $sFilePath  Archivo local a leer para los números
     *
     * @return bool     VERDADERO si éxito, FALSO si ocurre un error
     */
    private static function addCampaignNumbersFromFile($pDB, $idCampaign, $sFilePath, $sEncoding)
    {
        // Detectar codificación para procesar siempre como UTF-8 (bug #325)
        if (is_null($sEncoding))
            $sEncoding = self::_adivinarCharsetArchivo($sFilePath);

        $hArchivo = fopen($sFilePath, 'rt');
        if (!$hArchivo) {
            return array(FALSE, _tr('Invalid CSV File'));
        }

        $inserter = new paloContactInsert($pDB, $idCampaign);

        if (!$inserter->beforeBatchInsert()) {
            fclose($hArchivo);
            return array(
                FALSE,
                sprintf('(internal) Cannot start batch contact insert - %s',
                    $inserter->errMsg));
        }

        $iNumLinea = 0;
        $clavesColumnas = array();
        while ($tupla = fgetcsv($hArchivo, 8192, ',')) {
            $iNumLinea++;
            if (function_exists('mb_convert_encoding')) {
                foreach ($tupla as $k => $v)
                    $tupla[$k] = mb_convert_encoding($tupla[$k], 'UTF-8', $sEncoding);
            }
            $tupla[0] = trim($tupla[0]);
            if (count($tupla) == 1 && trim($tupla[0]) == '') {
                // Línea vacía
            } elseif (strlen($tupla[0]) > 0 && $tupla[0]{0} == '#') {
                // Línea que empieza por numeral
            } elseif (!preg_match('/^([\d#\*])+$/', $tupla[0])) {
                if ($iNumLinea == 1) {
                    // Podría ser una cabecera de nombres de columnas
                    array_shift($tupla);
                    $clavesColumnas = $tupla;
                } else {
                    // Teléfono no es numérico
                    fclose($hArchivo);
                    return array(
                        FALSE,
                        _tr('Invalid CSV File Line')." "."$iNumLinea: "._tr('Invalid number')
                    );
                }
            } else {
                // Como efecto colateral, $tupla pierde su primer elemento
                $numero = array_shift($tupla);
                $atributos = array();
                for ($i = 0; $i < count($tupla); $i++) {
                    $atributos[$i + 1] = array(
                        ($i < count($clavesColumnas) && $clavesColumnas[$i] != '') ? $clavesColumnas[$i] : ($i + 1),
                        $tupla[$i],
                    );
                }
                $idCall = $inserter->insertOneContact($numero, $atributos);
                if (is_null($idCall)) {
                    fclose($hArchivo);
                    return array(
                        FALSE,
                        sprintf('(internal) Cannot insert phone %s at line %d - %s',
                            $numero, $iNumLinea, $inserter->errMsg));
                }
            }
        }
        fclose($hArchivo);

        if (!$inserter->afterBatchInsert()) {
            return array(
                FALSE,
                sprintf('(internal) Cannot close batch contact insert - %s',
                    $inserter->errMsg));
        }

        return array(TRUE, NULL);
    }

    private static function _listarCodificaciones()
    {
        $listaEncodings = array(
            'UTF-8' =>  _tr('UTF-8'),
        );
        $listaPosterior = array();
        foreach (mb_list_encodings() as $sEnc) {
            if (!isset($listaEncodings[$sEnc]) && !isset($listaPosterior[$sEnc]) &&
                !in_array($sEnc, array('pass', 'wchar', 'BASE64', 'UUENCODE',
                    'HTML-ENTITIES', 'Quoted-Printable', 'UTF7-IMAP'))) {
                if ($sEnc != _tr($sEnc))
                    $listaEncodings[$sEnc] = _tr($sEnc);
                else $listaPosterior[$sEnc] = _tr($sEnc);
            }
        }
        $listaEncodings = array_merge($listaEncodings, $listaPosterior);
        return $listaEncodings;
    }

    // Función que intenta adivinar la codificación de caracteres del archivo
    private static function _adivinarCharsetArchivo($sFilePath)
    {
        if (!function_exists('mb_detect_encoding')) return 'UTF-8';

        // Agregar a lista para detectar más encodings. ISO-8859-15 debe estar
        // al último porque toda cadena de texto es válida como ISO-8859-15.
        $listaEncodings = array(
            "ASCII",
            "UTF-8",
            //"EUC-JP",
            //"SJIS",
            //"JIS",
            //"ISO-2022-JP",
            "ISO-8859-15"
        );
        //$sContenido = file_get_contents($sFilePath);

        // Ya no se usa file_get_contents() porque el archivo puede ser muy grande
        $hArchivo = fopen($sFilePath);
        if (!$hArchivo) return 'UTF-8';
        for ($i = 0; $i < 20 && !feof($hArchivo); $i++) $sContenido .= fgets($hArchivo);
        fclose($hArchivo);
        $sEncoding = mb_detect_encoding($sContenido, $listaEncodings);
        return $sEncoding;
    }
}