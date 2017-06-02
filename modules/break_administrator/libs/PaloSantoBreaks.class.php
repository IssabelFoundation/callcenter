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
    function countBreaks($id_break = NULL, $estatus='all'){
        // Validación
        $this->errMsg = "";
        if (!is_null($id_break) && !preg_match('/^\d+$/', $id_break)) {
            $this->errMsg = _tr("Break ID is not valid");
            return FALSE;
        }
        if (!in_array($estatus, array('I', 'A'))) $estatus = NULL;
    
        // Construcción de petición y sus parámetros
        $sPeticionSQL = 'SELECT count(id) FROM break WHERE tipo = ?';
        $paramSQL = array('B');
        if (!is_null($id_break)) { $sPeticionSQL .= ' AND id = ?'; $paramSQL[] = $id_break; }
        if (!is_null($estatus)) { $sPeticionSQL .= ' AND status = ?'; $paramSQL[] = $estatus; }
        $recordset = $this->_DB->getFirstRowQuery($sPeticionSQL, FALSE, $paramSQL);
        if (!is_array($recordset)) {
            $this->errMsg = $this->_DB->errMsg;
            return FALSE;
        }
        return $recordset[0];
    }
    
    /**
     * Procedimiento para obtener el listado de los breaks existentes. Si
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
    function getBreaks($id_break = NULL, $estatus='all', $limit=NULL, $offset=NULL)
    {
        // Validación
        $this->errMsg = "";
        if (!is_null($id_break) && !preg_match('/^\d+$/', $id_break)) {
            $this->errMsg = _tr("Break ID is not valid");
            return FALSE;
        }
        if (!in_array($estatus, array('I', 'A'))) $estatus = NULL;

        // Construcción de petición y sus parámetros
        $sPeticionSQL = 'SELECT id, name, description, status FROM break WHERE tipo = ?';
        $paramSQL = array('B');
        if (!is_null($id_break)) { $sPeticionSQL .= ' AND id = ?'; $paramSQL[] = $id_break; }
        if (!is_null($estatus)) { $sPeticionSQL .= ' AND status = ?'; $paramSQL[] = $estatus; }
            
        if(isset($limit)){
            $sPeticionSQL .=" LIMIT ?";
            $paramSQL[]=$limit;
        }
        
        if(isset($offset)){
            $sPeticionSQL .=" OFFSET ?";
            $paramSQL[]=$offset;
        }
        $recordset = $this->_DB->fetchTable($sPeticionSQL, TRUE, $paramSQL);
        if (!is_array($recordset)) {
            $this->errMsg = $this->_DB->errMsg;
            return FALSE;
        }
        return $recordset;
    }

    /**
     * Procedimiento para crear un nuevo Break.
     *
     * @param   $sNombre            Nombre del Break
     * @param   $sDescripcion       Un detalle del break
     * 
     * @return  bool    true or false si inserto o no
     */
    function createBreak($sNombre, $sDescripcion)
    {
        $result = FALSE;
        $sNombre = trim("$sNombre");
        if ($sNombre == '') {
            $this->errMsg = _tr("Name Break can't be empty");
        } else {
            $recordset =& $this->_DB->fetchTable(
                'SELECT * FROM break WHERE name = ?', FALSE, 
                array($sNombre));
            if (is_array($recordset) && count($recordset) > 0) 
                $this->errMsg = _tr("Name Break already exists");
            else {
                // Construir y ejecutar la orden de inserción SQL
                $result = $this->_DB->genQuery(
                    'INSERT INTO break (name, description) VALUES (?, ?)',
                    array($sNombre, $sDescripcion));
                if (!$result) {
                    $this->errMsg = _tr('(internal) Failed to insert break').': '.$this->_DB->errMsg;
                }
            }
        }
        return $result;
    }   

    /**
     * Procedimiento para actualizar un break dado
     *
     * @param   $idBreak        id del Break
     * @param   $sNombre        Nombre del Break
     * @param   $sDescripcion   Detalle del Break
     * 
     * @return  bool    true or false si actualizo o no
     */
    function updateBreak($idBreak, $sNombre, $sDescripcion)
    {
        $result = FALSE;
        $sNombre = trim("$sNombre");
        if ($sNombre == '') {
            $this->errMsg = _tr("Name Break can't be empty");
        } else if (!preg_match('/^\d+$/', $idBreak)) {
            $this->errMsg = _tr("Id Break is empty");
        } else {
            // Construir y ejecutar la orden de update SQL
            $result = $this->_DB->genQuery(
                'UPDATE break SET name = ?, description = ? WHERE id = ?',
                array($sNombre, $sDescripcion, $idBreak));            
            if (!$result) {
                $this->errMsg = _tr('(internal) Failed to update break').': '.$this->_DB->errMsg;
            }
        } 
        return $result;
    }

     /**
     * Procedimiento para poner en estado activo o inactivo un break
     * Activo = 'A'   ,  Inactivo = 'I'
     *
     * @param   $idBreak        id del Break
     * @param   $activate        Activo o Inactivo ('A' o 'I')
     * 
     * @return  bool    true or false si actualizo o no el estatus
     */
    function activateBreak($idBreak,$activate)
    {
        $result = FALSE;
        if (!in_array($activate, array('A', 'I'))) {
            $this->errMsg = _tr('Invalid status');
        } else if (!preg_match('/^\d+$/', $idBreak)) {
            $this->errMsg = _tr("Id Break is empty");
        } else {
            // Construir y ejecutar la orden de update SQL
            $result = $this->_DB->genQuery(
                'UPDATE break SET status = ? WHERE id = ?',
                array($activate, $idBreak));
            if (!$result) {
                $this->errMsg = _tr('(internal) Failed to update break').': '.$this->_DB->errMsg;
            }
        }
        return $result;
    } 
}

?>
