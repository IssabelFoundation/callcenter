<?php
  /* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Issabel version {ISSBEL_VERSION}                                               |
  | http://www.issabel.org                                               |
  +----------------------------------------------------------------------+
  | Copyright (c) 2017 Issabel Foundation                                |
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
  $Id: paloSantoCampaign_Lists.class.php,v 1.1 2018-09-25 02:09:20 Nestor Islas nestor_islas@outlook.com Exp $ */
class paloSantoCampaign_Lists{
    var $_DB;
    var $errMsg;
    var $table = "campaign_lists";

    function paloSantoCampaign_Lists(&$pDB)
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

    /*HERE YOUR FUNCTIONS*/

    function getNumCampaign_Lists($filter_field, $filter_value)
    {
        $where    = "";
        $arrParam = null;
        if(isset($filter_field) & $filter_field !=""){
            $where    = "where $filter_field like ?";
            $arrParam = array("$filter_value%");
        }

        $query   = "SELECT COUNT(id) FROM {$this->table} $where";
        $result=$this->_DB->getFirstRowQuery($query, false, $arrParam);

        if($result==FALSE){
            $this->errMsg = $this->_DB->errMsg;
            return 0;
        }
        return $result[0];
    }

    function getCampaign_Lists($limit, $offset, $filter_field, $filter_value)
    {
        $where    = "";
        $arrParam = null;
        if(isset($filter_field) & $filter_field !=""){
            $where    = "where $filter_field like ?";
            $arrParam = array("$filter_value%");
        }

        $query   = <<<SQL_QUERY
        SELECT {$this->table}.id, {$this->table}.id_campaign, {$this->table}.type,
        CASE {$this->table}.type
          WHEN 0 THEN "OUT"
          WHEN 1 THEN "IN"
        END AS sType,
        {$this->table}.name, {$this->table}.upload, {$this->table}.status, {$this->table}.total_calls, {$this->table}.pending_calls, {$this->table}.date_entered,
        CASE {$this->table}.status
          WHEN 1 THEN "Activa"
          WHEN 2 THEN "Detenida"
          WHEN 3 THEN "Terminada"
        END AS sStatus
        FROM {$this->table}
        $where LIMIT $limit OFFSET $offset
SQL_QUERY;

        $result=$this->_DB->fetchTable($query, true, $arrParam);

        if($result==FALSE){
            $this->errMsg = $this->_DB->errMsg;
            return array();
        }
        return $result;
    }
	
	function getCampaign_Lists_Stats()
    {
        $query   = <<<SQL_QUERY
        SELECT {$this->table}.id, {$this->table}.id_campaign, {$this->table}.type,
        CASE {$this->table}.type
          WHEN 0 THEN "OUT"
          WHEN 1 THEN "IN"
        END AS sType,
        {$this->table}.name, {$this->table}.upload, {$this->table}.status, {$this->table}.total_calls, {$this->table}.pending_calls, {$this->table}.date_entered,
        CASE {$this->table}.status
          WHEN 1 THEN "Activa"
          WHEN 2 THEN "Detenida"
          WHEN 3 THEN "Terminada"
        END AS sStatus
        FROM {$this->table}
        WHERE {$this->table}.status = 1
SQL_QUERY;

        $result=$this->_DB->fetchTable($query, true, $arrParam);

        if($result==FALSE){
            $this->errMsg = $this->_DB->errMsg;
            return array();
        }
        return $result;
    }
	
	function getCampaign_ListsById($id)
    {
        $query = <<<SQL_QUERY
        SELECT *, CASE {$this->table}.status
          WHEN 1 THEN "Activa"
          WHEN 2 THEN "Detenida"
          WHEN 3 THEN "Terminada"
        END AS sStatus FROM {$this->table} WHERE id=?
SQL_QUERY;

        $result=$this->_DB->getFirstRowQuery($query, true, array("$id"));

        if($result==FALSE){
            $this->errMsg = $this->_DB->errMsg;
            return null;
        }
        return $result;
    }

    function getCampaign_Name($id_campaign, $type)
    {
        $result = "UNDEFINED";
        switch ($type) {
          case 0:
            $query   = "SELECT name FROM campaign WHERE id = ?";
            $result=$this->_DB->getFirstRowQuery($query, false, array($id_campaign));

            if($result==FALSE){
                $this->errMsg = $this->_DB->errMsg;
                return 0;
            }
            $result = $result[0];
          break;
        }
        
        return $result;
    }

    function changeStatusList($id_list,$activate)
    {
        $result = FALSE;
        if (!in_array($activate, array(1, 2))) {
            $this->errMsg = _tr('Invalid status');
        } else if (!preg_match('/^\d+$/', $id_list)) {
            $this->errMsg = _tr("Id list is empty");
        } else {
            $changeRecords = true;
            if($activate == 1)
            {
                // Construir y ejecutar la orden de update SQL
                $result = $this->_DB->genQuery("UPDATE calls SET `status` = NULL, dnc = 0 WHERE calls.`status` = 'Paused' AND id_list = ?",array($id_list));
                if (!$result) {
                    $changeRecords = false;
                }
            }
            else if($activate == 2)
            {
                // Construir y ejecutar la orden de update SQL
                $result = $this->_DB->genQuery("UPDATE calls SET `status` = 'Paused', dnc = 1 WHERE calls.`status` IS NULL AND id_list = ?",array($id_list));
                if (!$result) {
                    $changeRecords = false;
                }
            }
            if($changeRecords)
            {
                // Construir y ejecutar la orden de update SQL
                $result = $this->_DB->genQuery("UPDATE {$this->table} SET status = ? WHERE id = ?",array($activate, $id_list));
                if (!$result) {
                    $this->errMsg = _tr('(internal) Failed to update list').': '.$this->_DB->errMsg;
                }
            }
            else
            {
                $result = false;
                $this->errMsg = _tr('(internal) Failed to update list').': '.$this->_DB->errMsg;
            }
            $this->updateListStats($id_list);
        }
        return $result;
    }

    function updateListStats($id_list)
    {
        $sPeticionCallStat = <<<SQL_QUERY
            SELECT count("id") AS total_calls,calls.`status`
            FROM calls
            WHERE calls.id_list = ? GROUP BY calls.`status`;
SQL_QUERY;
        $sStats = $this->_DB->fetchTable($sPeticionCallStat, true, array($id_list));

        $sentCallsStat = 0;
        foreach ($sStats as $rowStat) {
            switch ($rowStat['status']) {
                case NULL:
                {
                    $sth = $this->_DB->genQuery("UPDATE campaign_lists SET campaign_lists.pending_calls = ?, campaign_lists.paused_calls = 0 WHERE campaign_lists.id = ?;", array($rowStat['total_calls'], $id_list));
                    $pauseCalls = true;
                }
                break;
                case "Abandoned":
                {
                    $sth = $this->_DB->genQuery("UPDATE campaign_lists SET campaign_lists.abandoned_calls = ? WHERE campaign_lists.id = ?;",array($rowStat['total_calls'], $id_list));
                    $sentCallsStat = $sentCallsStat + $rowStat['total_calls'];
                }
                break;
                case "Failure":
                {
                    $sth = $this->_DB->genQuery("UPDATE campaign_lists SET campaign_lists.failed_calls = ? WHERE campaign_lists.id = ?;",array($rowStat['total_calls'], $id_list));
                    $sentCallsStat = $sentCallsStat + $rowStat['total_calls'];
                }
                break;
                case "ShortCall":
                {
                    $sth = $this->_DB->genQuery("UPDATE campaign_lists SET campaign_lists.short_calls = ? WHERE campaign_lists.id = ?;",array($rowStat['total_calls'], $id_list));
                    $sentCallsStat = $sentCallsStat + $rowStat['total_calls'];
                }
                break;
                case "Success":
                {
                    $sth = $this->_DB->genQuery("UPDATE campaign_lists SET campaign_lists.answered_calls = ? WHERE campaign_lists.id = ?;",array($rowStat['total_calls'], $id_list));
                    $sentCallsStat = $sentCallsStat + $rowStat['total_calls'];
                }
                break;
                case "NoAnswer":
                {
                    $sth = $this->_DB->genQuery("UPDATE campaign_lists SET campaign_lists.no_answer_calls = ? WHERE campaign_lists.id = ?;",array($rowStat['total_calls'], $id_list));
                    $sentCallsStat = $sentCallsStat + $rowStat['total_calls'];
                }
                break;
                case "Paused":
                {
                    $sth = $this->_DB->genQuery("UPDATE campaign_lists SET campaign_lists.paused_calls = ?, campaign_lists.pending_calls = 0 WHERE campaign_lists.id = ?;",array($rowStat['total_calls'], $id_list));
                }
                break;
                default:
                {
                    $sentCallsStat = $sentCallsStat + $rowStat['total_calls'];
                }
                break;
            }
        }
        /*if(!$pauseCalls && $tupla['status'] == 1)
        {
            $sth = $this->_DB->genQuery("UPDATE campaign_lists SET campaign_lists.pending_calls = ?, campaign_lists.`status` = 3 WHERE campaign_lists.id = ?;",array(0, $id_list));
        }*/
        $sth = $this->_DB->genQuery("UPDATE campaign_lists SET campaign_lists.sent_calls = ? WHERE campaign_lists.id = ?;",array($sentCallsStat, $id_list));
    }

    function getListStatsByWeek($id_list)
    {
        $thisMonday = date("Y-m-d 00:00:00",strtotime( "Monday this week" ));
        $thisSunday = date("Y-m-d 23:59:59",strtotime( "Sunday this week" ));
        
        $sql = <<<SQL_QUERY
        SELECT   DAYOFWEEK(DATE(calls.start_time)) AS day, COUNT(id) AS total
        FROM     calls
        WHERE (calls.start_time BETWEEN ? AND ?)
        AND calls.status = "Success"
        AND calls.id_list = ?
        GROUP BY DAYOFWEEK(DATE(calls.start_time))
SQL_QUERY;
        $weekStats = $this->_DB->fetchTable($sql, true, array($thisMonday, $thisSunday, $id_list));

        $arrayDays = array('monday' => 0, 'tuesday' => 0, 'wednesday' => 0, 'thursday' => 0, 'friday' => 0, 'saturday' => 0, 'sunday' => 0);
        if (!empty($weekStats))
	    {
	        foreach ($weekStats as $keyDay => $valueDay) {
	            switch ($valueDay['day']) {
	                case 1:
	                    $arrayDays['sunday'] = $valueDay['total'];
	                break;
	                case 2:
	                    $arrayDays['monday'] = $valueDay['total'];
	                break;
	                case 3:
	                    $arrayDays['tuesday'] = $valueDay['total'];
	                break;
	                case 4:
	                    $arrayDays['wednesday'] = $valueDay['total'];
	                break;
	                case 5:
	                    $arrayDays['thursday'] = $valueDay['total'];
	                break;
	                case 5:
	                    $arrayDays['friday'] = $valueDay['total'];
	                break;
	                case 6:
	                    $arrayDays['saturday'] = $valueDay['total'];
	                break;
	            }
	        }
	    }
        return $arrayDays;
    }

    function getCampaignsOutgoing()
    {
        $campaignsOutgoing = null;
        $recordset = $this->_DB->fetchTable("SELECT id,name FROM campaign;", true);
        foreach ($recordset as $key => $value) {
            $campaignsOutgoing[$value['id']] = "{$value['id']} - {$value['name']}";
        }

        return $campaignsOutgoing;
    }

    function delete_list($idList)
    {
        $listaSQL = array(
            'DELETE FROM call_recording WHERE id_call_outgoing IN (SELECT id from calls WHERE id_list = ?)',
            'DELETE FROM call_attribute WHERE id_call IN (SELECT id from calls WHERE id_list = ?)',
            'DELETE FROM form_data_recolected WHERE id_calls IN (SELECT id from calls WHERE id_list = ?)',
            'DELETE FROM call_progress_log WHERE id_call_outgoing IN (SELECT id from calls WHERE id_list = ?)',
            'DELETE FROM calls WHERE id_list = ?',
            'DELETE FROM campaign_lists WHERE id = ?'
        );
        $this->_DB->beginTransaction();
        foreach ($listaSQL as $sql) {
        $r = $this->_DB->genQuery($sql, array($idList));
          if (!$r) {
            $this->errMsg = $this->_DB->errMsg;
              $this->_DB->rollBack();
              return FALSE;
          }
      }
        $this->_DB->commit();
        return TRUE;
    }
}
