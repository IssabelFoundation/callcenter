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
  $Id: paloSantoCampaignCC.class.php,v 1.2 2008/06/06 07:15:07 cbarcos Exp $ */

class externalUrl
{
    private $_DB; // instancia de la clase paloDB
    var $errMsg;

    function externalUrl(&$pDB)
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

    function countURLs()
    {
    	$sql = 'SELECT COUNT(*) FROM campaign_external_url';
        $tupla =& $this->_DB->getFirstRowQuery($sql);
        if (!is_array($tupla)) {
            $this->errMsg = $this->_DB->errMsg;
            return FALSE;
        }
        return $tupla[0];
    }
    
    function getURLs($limit, $offset = 0)
    {
    	$sql = 'SELECT id, urltemplate, description, active, opentype FROM campaign_external_url LIMIT ? OFFSET ?';
        $recordset = $this->_DB->fetchTable($sql, TRUE, array($limit, $offset));
        if (!is_array($recordset)) {
            $this->errMsg = $this->_DB->errMsg;
        	return NULL;
        }
        return $recordset;
    }
    
    function getURL($id_url)
    {
        $sql = 'SELECT id, urltemplate, description, active, opentype FROM campaign_external_url WHERE id = ?';
        $tupla = $this->_DB->getFirstRowQuery($sql, TRUE, array($id_url));
        if (!is_array($tupla)) {
            $this->errMsg = $this->_DB->errMsg;
            return NULL;
        }
        return $tupla;
    }

    function createURL($sUrlTemplate, $sDescription, $sOpenType)
    {
    	if (!in_array($sOpenType, array('window', 'iframe', 'jsonp'))) {
            $this->errMsg = '(internal) Invalid URL open type';
    		return FALSE;
    	}        
        $sql = 'INSERT INTO campaign_external_url (urltemplate, description, opentype) VALUES (?, ?, ?)';
        $r = $this->_DB->genQuery($sql, array($sUrlTemplate, $sDescription, $sOpenType));
        if (!$r) {
        	$this->errMsg = $this->_DB->errMsg;
            return FALSE;
        }
        return TRUE;
    }


    function updateURL($id_url, $sUrlTemplate, $sDescription, $sOpenType)
    {
        if (!in_array($sOpenType, array('window', 'iframe', 'jsonp'))) {
            $this->errMsg = '(internal) Invalid URL open type';
            return FALSE;
        }        
        $sql = 'UPDATE campaign_external_url SET urltemplate = ?, description = ?, opentype = ? WHERE id = ?';
        $r = $this->_DB->genQuery($sql, array($sUrlTemplate, $sDescription, $sOpenType, $id_url));
        if (!$r) {
            $this->errMsg = $this->_DB->errMsg;
            return FALSE;
        }
        return TRUE;
    }
    
    function enableURL($id_url, $bEnable)
    {
        $sql = 'UPDATE campaign_external_url SET active = ? WHERE id = ?';
        $r = $this->_DB->genQuery($sql, array($bEnable ? 1 : 0, $id_url));
        if (!$r) {
            $this->errMsg = $this->_DB->errMsg;
            return FALSE;
        }
        return TRUE;
    }
}
?>