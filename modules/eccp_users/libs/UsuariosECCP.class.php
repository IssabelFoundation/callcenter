<?php
/*
  vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
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
  | Autores: Alex Villacís Lasso <a_villacis@palosanto.com>              |
  +----------------------------------------------------------------------+
  $Id: index.php,v 1.1 2007/01/09 23:49:36 alex Exp $
*/

require_once("libs/paloSantoDB.class.php");


class UsuariosECCP
{
    private $_DB; // instancia de la clase paloDB
    var $errMsg;

    function UsuariosECCP(&$pDB)
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
    
    function contarUsuarios()
    {
    	$cuentaUsuarios = $this->_DB->getFirstRowQuery('SELECT COUNT(*) FROM eccp_authorized_clients');
        if (!is_array($cuentaUsuarios)) {
            $this->errMsg = '(internal) Unable to count users - '.$this->_DB->errMsg;
            return NULL;
        }
        return $cuentaUsuarios[0];
    }
    
    function listarUsuarios($idUsuario, $offset = 0, $limit = NULL)
    {
    	$sql = 'SELECT id, username FROM eccp_authorized_clients';
        $params = array();
        if (!is_null($idUsuario)) {
        	$params[] = $idUsuario;
            $sql .= ' WHERE id = ?';            
        }
        $sql .= ' ORDER BY username';
        if (!is_null($limit)) {
        	$sql .= ' LIMIT ?,?';
            $params[] = $offset;
            $params[] = $limit;
        }
        $listaUsuarios = $this->_DB->fetchTable($sql, TRUE, $params);
        if (!is_array($listaUsuarios)) {
            $this->errMsg = '(internal) Unable to read users - '.$this->_DB->errMsg;
        	return NULL;
        }
        return $listaUsuarios;
    }
    
    function borrarUsuario($idUsuario)
    {
    	$sql = 'DELETE FROM eccp_authorized_clients WHERE id = ?';
        $r = $this->_DB->genQuery($sql, array($idUsuario));
        if (!$r) {
        	$this->errMsg = '(internal) Unable to delete users - '.$this->_DB->errMsg;
        }
        return $r;
    }
    
    function crearUsuario($sNombre, $sPasswd)
    {
    	$sql = 'INSERT INTO eccp_authorized_clients (username, md5_password) VALUES (?, MD5(?))';
        $r = $this->_DB->genQuery($sql, array($sNombre, $sPasswd));
        if (!$r) {
            $this->errMsg = '(internal) Unable to create user - '.$this->_DB->errMsg;
            return NULL;
        } else {
        	$tuplaID = $this->_DB->getFirstRowQuery('SELECT LAST_INSERT_ID()');
            return $tuplaID[0];
        }
    }
    
    function editarUsuario($id, $sNombre, $sPasswd = NULL)
    {
    	$sql = 'UPDATE eccp_authorized_clients SET username = ?';
        $params = array($sNombre);
        if (!is_null($sPasswd)) {
        	$sql .= ', md5_password = MD5(?)';
            $params[] = $sPasswd;
        }
        $sql .= ' WHERE id = ?';
        $params[] = $id;
        $r = $this->_DB->genQuery($sql, $params);
        if (!$r) {
            $this->errMsg = '(internal) Unable to update user - '.$this->_DB->errMsg;
        }
        return $r;
    }
}
?>
