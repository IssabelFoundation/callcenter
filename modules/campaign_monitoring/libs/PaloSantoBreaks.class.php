<?php
/* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Issabel version 0.5                                                  |
  | http://www.issabel.org                                               |
  +----------------------------------------------------------------------+
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
  $Id: new_campaign.php $ */

include_once("libs/paloSantoDB.class.php");

/* Clase que implementa breaks */
class PaloSantoBreaks
{
    var $_DB; // instancia de la clase paloDB
    var $errMsg;

    function PaloSantoBreaks(&$pDB)
    {
        // Se recibe como parámetro una referencia a una conexión paloDB
        if (is_object($pDB)) {
            $this->_DB =& $pDB;
            $this->errMsg = $this->_DB->errMsg;
        } else {
            $dsn = (string)$pDB;
            $this->_DB = new paloDB($dsn);

            if (!$this->_DB->connStatus) {
                $this->errMsg = $this->_DB->errMsg;
                // debo llenar alguna variable de error
            } else {
                // debo llenar alguna variable de error
            }
        }
    }

    /**
     * Procedimiento para obtener la cantidad de los breaks existentes. Si
     * se especifica id, el listado contendrá únicamente el break
     * indicada por el valor. De otro modo, se listarán todas los breaks.
     *
     * @param int       $id_break    Si != NULL, indica el id del break a recoger
     * @param string    $estatus    'I' para breaks inactivos, 'A' para activos,
     *                              cualquier otra cosa para todos los breaks.
     *
     * @return array    Listado de breaks en el siguiente formato, o FALSE en
     *                  caso de error:
     *  array(
     *      array(id,name,description),....,
     *  )
     */


    function Ver_Agendadas($idcampana)
	{
	$sPeticionSQL = 'SELECT * FROM calls where scheduled = 1 and id_campaign='.$idcampana;
//die($sPeticionSQL);
	$id_break="";
	$recordset = $this->_DB->fetchTable($sPeticionSQL, FALSE, $paramSQL);
	return $recordset;
	}

}

?>
