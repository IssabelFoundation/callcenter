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
  $Id: ECCPProxyConn.class.php,v 1.48 2009/03/26 13:46:58 alex Exp $ */

class ECCPProxyConn extends MultiplexConn
{
    private $_log;
    private $_tuberia;
    private $_listaReq = array();    // Lista de requerimientos pendientes
    private $_parser = NULL;        // Parser expat para separar los paquetes
    private $_iPosFinal = NULL;     // Posición de parser para el paquete parseado
    private $_sTipoDoc = NULL;      // Tipo de paquete. Sólo se acepta 'request'
    private $_bufferXML = '';       // Datos pendientes que no forman un paquete completo
    private $_iNestLevel = 0;       // Al llegar a cero, se tiene fin de paquete

    // Estado de la conexión
    private $_sUsuarioECCP  = NULL; // Nombre de usuario para cliente logoneado, o NULL si no logoneado
    private $_sAppCookie = NULL;    // Cadena a usar como cookie de la aplicación
    private $_sAgenteFiltrado = NULL;   // Si != NULL, eventos sólo se despachan si el agente coincide con este valor
    private $_bProgresoLlamada = FALSE; // Si VERDADERO, cliente está interesado en eventos de progreso de llamada

    private $_bFinalizando = FALSE;

    function __construct($oMainLog, $tuberia)
    {
        $this->_log = $oMainLog;
        $this->_tuberia = $tuberia;
        $this->_resetParser();
    }

    // Datos a mandar a escribir apenas se inicia la conexión
    function procesarInicial() {}

    // Separar flujo de datos en paquetes, devuelve número de bytes de paquetes aceptados
    function parsearPaquetes($sDatos)
    {
        $this->parsearPaquetesXML($sDatos);
        return strlen($sDatos);
    }

    // Procesar cierre de la conexión
    function procesarCierre()
    {
        if (!is_null($this->_parser)) {
            xml_parser_free($this->_parser);
            $this->_parser = NULL;
        }
    }

    // Preguntar si hay paquetes pendientes de procesar
    function hayPaquetes() {
        return (count($this->_listaReq) > 0);
    }

    // Procesar un solo paquete de la cola de paquetes
    function procesarPaquete()
    {
        $request = array_shift($this->_listaReq);
        if (isset($request['request'])) {

            $connvars = array(
                'appcookie'     =>  $this->_sAppCookie,
                'usuarioeccp'   =>  $this->_sUsuarioECCP,
            );

            /* TODO: En una fase futura, en caso necesario, se requiere un
             * worker dedicado exclusivamente a los accesos de DB necesarios
             * para los eventos recibidos de AMIEventProcess. No se puede usar
             * el mismo pool de workers que para las peticiones ECCP porque no
             * se garantizaría el orden de atención de eventos. */
            $this->_tuberia->msg_ECCPWorkerProcess_eccprequest($this->sKey, $request, $connvars);
        } else {
            // Marcador de error, se cierra la conexión
            $r = $this->_generarRespuestaFallo(400, 'Bad request');
            $s = $r->asXML();
            $this->multiplexSrv->encolarDatosEscribir($this->sKey, $s);
            $this->multiplexSrv->marcarCerrado($this->sKey);
        }
    }

    function do_eccpresponse(&$s, &$nuevos_valores)
    {
        if (!is_null($nuevos_valores)) {
            foreach ($nuevos_valores as $k => $v) {
                if ($k == 'usuarioeccp')
                    $this->_sUsuarioECCP = $v;
                if ($k == 'appcookie')
                    $this->_sAppCookie = $v;
                if ($k == 'agentefiltrado')
                    $this->_sAgenteFiltrado = $v;
                if ($k == 'progresollamada')
                    $this->_bProgresoLlamada = $v;
                if ($k == 'finalizando')
                    $this->_bFinalizando = $v;
            }
        }

        $this->multiplexSrv->encolarDatosEscribir($this->sKey, $s);
        if ($this->_bFinalizando) $this->multiplexSrv->marcarCerrado($this->sKey);
    }

    // Función que construye una respuesta de petición incorrecta
    private function _generarRespuestaFallo($iCodigo, $sMensaje, $idPeticion = NULL)
    {
        $x = new SimpleXMLElement("<response />");
        if (!is_null($idPeticion))
            $x->addAttribute("id", $idPeticion);
        $this->_agregarRespuestaFallo($x, $iCodigo, $sMensaje);
        return $x;
    }

    // Agregar etiqueta failure a la respuesta indicada
    private function _agregarRespuestaFallo($x, $iCodigo, $sMensaje)
    {
        $failureTag = $x->addChild("failure");
        $failureTag->addChild("code", $iCodigo);
        $failureTag->addChild("message", str_replace('&', '&amp;', $sMensaje));
    }

    // Procedimiento a llamar cuando se finaliza la conexión en cierre normal
    // del programa.
    function finalizarConexion()
    {
        // Mandar a cerrar la conexión en sí
        $this->multiplexSrv->marcarCerrado($this->sKey);

        if (!is_null($this->_parser)) {
            xml_parser_free($this->_parser);
            $this->_parser = NULL;
        }
    }

    // Implementación de parser expat: inicio

    // Parsear y separar tantos paquetes XML como sean posibles
    private function parsearPaquetesXML($data)
    {
        $this->_bufferXML .= $data;
        $r = xml_parse($this->_parser, $data);
        while (!is_null($this->_iPosFinal)) {
            if ($this->_sTipoDoc == 'request') {
                $this->_listaReq[] = array(
                    'request'   =>  substr($this->_bufferXML, 0, $this->_iPosFinal),
                    'received'  =>  microtime(TRUE),
                );
            } else {
                $this->_listaReq[] = array(
                    'errorcode'     =>  -1,
                    'errorstring'   =>  "Unrecognized packet type: {$this->_sTipoDoc}",
                    'errorline'     =>  xml_get_current_line_number($this->_parser),
                    'errorpos'      =>  xml_get_current_column_number($this->_parser),
                );
            }
            $this->_bufferXML = ltrim(substr($this->_bufferXML, $this->_iPosFinal));
            $this->_iPosFinal = NULL;
            $this->_resetParser();
            if ($this->_bufferXML != '')
                $r = xml_parse($this->_parser, $this->_bufferXML);
        }
        if (!$r) {
            $this->_listaReq[] = array(
                'errorcode'     =>  xml_get_error_code($this->_parser),
                'errorstring'   =>  xml_error_string(xml_get_error_code($this->_parser)),
                'errorline'     =>  xml_get_current_line_number($this->_parser),
                'errorpos'      =>  xml_get_current_column_number($this->_parser),
            );
        }
        return $r;
    }

    // Resetear el parseador, para iniciarlo, o luego de parsear un paquete
    private function _resetParser()
    {
        if (!is_null($this->_parser)) xml_parser_free($this->_parser);
        $this->_parser = xml_parser_create('UTF-8');
        xml_set_element_handler ($this->_parser,
            array($this, 'xmlStartHandler'),
            array($this, 'xmlEndHandler'));
        xml_parser_set_option($this->_parser, XML_OPTION_CASE_FOLDING, 0);
    }

    function xmlStartHandler($parser, $name, $attribs)
    {
        $this->_iNestLevel++;
    }

    function xmlEndHandler($parser, $name)
    {
        $this->_iNestLevel--;
        if ($this->_iNestLevel == 0) {
            $this->_iPosFinal = xml_get_current_byte_index($parser);
            $this->_sTipoDoc = $name;
        }
    }

    // Implementación de parser expat: final

    /***************************** EVENTOS *****************************/

    function notificarEvento_AgentLogin($sAgente, $bExitoLogin)
    {
        if (is_null($this->_sUsuarioECCP)) return;
        if (!is_null($this->_sAgenteFiltrado) && $this->_sAgenteFiltrado != $sAgente) return;

        $xml_response = new SimpleXMLElement('<event />');
        $xml_agentLoggedIn = $bExitoLogin
            ? $xml_response->addChild('agentloggedin')
            : $xml_response->addChild('agentfailedlogin');
        $xml_agentLoggedIn->addChild('agent', str_replace('&', '&amp;', $sAgente));

        $s = $xml_response->asXML();
        $this->multiplexSrv->encolarDatosEscribir($this->sKey, $s);
    }

    function notificarEvento_AgentLogoff($sAgente)
    {
        if (is_null($this->_sUsuarioECCP)) return;
        if (!is_null($this->_sAgenteFiltrado) && $this->_sAgenteFiltrado != $sAgente) return;

        $xml_response = new SimpleXMLElement('<event />');
        $xml_agentLoggedIn = $xml_response->addChild('agentloggedout');
        $xml_agentLoggedIn->addChild('agent', str_replace('&', '&amp;', $sAgente));

        $s = $xml_response->asXML();
        $this->multiplexSrv->encolarDatosEscribir($this->sKey, $s);
    }

    function notificarEvento_AgentLinked($sAgente, $sRemChannel, $infoLlamada)
    {
        if (is_null($this->_sUsuarioECCP)) return;
        if (!is_null($this->_sAgenteFiltrado) && $this->_sAgenteFiltrado != $sAgente) return;

        $xml_response = new SimpleXMLElement('<event />');
        $xml_agentLinked = $xml_response->addChild('agentlinked');
        $infoLlamada['agent_number'] = $sAgente;
        $infoLlamada['remote_channel'] = $sRemChannel;
        ECCPConn::construirRespuestaCallInfo($infoLlamada, $xml_agentLinked);

        $s = $xml_response->asXML();
        $this->multiplexSrv->encolarDatosEscribir($this->sKey, $s);
    }

    function notificarEvento_AgentUnlinked($sAgente, $infoLlamada)
    {
        if (is_null($this->_sUsuarioECCP)) return;
        if (!is_null($this->_sAgenteFiltrado) && $this->_sAgenteFiltrado != $sAgente) return;

        $xml_response = new SimpleXMLElement('<event />');
        $xml_agentLinked = $xml_response->addChild('agentunlinked');
        $infoLlamada['agent_number'] = $sAgente;
        foreach ($infoLlamada as $sKey => $valor) {
            if (!is_null($valor)) $xml_agentLinked->addChild($sKey, str_replace('&', '&amp;', $valor));
        }

        $s = $xml_response->asXML();
        $this->multiplexSrv->encolarDatosEscribir($this->sKey, $s);
    }

    function notificarEvento_PauseStart($sAgente, $infoPausa)
    {
        if (is_null($this->_sUsuarioECCP)) return;
        if (!is_null($this->_sAgenteFiltrado) && $this->_sAgenteFiltrado != $sAgente) return;

        $xml_response = new SimpleXMLElement('<event />');
        $xml_agentLinked = $xml_response->addChild('pausestart');
        $infoPausa['agent_number'] = $sAgente;
        foreach ($infoPausa as $sKey => $valor) {
            if (!is_null($valor)) $xml_agentLinked->addChild($sKey, str_replace('&', '&amp;', $valor));
        }

        $s = $xml_response->asXML();
        $this->multiplexSrv->encolarDatosEscribir($this->sKey, $s);
    }

    function notificarEvento_PauseEnd($sAgente, $infoPausa)
    {
        if (is_null($this->_sUsuarioECCP)) return;
        if (!is_null($this->_sAgenteFiltrado) && $this->_sAgenteFiltrado != $sAgente) return;

        $xml_response = new SimpleXMLElement('<event />');
        $xml_agentLinked = $xml_response->addChild('pauseend');
        $infoPausa['agent_number'] = $sAgente;
        foreach ($infoPausa as $sKey => $valor) {
            if (!is_null($valor)) $xml_agentLinked->addChild($sKey, str_replace('&', '&amp;', $valor));
        }

        $s = $xml_response->asXML();
        $this->multiplexSrv->encolarDatosEscribir($this->sKey, $s);
    }

    function notificarEvento_CallProgress($infoProgreso)
    {
    	if (is_null($this->_sUsuarioECCP)) return;
        if (!$this->_bProgresoLlamada) return;

        $xml_response = new SimpleXMLElement('<event />');
        $xml_callProgress = $xml_response->addChild('callprogress');
        foreach ($infoProgreso as $sKey => $valor) {
            if (!is_null($valor)) $xml_callProgress->addChild($sKey, str_replace('&', '&amp;', $valor));
        }

        $s = $xml_response->asXML();
        $this->multiplexSrv->encolarDatosEscribir($this->sKey, $s);
    }

    function notificarEvento_QueueMembership($sAgente, $infoSeguimiento, $listaColas)
    {
        if (is_null($this->_sUsuarioECCP)) return;
        if (!is_null($this->_sAgenteFiltrado) && $this->_sAgenteFiltrado != $sAgente) return;

        $xml_response = new SimpleXMLElement('<event />');
        $xml_queueMembership = $xml_response->addChild('queuemembership');

        $xml_queueMembership->addChild('agent_number', str_replace('&', '&amp;', $sAgente));
        ECCPConn::getcampaignstatus_setagent($xml_queueMembership, $infoSeguimiento);
        $xml_agentQueues = $xml_queueMembership->addChild('queues');
        foreach ($listaColas as $sCola) {
            $xml_agentQueues->addChild('queue', str_replace('&', '&amp;', $sCola));
        }
        $s = $xml_response->asXML();
        $this->multiplexSrv->encolarDatosEscribir($this->sKey, $s);
    }

    function notificarEvento_RecordingMute($sAgente, $sTipoLlamada, $idCampaign, $idLlamada)
    {
        if (is_null($this->_sUsuarioECCP)) return;
        if (!is_null($this->_sAgenteFiltrado) && $this->_sAgenteFiltrado != $sAgente) return;

        $xml_response = new SimpleXMLElement('<event />');
        $xml_recordingMute = $xml_response->addChild('recordingmute');

        $xml_recordingMute->addChild('agent_number', str_replace('&', '&amp;', $sAgente));
        $xml_recordingMute->addChild('calltype', $sTipoLlamada);
        if (!is_null($idCampaign)) $xml_recordingMute->addChild('campaign_id', $idCampaign);
        $xml_recordingMute->addChild('call_id', $idLlamada);

        $s = $xml_response->asXML();
        $this->multiplexSrv->encolarDatosEscribir($this->sKey, $s);
    }

    function notificarEvento_RecordingUnmute($sAgente, $sTipoLlamada, $idCampaign, $idLlamada)
    {
        if (is_null($this->_sUsuarioECCP)) return;
        if (!is_null($this->_sAgenteFiltrado) && $this->_sAgenteFiltrado != $sAgente) return;

        $xml_response = new SimpleXMLElement('<event />');
        $xml_recordingUnmute = $xml_response->addChild('recordingunmute');

        $xml_recordingUnmute->addChild('agent_number', str_replace('&', '&amp;', $sAgente));
        $xml_recordingUnmute->addChild('calltype', $sTipoLlamada);
        if (!is_null($idCampaign)) $xml_recordingUnmute->addChild('campaign_id', $idCampaign);
        $xml_recordingUnmute->addChild('call_id', $idLlamada);

        $s = $xml_response->asXML();
        $this->multiplexSrv->encolarDatosEscribir($this->sKey, $s);
    }

    function notificarEvento_ScheduledCallStart($sAgente, $sTipoLlamada, $idCampaign, $idLlamada)
    {
        if (is_null($this->_sUsuarioECCP)) return;
        if (!is_null($this->_sAgenteFiltrado) && $this->_sAgenteFiltrado != $sAgente) return;

        $xml_response = new SimpleXMLElement('<event />');
        $xml_scheduleCallStart = $xml_response->addChild('schedulecallstart');

        $xml_scheduleCallStart->addChild('agent_number', str_replace('&', '&amp;', $sAgente));
        $xml_scheduleCallStart->addChild('calltype', $sTipoLlamada);
        if (!is_null($idCampaign)) $xml_scheduleCallStart->addChild('campaign_id', $idCampaign);
        $xml_scheduleCallStart->addChild('call_id', $idLlamada);

        $s = $xml_response->asXML();
        $this->multiplexSrv->encolarDatosEscribir($this->sKey, $s);
    }

    function notificarEvento_ScheduledCallFailed($sAgente, $sTipoLlamada, $idCampaign, $idLlamada)
    {
        if (is_null($this->_sUsuarioECCP)) return;
        if (!is_null($this->_sAgenteFiltrado) && $this->_sAgenteFiltrado != $sAgente) return;

        $xml_response = new SimpleXMLElement('<event />');
        $xml_scheduleCallFailed = $xml_response->addChild('schedulecallfailed');

        $xml_scheduleCallFailed->addChild('agent_number', str_replace('&', '&amp;', $sAgente));
        $xml_scheduleCallFailed->addChild('calltype', $sTipoLlamada);
        if (!is_null($idCampaign)) $xml_scheduleCallFailed->addChild('campaign_id', $idCampaign);
        $xml_scheduleCallFailed->addChild('call_id', $idLlamada);

        $s = $xml_response->asXML();
        $this->multiplexSrv->encolarDatosEscribir($this->sKey, $s);
    }
}
?>