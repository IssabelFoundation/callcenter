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
  $Id: HubServer.class.php,v 1.48 2009/03/26 13:46:58 alex Exp $ */

/**
 * Esta clase implementa un hub central de mensajes. Se deben de pedir nuevas
 * instancias de TuberiaMensaje antes de realizar fork() para cada proceso. Una
 * vez hecho los forks, la única tarea en el proceso padre de esta clase es la
 * de recibir los mensajes en cada tubería, y rutearlos al destino indicado en
 * el mensaje.
 */
class HubServer extends MultiplexServer
{
    private $_tuberias = array();   // Lista de tuberías, una por cada proceso
    private $_iNumFinalizados = 0;
    private $_inspectores = array();    // Lista de inspectores de mensajes

    function __construct(&$oLog)
    {
    	parent::__construct(NULL, $oLog);
    }

    // Pedir la creación de una nueva tubería
    function crearTuberia($sFuente)
    {
    	$t = new TuberiaMensaje($sFuente);
        $this->_tuberias[$sFuente] = $t;
        return $t;
    }

    // Remover una tubería de un proceso que ha terminado
    function quitarTuberia($sFuente)
    {
    	if (isset($this->_tuberias[$sFuente])) {
    		$this->_tuberias[$sFuente]->finalizarConexion();
            unset($this->_tuberias[$sFuente]);
    	}
    }

    // Registrar el multiplex con las tuberías luego del fork()
    function registrarMultiplexPadre()
    {
    	foreach ($this->_tuberias as $t) {
            $t->registrarMultiplexPadre($this);
            $t->registrarManejador('*', '*', array($this, 'rutearMensaje'));
        }
    }

    // Registrar un inspector de mensajes ruteados
    function registrarInspectorMsg($msgH)
    {
        if (!($msgH instanceof iRoutedMessageHook)) {
            $this->_log->output("FATAL: ".__METHOD__." (internal) not an instance of iRoutedMessageHook");
            die(__METHOD__." (internal) not an instance of iRoutedMessageHook\n");
        }
        $this->_inspectores[] = $msgH;
    }

    // Rutear el mensaje recibido de una fuente a un destino específico
    function rutearMensaje($sFuente, $sDestino, $sNombreMensaje, $iTimestamp, $datos)
    {
        // Proveer oportunidad para que el inspector tome acción según el mensaje
        foreach ($this->_inspectores as $msgH) {
            $msgH->inspeccionarMensaje($sFuente, $sDestino, $sNombreMensaje, $datos);
        }

    	if (!isset($this->_tuberias[$sDestino])) {
    		$this->_oLog->output('ERR: '.__METHOD__." - no se encuentra destino para $sNombreMensaje($sFuente-->$sDestino)");
            return;
    	}
        $this->_tuberias[$sDestino]->enviarMensajeDesdeFuente($sFuente, $sDestino, $sNombreMensaje, $datos);
    }

    // Mandar mensaje de término del programa
    function enviarFinalizacion()
    {
        foreach ($this->_tuberias as $k => $t) {
            $t->registrarManejador('*', 'finalizacionTerminada', array($this, 'msg_finalizacionTerminada'));
            $t->enviarMensajeDesdeFuente('HubProcess', $k, 'finalizando', NULL);
        }
    }

    function msg_finalizacionTerminada($sFuente, $sDestino, $sNombreMensaje, $iTimestamp, $datos)
    {
    	$this->_oLog->output("INFO: $sFuente indica que ya terminó de prepararse para finalización.");
        $this->_iNumFinalizados++;
    }

    function numFinalizados() { return $this->_iNumFinalizados; }
}
?>