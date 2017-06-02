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
  $Id: DialerProcess.class.php,v 1.48 2009/03/26 13:46:58 alex Exp $ */

class ECCPProcess extends TuberiaProcess
{
    private $DEBUG = FALSE; // VERDADERO si se activa la depuración

    private $_log;      // Log abierto por framework de demonio

    /* Si se pone a VERDADERO, el programa intenta finalizar y no deben
     * aceptarse conexiones nuevas. Todas las conexiones existentes serán
     * desconectadas. */
    private $_finalizandoPrograma = FALSE;

    public function inicioPostDemonio($infoConfig, &$oMainLog)
    {
    	$this->_log = $oMainLog;
        $this->_multiplex = new ECCPServer('tcp://0.0.0.0:20005', $this->_log, $this->_tuberia);
        $this->_tuberia->registrarMultiplexHijo($this->_multiplex);
        $this->_tuberia->setLog($this->_log);

        // Registro de manejadores de eventos
        foreach (array('actualizarConfig', 'emitirEventos',) as $k)
            $this->_tuberia->registrarManejador('SQLWorkerProcess', $k, array($this, "msg_$k"));
        foreach (array('recordingMute', 'recordingUnmute') as $k)
            $this->_tuberia->registrarManejador('AMIEventProcess', $k, array($this, "msg_$k"));
        foreach (array('eccpresponse') as $k)
            $this->_tuberia->registrarManejador('*', $k, array($this, "msg_$k"));

        // Registro de manejadores de eventos desde HubProcess
        $this->_tuberia->registrarManejador('HubProcess', 'finalizando', array($this, "msg_finalizando"));

        // Se ha tenido éxito si se están escuchando conexiones
        return $this->_multiplex->escuchaActiva();
    }

    public function procedimientoDemonio()
    {
        // Rutear todos los mensajes pendientes entre tareas y agentes
        if ($this->_multiplex->procesarPaquetes())
            $this->_multiplex->procesarActividad(0);
        else $this->_multiplex->procesarActividad(1);

    	return TRUE;
    }

    public function limpiezaDemonio($signum)
    {
        // Mandar a cerrar todas las conexiones activas
        $this->_multiplex->finalizarServidor();
    }

    /**************************************************************************/

    public function msg_emitirEventos($sFuente, $sDestino, $sNombreMensaje,
        $iTimestamp, $datos)
    {
        if ($this->DEBUG) {
            $this->_log->output('DEBUG: '.__METHOD__.' - '.print_r($datos, 1));
        }
        list($eventos) = $datos;

        $this->_lanzarEventos($eventos);
    }

    public function msg_actualizarConfig($sFuente, $sDestino,
        $sNombreMensaje, $iTimestamp, $datos)
    {
        if ($this->DEBUG) {
            $this->_log->output('DEBUG: '.__METHOD__.' recibido: '.print_r($datos, 1));
        }
        call_user_func_array(array($this, '_actualizarConfig'), $datos);
    }

    public function msg_finalizando($sFuente, $sDestino, $sNombreMensaje, $iTimestamp, $datos)
    {
        $this->_log->output('INFO: recibido mensaje de finalización, se desconectan conexiones...');
        $this->_finalizandoPrograma = TRUE;
        $this->_multiplex->finalizarConexionesECCP();
        $this->_tuberia->msg_HubProcess_finalizacionTerminada();
    }

    public function msg_recordingMute($sFuente, $sDestino, $sNombreMensaje, $iTimestamp, $datos)
    {
        if ($this->DEBUG) {
            $this->_log->output('DEBUG: '.__METHOD__.' - '.print_r($datos, 1));
        }
        list($sAgente, $sTipoLlamada, $idCampaign, $idLlamada) = $datos;

        $this->_multiplex->notificarEvento_RecordingMute($sAgente, $sTipoLlamada, $idCampaign, $idLlamada);
    }

    public function msg_recordingUnmute($sFuente, $sDestino, $sNombreMensaje, $iTimestamp, $datos)
    {
        if ($this->DEBUG) {
            $this->_log->output('DEBUG: '.__METHOD__.' - '.print_r($datos, 1));
        }
        list($sAgente, $sTipoLlamada, $idCampaign, $idLlamada) = $datos;

        $this->_multiplex->notificarEvento_RecordingUnmute($sAgente, $sTipoLlamada, $idCampaign, $idLlamada);
    }

    public function msg_eccpresponse($sFuente, $sDestino, $sNombreMensaje, $iTimestamp, $datos)
    {
        if ($this->DEBUG) {
            $this->_log->output('DEBUG: '.__METHOD__.' - '.print_r($datos, 1));
        }

        list($sKey, $s, $nuevos_valores, $eventos) = $datos;

        if (!is_null($eventos)) $this->_lanzarEventos($eventos);

        $oConn = $this->_multiplex->getConn($sKey);
        if (is_null($oConn)) {
            $this->_log->output("ERR: ".__METHOD__." ECCP connection $sKey no longer present, cannot deliver ECCP response.");
            return;
        }
        $oConn->do_eccpresponse($s, $nuevos_valores);
    }

    private function _lanzarEventos(&$eventos)
    {
        foreach ($eventos as $ev) {
            if (!is_null($ev)) call_user_func_array(
                array(
                    $this->_multiplex,
                    'notificarEvento_'.$ev[0]),
                $ev[1]);
        }
    }

    private function _actualizarConfig($k, $v)
    {
        switch ($k) {
        case 'dialer_debug':
            $this->_log->output('INFO: actualizando DEBUG...');
            $this->DEBUG = $v;
            break;
        default:
            $this->_log->output('WARN: '.__METHOD__.': se ignora clave de config no implementada: '.$k);
            break;
        }
    }
}
?>