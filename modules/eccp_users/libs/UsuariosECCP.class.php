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