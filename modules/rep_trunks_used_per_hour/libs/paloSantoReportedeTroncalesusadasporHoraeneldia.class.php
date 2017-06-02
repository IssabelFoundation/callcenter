<?php
  /* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Elastix version 1.5.2-3.1                                               |
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
  $Id: paloSantoReportedeTroncalesusadasporHoraeneldia.class.php,v 1.1.1.1 2009/07/27 09:10:19 dlopez Exp $ */
class paloSantoReportedeTroncalesusadasporHoraeneldia {
    var $_DB;
    var $errMsg;

    function paloSantoReportedeTroncalesusadasporHoraeneldia(&$pDB)
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

    function listarTraficoLlamadasHora($sFechaInicio, $sFechaFinal, $sTrunk = NULL)
    {
    	$regexp = '/^\d{4}-\d{2}-\d{2}$/';
        if (!preg_match($regexp, $sFechaInicio)) {
            $this->errMsg = '(internal) Invalid start date';
        	return NULL;
        }
        if (!preg_match($regexp, $sFechaFinal)) {
            $this->errMsg = '(internal) Invalid end date';
            return NULL;
        }
        if ($sFechaFinal < $sFechaInicio) {
        	$t = $sFechaInicio;
            $sFechaInicio = $sFechaFinal;
            $sFechaFinal = $t;
        }
        $paramSQL = array($sFechaInicio.' 00:00:00', $sFechaFinal.' 23:59:59');
        $whereTrunk = '';
        if (!empty($sTrunk)) {
        	$whereTrunk = ' AND trunk = ? ';
            $paramSQL[] = $sTrunk;
        }
        $sqlTrafico = <<<SQL_TRAFICO
SELECT
    DATE_FORMAT(datetime_entry_queue,'%H') AS H,
    COUNT(datetime_entry_queue) AS N,
    SUM(IF(datetime_init IS NULL, 0, 1)) AS Nhandled,
    status
FROM call_entry
WHERE datetime_entry_queue BETWEEN ? and ? $whereTrunk
GROUP BY H, status
ORDER BY H, status
SQL_TRAFICO;
        $result = $this->_DB->fetchTable($sqlTrafico, TRUE, $paramSQL);
        if (!is_array($result)) {
            $this->errMsg = $this->_DB->errMsg;
            return NULL;
        }
        
        $hist = array();
        foreach ($result as $tupla) {
        	$iHora = (int)$tupla['H'];
            if (!isset($hist[$iHora]))
                $hist[$iHora] = array(
                    'entered'       =>  0,
                    'terminada'     =>  0,
                    'abandonada'    =>  0,
                    'en-cola'       =>  0,
                    'fin-monitoreo' =>  0,
                );
            $hist[$iHora]['entered'] += $tupla['N'];
            switch ($tupla['status']) {
            case 'terminada':
            case 'activa':
            case 'hold':
                $hist[$iHora]['terminada'] += $tupla['N'];
                break;
            case 'fin-monitoreo':
                /* Llamadas dejadas de monitorear se marcan como terminadas si
                 * fueron asignadas a un agente. Caso contrario se contabilizan
                 * por separado. */
                $hist[$iHora]['terminada'] += $tupla['Nhandled'];
                $hist[$iHora]['fin-monitoreo'] += $tupla['N'] - $tupla['Nhandled'];
                break;
            default:
                $hist[$iHora][$tupla['status']] += $tupla['N'];
                break;
            }
        }
        return $hist;
    }
}
?>