<?php
/* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Elastix version 0.5                                                  |f
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
  $Id: new_campaign.php $ */

define('ECCP_PORT', 20005);

class ECCPConnFailedException extends Exception {}
class ECCPUnauthorizedException extends Exception {}
class ECCPIOException extends Exception {}
class ECCPMalformedXMLException extends Exception {}
class ECCPUnrecognizedPacketException extends Exception {}
class ECCPBadRequestException extends Exception {}

/**
 * Clase que contiene una implementación de un cliente del protocolo ECCP
 * (Elastix CallCenter Protocol) para uso de una consola de cliente web.
 */
class ECCP
{
    private $_listaEventos = array(); // Lista de eventos pendientes
    private $_parseError = NULL;
    private $_response = NULL;      // Respuesta recibida para un requerimiento
    private $_parser = NULL;        // Parser expat para separar los paquetes
    private $_iPosFinal = NULL;     // Posición de parser para el paquete parseado
    private $_sTipoDoc = NULL;      // Tipo de paquete. Sólo se acepta 'event' y 'response'
    private $_bufferXML = '';       // Datos pendientes que no forman un paquete completo
    private $_iNestLevel = 0;       // Al llegar a cero, se tiene fin de paquete

    private $_hConn = NULL;
    private $_iRequestID = 0;
    private $_sAppCookie;

    private $_agentNumber = '';
    private $_agentPass = '';

    /**
     * Procedimiento que inicia la conexión y el login al servidor ECCP.
     *
     * @param   string  $server     Servidor al cual conectarse. Puede opcionalmente
     *                              indicar el puerto como localhost:20005
     * @param   string  $username   Nombre de usuario a usar para la conexión
     * @param   string  $secret     Contraseña a usar para login
     *
     * @return  void
     * @throws  ECCPConnFailedException, ECCPUnauthorizedException, ECCPIOException
     */
    public function connect($server, $username, $secret)
    {
    	// Determinar servidor y puerto a usar
        $iPuerto = ECCP_PORT;
        if(strpos($server, ':') !== false) {
            $c = explode(':', $server);
            $server = $c[0];
            $iPuerto = $c[1];
        }

        // Iniciar la conexión
        $errno = $errstr = NULL;
        $sUrlConexion = "tcp://$server:$iPuerto";
        $this->_hConn = @stream_socket_client($sUrlConexion, $errno, $errstr);
        if (!$this->_hConn) throw new ECCPConnFailedException("$sUrlConexion: ($errno) $errstr", $errno);

        return $this->login($username, $secret);
    }

    public function setAgentNumber($sAgentNumber) { $this->_agentNumber = $sAgentNumber; }
    public function setAgentPass($sAgentPass) { $this->_agentPass = $sAgentPass; }

    public function disconnect()
    {
        $this->logout();
        if (!is_null($this->_parser)) {
            xml_parser_free($this->_parser);
            $this->_parser = NULL;
        }
        fclose($this->_hConn);
        $this->_hConn = NULL;
    }

    // Enviar una cadena entera de requerimiento al servidor ECCP
    private function send_request($xml_request)
    {
        $this->_iRequestID++;
        $xml_request->addAttribute('id', $this->_iRequestID);
        $s = $xml_request->asXML();
        while ($s != '') {
            $iEscrito = @fwrite($this->_hConn, $s);
            // fwrite en socket bloqueante puede devolver 0 en lugar de FALSE en error
            if ($iEscrito === FALSE || $iEscrito <= 0) throw new ECCPIOException('output');
            $s = substr($s, $iEscrito);
        }
        $xml_response = $this->wait_response();
        if (isset($xml_response->failure))
            throw new ECCPBadRequestException((string)$xml_response->failure->message, (int)$xml_response->failure->code);
        return $xml_response;
    }

    /**
     * Procedimiento para recibir eventos o respuestas del servidor ECCP.
     * Este método leerá datos hasta que se haya visto alguna respuesta, o hasta
     * que el timeout opcional haya expirado.
     *
     * @param   int     $timeout    Intervalo en segundos a esperar por una
     *                              respuesta. Si se omite, se espera para siempre.
     *                              Si se especifica 0, se regresa de inmediato luego
     *                              de una sola verificación de datos.
     *
     * @return  mixed   Objeto SimpleXMLElement que representa los datos de la
     *                  respuesta, o NULL si timeout.
     *
     * @throws  ECCPIOException
     */
    public function wait_response($timeout = NULL)
    {
        $iTotalPaquetes = 0;
        $iTimestampInicio = microtime(TRUE);
        do {
            if (is_null($timeout)) {
                $sec = $usec = NULL;
            } elseif (count($this->_listaEventos) == 0) {
                $sec = (int)$timeout;
                $usec = (int)(($timeout - $sec) * 1000000);
            } else {
                $timeout = $sec = $usec = 0;
            }

            $listoLeer = array($this->_hConn);
            $listoEscribir = array();
            $listoErr = array();
            $iNumCambio = @stream_select($listoLeer, $listoEscribir, $listoErr, $sec, $usec);
            if ($iNumCambio === FALSE) {
                throw new ECCPIOException('input');
            } elseif (count($listoErr) > 0) {
                throw new ECCPIOException('input');
            } elseif ($iNumCambio > 0 || count($listoLeer) > 0) {
                $s = fread($this->_hConn, 65536);
                if ($s == '') throw new ECCPIOException('input');
                $iTotalPaquetes += $this->_parsearPaquetesXML($s);
            }

            if (!is_null($timeout)) {
                $iTimestampFinal = microtime(TRUE);
                $timeout -= $iTimestampFinal - $iTimestampInicio;
                $iTimestampInicio = $iTimestampFinal;
            }
        } while (is_null($this->_response) && (is_null($timeout) || ($timeout > 0 && $iTotalPaquetes == 0)));

        // Devolver lo que haya de respuesta
        $r = $this->_response;
        $this->_response = NULL;
        return $r;
    }

    public function getParseError() { return $this->_parseError; }
    public function getEvent() { return array_shift($this->_listaEventos); }

    // Implementación de parser expat: inicio

    // Parsear y separar tantos paquetes XML como sean posibles
    private function _parsearPaquetesXML($data)
    {
        $iNumPaquetes = 0;
        if (is_null($this->_parser)) $this->_resetParser();

        $this->_bufferXML .= $data;
        $r = xml_parse($this->_parser, $data);
        while (!is_null($this->_iPosFinal)) {
            $iNumPaquetes++;
            if ($this->_sTipoDoc == 'event') {
                $this->_listaEventos[] = simplexml_load_string(substr($this->_bufferXML, 0, $this->_iPosFinal));
            } elseif ($this->_sTipoDoc == 'response') {
                $this->_response = simplexml_load_string(substr($this->_bufferXML, 0, $this->_iPosFinal));
            } else {
                $this->_parseError = array(
                    'errorcode'     =>  -1,
                    'errorstring'   =>  "Unrecognized packet type: {$this->_sTipoDoc}",
                    'errorline'     =>  xml_get_current_line_number($this->_parser),
                    'errorpos'      =>  xml_get_current_column_number($this->_parser),
                );
                throw new ECCPUnrecognizedPacketException();
            }
            $this->_bufferXML = ltrim(substr($this->_bufferXML, $this->_iPosFinal));
            $this->_iPosFinal = NULL;
            $this->_resetParser();
            if ($this->_bufferXML != '')
                $r = xml_parse($this->_parser, $this->_bufferXML);
        }
        if (!$r) {
            $this->_parseError = array(
                'errorcode'     =>  xml_get_error_code($this->_parser),
                'errorstring'   =>  xml_error_string(xml_get_error_code($this->_parser)),
                'errorline'     =>  xml_get_current_line_number($this->_parser),
                'errorpos'      =>  xml_get_current_column_number($this->_parser),
            );
            throw new ECCPMalformedXMLException();
        }
        return $iNumPaquetes;
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

    private function agentHash($agent_number, $agent_pass)
    {
        return md5($this->_sAppCookie.$agent_number.$agent_pass);
    }

    // Requerimientos conocidos del protocolo ECCP

    public function login($username, $password)
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('login');
        $xml_cmdRequest->addChild('username', str_replace('&', '&amp;', $username));
        $xml_cmdRequest->addChild('password', preg_match('/^[[:xdigit:]]{32}$/', $password) ? $password : md5($password));
        $xml_response = $this->send_request($xml_request);
        if (isset($xml_response->login_response->app_cookie))
            $this->_sAppCookie = $xml_response->login_response->app_cookie;
        return $xml_response->login_response;
    }

    public function logout()
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_logoutRequest = $xml_request->addChild('logout');
        $xml_response = $this->send_request($xml_request);
        return TRUE;
    }

    public function loginagent($extension, $password = NULL, $timeout = NULL)
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('loginagent');
        $xml_cmdRequest->addChild('agent_number', $this->_agentNumber);
        $xml_cmdRequest->addChild('agent_hash', $this->agentHash($this->_agentNumber, $this->_agentPass));
        $xml_cmdRequest->addChild('extension', str_replace('&', '&amp;', $extension));
        if (!is_null($password))
            $xml_cmdRequest->addChild('password', str_replace('&', '&amp;', $password));
        if (!is_null($timeout))
            $xml_cmdRequest->addChild('timeout', str_replace('&', '&amp;', $timeout));
        $xml_response = $this->send_request($xml_request);
        return $xml_response->loginagent_response;
    }

    public function logoutagent()
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('logoutagent');
        $xml_cmdRequest->addChild('agent_number', $this->_agentNumber);
        $xml_cmdRequest->addChild('agent_hash', $this->agentHash($this->_agentNumber, $this->_agentPass));
        $xml_response = $this->send_request($xml_request);
        return $xml_response->logoutagent_response;
    }

    public function getagentstatus($sAgentNumber = NULL)
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('getagentstatus');
        $xml_cmdRequest->addChild('agent_number', is_null($sAgentNumber) ? $this->_agentNumber : $sAgentNumber);
        $xml_response = $this->send_request($xml_request);
        return $xml_response->getagentstatus_response;
    }

    public function mixmonitormute($timeout = NULL)
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('mixmonitormute');
        $xml_cmdRequest->addChild('agent_number', $this->_agentNumber);
        $xml_cmdRequest->addChild('agent_hash', $this->agentHash($this->_agentNumber, $this->_agentPass));
        if (!is_null($timeout) && (int)$timeout > 0) {
            $xml_cmdRequest->addChild('timeout', (int)$timeout);
        }
        $xml_response = $this->send_request($xml_request);
        return $xml_response->mixmonitormute_response;
    }

    public function mixmonitorunmute()
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('mixmonitorunmute');
        $xml_cmdRequest->addChild('agent_number', $this->_agentNumber);
        $xml_cmdRequest->addChild('agent_hash', $this->agentHash($this->_agentNumber, $this->_agentPass));
        $xml_response = $this->send_request($xml_request);
        return $xml_response->mixmonitorunmute_response;
    }

    public function getmultipleagentstatus($agentlist)
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('getmultipleagentstatus');
        $xml_agents = $xml_cmdRequest->addChild('agents');
        foreach ($agentlist as $sAgentNumber) $xml_agents->addChild('agent_number', $sAgentNumber);
        $xml_response = $this->send_request($xml_request);
        return $xml_response->getmultipleagentstatus_response;
    }

    public function getcampaigninfo($campaign_type, $campaign_id)
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('getcampaigninfo');
        $xml_cmdRequest->addChild('campaign_type', str_replace('&', '&amp;', $campaign_type));
        $xml_cmdRequest->addChild('campaign_id', str_replace('&', '&amp;', $campaign_id));
        $xml_response = $this->send_request($xml_request);
        return $xml_response->getcampaigninfo_response;
    }

    public function getqueuescript($queue)
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('getqueuescript');
        $xml_cmdRequest->addChild('queue', str_replace('&', '&amp;', $queue));
        $xml_response = $this->send_request($xml_request);
        return $xml_response->getqueuescript_response;
    }

    public function getcallinfo($campaign_type, $campaign_id, $call_id)
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('getcallinfo');
        $xml_cmdRequest->addChild('campaign_type', str_replace('&', '&amp;', $campaign_type));
        if (!is_null($campaign_id))
            $xml_cmdRequest->addChild('campaign_id', str_replace('&', '&amp;', $campaign_id));
        $xml_cmdRequest->addChild('call_id', str_replace('&', '&amp;', $call_id));
        $xml_response = $this->send_request($xml_request);
        return $xml_response->getcallinfo_response;
    }

    public function setcontact($call_id, $contact_id)
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('setcontact');
        $xml_cmdRequest->addChild('agent_number', $this->_agentNumber);
        $xml_cmdRequest->addChild('agent_hash', $this->agentHash($this->_agentNumber, $this->_agentPass));
        $xml_cmdRequest->addChild('call_id', str_replace('&', '&amp;', $call_id));
        $xml_cmdRequest->addChild('contact_id', str_replace('&', '&amp;', $contact_id));
        $xml_response = $this->send_request($xml_request);
        return $xml_response->setcontact_response;
    }

    public function saveformdata($campaign_type, $call_id, $formdata)
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('saveformdata');
        $xml_cmdRequest->addChild('agent_number', $this->_agentNumber);
        $xml_cmdRequest->addChild('agent_hash', $this->agentHash($this->_agentNumber, $this->_agentPass));
        $xml_cmdRequest->addChild('campaign_type', str_replace('&', '&amp;', $campaign_type));
        $xml_cmdRequest->addChild('call_id', str_replace('&', '&amp;', $call_id));

        $xml_forms = $xml_cmdRequest->addChild('forms');
        foreach ($formdata as $idForm => $fields) {
            $xml_form = $xml_forms->addChild('form');
            $xml_form->addAttribute('id', $idForm);
            foreach ($fields as $idField => $sFieldValue) {
                $xml_field = $xml_form->addChild('field', str_replace('&', '&amp;', $sFieldValue));
                $xml_field->addAttribute('id', $idField);
            }
        }

        $xml_response = $this->send_request($xml_request);
        return $xml_response->saveformdata_response;
    }

    public function getpauses()
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('getpauses');
        $xml_response = $this->send_request($xml_request);
        return $xml_response->getpauses_response;
    }

    public function pauseagent($pause_type)
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('pauseagent');
        $xml_cmdRequest->addChild('agent_number', $this->_agentNumber);
        $xml_cmdRequest->addChild('agent_hash', $this->agentHash($this->_agentNumber, $this->_agentPass));
        $xml_cmdRequest->addChild('pause_type', str_replace('&', '&amp;', $pause_type));
        $xml_response = $this->send_request($xml_request);
        return $xml_response->pauseagent_response;
    }

    public function unpauseagent()
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('unpauseagent');
        $xml_cmdRequest->addChild('agent_number', $this->_agentNumber);
        $xml_cmdRequest->addChild('agent_hash', $this->agentHash($this->_agentNumber, $this->_agentPass));
        $xml_response = $this->send_request($xml_request);
        return $xml_response->unpauseagent_response;
    }

    public function hangup()
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('hangup');
        $xml_cmdRequest->addChild('agent_number', $this->_agentNumber);
        $xml_cmdRequest->addChild('agent_hash', $this->agentHash($this->_agentNumber, $this->_agentPass));
        $xml_response = $this->send_request($xml_request);
        return $xml_response->hangup_response;
    }

    public function hold()
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('hold');
        $xml_cmdRequest->addChild('agent_number', $this->_agentNumber);
        $xml_cmdRequest->addChild('agent_hash', $this->agentHash($this->_agentNumber, $this->_agentPass));
        $xml_response = $this->send_request($xml_request);
        return $xml_response->hold_response;
    }

    public function unhold()
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('unhold');
        $xml_cmdRequest->addChild('agent_number', $this->_agentNumber);
        $xml_cmdRequest->addChild('agent_hash', $this->agentHash($this->_agentNumber, $this->_agentPass));
        $xml_response = $this->send_request($xml_request);
        return $xml_response->unhold_response;
    }

    public function transfercall($extension)
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('transfercall');
        $xml_cmdRequest->addChild('agent_number', $this->_agentNumber);
        $xml_cmdRequest->addChild('agent_hash', $this->agentHash($this->_agentNumber, $this->_agentPass));
        $xml_cmdRequest->addChild('extension', str_replace('&', '&amp;', $extension));
        $xml_response = $this->send_request($xml_request);
        return $xml_response->transfercall_response;
    }

    public function atxfercall($extension)
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('atxfercall');
        $xml_cmdRequest->addChild('agent_number', $this->_agentNumber);
        $xml_cmdRequest->addChild('agent_hash', $this->agentHash($this->_agentNumber, $this->_agentPass));
        $xml_cmdRequest->addChild('extension', str_replace('&', '&amp;', $extension));
        $xml_response = $this->send_request($xml_request);
        return $xml_response->atxfercall_response;
    }

    public function getcampaignstatus($campaign_type, $campaign_id, $datetime_start = NULL)
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('getcampaignstatus');
        $xml_cmdRequest->addChild('campaign_type', str_replace('&', '&amp;', $campaign_type));
        $xml_cmdRequest->addChild('campaign_id', str_replace('&', '&amp;', $campaign_id));
        if (!is_null($datetime_start))
            $xml_cmdRequest->addChild('datetime_start', str_replace('&', '&amp;', $datetime_start));
        $xml_response = $this->send_request($xml_request);
        return $xml_response->getcampaignstatus_response;
    }

    public function dial()
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('dial');
        $xml_response = $this->send_request($xml_request);
        return $xml_response->dial_response;
    }

    public function getrequestlist()
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('getrequestlist');
        $xml_response = $this->send_request($xml_request);
        return $xml_response->getrequestlist_response;
    }

    public function schedulecall($schedule, $sameagent, $newphone, $newcontactname,
        $campaign_type = NULL, $call_id = NULL)
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('schedulecall');
        $xml_cmdRequest->addChild('agent_number', $this->_agentNumber);
        $xml_cmdRequest->addChild('agent_hash', $this->agentHash($this->_agentNumber, $this->_agentPass));
        if (is_array($schedule)) {
            $xml_schedule = $xml_cmdRequest->addChild('schedule');
        	foreach (array('date_init', 'date_end', 'time_init', 'time_end') as $k)
                if (isset($schedule[$k])) $xml_schedule->addChild($k, $schedule[$k]);
        }
        if ($sameagent)
            $xml_cmdRequest->addChild('sameagent', 1);
        if (!is_null($newphone))
            $xml_cmdRequest->addChild('newphone', str_replace('&', '&amp;', $newphone));
        if (!is_null($newcontactname))
            $xml_cmdRequest->addChild('newcontactname', str_replace('&', '&amp;', $newcontactname));
        if (!is_null($campaign_type))
            $xml_cmdRequest->addChild('campaign_type', str_replace('&', '&amp;', $campaign_type));
        if (!is_null($call_id))
            $xml_cmdRequest->addChild('call_id', str_replace('&', '&amp;', $call_id));

        $xml_response = $this->send_request($xml_request);
        return $xml_response->schedulecall_response;
    }

    public function filterbyagent()
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('filterbyagent');
        $xml_cmdRequest->addChild('agent_number', $this->_agentNumber);
        $xml_response = $this->send_request($xml_request);
        return $xml_response->filterbyagent_response;
    }

    public function removefilterbyagent()
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('filterbyagent');
        $xml_cmdRequest->addChild('agent_number', 'any');
        $xml_response = $this->send_request($xml_request);
        return $xml_response->filterbyagent_response;
    }

    public function getcampaignlist($campaign_type = NULL, $status = NULL,
        $filtername = NULL, $datetime_start = NULL, $datetime_end = NULL,
        $offset = NULL, $limit = NULL)
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('getcampaignlist');
        if (!is_null($campaign_type))
            $xml_cmdRequest->addChild('campaign_type', str_replace('&', '&amp;', $campaign_type));
        if (!is_null($status))
            $xml_cmdRequest->addChild('status', str_replace('&', '&amp;', $status));
        if (!is_null($filtername))
            $xml_cmdRequest->addChild('filtername', str_replace('&', '&amp;', $filtername));
        if (!is_null($datetime_start))
            $xml_cmdRequest->addChild('datetime_start', str_replace('&', '&amp;', $datetime_start));
        if (!is_null($datetime_end))
            $xml_cmdRequest->addChild('datetime_end', str_replace('&', '&amp;', $datetime_end));
        if (!is_null($offset))
            $xml_cmdRequest->addChild('offset', str_replace('&', '&amp;', $offset));
        if (!is_null($limit))
            $xml_cmdRequest->addChild('limit', str_replace('&', '&amp;', $limit));
        $xml_response = $this->send_request($xml_request);
        return $xml_response->getcampaignlist_response;
    }

    public function getcampaignqueuewait($campaign_type, $campaign_id)
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('getcampaignqueuewait');
        $xml_cmdRequest->addChild('campaign_type', str_replace('&', '&amp;', $campaign_type));
        $xml_cmdRequest->addChild('campaign_id', str_replace('&', '&amp;', $campaign_id));
        $xml_response = $this->send_request($xml_request);
        return $xml_response->getcampaignqueuewait_response;
    }

    public function getagentqueues($sAgentNumber = NULL)
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('getagentqueues');
        $xml_cmdRequest->addChild('agent_number', is_null($sAgentNumber) ? $this->_agentNumber : $sAgentNumber);
        $xml_response = $this->send_request($xml_request);
        return $xml_response->getagentqueues_response;
    }

    public function getmultipleagentqueues($agentlist)
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('getmultipleagentqueues');
        $xml_agents = $xml_cmdRequest->addChild('agents');
        foreach ($agentlist as $sAgentNumber) $xml_agents->addChild('agent_number', $sAgentNumber);
        $xml_response = $this->send_request($xml_request);
        return $xml_response->getmultipleagentqueues_response;
    }

    public function getagentactivitysummary($datetime_start = NULL, $datetime_end = NULL)
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('getagentactivitysummary');
        if (!is_null($datetime_start))
            $xml_cmdRequest->addChild('datetime_start', str_replace('&', '&amp;', $datetime_start));
        if (!is_null($datetime_end))
            $xml_cmdRequest->addChild('datetime_end', str_replace('&', '&amp;', $datetime_end));
        $xml_response = $this->send_request($xml_request);
        return $xml_response->getagentactivitysummary_response;
    }

    public function getchanvars($sAgentNumber = NULL)
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('getchanvars');
        $xml_cmdRequest->addChild('agent_number', is_null($sAgentNumber) ? $this->_agentNumber : $sAgentNumber);
        $xml_cmdRequest->addChild('agent_hash', $this->agentHash($this->_agentNumber, $this->_agentPass));
        $xml_response = $this->send_request($xml_request);
        return $xml_response->getchanvars_response;
    }

    public function campaignlog($campaign_type, $campaign_id = NULL, $queue = NULL,
        $datetime_start = NULL, $datetime_end = NULL, $lastN = NULL, $idbefore = 0)
    {
    	$xml_request = new SimpleXMLElement('<request />');
        $xml_cmdRequest = $xml_request->addChild('campaignlog');
        $xml_cmdRequest->addChild('campaign_type', str_replace('&', '&amp;', $campaign_type));
        if (!is_null($campaign_id))
            $xml_cmdRequest->addChild('campaign_id', str_replace('&', '&amp;', $campaign_id));
        if (!is_null($queue))
            $xml_cmdRequest->addChild('queue', str_replace('&', '&amp;', $queue));
        if (!is_null($datetime_start))
            $xml_cmdRequest->addChild('datetime_start', str_replace('&', '&amp;', $datetime_start));
        if (!is_null($datetime_end))
            $xml_cmdRequest->addChild('datetime_end', str_replace('&', '&amp;', $datetime_end));
        if (!is_null($datetime_end))
            $xml_cmdRequest->addChild('datetime_end', str_replace('&', '&amp;', $datetime_end));
        if (!is_null($lastN))
            $xml_cmdRequest->addChild('last_n', $lastN);
        if (!is_null($idbefore))
            $xml_cmdRequest->addChild('idbefore', $idbefore);
        $xml_response = $this->send_request($xml_request);
        return $xml_response->campaignlog_response;
    }

    public function callprogress($enable)
    {
        $xml_request = new SimpleXMLElement('<request />');
        $xml_cmdRequest = $xml_request->addChild('callprogress');
        $xml_cmdRequest->addChild('enable', $enable ? 1 : 0);
        $xml_response = $this->send_request($xml_request);
        return $xml_response->callprogress_response;
    }

    public function getincomingqueuestatus($queue, $datetime_start = NULL)
    {
        $xml_request = new SimpleXMLElement('<request />');
        $xml_cmdRequest = $xml_request->addChild('getincomingqueuestatus');
        $xml_cmdRequest->addChild('queue', str_replace('&', '&amp;', $queue));
        if (!is_null($datetime_start))
            $xml_cmdRequest->addChild('datetime_start', str_replace('&', '&amp;', $datetime_start));
        $xml_response = $this->send_request($xml_request);
        return $xml_response->getincomingqueuestatus_response;
    }

    public function getincomingqueuelist()
    {
        $xml_request = new SimpleXMLElement('<request />');
        $xml_cmdRequest = $xml_request->addChild('getincomingqueuelist');
        $xml_response = $this->send_request($xml_request);
        return $xml_response->getincomingqueuelist_response;
    }

    public function pingagent()
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('pingagent');
        $xml_cmdRequest->addChild('agent_number', $this->_agentNumber);
        $xml_cmdRequest->addChild('agent_hash', $this->agentHash($this->_agentNumber, $this->_agentPass));
        $xml_response = $this->send_request($xml_request);
        return $xml_response->pingagent_response;
    }

    public function dumpstatus()
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('dumpstatus');
        $xml_response = $this->send_request($xml_request);
        return $xml_response->dumpstatus_response;
    }

    public function refreshagents()
    {
        $xml_request = new SimpleXMLElement("<request />");
        $xml_cmdRequest = $xml_request->addChild('refreshagents');
        $xml_response = $this->send_request($xml_request);
        return $xml_response->refreshagents_response;
    }
}
?>
