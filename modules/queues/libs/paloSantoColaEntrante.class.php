<?php
/* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Elastix version 1.2-2                                                |
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
  $Id: default.conf.php,v 1.1 2008-09-03 01:09:56 Alex Villacís Lasso Exp $
*/

class paloSantoColaEntrante
{
    private $_DB; // instancia de la clase paloDB
    var $errMsg;

    function paloSantoColaEntrante(&$pDB)
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
    
    private function _construirCondicionWhere($idCola, $status)
    {
        $listaWhere = array();
        $paramSQL = array();
        
        // Selección de cola específica
        if (!is_null($idCola)) {
            if (!ctype_digit("$idCola")) {
                $this->errMsg = '(internal) Invalid queue ID';
                return false;
            }
            $listaWhere[] = 'id = ?';
            $paramSQL[] = $idCola;
        }
        
        // Selección de estado de la cola
        if (!is_null($status) && $status!='all') {
            if (!in_array($status, array('A', 'I'))) {
                $this->errMsg = '(internal) Invalid status, must be A,I';
                return false;
            }
            $listaWhere[] = 'estatus = ?';
            $paramSQL[] = $status;
        }
        
        return array($listaWhere, $paramSQL);
    }
    
    function getNumColas($idCola = NULL, $status = NULL)
    {
        $l = $this->_construirCondicionWhere($idCola, $status);
        if (!is_array($l)) return FALSE;
        list($listaWhere, $paramSQL) = $l;        
        
        // Construcción de SQL
        $sql = 'SELECT count(id) FROM queue_call_entry'.
            ((count($listaWhere) > 0) ? ' WHERE '.implode(' AND ',$listaWhere) : '').
            ' ORDER BY queue';
            
        $recordset = $this->_DB->getFirstRowQuery($sql, FALSE, $paramSQL);
        if (!is_array($recordset)) {
            $this->errMsg = '(internal) Unable to read queues - '.$this->_DB->errMsg;
            return false;
        }
        return $recordset[0];
    }
    
    /**
     * Procedimiento para leer toda la información de las colas entrantes 
     * monitoreadas. 
     * 
     * @param   int     $idCola ID de cola a leer, o NULL para leer todas las colas
     * @param   string  $status NULL para cualquier estado, (A)ctivas, (I)nactivas
     * 
     * @return  mixed   Recordset de las colas, o NULL
     */
    function leerColas($idCola = NULL, $status = NULL, $limit = NULL, $offset = NULL)
    {
        $l = $this->_construirCondicionWhere($idCola, $status);
        if (!is_array($l)) return FALSE;
        list($listaWhere, $paramSQL) = $l;        
        
        // Construcción de SQL
        $sql = 'SELECT id, queue, estatus, script FROM queue_call_entry'.
            ((count($listaWhere) > 0) ? ' WHERE '.implode($listaWhere) : '').
            ' ORDER BY queue';
            
        if (!is_null($limit)) {
            $sql .=" LIMIT ?";
            $paramSQL[] = $limit;
        }
        
        if (!is_null($offset)) {
            $sql .=" OFFSET ?";
            $paramSQL[] = $offset;
        }
        
        $recordset = $this->_DB->fetchTable($sql, TRUE, $paramSQL);
        if (!is_array($recordset)) {
            $this->errMsg = '(internal) Unable to read queues - '.$this->_DB->errMsg;
        	return NULL;
        }
        return $recordset;
    }
    
    /**
     * Procedimiento para registrar o actualizar una cola como monitoreada para
     * las campañas entrantes, con un script de campaña entrante indicado.
     * Si la cola entrante indicada no se halla registrada, se ingresa. De lo
     * contrario, se actualiza su script y se marca como activa.
     * 
     * @param   int     $queue  Número de la cola a monitorear
     * @param   string  $script Texto a visualizar en campaña entrante cuando
     *                          entra una llamada a la cola y se asigna a un
     *                          agente.
     * 
     * @return  bool    VERDADERO en caso de éxito, FALSO en error.
     */
    function iniciarMonitoreoCola($queue, $script)
    {
        if (!ctype_digit("$queue")) {
            $this->errMsg = '(internal) Invalid queue number, must be numeric';
        	return FALSE;
        }
        
        // Verificar que la cola no se esté usando en una campaña saliente
        $tupla = $this->_DB->getFirstRowQuery(
            'SELECT COUNT(*) FROM campaign WHERE estatus = "A" AND queue = ?', FALSE,
            array($queue));
        if (!is_array($tupla)) {
            $this->errMsg = '(internal) Unable to check outgoing queues - '.$this->_DB->errMsg;
            return FALSE;
        }
        if ($tupla[0] > 0) {
            $this->errMsg = _tr('Selected queue already in use by outgoing campaign');
        	return FALSE;
        }
        
        // Verificar si la cola ya se ha ingresado
    	$tupla = $this->_DB->getFirstRowQuery(
            'SELECT id FROM queue_call_entry WHERE queue = ?', TRUE, 
            array($queue));
        if (!is_array($tupla)) {
            $this->errMsg = '(internal) Unable to read queues - '.$this->_DB->errMsg;
        	return FALSE;
        }
        $idCola = isset($tupla['id']) ? $tupla['id'] : NULL;
        
        // Construcción de SQL
        $paramSQL = array($queue, $script, 'A');
        if (!is_null($idCola)) $paramSQL[] = $idCola; 
        $sql = is_null($idCola)
            ? 'INSERT INTO queue_call_entry (queue, script, estatus) VALUES (?, ?, ?)'
            : 'UPDATE queue_call_entry SET queue = ?, script = ?, estatus = ? WHERE id = ?';
        $r = $this->_DB->genQuery($sql, $paramSQL);
        if (!$r) {
        	$this->errMsg = '(internal) Unable to monitor queue - '.$this->_DB->errMsg;
        }
        return $r;
    }
    
    /**
     * Procedimiento para cambiar el estado de monitoreo de una cola entrante
     * 
     * @param   int     $id     ID de la cola monitoreada (NO el número)
     * @param   string  $status Nuevo estado deseado de la cola (A/I)
     * 
     * @return bool VERDADERO en éxito, FALSO en error
     */
    function cambiarMonitoreoCola($id, $status)
    {
        if (!ctype_digit("$id")) {
            $this->errMsg = '(internal) Invalid queue ID, must be numeric';
            return FALSE;
        }        
        if (!in_array($status, array('A', 'I'))) {
            $this->errMsg = '(internal) Invalid status, must be A,I';
            return NULL;
        }

        $r = $this->_DB->genQuery(
            'UPDATE queue_call_entry SET estatus = ? WHERE id = ?', 
            array($status, $id));
        if (!$r) {
            $this->errMsg = '(internal) Unable to monitor queue - '.$this->_DB->errMsg;
        }
        return $r;
    }
    
    /**
     * Procedimiento para filtrar de la lista de colas indicada, todas las colas
     * que se usan para campañas entrantes. Se devuelve la lista de colas que
     * pueden usarse para nuevas campañas entrantes.
     * 
     * @param   array   $listaColas Lista de las colas que se han leído de 
     *                              paloQueue::getQueue()
     * 
     * @return  mixed   NULL en error, o lista filtrada (posiblemente vacía)
     */
    function filtrarColasUsadas($listaColas)
    {
    	$sql = <<<COLAS_USADAS
(SELECT queue FROM queue_call_entry WHERE estatus = 'A') 
UNION DISTINCT 
(SELECT queue FROM campaign WHERE estatus = 'A')
COLAS_USADAS;
        $recordset = $this->_DB->fetchTable($sql);
        if (!is_array($recordset)) {
            $this->errMsg = '(internal) Unable to read used queues - '.$this->_DB->errMsg;
        	return NULL;
        }
        $listaColasUsadas = array();
        foreach ($recordset as $tupla) {
        	$listaColasUsadas[] = $tupla[0];
        }
        
        $nuevaLista = array();
        foreach ($listaColas as $tuplaCola) {
        	if (!in_array($tuplaCola[0], $listaColasUsadas))
                $nuevaLista[] = $tuplaCola;
        }
        return $nuevaLista;
    }
}
?>