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
    private $AGENT_FILE;
    var $arrAgents;
    private $_DB; // instancia de la clase paloDB
    var $errMsg;

    function Agentes(&$pDB, $file = "/etc/asterisk/agents.conf")
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

        $this->arrAgents = array();
        $this->AGENT_FILE=$file;
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
        $paramQuery = array(); $where = array("type = 'Agent'", "estatus = 'A'"); $sWhere = '';
        if (!is_null($id)) {
        	$paramQuery[] = $id;
            $where[] = 'number = ?';
        }
        if (count($where) > 0) $sWhere = 'WHERE '.join(' AND ', $where);
        $sQuery = 
            "SELECT id, number, name, password, estatus, eccp_password ".
            "FROM agent $sWhere ORDER BY number";
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


    function existAgent($agent)
    {
        $this->_read_agents();
        foreach ($this->arrAgents as $agente){
            if ($agente[0] == $agent)
                return $agente;
        }
        return false;
    }

    function getAgentsFile()
    {
        $this->_read_agents();
        return array_keys($this->arrAgents);
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

        $tupla = $this->_DB->getFirstRowQuery(
            'SELECT COUNT(*) FROM agent WHERE estatus = "A" AND number = ?',
            FALSE, array($agent[0]));
        if ($tupla[0] > 0) {
            $this->errMsg = _tr('Agent already exists');
            return FALSE;
        }
        
        /* Se debe de autogenerar una contraseña ECCP si no se especifica. 
         * La contraseña será legible por la nueva consola de agente */
        if (!isset($agent[3]) || $agent[3] == '') $agent[3] = sha1(time().rand());

        // GRABAR EN BASE DE DATOS
        $sPeticionSQL = 'INSERT INTO agent (number, password, name, eccp_password) VALUES (?, ?, ?, ?)';
        $paramSQL = array($agent[0], $agent[1], $agent[2], $agent[3]);
        
        $this->_DB->genQuery("SET AUTOCOMMIT = 0");
        $result = $this->_DB->genQuery($sPeticionSQL, $paramSQL);

        if (!$result) {
            $this->errMsg = $this->_DB->errMsg;
            $this->_DB->genQuery("ROLLBACK");
            $this->_DB->genQuery("SET AUTOCOMMIT = 1");
            return false;
        }

        $resp = $this->addAgentFile($agent);
        if ($resp) {
            $this->_DB->genQuery("COMMIT");
        } else {
            $this->_DB->genQuery("ROLLBACK");
        }
        $this->_DB->genQuery("SET AUTOCOMMIT = 1");
        return $resp; 
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

        // Verificar que el agente referenciado existe
        $tupla = $this->_DB->getFirstRowQuery(
            'SELECT COUNT(*) FROM agent WHERE estatus = "A" AND number = ?',
            FALSE, array($agent[0]));
        if ($tupla[0] <= 0) {
            $this->errMsg = _tr('Agent not found');
            return FALSE;
        }        

        // Asumir ninguna contraseña de ECCP (agente no será usable por ECCP)
        if (!isset($agent[3]) || $agent[3] == '') $agent[3] = NULL;

        // EDITAR EN BASE DE DATOS
        $sPeticionSQL = 'UPDATE agent SET password = ?, name = ?';
        $paramSQL = array($agent[1], $agent[2]);
        if (!is_null($agent[3])) {
        	$sPeticionSQL .= ', eccp_password = ?';
            $paramSQL[] = $agent[3];
        }
        $sPeticionSQL .= ' WHERE number = ?';
        $paramSQL[] = $agent[0];

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
            $paramSQL = array($agent[3], $agent[0]);
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
        $contenido = file($this->AGENT_FILE);
        if (!is_array($contenido)) {
            $bExito = FALSE;
            $this->errMsg = '(internal) Unable to read agent file';
        } else {
            $sLineaAgente = "agent => {$agent[0]},{$agent[1]},{$agent[2]}\n";
            $bModificado = FALSE;
            for ($i = 0; $i < count($contenido); $i++) {
                $regs = NULL;
                if (preg_match('/^[[:space:]]*agent[[:space:]]*=>[[:space:]]*([[:digit:]]+),/', $contenido[$i], $regs) &&
                    $regs[1] == $agent[0]) {
                    // Se ha encontrado la línea del agente modificado
                    $contenido[$i] = $sLineaAgente;
                    $bModificado = TRUE;
                }
            }
            if (!$bModificado) $contenido[] = $sLineaAgente;

            $hArchivo = fopen($this->AGENT_FILE, 'w');
            if (!$hArchivo) {
                $bExito = FALSE;
                $this->errMsg = '(internal) Unable to write agent file';
            } else {
                foreach ($contenido as $sLinea) fwrite($hArchivo, $sLinea);
                fclose($hArchivo);
            }
        }
        
        if ($bExito) {
            $this->_DB->genQuery("COMMIT");
            $this->_DB->genQuery("SET AUTOCOMMIT = 1");

            return $this->_reloadAsterisk();
        } else {
            $this->_DB->genQuery("ROLLBACK");
            $this->_DB->genQuery("SET AUTOCOMMIT = 1");
            return FALSE;
        }
    }

    function deleteAgent($id_agent)
    {
        if (!preg_match('/^[[:digit:]]+$/', $id_agent)) {
            $this->errMsg = '(internal) Invalid agent information';
            return FALSE;
        }

        // BORRAR EN BASE DE DATOS

        $sPeticionSQL = "UPDATE agent SET estatus='I' WHERE number=$id_agent";

        $this->_DB->genQuery("SET AUTOCOMMIT = 0");
        $result = $this->_DB->genQuery($sPeticionSQL);
        if (!$result) {
            $this->errMsg = $this->_DB->errMsg;
            $this->_DB->genQuery("ROLLBACK");
            $this->_DB->genQuery("SET AUTOCOMMIT = 1");
            return false;
        }

        $resp = $this->deleteAgentFile($id_agent);
        if ($resp) {
            $this->_DB->genQuery("COMMIT");
        } else {
            $this->_DB->genQuery("ROLLBACK");
        }
        $this->_DB->genQuery("SET AUTOCOMMIT = 1");

        return $resp;
    }

    /**
     * Procedimiento para agregar un agente estático al archivo agents.conf y
     * reiniciar Asterisk para que lea los cambios de agentes.
     * 
     * @param   array   $agent  Información del agente. Se recogen los valores:
     *                  0   =>  Número del agente
     *                  1   =>  Contraseña telefónica del agente
     *                  2   =>  Nombre descriptivo del agente
     *                  Otras claves o posiciones se ignoran.
     * 
     * @return  VERDADERO si se puede escribir el archivo y reiniciar Asterisk,
     *          FALSO si ocurre algún error.
     */
    function addAgentFile($agent)
    {
        if (!is_array($agent) || count($agent) < 3) {
            $this->errMsg = '(internal) Invalid agent information';
            return FALSE;
        }

        // GRABAR EN EL ARCHIVO
        $archivo=$this->AGENT_FILE;
        $tamanio_linea = 4096;
        $open = fopen ($archivo,"a+");

        $nuevo_agente="agent => {$agent[0]},{$agent[1]},{$agent[2]}\n";
        // vas leyendo linea a linea , hasta llegar al final del archivo en
        //donde  fgets() retorna false

        while ($sLinea = fgets($open,$tamanio_linea))  // [0]
        {
            $regs = NULL;
            if (preg_match('/^[[:space:]]*agent[[:space:]]*=>[[:space:]]*([[:digit:]]+),/', $sLinea, $regs) &&
                $regs[1] == $agent[0]) {
                $this->errMsg = "Agent number already exists.";
                fclose($open);
                return false;
            }
        }

        $escribir = fwrite ( $open, $nuevo_agente);
        fclose($open);
        return $this->_reloadAsterisk();
    }

    function deleteAgentFile($id_agent)
    {
        if (!preg_match('/^[[:digit:]]+$/', $id_agent)) {
            $this->errMsg = '(internal) Invalid agent ID';
            return FALSE;
        }

        // Leer el archivo y buscar la línea del agente a eliminar
        $bExito = TRUE;
        $contenido = file($this->AGENT_FILE);
        if (!is_array($contenido)) {
            $bExito = FALSE;
            $this->errMsg = '(internal) Unable to read agent file';
        } else {
            $bModificado = FALSE;
            $contenidoNuevo = array();

            // Filtrar las líneas, y setear bandera si se eliminó alguna
            foreach ($contenido as $sLinea) {
                $regs = NULL;
                if (preg_match('/^[[:space:]]*agent[[:space:]]*=>[[:space:]]*([[:digit:]]+),/', $sLinea, $regs) &&
                    $regs[1] == $id_agent) {
                    // Se ha encontrado la línea del agente eliminado
                    $bModificado = TRUE;
                } else {
                    $contenidoNuevo[] = $sLinea;
                }
            }

            if ($bModificado) {
                $hArchivo = fopen($this->AGENT_FILE, 'w');
                if (!$hArchivo) {
                    $bExito = FALSE;
                    $this->errMsg = '(internal) Unable to write agent file';
                } else {
                    foreach ($contenidoNuevo as $sLinea) fwrite($hArchivo, $sLinea);
                    fclose($hArchivo);
                }
            }
        }

        return $this->_reloadAsterisk();
    }

    private function _read_agents()
    {
        $contenido = file($this->AGENT_FILE);
        if (!is_array($contenido)) {
            $bExito = FALSE;
            $this->errMsg = '(internal) Unable to read agent file';
        } else {
            $this->arrAgents = array();
            foreach ($contenido as $sLinea) {
                if (preg_match('/^[[:space:]]*agent[[:space:]]*=>[[:space:]]*([[:digit:]]+),([[:digit:]]+),(.*)/', trim($sLinea), $regs)) {
                    $this->arrAgents[$regs[1]] = array($regs[1], $regs[2], $regs[3]);
                }
            }
        }
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

    private function _reloadAsterisk()
    {
        $astman = $this->_get_AGI_AsteriskManager();
        if (is_null($astman)) {
            return FALSE;
        } else {
            // TODO: verify whether reload actually succeeded
            $strReload = $astman->Command("module reload chan_agent.so");
            $astman->disconnect();
            return TRUE;
        }
    }

    function getOnlineAgents()
    {
        $astman = $this->_get_AGI_AsteriskManager();
        if (is_null($astman)) {
            return NULL;
        } else {
            $strAgentsOnline = $astman->Command("agent show online");
            $astman->disconnect();
            $data = $strAgentsOnline['data'];
            $lineas = explode("\n", $data);
            $listaAgentes = array();

            foreach ($lineas as $sLinea) {
                // El primer número de la línea es el ID del agente a recuperar
                $regs = NULL;
                if (strpos($sLinea, 'agents online') === FALSE &&
                    preg_match('/^([[:digit:]]+)[[:space:]]*/', $sLinea, $regs)) {
                    $listaAgentes[] = $regs[1];
                }
            }
            return $listaAgentes;
        }
    }

    function isAgentOnline($agentNum)
    {
        $astman = $this->_get_AGI_AsteriskManager();
        if (is_null($astman)) {
            return FALSE;
        } else {
            $strAgentsOnline = $astman->Command("agent show online");
            $astman->disconnect();
            $data = $strAgentsOnline['data'];
            $res = explode($agentNum,$data);
            if(is_array($res) && count($res)==2) {
                return true;
            }
            return false;
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

        for ($i =0 ; $i < count($arrAgentes) ; $i++) {
            $res = $this->Agentlogoff($astman, $arrAgentes[$i]);
            if ($res['Response']=='Error') {
                $this->errMsg = "Error logoff ".$res['Message'];
                $astman->disconnect();
                return false;
            }
        }
        $astman->disconnect();
        return true;
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
