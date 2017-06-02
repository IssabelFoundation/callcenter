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

class paloSantoDataFormList
{
	private $_db;
    var $errMsg;
    
    function __construct($pDB)
    {
        // Se recibe como parámetro una referencia a una conexión paloDB
        if (is_object($pDB)) {
            $this->_db =& $pDB;
            $this->errMsg = $this->_db->errMsg;
        } else {
            $dsn = (string)$pDB;
            $this->_db = new paloDB($dsn);

            if (!$this->_db->connStatus) {
                $this->errMsg = $this->_db->errMsg;
                // debo llenar alguna variable de error
            } else {
                // debo llenar alguna variable de error
            }
        }
    }
    
    private function _condSQL($status)
    {
        $param = array();
        $where = array();
        switch ($status) {
        case 'all':
            break;
        case 'A':
        case 'I':
            $param[] = $status;
            $where[] = 'estatus = ?';
            break;
        }
        $cond = (count($where) > 0) ? ' WHERE '.implode(' AND ', $where) : '';
        
        return array($cond, $param);
    }
    
    function contarFormularios($status)
    {
        list($cond, $param) = $this->_condSQL($status);
        $sql = 'SELECT COUNT(*) AS N FROM form'.$cond;
        $tupla = $this->_db->getFirstRowQuery($sql, TRUE, $param);
        if (!is_array($tupla)) {
            $this->errMsg = $this->_db->errMsg;
        	return FALSE;
        }
        return $tupla['N'];
    }
    
    function listarFormularios($status, $limit = NULL, $offset = 0)
    {
    	list($cond, $param) = $this->_condSQL($status);
        $sql = 'SELECT id, nombre, descripcion, estatus FROM form'.$cond;
        if (!is_null($limit)) {
        	$sql .= ' LIMIT ? OFFSET ?';
            $param[] = $limit;
            $param[] = $offset;
        }
        $recordset = $this->_db->fetchTable($sql, TRUE, $param);
        if (!is_array($recordset)) {
            $this->errMsg = $this->_db->errMsg;
            return NULL;
        }
        return $recordset;
    }
    
    function generarFormulario($id_form)
    {
    	$sql = 'SELECT nombre, descripcion, estatus FROM form WHERE id = ?';
        $tupla = $this->_db->getFirstRowQuery($sql, TRUE, array($id_form));
        if (!is_array($tupla)) {
            $this->errMsg = $this->_db->errMsg;
            return FALSE;
        }
        if (count($tupla) <= 0) return $tupla;
        
        $sql = 'SELECT id, etiqueta, value, tipo, orden FROM form_field WHERE id_form = ? ORDER by orden';
        $recordset = $this->_db->fetchTable($sql, TRUE, array($id_form));
        if (!is_array($recordset)) {
            $this->errMsg = $this->_db->errMsg;
            return NULL;
        }
        $form = array();
        foreach ($recordset as $tuplacampo) {
        	$field = array(
                'LABEL'                     =>  $tuplacampo['etiqueta'],
                'REQUIRED'                  => 'no',
                'INPUT_TYPE'                =>  $tuplacampo['tipo'],
                'INPUT_EXTRA_PARAM'         => '',
                'VALIDATION_TYPE'           =>  'text',
                'VALIDATION_EXTRA_PARAM'    =>  '',
            );
            if ($tuplacampo['tipo'] == 'LIST') {
            	$field['INPUT_TYPE'] = 'SELECT';
                $field['INPUT_EXTRA_PARAM'] = array();
                foreach (explode(',', $tuplacampo['value']) as $v) {
                	$field['INPUT_EXTRA_PARAM'][$v] = $v;
                }
            }
            $form[$tuplacampo['etiqueta']] = $field;
        }
        $tupla['campos'] = $form;
        return $tupla;
    }
}

?>