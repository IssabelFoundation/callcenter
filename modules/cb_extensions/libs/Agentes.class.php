<?php

/* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Elastix version 0.5                                                  |
  | http://www.elastix.com                                               |
  +----------------------------------------------------------------------+
  | Copyright (c) 2007 Palosanto Solutions S. A.                         |
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
  $Id: Agentes.class.php,v  $ */
if (file_exists("/var/lib/asterisk/agi-bin/phpagi-asmanager.php")) {
    include_once "/var/lib/asterisk/agi-bin/phpagi-asmanager.php";
} elseif (file_exists('libs/phpagi-asmanager.php')) {
	include_once 'libs/phpagi-asmanager.php';
} else {
	die('Unable to find phpagi-asmanager.php');
}
include_once("libs/paloSantoDB.class.php");

class Agentes
{
    var $arrAgents;
    private $_DB; // instancia de la clase paloDB
    var $errMsg;

    function Agentes(&$pDB)
    {
        // Se recibe como parámetro una referencia a una conexión paloDB
        if (is_object($pDB)) {
            $this->_DB =& $pDB;
            $this->errMsg = $this->_DB->errMsg;
        } else {
            $dsn = (string)$pDB;
            $this->_DB = new paloDB($dsn);

            if ($this->_DB->connStatus) {
                $this->errMsg = $this->_DB->errMsg;
                // debo llenar alguna variable de error
            } else {
                // debo llenar alguna variable de error
            }
        }

        $this->arrAgents = array();
    }

    /**
     * Procedimiento para consultar los agentes estáticos que existen en la
     * base de datos de CallCenter. Opcionalmente, se puede consultar un solo
     * agente específico.
     * 
     * @param   int     $id     Número de agente asignado
     * 
     * @return  NULL en caso de error
     *          Si $id es NULL, devuelve una matriz de las columnas conocidas:
     *              id number name password estatus eccp_password
     *          Si $id no es NULL y agente existe, se devuelve una sola tupla
     *          con la estructura de las columnas indicada anteriormente.
     *          Si $id no es NULL y agente no existe, se devuelve arreglo vacío.  
     */
    function getAgents($id=null)
    {
        // CONSULTA DE LA BASE DE DATOS LA INFORMACIÓN DE LOS AGENTES
        $paramQuery = array();
        $where = array("estatus = 'A'", 'type <> "Agent"');
        if (!is_null($id)) {
        	$paramQuery[] = $id;
            $where[] = 'number = ?';
        }
        $sQuery =
            'SELECT id, type, number, name, password, estatus, eccp_password '.
            'FROM agent WHERE '.implode(' AND ', $where).' ORDER BY number';

        $arr_result =& $this->_DB->fetchTable($sQuery, true, $paramQuery);

        if (is_array($arr_result)) {
            if (is_null($id) || count($arr_result) <= 0) {
                return $arr_result;
            } else {
                return $arr_result[0];
            }
        } else {
            $this->errMsg = 'Unable to read agent information - '.$this->_DB->errMsg;
            return NULL;
        }
    }


    /**
     * Procedimiento para agregar un nuevo agente estático a la base de datos
     * de CallCenter y al archivo agents.conf de Asterisk.
     * 
     * @param   array   $agent  Información del agente con las posiciones:
     *                  0   =>  Número del agente a crear
     *                  1   =>  Contraseña telefónica del agente
     *                  2   =>  Nombre descriptivo del agente
     *                  3   =>  Contraseña para login de ECCP
     * 
     * @return  bool    VERDADERO si se inserta correctamente agente, FALSO si no.
     */
    function addAgent($agent)
    {
	
        if (!is_array($agent) || count($agent) < 3) {
            $this->errMsg = 'Invalid agent data';
            return FALSE;
        }

        $typeExtension = explode("/",$agent[0]);

        $tupla = $this->_DB->getFirstRowQuery(
            'SELECT COUNT(*) FROM agent WHERE estatus = "A" AND number = ?',
            FALSE, array($typeExtension[1]));
        if ($tupla[0] > 0) {
            $this->errMsg = _tr('Agent already exists');
            return FALSE;
        }
        
        /* Se debe de autogenerar una contraseña ECCP si no se especifica. 
         * La contraseña será legible por la nueva consola de agente */
        if (!isset($agent[3]) || $agent[3] == '') $agent[3] = sha1(time().rand());

        // GRABAR EN BASE DE DATOS
        $sPeticionSQL = 'INSERT INTO agent (type, number, password, name, eccp_password) VALUES (?, ?, ?, ?, ?)';
        $paramSQL = array($typeExtension[0], $typeExtension[1], $agent[1], $agent[2], $agent[3]);
        
        $this->_DB->genQuery("SET AUTOCOMMIT = 0");
        $result = $this->_DB->genQuery($sPeticionSQL, $paramSQL);

        if (!$result) {
            $this->errMsg = $this->_DB->errMsg;
            $this->_DB->genQuery("ROLLBACK");
            $this->_DB->genQuery("SET AUTOCOMMIT = 1");
            return false;
        }

	$this->_DB->genQuery("COMMIT");
        $this->_DB->genQuery("SET AUTOCOMMIT = 1");
        return true; 
    }

    /**
     * Procedimiento para modificar un agente estático exitente en la base de
     * datos de CallCenter y en el archivo agents.conf de Asterisk.
     * 
     * @param   array   $agent  Información del agente con las posiciones:
     *                  0   =>  Número del agente a crear
     *                  1   =>  Contraseña telefónica del agente
     *                  2   =>  Nombre descriptivo del agente
     *                  3   =>  Contraseña para login de ECCP
     * 
     * @return  bool    VERDADERO si se inserta correctamente agente, FALSO si no.
     */
    function editAgent($agent)
    {
        if (!is_array($agent) || count($agent) < 3) {
            $this->errMsg = 'Invalid agent data';
            return FALSE;
        }

        $typeExtension = explode("/",$agent[0]);

        // Verificar que el agente referenciado existe
        $tupla = $this->_DB->getFirstRowQuery(
            'SELECT COUNT(*) FROM agent WHERE estatus = "A" AND number = ?',
            FALSE, array($typeExtension[1]));
        if ($tupla[0] <= 0) {
            $this->errMsg = _tr('Agent not found');
            return FALSE;
        }        

        // Asumir ninguna contraseña de ECCP (agente no será usable por ECCP)
        if (!isset($agent[3]) || $agent[3] == '') $agent[3] = NULL;

        // EDITAR EN BASE DE DATOS
        $sPeticionSQL = 'UPDATE agent SET password = ?, name = ?, type = ?';
        $paramSQL = array($agent[1], $agent[2], $typeExtension[0]);
        if (!is_null($agent[3])) {
        	$sPeticionSQL .= ', eccp_password = ?';
            $paramSQL[] = $agent[3];
        }
        $sPeticionSQL .= ' WHERE number = ?';
        $paramSQL[] = $typeExtension[1];

        $this->_DB->genQuery("SET AUTOCOMMIT = 0");
        $result = $this->_DB->genQuery($sPeticionSQL, $paramSQL);
        if (!$result) {
            $this->errMsg = $this->_DB->errMsg;
            $this->_DB->genQuery("ROLLBACK");
            $this->_DB->genQuery("SET AUTOCOMMIT = 1");
            return false;
        }

        /* Se debe de autogenerar una contraseña ECCP si no se especifica. 
         * La contraseña será legible por la nueva consola de agente */
        if (is_null($agent[3])) {
            $agent[3] = sha1(time().rand());
            $sPeticionSQL = 'UPDATE agent SET eccp_password = ? WHERE number = ? AND eccp_password IS NULL';
            $paramSQL = array($agent[3], $typeExtension[1]);
            $result = $this->_DB->genQuery($sPeticionSQL, $paramSQL);
            if (!$result) {
                $this->errMsg = $this->_DB->errMsg;
                $this->_DB->genQuery("ROLLBACK");
                $this->_DB->genQuery("SET AUTOCOMMIT = 1");
                return false;
            }
        }

        // Leer el archivo y buscar la línea del agente a modificar
        $bExito = TRUE;
        if ($bExito) {
            $this->_DB->genQuery("COMMIT");
            $this->_DB->genQuery("SET AUTOCOMMIT = 1");
            return TRUE;
        } else {
            $this->_DB->genQuery("ROLLBACK");
            $this->_DB->genQuery("SET AUTOCOMMIT = 1");
            return FALSE;
        }
    }

    function deleteAgent($id_agent)
    {
        if (!ereg('^[[:digit:]]+$', $id_agent)) {
            $this->errMsg = '(internal) Invalid agent information';
            return FALSE;
        }

        // BORRAR EN BASE DE DATOS

        $sPeticionSQL = "UPDATE agent SET estatus='I' WHERE number=$id_agent";

        $result = $this->_DB->genQuery($sPeticionSQL);
        if (!$result) {
            $this->errMsg = $this->_DB->errMsg;
            return false;
        }
        return true;
    }

    private function _get_AGI_AsteriskManager()
    {
        $ip_asterisk = '127.0.0.1';
        $user_asterisk = 'admin';
        $pass_asterisk = function_exists('obtenerClaveAMIAdmin') ? obtenerClaveAMIAdmin() : 'elastix456';
        $astman = new AGI_AsteriskManager();
        if (!$astman->connect($ip_asterisk, $user_asterisk , $pass_asterisk)) {
            $this->errMsg = "Error when connecting to Asterisk Manager";
            return NULL;
        } else {
            return $astman;
        }
    }

    function getOnlineAgents()
    {
        $astman = $this->_get_AGI_AsteriskManager();
        if (is_null($astman)) {
            return NULL;
        } else {
            $strAgentsOnline = $astman->Command('queue show');
            $astman->disconnect();
            $data = $strAgentsOnline['data'];
            $lineas = explode("\n", $data);
            $listaAgentes = array();

            $bMembers = FALSE;
            foreach ($lineas as $sLinea) {
                if (strpos($sLinea, 'No Members') !== FALSE || strpos($sLinea, 'Members:') !== FALSE)
                    $bMembers = TRUE;
                elseif (strpos($sLinea, 'No Callers') !== FALSE || strpos($sLinea, 'Callers:') !== FALSE)
                    $bMembers = FALSE;
                elseif ($bMembers) {
                	$regs = NULL;
                    if (preg_match('/^\s*(\S+)/', $sLinea, $regs)) {
                    	if (!in_array($regs[1], $listaAgentes)) $listaAgentes[] = $regs[1];
                    }
                }
            }
            return $listaAgentes;
        }
    }

    function desconectarAgentes($arrAgentes)
    {
        $this->errMsg = NULL;

        if (!(is_array($arrAgentes) && count($arrAgentes) > 0)) {
            $this->errMsg = "Lista de agentes no válida";
            return FALSE;
        }

        $astman = $this->_get_AGI_AsteriskManager();
        if (is_null($astman)) {
            return FALSE;
        }

        // Construir lista de colas a las que pertenece cada agente
        $queuesByAgent = array();
        $strAgentsOnline = $astman->Command('queue show');
        $data = $strAgentsOnline['data'];
        $lineas = explode("\n", $data);
        $sCurQueue = NULL;
        $bMembers = FALSE;
        foreach ($lineas as $sLinea) {
            $regs = NULL;
            if (preg_match('/^(\w+) has \d+ calls/', $sLinea, $regs)) {
            	$sCurQueue = $regs[1];
            } elseif (strpos($sLinea, 'No Members') !== FALSE || strpos($sLinea, 'Members:') !== FALSE)
                $bMembers = TRUE;
            elseif (strpos($sLinea, 'No Callers') !== FALSE || strpos($sLinea, 'Callers:') !== FALSE)
                $bMembers = FALSE;
            elseif ($bMembers) {
                if (preg_match('/^\s*(\S+)/', $sLinea, $regs)) {
                    if (!isset($queuesByAgent[$regs[1]]))
                        $queuesByAgent[$regs[1]] = array();
                    $queuesByAgent[$regs[1]][] = $sCurQueue;
                }
            }
        }

        // Desconectar cada agente de todas sus colas
        foreach ($arrAgentes as $sAgente) {
            if (isset($queuesByAgent[$sAgente])) {
            	foreach ($queuesByAgent[$sAgente] as $sQueue) {
            		$res = $astman->QueueRemove($sQueue, $sAgente);
                    if ($res['Response']=='Error') {
                        $this->errMsg = "Error logoff ".$res['Message'];
                        $astman->disconnect();
                        return false;
                    }
            	}
            }
        }
        
        $astman->disconnect();
        return true;
    }

    /**
      Retorna un array de extensiones de la PBX no utilizadas como callback extensions.
    */    
    public function getUnusedExtensions()
    {
        // Consultar todas las extensiones disponibles
        $sPwdFreepbx = obtenerClaveConocidaMySQL('asteriskuser');
        if (is_null($sPwdFreepbx)) {
        	$this->errMsg = 'No se puede leer clave DB para FreePBX';
            return NULL;
        }
        
        // BUG del framework: para asteriskuser se devuelve un array
        if (is_array($sPwdFreepbx)) $sPwdFreepbx = $sPwdFreepbx['valor'];
        
        $dsn = "mysql://asteriskuser:{$sPwdFreepbx}@localhost/asterisk";
        $dbFreepbx = new paloDB($dsn);
        if ($dbFreepbx->connStatus) {
            $this->errMsg = 'No se puede conectar a DB para FreePBX';
        	return NULL;
        }
        $extensiones = array();
        $recordset = $dbFreepbx->fetchTable(
            'SELECT data FROM sip WHERE keyword = "Dial" UNION '.
            'SELECT data FROM iax WHERE keyword = "Dial"',
            TRUE);
        if (!is_array($recordset)) {
            $this->errMsg = 'No se pueden consultar extensiones en FreePBX - '.$dbFreepbx->errMsg;
        	return NULL;
        }
        foreach ($recordset as $tupla) $extensiones[$tupla['data']] = $tupla['data'];
        $dbFreepbx = NULL;
        
        // Quitar de la lista las extensiones ya registradas
        $listaAgentes = $this->getAgents();
        if (!is_array($listaAgentes)) return NULL;
        foreach ($listaAgentes as $agente) {
        	$k = $agente['type'].'/'.$agente['number'];
            if (isset($extensiones[$k])) unset($extensiones[$k]);
        }
        return $extensiones;
    }

    /* FUNCIONES DEL AGI*/
    /**
    * Agent Logoff
    *
    * @link http://www.voip-info.org/wiki/index.php?page=Asterisk+Manager+API+AgentLogoff
    * @param Agent: Agent ID of the agent to login 
    */
    private function Agentlogoff($obj_phpAgi, $agent)
    {
        return $obj_phpAgi->send_request('Agentlogoff', array('Agent'=>$agent));
    }
}
?>
