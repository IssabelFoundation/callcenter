<?php
/* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Elastix version 1.2-2                                               |
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
  $Id: Llamada.class.php,v 1.48 2009/03/26 13:46:58 alex Exp $ */

class ListaAgentes implements IteratorAggregate
{
    private $_tuberia;
    private $_log;

    private $_agentes = array();
    private $_indices = array(
        'agentchannel'  =>  array(),    // agent->channel canal de agente (Agent/9000)
        'uniqueidlogin' =>  array(),    // agent->Uniqueid uniqueid de llamada usada para login de agente
        'uniqueidlink'  =>  array(),    // agent->UniqueidAgente uniqueid de pata enlazada con llamada atendida
        'extension'     =>  array(),    // agent->extension extensión (SIP/1064) que inició la llamada de login
    );

    function __construct($tuberia, $log)
    {
    	$this->_tuberia = $tuberia;
        $this->_log = $log;
    }

    function numLlamadas() { return count($this->_agentes); }

    function nuevoAgente($idAgente, $iNumero, $sNombre, $bEstatus, $sType)
    {
        $o = new Agente($this, $idAgente, $iNumero, $sNombre, $bEstatus, $sType,
            $this->_tuberia, $this->_log);
        $this->_agentes[] = $o;
        return $o;
    }

    function getIterator() {
        return new ArrayIterator($this->_agentes);
    }

    function agregarIndice($sIndice, $key, Agente $obj)
    {
        if (!isset($this->_indices[$sIndice]))
            die(__METHOD__.' - índice no implementado: '.$sIndice);
        $this->_indices[$sIndice][$key] = $obj;
    }

    function removerIndice($sIndice, $key)
    {
        if (!isset($this->_indices[$sIndice]))
            die(__METHOD__.' - índice no implementado: '.$sIndice);
        unset($this->_indices[$sIndice][$key]);
    }

    function buscar($sIndice, $key)
    {
        if (!isset($this->_indices[$sIndice]))
            die(__METHOD__.' - índice no implementado: '.$sIndice);
        return isset($this->_indices[$sIndice][$key])
            ? $this->_indices[$sIndice][$key] : NULL;
    }

    function remover(Agente $obj)
    {
        foreach (array_keys($this->_agentes) as $k) {
            if ($this->_agentes[$k] === $obj) {
                unset($this->_agentes[$k]);
                if (isset($this->_indices['agentchannel'][$obj->channel]))
                    unset($this->_indices['agentchannel'][$obj->channel]);
                if (isset($this->_indices['uniqueidlogin'][$obj->Uniqueid]))
                    unset($this->_indices['uniqueidlogin'][$obj->Uniqueid]);
                if (isset($this->_indices['uniqueidlink'][$obj->UniqueidAgente]))
                    unset($this->_indices['uniqueidlink'][$obj->UniqueidAgente]);
                if (isset($this->_indices['extension'][$obj->extension]))
                    unset($this->_indices['extension'][$obj->extension]);
            }
        }
    }

    function dump($log)
    {
        foreach ($this->_agentes as &$agente) $agente->dump($log);
    }
}
?>