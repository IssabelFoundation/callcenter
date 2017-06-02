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
  $Id: Predictor.class.php,v 1.5 2008/12/04 19:11:21 alex Exp $ */

define('AST_DEVICE_NOTINQUEUE', -1);
define('AST_DEVICE_UNKNOWN',    0);
define('AST_DEVICE_NOT_INUSE',  1);
define('AST_DEVICE_INUSE',      2);
define('AST_DEVICE_BUSY',       3);
define('AST_DEVICE_INVALID',    4);
define('AST_DEVICE_UNAVAILABLE',5);
define('AST_DEVICE_RINGING',    6);
define('AST_DEVICE_RINGINUSE',  7);
define('AST_DEVICE_ONHOLD',     8);

class Predictor
{
    private $_astConn;  // Conexión al Asterisk
    private $_agentesAppQueue = array();    // Agentes ocupados por llamadas de cola
    private $_infoColas = array();          // Información de colas examinadas
    private $_tmp_actionid = NULL;
    private $_enum_complete = TRUE;
    var $timestamp_examen = 0;

    function __construct($astman)
    {
        $this->_astConn = $astman;
    }

    function examinarColas($colas)
    {
        // Manejadores de eventos de interés
        $this->_tmp_actionid = posix_getpid().'-'.time();
        $evlist = array('CoreShowChannel', 'CoreShowChannelsComplete',
            'QueueParams', 'QueueMember', 'QueueEntry', 'QueueStatusComplete');
        foreach ($evlist as $k)
            $this->_astConn->remove_event_handler($k);
        foreach ($evlist as $k)
            if (!$this->_astConn->add_event_handler($k, array($this, "msg_$k"))) {
                // Quitar manejadores de eventos si alguno no se puede agregar
                foreach ($evlist as $k)
                    $this->_astConn->remove_event_handler($k);
                return FALSE;
            }

        // Anular resultados previos
        $this->_agentesAppQueue = array();
        $this->_infoColas = array();

        try {
            $this->_astConn->CoreShowChannels($this->_tmp_actionid);
            $this->_esperarEnumeracion();

            foreach ($colas as $queue) {
                $this->_astConn->QueueStatus($queue, $this->_tmp_actionid);
                $this->_esperarEnumeracion();
            }
        } catch (Exception $e) {
            // Quitar manejadores de eventos antes de relanzar excepción
            foreach ($evlist as $k)
                $this->_astConn->remove_event_handler($k);

            throw $e;
        }

        // Quitar manejadores de eventos
        foreach ($evlist as $k)
            $this->_astConn->remove_event_handler($k);
        $this->_tmp_actionid = NULL;
        $this->timestamp_examen = microtime(TRUE);

        return TRUE;
    }

    private function _esperarEnumeracion()
    {
        $this->_enum_complete = FALSE;
        do {
            if ($this->_astConn->multiplexSrv->procesarPaquetes())
                $this->_astConn->multiplexSrv->procesarActividad(0);
            else $this->_astConn->multiplexSrv->procesarActividad(1);
        } while (!$this->_enum_complete);
    }

    function msg_CoreShowChannel($sEvent, $params, $sServer, $iPort)
    {
        if (is_null($this->_tmp_actionid)) return;
        if ($params['ActionID'] != $this->_tmp_actionid) return;

/*
Event: CoreShowChannel
ActionID: gatito
Channel: SIP/1064-00000003
UniqueID: 1441991140.4
Context: from-internal
Extension: 8001
Priority: 1
ChannelState: 6
ChannelStateDesc: Up
Application: AppQueue
ApplicationData: (Outgoing Line)
CallerIDnum: 1064
CallerIDname: Alex
ConnectedLineNum: 1071
ConnectedLineName: A Cuenta SIP
Duration: 00:09:46
AccountCode:
BridgedChannel: SIP/1071-00000002
BridgedUniqueID: 1441991139.3

 */
        if ($params['ChannelState'] != 6) return;
        if ($params['Application'] != 'AppQueue') return;
        if (!isset($params['BridgedChannel']) || trim($params['BridgedChannel']) == '') return;
        $regs = NULL; $interface = NULL;
        $regex = '#^([[:alnum:]-]+/.+?)(-[\dabcdef]+((,|;)(1|2))?)?$#';
        if (is_null($interface) && preg_match($regex, $params['Channel'], $regs)) {
            $interface = $regs[1];
            $timefields = explode(':', $params['Duration']);
            $this->_agentesAppQueue[$interface] = (int)$timefields[0] * 3600 + (int)$timefields[1] * 60 + (int)$timefields[2];
        }
    }

    function msg_CoreShowChannelsComplete($sEvent, $params, $sServer, $iPort)
    {
        if (is_null($this->_tmp_actionid)) return;
        if ($params['ActionID'] != $this->_tmp_actionid) return;

        $this->_enum_complete = TRUE;
    }

    function msg_QueueParams($sEvent, $params, $sServer, $iPort)
    {
        if (is_null($this->_tmp_actionid)) return;
        if ($params['ActionID'] != $this->_tmp_actionid) return;

        if (!isset($this->_infoColas[$params['Queue']])) {
            $this->_infoColas[$params['Queue']] = array(
                'members'   =>  array(),
                'callers'   =>  0,
            );
        }
    }

    function msg_QueueMember($sEvent, $params, $sServer, $iPort)
    {
        if (is_null($this->_tmp_actionid)) return;
        if ($params['ActionID'] != $this->_tmp_actionid) return;

        $this->_infoColas[$params['Queue']]['members'][$params['Location']] = array(
            //'Name'      =>  $params['Name'],
            //'Location'  =>  $params['Location'],
            //'StateInterface'  =>  $params['StateInterface'],
            'Paused'    =>  ($params['Paused'] != 0),
            'Status'    =>  (int)$params['Status'],
        );
    }

    function msg_QueueEntry($sEvent, $params, $sServer, $iPort)
    {
        if (is_null($this->_tmp_actionid)) return;
        if ($params['ActionID'] != $this->_tmp_actionid) return;

        if (!isset($this->_infoColas[$params['Queue']]['callers']))
            $this->_infoColas[$params['Queue']]['callers'] = 0;
        $this->_infoColas[$params['Queue']]['callers']++;
    }

    function msg_QueueStatusComplete($sEvent, $params, $sServer, $iPort)
    {
        if (is_null($this->_tmp_actionid)) return;
        if ($params['ActionID'] != $this->_tmp_actionid) return;

        $this->_enum_complete = TRUE;
    }

    function infoPrediccionCola($cola)
    {
        if (!isset($this->_infoColas[$cola])) return NULL;

        $iNumLlamadasColocar = array(
            'AGENTES_LIBRES'        =>  0,
            'AGENTES_POR_DESOCUPAR' =>  array(),
            'CLIENTES_ESPERA'       =>  0,
        );

        $iNumLlamadasColocar['CLIENTES_ESPERA'] = $this->_infoColas[$cola]['callers'];
        foreach ($this->_infoColas[$cola]['members'] as $interface => $miembro) {

            // Se ignora miembro en pausa
            if ($miembro['Paused']) continue;

            // Miembro definitivamente libre
            if (in_array($miembro['Status'], array(AST_DEVICE_NOT_INUSE, AST_DEVICE_RINGING)))
                $iNumLlamadasColocar['AGENTES_LIBRES']++;

            // Miembro ocupado, se verifica si se desocupará
            if (in_array($miembro['Status'], array(AST_DEVICE_INUSE, AST_DEVICE_BUSY, AST_DEVICE_RINGINUSE)) &&
                isset($this->_agentesAppQueue[$interface])) {
                $iNumLlamadasColocar['AGENTES_POR_DESOCUPAR'][] = $this->_agentesAppQueue[$interface];
            }
        }
        return $iNumLlamadasColocar;
    }

    function predecirNumeroLlamadas($infoCola, $prob_atencion = NULL, $avg_duracion = NULL, $avg_contestar = NULL)
    {
        $n = 0;
        // Miembro ocupado, se verifica si se desocupará
        if (!is_null($prob_atencion)) foreach ($infoCola['AGENTES_POR_DESOCUPAR'] as $t) {
            $iTiempoTotal = $avg_contestar + $t;

            // Probabilidad de que 1 llamada haya terminado al cabo de $iTiempoTotal s.
            $iProbabilidad = $this->_probabilidadErlangAcumulada(
                $iTiempoTotal,
                1,
                1 / $avg_duracion);
            if ($iProbabilidad >= $prob_atencion) $n++;
        }
        $infoCola['AGENTES_POR_DESOCUPAR'] = $n;
        return $infoCola;
    }

    private function _probabilidadErlangAcumulada($x, $k, $lambda)
    {
        $iSum = 0;
        $iTerm = 1;
        for ($n = 0; $n < $k; $n++) {
            if ($n > 0) $iTerm *= $lambda * $x / $n;
            $iSum += $iTerm;
        }

        return 1 - exp(-$lambda * $x) * $iSum;
    }
}
?>