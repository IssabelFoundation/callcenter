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

class paloSantoDontCall
{
    private $_db;
    private $_stmt;
    var $errMsg;

    function paloSantoDontCall($pDB)
    {
        $this->_stmt = array();
        if (is_object($pDB)) {
            $this->_db =& $pDB;
            $this->errMsg = $this->_db->errMsg;
        } else {
            $dsn = (string)$pDB;
            $this->_db = new paloDB($dsn);
            if (!$this->_db->connStatus) {
                $this->errMsg = $this->_db->errMsg;
            }
        }
    }

    function contarDontCall()
    {
        $tupla = $this->_db->getFirstRowQuery('SELECT COUNT(*) AS N FROM dont_call', TRUE);
        if (!is_array($tupla)) {
            $this->errMsg = $this->_db->errMsg;
            return FALSE;
        }
        return $tupla['N'];
    }

    function listarDontCall($limit = NULL, $offset = 0)
    {
        $sql = 'SELECT id, caller_id, date_income, status FROM dont_call ORDER BY caller_id';
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

    function borrarDontCall($arrData)
    {
        $this->_db->beginTransaction();
        foreach ($arrData as $id_dnc) {
            $sql = 'UPDATE calls SET dnc = 0 WHERE dnc = 1 AND phone IN '.
                '(SELECT caller_id FROM dont_call WHERE id = ?)';
            if (!$this->_db->genQuery($sql, array($id_dnc))) {
                $this->errMsg = $this->_db->errMsg;
                $this->_db->rollBack();
                return FALSE;
            }
            $sql = 'DELETE FROM dont_call WHERE id = ?';
            if (!$this->_db->genQuery($sql, array($id_dnc))) {
                $this->errMsg = $this->_db->errMsg;
                $this->_db->rollBack();
                return FALSE;
            }
        }
        $this->_db->commit();
        return TRUE;
    }

    private function _insertarNumero($dnc, &$loadReport)
    {
        if (count($this->_stmt) <= 0) {
            $this->_stmt['SELECT'] = $this->_db->conn->prepare(
                'SELECT id, status FROM dont_call WHERE caller_id = ?');
            $this->_stmt['UPDATE'] = $this->_db->conn->prepare(
                'UPDATE dont_call SET status = "A" WHERE id = ?');
            $this->_stmt['INSERT'] = $this->_db->conn->prepare(
                'INSERT INTO dont_call (caller_id, date_income, status) VALUES (?, NOW(), "A")');
        }

        $r = $this->_stmt['SELECT']->execute(array($dnc));
        if (!$r) {
            $this->errMsg = print_r($this->_stmt['SELECT']->errorInfo(), TRUE);
            return FALSE;
        }
        $tupla = $this->_stmt['SELECT']->fetch(PDO::FETCH_ASSOC);
        $this->_stmt['SELECT']->closeCursor();

        if (is_array($tupla) && count($tupla) > 0) {
            // Número ya ha sido insertado
            if ($tupla['status'] != 'A') {
                // Activar número, si estaba inactivo
                if (!$this->_stmt['UPDATE']->execute(array($tupla['id']))) {
                    $this->errMsg = print_r($this->_stmt['UPDATE']->errorInfo(), TRUE);
                    return FALSE;
                }
            }
        } else {
            // Número debe de insertarse
            if (!$this->_stmt['INSERT']->execute(array($dnc))) {
                $this->errMsg = print_r($this->_stmt['INSERT']->errorInfo(), TRUE);
                return FALSE;
            }
            $loadReport['inserted']++;
        }
        return TRUE;
    }

    function insertarNumero($dnc)
    {
        $loadReport = array('inserted' => 0);
        return $this->_insertarNumero($dnc, $loadReport);
    }

    function cargarArchivo($sArchivo, $callback = NULL)
    {
        $hArchivo = @fopen($sArchivo, 'r');
        if (!$hArchivo) {
            $this->errMsg = _tr('Failed to open file');
            return NULL;
        }

        $loadReport = array(
            'total'     =>  0,  // Total de líneas procesadas
            'inserted'  =>  0,  // Total de registros nuevos (no existentes)
            'rejected'  =>  0,  // Total de registros rechazados
        );

        while (!feof($hArchivo)) {
            $t = fgetcsv($hArchivo);
            if (count($t) > 0 && trim($t[0]) != '') {
                if (ctype_digit($t[0])) {
                    if (!$this->_insertarNumero($t[0], $loadReport)) {
                        $loadReport = NULL;
                        break;
                    }
                } else {
                    $loadReport['rejected']++;
                }
                $loadReport['total']++;

                if (!is_null($callback) && $loadReport['total'] % 1000 == 0) {
                    call_user_func($callback, $loadReport);
                }
            }
        }
        fclose($hArchivo);
        return $loadReport;
    }
}
?>