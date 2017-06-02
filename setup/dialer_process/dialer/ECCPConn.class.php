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

require_once 'ECCPHelper.lib.php';

class ECCPConn
{
    public $DEBUG = FALSE;

    private $_log;
    private $_ami;
    private $_astVersion;
    private $_db;
    private $_tuberia;

    /* Lista de atributos de funciones (decorator). Actualmente se usa para
     * abstraer la autenticación sin tener que repetirla para cada función
     * que la requiera */
    private $_peticionesAttr = array();

    function __construct($oMainLog, $tuberia)
    {
        $this->_log = $oMainLog;
        $this->_tuberia = $tuberia;

        // Recolectar atributos de los requerimientos
        foreach (get_class_methods(get_class($this)) as $sMetodo) {
        	$regs = NULL;
            if (preg_match('/^Request_(.+)$/i', $sMetodo, $regs)) {
        		$sRequerimiento = $regs[1];
                $atributos = array(
                    'method'    => $sMetodo,
                    'eccpauth'  =>  FALSE,  // Método requiere autenticación ECCP
                    'agentauth' =>  FALSE,  // Método requiere auth ECCP y de agente
                );
                foreach (array('eccpauth', 'agentauth') as $decorator) {
                    if (preg_match("/^(.*){$decorator}_(.+)$/", $sRequerimiento, $regs)) {
                    	$atributos[$decorator] = TRUE;
                        $sRequerimiento = $regs[1].$regs[2];
                    }
                }
                $this->_peticionesAttr[$sRequerimiento] = $atributos;
        	}
        }
    }

    function setAstConn($astConn, $astVersion)
    {
        $this->_ami = $astConn;
        $this->_astVersion = $astVersion;
    }

    function setDbConn($dbConn)
    {
        $this->_db = $dbConn;
    }

    public function do_eccprequest(&$request, &$connvars)
    {
        $response = NULL;

        $t = $request['received'];
        $request = simplexml_load_string($request['request']);
        $request->addAttribute('received', $t);

        $nuevos_valores = NULL;
        $eventos = NULL;

        // Petición es un request, procesar
        if (count($request) != 1) {
            // La petición debe tener al menos un elemento hijo
            $response = $this->_generarRespuestaFallo(400, 'Bad request');
        } elseif (!isset($request['id'])) {
            // La petición debe tener un identificador
            $response = $this->_generarRespuestaFallo(400, 'Bad request');
        } else {
            if (is_null($this->_db)) {
                // Todavía no se ha restaurado la conexión a la base de datos
                $response = $this->_generarRespuestaFallo(500, 'Server error - database failure');
            } else {
                if ($this->DEBUG) {
                    $iTimestampRecibido = (double)$request['received'];
                    $proc_start = microtime(TRUE);
                    $this->_log->output('DEBUG: '.__METHOD__.': retraso '.
                        '(sec) hasta procesar: '.($proc_start - $iTimestampRecibido));
                }

                // Se procede normalmente...
                $comando = NULL;
                foreach ($request->children() as $c) $comando = $c;

                // Hack para no agregar parámetro a todas las peticiones
                if (!is_null($connvars['appcookie']))
                    $comando->addAttribute('appcookie', $connvars['appcookie']);

                $iTimestampInicio = microtime(TRUE);
                $sRequerimiento = (string)$comando->getName();
                if ($this->DEBUG) {
                    $this->_log->output('DEBUG: '.__METHOD__.': procesando requerimiento '.
                        $sRequerimiento.' params: '.print_r($comando, TRUE));
                }
                if (!isset($this->_peticionesAttr[$sRequerimiento])) {
                    $this->_log->output('ERR: (interno) no existe implementación para método: '.$sRequerimiento);
                    $response = $this->_generarRespuestaFallo(501, 'Not Implemented');
                } else {
                    $sMetodoImplementacion = $this->_peticionesAttr[$sRequerimiento]['method'];

                    // Autenticación según las decoraciones de la petición

                    // Verificación de usuario ECCP válido
                    if (is_null($response) &&
                        ($this->_peticionesAttr[$sRequerimiento]['eccpauth'] ||
                            $this->_peticionesAttr[$sRequerimiento]['agentauth'])) {
                                if (is_null($connvars['usuarioeccp']))
                                    $response = $this->_generarRespuestaFallo(401, 'Unauthorized');
                    }
                    try {
                        // Verificación de que agente existe y tiene contraseña válida
                        if (is_null($response) && $this->_peticionesAttr[$sRequerimiento]['agentauth']) {
                            // Verificar que agente está presente
                            if (!isset($comando->agent_number)) {
                                $response = $this->_generarRespuestaFallo(400, 'Bad request');
                            } else {
                                $sAgente = (string)$comando->agent_number;

                                $xml_response = new SimpleXMLElement('<response />');
                                $xml_reqresponse = $xml_response->addChild($sRequerimiento.'_response');

                                // El siguiente código asume formato Agent/9000
                                if (is_null($this->_parseAgent($sAgente))) {
                                    $this->_agregarRespuestaFallo($xml_reqresponse, 417, 'Invalid agent number');
                                    $response = $xml_response;
                                } else {
                                    // Verificar que el agente sea válido en el sistema
                                    if (!$this->_existeAgente($sAgente)) {
                                        $this->_agregarRespuestaFallo($xml_reqresponse, 404, 'Specified agent not found');
                                        $response = $xml_response;
                                    } elseif (!$this->_hashValidoAgenteECCP($comando, $comando['appcookie'])) {
                                        $this->_agregarRespuestaFallo($xml_reqresponse, 401, 'Unauthorized agent');
                                        $response = $xml_response;
                                    }
                                }
                            }
                        }

                        // Verificaciones realizadas, ejecutar método
                        if (is_null($response)) {
                            $response = $this->$sMetodoImplementacion($comando);
                            if (is_array($response)) {
                                if (isset($response['nuevos_valores']))
                                    $nuevos_valores = $response['nuevos_valores'];
                                if (isset($response['eventos']))
                                    $eventos = $response['eventos'];
                                $response = $response['response'];
                            }
                        }
                    } catch (PDOException $e) {
                        $response = $this->_generarRespuestaFallo(503, 'Internal server error - database failure');
                        $this->_stdManejoExcepcionDB($e, 'no se puede realizar operación de base de datos');
                    }
                }

                $iTimestampFinal = microtime(TRUE);
                if ($this->DEBUG || (($iTimestampFinal - $iTimestampInicio) >= 1.0)) {
                    $this->_log->output('DEBUG: '.__METHOD__.': requerimiento '.
                        $comando->getName().' procesado luego de (sec): '.
                        ($iTimestampFinal - $iTimestampInicio));
                }
            }
            $response->addAttribute('id', (string)$request['id']);
        }

        $s = $response->asXML();

        return array($s, $nuevos_valores, $eventos);
    }

    private function _stdManejoExcepcionDB($e, $s)
    {
        $this->_log->output('ERR: '.__METHOD__.": $s: ".implode(' - ', $e->errorInfo));
        $this->_log->output("ERR: traza de pila: \n".$e->getTraceAsString());
        if ($e->errorInfo[0] == 'HY000' && $e->errorInfo[1] == 2006) {
            // Códigos correspondientes a pérdida de conexión de base de datos
            $this->_log->output('WARN: '.__METHOD__.
                ': conexión a DB parece ser inválida, se cierra...');
            $this->multiplexSrv->setDBConn(NULL);
        }
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

    private function _parseAgent($sAgente)
    {
        // Se puede expandir para acomodar más tecnologías
        $regexp = '#^(\w+)/(\w+)$#';
        $regs = NULL;
        return preg_match($regexp, $sAgente, $regs)
            ? array('type' => $regs[1], 'number' => $regs[2]) : NULL;
    }

    private function Request_getrequestlist($comando)
    {
        $xml_response = new SimpleXMLElement('<response />');
        $xml_getRequestListResponse = $xml_response->addChild('getrequestlist_response');

        $xml_requests = $xml_getRequestListResponse->addChild('requests');
        foreach (array_keys($this->_peticionesAttr) as $sPeticion)
            $xml_requests->addChild('request', $sPeticion);
        return $xml_response;
    }

    private function Request_eccpauth_filterbyagent($comando)
    {
        // Verificar que agente está presente
        if (!isset($comando->agent_number))
            return $this->_generarRespuestaFallo(400, 'Bad request');
        $sAgente = (string)$comando->agent_number;

        $xml_response = new SimpleXMLElement('<response />');
        $xml_filterbyagentResponse = $xml_response->addChild('filterbyagent_response');

        // El siguiente código asume formato Agent/9000
        if ($sAgente == 'any') {
            $sAgente = NULL;
        } elseif (is_null($this->_parseAgent($sAgente))) {
            $this->_agregarRespuestaFallo($xml_filterbyagentResponse, 417, 'Invalid agent number');
            return $xml_response;
        }

        $xml_filterbyagentResponse->addChild('success');

        return array(
            'response'          =>  $xml_response,
            'nuevos_valores'    =>  array(
                'agentefiltrado'    =>  $sAgente,
            ),
        );
    }

    /**
     * Procedimiento que implementa el login del cliente del protocolo. No se
     * debe mandar ningún evento ni obedecer ningún otro requerimiento hasta que
     * se haya usado este comando para logonearse exitosamente
     *
     * @param   object   $comando    Comando de login
     *      <login>
     *          <username>alice</username>
     *          <password>[md5hash]</password> <!-- md5hash es hash md5 de passwd -->
     *      </login>
     *
     * @return  object  Respuesta codificada como un SimpleXMLObject
     *      <login_response>
     *          <success /> | <failure>mensaje</failure>
     *      </login_response>
     */
    private function Request_login($comando)
    {
        // Verificar que usuario y clave están presentes
        if (!isset($comando->username) || !isset($comando->password))
            return $this->_generarRespuestaFallo(400, 'Bad request');

        $xml_response = new SimpleXMLElement('<response />');
        $xml_loginResponse = $xml_response->addChild('login_response');

        /* FIXME: No me queda claro de qué manera es más seguro mandar el hash
         * del password, que el password en texto plano, en una conexión sin
         * encriptar, ya que en ambos casos se puede recoger con un sniffer.
         * Por ahora se acepta el password con o sin hash. */
        /* TODO: se puede almacenar cuál agente(s) está autorizado a atender en
         * la tabla eccp_authorized_clients */
        $sPeticionSQL =
            'SELECT COUNT(*) AS N FROM eccp_authorized_clients '.
            'WHERE username = ? AND (md5_password = ? OR md5_password = md5(?))';
        $paramSQL = array($comando->username, $comando->password, $comando->password);
        $recordset = $this->_db->prepare($sPeticionSQL);
        $recordset->execute($paramSQL);
        $tupla = $recordset->fetch(); $recordset->closeCursor();
        if ($tupla['N'] > 0) {
            // Usuario autorizado
            $xml_status = $xml_loginResponse->addChild('success');

            // Generar una cadena de hash para cookie de aplicación
            $sAppCookie = md5(posix_getpid().time().mt_rand());
            $xml_loginResponse->addChild('app_cookie', $sAppCookie);
            return array(
                'response'          =>  $xml_response,
                'nuevos_valores'    =>  array(
                    'usuarioeccp'   =>  (string)$comando->username,
                    'appcookie'     =>  $sAppCookie,
                ),
            );
        } else {
            // Usuario no existe, o clave incorrecta
            $this->_agregarRespuestaFallo($xml_loginResponse, 401, 'Invalid username or password');
            return $xml_response;
        }
    }

    /**
     * Procedimiento que implementa el logout del cliente del protocolo. Luego
     * de este requerimiento, se espera que se cierre la conexión.
     *
     * @param   object   $comando    Comando de logout
     *      <logout />
     *
     * @return  object  Respuesta codificada como un SimpleXMLObject
     *      <logout_response />
     */
    private function Request_logout($comando)
    {
        $xml_response = new SimpleXMLElement('<response />');
        $xml_loginResponse = $xml_response->addChild('logout_response');
        $xml_status = $xml_loginResponse->addChild('success');
        return array(
            'response'          =>  $xml_response,
            'nuevos_valores'    =>  array(
                'usuarioeccp'   =>  NULL,
                'appcookie'     =>  NULL,
                'finalizando'   =>  TRUE,
            ),
        );
    }

    // Revisar si el comando indicado tiene un hash válido. El comando debe de
    // tener los campos agent_number y agent_hash
    private function _hashValidoAgenteECCP($comando, $appcookie)
    {
        if (!isset($comando->agent_number) || !isset($comando->agent_hash))
            return FALSE;
        $sAgente = (string)$comando->agent_number;
        $sHashCliente = (string)$comando->agent_hash;

        $recordset = $this->_db->prepare(
            'SELECT number, eccp_password FROM agent '.
            "WHERE estatus = 'A' AND CONCAT(type,'/',number) = ?");
        $recordset->execute(array($sAgente));
        $tuplaAgente = $recordset->fetch(); $recordset->closeCursor();
        if (!$tuplaAgente) {
            // Agente no se ha encontrado en la base de datos
            return FALSE;
        }
        $sClaveECCPAgente = $tuplaAgente['eccp_password'];

        // Para pruebas, se acepta a agente sin password
        if (is_null($sClaveECCPAgente)) return TRUE;

        // Calcular el hash que debió haber enviado el cliente
        $sHashEsperado = md5($appcookie.$sAgente.$sClaveECCPAgente);
        return ($sHashEsperado == $sHashCliente);
    }

    private function Request_eccpauth_getqueuescript($comando)
    {
        // Verificar que queue está presente
        if (!isset($comando->queue))
            return $this->_generarRespuestaFallo(400, 'Bad request');
        $queue = (int)$comando->queue;

        $xml_response = new SimpleXMLElement('<response />');
        $xml_GetQueueScriptResponse = $xml_response->addChild('getqueuescript_response');

        // Leer la información del script de la cola. El ORDER BY estatus hace
        // que se devuelva A y luego I.
        $recordset = $this->_db->prepare(
            'SELECT script FROM queue_call_entry '.
            'WHERE queue = ? ORDER BY estatus LIMIT 0,1');
        $recordset->execute(array($queue));
        $tupla = $recordset->fetch(); $recordset->closeCursor();
        if (!$tupla) {
            $this->_agregarRespuestaFallo($xml_GetQueueScriptResponse, 404, 'Queue not found in incoming queues');
            return $xml_response;
        }
        $xml_GetQueueScriptResponse->addChild('script', str_replace('&', '&amp;', $tupla['script']));
        return $xml_response;
    }

    private function Request_eccpauth_getcampaignlist($comando)
    {
        // Tipo de campaña
        $sTipoCampania = NULL;
        if (isset($comando->campaign_type)) {
            $sTipoCampania = (string)$comando->campaign_type;
        }
        $listaTiposConocidos = array('incoming', 'outgoing');
        if (!is_null($sTipoCampania) && !in_array($sTipoCampania, $listaTiposConocidos))
            return $this->_generarRespuestaFallo(400, 'Bad request - invalid campaign type');
        if (!is_null($sTipoCampania))
            $listaTipos = array($sTipoCampania);
        else $listaTipos = $listaTiposConocidos;

        // Filtro por nombre
        $sNombreContiene = NULL;
        if (isset($comando->filtername)) {
            $sNombreContiene = (string)$comando->filtername;
        }

        // Filtro por status
        $sEstado = NULL;
        if (isset($comando->status)) {
            $sEstado = (string)$comando->status;
            $listaEstadosConocidos = array(
                'active'    =>  'A',
                'inactive'  =>  'I',
                'finished'  =>  'T');
            if (!in_array($sEstado, array_keys($listaEstadosConocidos)))
                return $this->_generarRespuestaFallo(400, 'Bad request - invalid status');
            $sEstado = $listaEstadosConocidos[$sEstado];
        }

        // Fechas de inicio y fin
        $sFechaInicio = $sFechaFin = NULL;
        if (isset($comando->datetime_start)) {
            $sFechaInicio = (string)$comando->datetime_start;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sFechaInicio))
                return $this->_generarRespuestaFallo(400, 'Bad request - invalid start date');
        }
        if (isset($comando->datetime_end)) {
            $sFechaFin = (string)$comando->datetime_end;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sFechaFin))
                return $this->_generarRespuestaFallo(400, 'Bad request - invalid end date');
        }
        if (!is_null($sFechaInicio) && !is_null($sFechaFin) && $sFechaFin < $sFechaInicio) {
            $t = $sFechaInicio;
            $sFechaInicio = $sFechaFin;
            $sFechaFin = $t;
        }

        // Offset y límite
        $iOffset = NULL; $iLimite = NULL;
        if (isset($comando->limit)) {
            $iLimite = (int)$comando->limit;
            $iOffset = 0;
        }
        if (isset($comando->offset)) $iOffset = (int)$comando->offset;
        if (!is_null($iOffset) && is_null($iLimite))
            return $this->_generarRespuestaFallo(400, 'Bad request - offset without limit');

        $xml_response = new SimpleXMLElement('<response />');
        $xml_GetCampaignListResponse = $xml_response->addChild('getcampaignlist_response');

        $recordset = array();
        $listaSQL = array();
        $paramSQL = array();

        foreach ($listaTipos as $sTipo) {
            switch ($sTipo) {
            case 'incoming':
                $sPeticionSQL = "SELECT 'incoming' AS campaign_type, id, name, estatus AS status FROM campaign_entry";
                break;
            case 'outgoing':
                $sPeticionSQL = "SELECT 'outgoing' AS campaign_type, id, name, estatus AS status FROM campaign";
                break;
            }

            $listaWhere = array();
            if (!is_null($sNombreContiene)) {
                $listaWhere[] = 'name LIKE ?';
                $paramSQL[] = '%'.$sNombreContiene.'%';
            }
            if (!is_null($sEstado)) {
                $listaWhere[] = 'estatus = ?';
                $paramSQL[] = $sEstado;
            }
            if (!is_null($sFechaInicio)) {
                $listaWhere[] = 'datetime_init >= ?';
                $paramSQL[] = $sFechaInicio;
            }
            if (!is_null($sFechaFin)) {
                $listaWhere[] = 'datetime_init < ?';
                $paramSQL[] = $sFechaFin;
            }

            if (count($listaWhere) > 0) {
                $sPeticionSQL .= ' WHERE '.implode(' AND ', $listaWhere);
            }

            $listaSQL[] = $sPeticionSQL;
        }

        // Preparar UNION SQL
        if (count($listaSQL) > 0)
            $sPeticionSQL = '('.implode(') UNION (', $listaSQL).')';
        else $sPeticionSQL = $listaSQL[0];

        $sPeticionSQL .= ' ORDER BY campaign_type, id';
        if (!is_null($iLimite)) {
            $sPeticionSQL .= ' LIMIT ? OFFSET ?';
            $paramSQL[] = $iLimite;
            $paramSQL[] = $iOffset;
        }

        $recordset = $this->_db->prepare($sPeticionSQL);
        $recordset->execute($paramSQL);

        $descEstados = array(
            'A' =>  'active',
            'I' =>  'inactive',
            'T' =>  'finished',
        );

        $xml_campaigns = $xml_GetCampaignListResponse->addChild('campaigns');
        foreach ($recordset as $tupla) {
            $xml_campaign = $xml_campaigns->addChild('campaign');
            $xml_campaign->addChild('id', $tupla['id']);
            $xml_campaign->addChild('type', $tupla['campaign_type']);
            $xml_campaign->addChild('name', str_replace('&', '&amp;', $tupla['name']));
            $xml_campaign->addChild('status', $descEstados[$tupla['status']]);
        }

        return $xml_response;
    }

    private function Request_eccpauth_getincomingqueuelist($comando)
    {
        // Offset y límite
        $iOffset = NULL; $iLimite = NULL;
        if (isset($comando->limit)) {
            $iLimite = (int)$comando->limit;
            $iOffset = 0;
        }
        if (isset($comando->offset)) $iOffset = (int)$comando->offset;
        if (!is_null($iOffset) && is_null($iLimite))
            return $this->_generarRespuestaFallo(400, 'Bad request - offset without limit');

        $xml_response = new SimpleXMLElement('<response />');
        $xml_ListResponse = $xml_response->addChild('getincomingqueuelist_response');

        $sPeticionSQL = 'SELECT id, queue, estatus FROM queue_call_entry ORDER BY id';
        $paramSQL = array();
        if (!is_null($iLimite)) {
            $sPeticionSQL .= ' LIMIT ? OFFSET ?';
            $paramSQL[] = $iLimite;
            $paramSQL[] = $iOffset;
        }

        $recordset = $this->_db->prepare($sPeticionSQL);
        $recordset->execute($paramSQL);

        $descEstados = array(
            'A' =>  'active',
            'I' =>  'inactive',
            'T' =>  'finished',
        );

        $xml_queues = $xml_ListResponse->addChild('queues');
        foreach ($recordset as $tupla) {
            $xml_queue = $xml_queues->addChild('queue');
            $xml_queue->addChild('id', $tupla['id']);
            $xml_queue->addChild('queue', $tupla['queue']);
            $xml_queue->addChild('status', $descEstados[$tupla['estatus']]);
        }
        return $xml_response;
    }

    private function Request_eccpauth_getcampaignqueuewait($comando)
    {
        // Verificar que id y tipo está presente
        if (!isset($comando->campaign_id))
            return $this->_generarRespuestaFallo(400, 'Bad request');
        if (!isset($comando->campaign_type))
            return $this->_generarRespuestaFallo(400, 'Bad request');
        $idCampania = (int)$comando->campaign_id;
        $sTipoCampania = (string)$comando->campaign_type;

        // Elegir SQL a partir del tipo de campaña requerida
        if ($sTipoCampania == 'incoming') {
            $sqlLlamadasExito = 'SELECT COUNT(*) AS N, duration_wait FROM call_entry WHERE id_campaign = ? AND (status = "activa" OR status = "terminada") GROUP BY duration_wait';
            $sqlLlamadasAbandonadas = 'SELECT COUNT(*) AS N FROM call_entry WHERE id_campaign = ? AND status = "abandonada"';
        } elseif ($sTipoCampania == 'outgoing') {
            $sqlLlamadasExito = 'SELECT COUNT(*) AS N, duration_wait FROM calls WHERE id_campaign = ? AND status = "Success" GROUP BY duration_wait';
            $sqlLlamadasAbandonadas = 'SELECT COUNT(*) AS N FROM calls WHERE id_campaign = ? AND status = "Abandoned"';
        } else {
            return $this->_generarRespuestaFallo(400, 'Bad request');
        }

        $xml_response = new SimpleXMLElement('<response />');
        $xml_GetCampaignQueueWaitResponse = $xml_response->addChild('getcampaignqueuewait_response');

        $recordset = $this->_db->prepare($sqlLlamadasExito);
        $recordset->execute(array($idCampania));

        // División del histograma: tamaño de intervalos y límite máximo
        $iValorIntervalo = 5; $iMaxValor = 30;
        $histograma = array();
        for ($i = 0; $i <= $iMaxValor; $i += $iValorIntervalo) {
            $histograma[$i / $iValorIntervalo] = 0;
        }
        foreach ($recordset as $tupla) {
            $iPosHistograma = ($tupla['duration_wait'] >= $iMaxValor)
                ? count($histograma) - 1
                : (int)($tupla['duration_wait'] / $iValorIntervalo);
            $histograma[$iPosHistograma] += $tupla['N'];
        }

        $recordset = $this->_db->prepare($sqlLlamadasAbandonadas);
        $recordset->execute(array($idCampania));
        $tuplaAbandonadas = $recordset->fetch(); $recordset->closeCursor();

        // Construcción de la respuesta
        $xml_histograma = $xml_GetCampaignQueueWaitResponse->addChild('histogram');
        foreach ($histograma as $iPosHistograma => $iCuentaHistograma) {
            $iValorInferior = $iPosHistograma * $iValorIntervalo;
            $iValorSuperior = $iValorInferior + $iValorIntervalo - 1;
            $xml_intervalo = $xml_histograma->addChild('interval');
            $xml_intervalo->addChild('lower', $iValorInferior);
            if ($iPosHistograma != count($histograma) - 1)
                $xml_intervalo->addChild('upper', $iValorSuperior);
            $xml_intervalo->addChild('count', $iCuentaHistograma);
        }
        $xml_GetCampaignQueueWaitResponse->addChild('abandoned', $tuplaAbandonadas['N']);

        return $xml_response;
    }

    /**
     * Procedimiento que implementa la lectura de la información estática de
     * una campaña entrante o saliente. Por información estática se entiende la
     * información que no cambia a medida que se progresa con las llamadas
     * asociadas a la campaña.
     *
     * @param   object  $comando    Comando
     *      <getcampaigninfo>
     *          <campaign_type>outgoing|incoming</campaign_type> <!-- Opcional, por omisión es outgoing -->
     *          <campaign_id>123</campaign_id>
     *      </getcampaigninfo>
     *
     * @return  object  Respuesta codificada como un SimpleXMLObject
     *      <getcampaigninfo_response>
     *          <name>Nombre de la campaña</name>
     *          <type>incoming|outgoing</type>
     *          <startdate>yyyy-mm-dd</startdate>
     *          <enddate>yyyy-mm-dd</enddate>
     *          <working_time_starttime>hh:mm:ss</working_time_starttime>
     *          <working_time_endtime>hh:mm:ss</working_time_endtime>
     *          <queue>8000</queue>
     *          <retries>5</retries>                <!-- Sólo saliente -->
     *          <trunk>SIP/saliente</trunk>         <!-- Sólo saliente. Si no presente, se asume Local/xxx@from-internal -->
     *          <context>from-internal</context>    <!-- Sólo saliente -->
     *          <maxchan>32</maxchan>               <!-- Sólo saliente -->
     *          <status>active|inactive|complete</status>
     *          <script>Texto a usar como script de la campaña</script>
     *          <form id="2">...</form>
     *          <form id="3">...</form>
     *      </getcampaigninfo_response>
     */
    private function Request_eccpauth_getcampaigninfo($comando)
    {
        // Verificar que id y tipo está presente
        if (!isset($comando->campaign_id))
            return $this->_generarRespuestaFallo(400, 'Bad request');
        $idCampania = (int)$comando->campaign_id;
        $sTipoCampania = 'outgoing';
        if (isset($comando->campaign_type)) {
            $sTipoCampania = (string)$comando->campaign_type;
        }

        if (!in_array($sTipoCampania, array('incoming', 'outgoing')))
            return $this->_generarRespuestaFallo(400, 'Bad request');

        $xml_response = new SimpleXMLElement('<response />');
        $xml_GetCampaignInfoResponse = $xml_response->addChild('getcampaigninfo_response');

        switch ($sTipoCampania) {
        case 'outgoing':
            $sql_campania = <<<LEER_CAMPANIA
SELECT name, 'outgoing' AS type, datetime_init AS startdate, datetime_end AS enddate,
    daytime_init AS working_time_starttime, daytime_end AS working_time_endtime,
    queue, retries, trunk, context, max_canales AS maxchan, estatus AS status,
    script, urltemplate, opentype AS urlopentype
FROM campaign
LEFT JOIN campaign_external_url
    ON campaign.id_url = campaign_external_url.id AND campaign_external_url.active = 1
WHERE campaign.id = ?
LEER_CAMPANIA;
            $sql_forms = 'SELECT DISTINCT id_form FROM campaign_form WHERE id_campaign = ?';
            break;
        case 'incoming':
            $sql_campania = <<<LEER_CAMPANIA
SELECT name, 'incoming' AS type, datetime_init AS startdate, datetime_end AS enddate,
    daytime_init AS working_time_starttime, daytime_end AS working_time_endtime,
    queue, campaign_entry.estatus AS status, campaign_entry.script, id_form,
    urltemplate, opentype AS urlopentype
FROM (campaign_entry, queue_call_entry)
LEFT JOIN campaign_external_url
    ON campaign_entry.id_url = campaign_external_url.id AND campaign_external_url.active = 1
WHERE campaign_entry.id = ? AND campaign_entry.id_queue_call_entry = queue_call_entry.id
LEER_CAMPANIA;
            $sql_forms = 'SELECT DISTINCT id_form FROM campaign_form_entry WHERE id_campaign = ?';
            break;
        }

        // Leer la información de la campaña
        $recordset = $this->_db->prepare($sql_campania);
        $recordset->execute(array($idCampania));
        $tuplaCampania = $recordset->fetch(PDO::FETCH_ASSOC);
        $recordset->closeCursor();
        if (!$tuplaCampania) {
            $this->_agregarRespuestaFallo($xml_GetCampaignInfoResponse, 404, 'Campaign not found');
            return $xml_response;
        }

        // Leer la lista de formularios asociados a esta campaña
        $recordset = $this->_db->prepare($sql_forms);
        $recordset->execute(array($idCampania));
        $idxForm = $recordset->fetchAll(PDO::FETCH_COLUMN, 0);

        // Se agrega posible formulario asociado en tabla campaign_entry
        if (isset($tuplaCampania['id_form']) &&
            !is_null($tuplaCampania['id_form']) &&
            !in_array($tuplaCampania['id_form'], $idxForm))
            $idxForm[] = $tuplaCampania['id_form'];
        unset($tuplaCampania['id_form']);

        // Leer los campos asociados a cada formulario
        $listaForm = $this->_leerCamposFormulario($idxForm);
        if (is_null($listaForm)) {
            $this->_agregarRespuestaFallo($xml_GetCampaignInfoResponse, 500, 'Cannot read campaign info (formfields)');
            return $xml_response;
        }
        $listaNombresForm = $this->_leerInfoFormulario($idxForm);
        if (is_null($listaNombresForm)) {
            $this->_agregarRespuestaFallo($xml_GetCampaignInfoResponse, 500, 'Cannot read campaign info (formnames)');
            return $xml_response;
        }

        // Construir la respuesta con la información del campo
        $descEstados = array(
            'A' =>  'active',
            'I' =>  'inactive',
            'T' =>  'finished',
        );
        foreach ($tuplaCampania as $sKey => $sValor) {
            switch ($sKey) {
            case 'script':
                /* El control de edición en la creación/modificación del script
                 * manda a guardar texto con entidades de HTML a la base de
                 * datos. Para compatibilidad con campañas antiguas, se deshace
                 * la codificación de HTML aquí. */
                $sValor = html_entity_decode($sValor, ENT_COMPAT, 'UTF-8');
                $xml_GetCampaignInfoResponse->addChild($sKey, str_replace('&', '&amp;', $sValor));
                break;
            case 'status':
                $sValor = $descEstados[$sValor];
                $xml_GetCampaignInfoResponse->addChild($sKey, str_replace('&', '&amp;', $sValor));
                break;
            case 'trunk':   // Sólo para campañas salientes
                // Pasar al caso default si el valor no es nulo
                if (is_null($sValor)) break;
            default:
                $xml_GetCampaignInfoResponse->addChild($sKey, str_replace('&', '&amp;', $sValor));
                break;
            }
        }

        // Construir la información de los formularios
        $xml_Forms = $xml_GetCampaignInfoResponse->addChild('forms');
        foreach ($listaForm as $idForm => $listaCampos) {
            $this->_agregarCamposFormulario($xml_Forms, $idForm, $listaCampos, $listaNombresForm[$idForm]);
        }

        return $xml_response;
    }

    private function _leerInfoFormulario($idxForm)
    {
        $listaForm = array();
        foreach ($idxForm as $idForm) {
            $recordset = $this->_db->prepare(
                'SELECT id, nombre, descripcion, estatus FROM form WHERE id = ?');
            $recordset->execute(array($idForm));
            $r = $recordset->fetch(); $recordset->closeCursor();
            if ($r) {
                $listaForm[$idForm] = array(
                    'name'          =>  $r['nombre'],
                    'description'   =>  $r['descripcion'],
                    'status'        =>  $r['estatus'],
                );
            }
        }
        return $listaForm;
    }

    private function _leerCamposFormulario($idxForm)
    {
        $listaForm = array();
        foreach ($idxForm as $idForm) {
            $recordset = $this->_db->prepare(
                'SELECT id, etiqueta AS label, value, tipo AS type, orden AS `order` '.
                'FROM form_field WHERE id_form = ? ORDER BY `order`');
            $recordset->execute(array($idForm));
            $r = $recordset->fetchAll(PDO::FETCH_ASSOC);
            $recordset->closeCursor();
            if (count($r) > 0) {
                $listaForm[$idForm] = array();
                foreach ($r as $tuplaCampo)
                    $listaForm[$idForm][$tuplaCampo['id']] = $tuplaCampo;
            }
        }
        return $listaForm;
    }

    private function _agregarCamposFormulario(&$xml_GetCampaignInfoResponse, $idForm, &$listaCampos, &$nombresForm)
    {
        $xml_Form = $xml_GetCampaignInfoResponse->addChild('form');
        $xml_Form->addAttribute('id', $idForm);
        // Rodeo para bug PHP https://bugs.php.net/bug.php?id=41175
        if ($nombresForm['name'] != '')
            $xml_Form->addAttribute('name', $nombresForm['name']);
        if ($nombresForm['description'] != '')
            $xml_Form->addAttribute('description', $nombresForm['description']);
        $xml_Form->addAttribute('status', $nombresForm['status']);
        foreach ($listaCampos as $tuplaCampo) {
            $xml_Field = $xml_Form->addChild('field');
            $xml_Field->addAttribute('order', $tuplaCampo['order']);
            $xml_Field->addAttribute('id', $tuplaCampo['id']);
            $xml_Field->addChild('label', str_replace('&', '&amp;', $tuplaCampo['label']));
            $xml_Field->addChild('type', str_replace('&', '&amp;', $tuplaCampo['type']));

            // TODO: permitir especificar longitud de la entrada
            if (!in_array($tuplaCampo['type'], array('LABEL', 'DATE')))
                $xml_Field->addChild('maxsize', 250);

            if ($tuplaCampo['type'] == 'LIST') {
                // OJO: PRIMERA FORMA ANORMAL!!!
                // La implementación actual del código de formulario
                // agrega una coma de más al final de la lista
                if (strlen($tuplaCampo['value']) > 0 &&
                    substr($tuplaCampo['value'], strlen($tuplaCampo['value']) - 1, 1) == ',') {
                    $tuplaCampo['value'] = substr($tuplaCampo['value'], 0, strlen($tuplaCampo['value']) - 1);
                }
                $xml_Values = $xml_Field->addChild('options');
                foreach (explode(',', $tuplaCampo['value']) as $sValor) {
                    $xml_Values->addChild('value', str_replace('&', '&amp;', $sValor));
                }
            } else {
                // Usar el valor 'value' como valor por omisión.
                // TODO: (2011-02-02) soporte de formulario para valor por
                // omisión todavía no está implementado en agent_console o en
                // definición de formulario en interfaz web
                $sDefVal = trim($tuplaCampo['value']);
                if ($sDefVal != '')
                    $xml_Field->addChild('default_value', str_replace('&', '&amp;', $sDefVal));
            }
        }
    }

    private function Request_eccpauth_getcallinfo($comando)
    {
        // Si no hay un tipo de campaña, se asume saliente
        $sTipoCampania = 'outgoing';
        if (isset($comando->campaign_type)) {
            $sTipoCampania = (string)$comando->campaign_type;
        }
        if (!in_array($sTipoCampania, array('incoming', 'outgoing')))
            return $this->_generarRespuestaFallo(400, 'Bad request');

        // El ID de campaña es opcional para campañas entrantes
        if (!isset($comando->campaign_id) && $sTipoCampania != 'incoming')
            return $this->_generarRespuestaFallo(400, 'Bad request');
        $idCampania = isset($comando->campaign_id) ? (int)$comando->campaign_id : NULL;

        // Verificar que id de llamada está presente
        if (!isset($comando->call_id))
            return $this->_generarRespuestaFallo(400, 'Bad request');
        $idLlamada = (int)$comando->call_id;

        // Ejecutar la llamada y verificar la respuesta...
        $infoLlamada = leerInfoLlamada($this->_db, $sTipoCampania, $idCampania, $idLlamada);

        $xml_response = new SimpleXMLElement('<response />');
        $xml_GetCallInfoResponse = $xml_response->addChild('getcallinfo_response');
        if (is_null($infoLlamada)) {
            $this->_agregarRespuestaFallo($xml_GetCallInfoResponse, 500, 'Cannot read call info');
            return $xml_response;
        }
        if (count($infoLlamada) <= 0) {
            $this->_agregarRespuestaFallo($xml_GetCallInfoResponse, 404, 'Call not found');
            return $xml_response;
        }

        // Armar la respuesta XML
        self::construirRespuestaCallInfo($infoLlamada, $xml_GetCallInfoResponse);
        return $xml_response;
    }

    // Compartido entre getcallinfo y evento agentlinked
    static function construirRespuestaCallInfo($infoLlamada, $xml_GetCallInfoResponse)
    {
        foreach ($infoLlamada as $sKey => $valor) {
            switch ($sKey) {
            case 'call_attributes':
                $xml_callAttrlist = $xml_GetCallInfoResponse->addChild($sKey);
                foreach ($valor as $tuplaAttr) {
                    $xml_callAttr = $xml_callAttrlist->addChild('attribute');
                    $xml_callAttr->addChild('label', str_replace('&', '&amp;', $tuplaAttr['label']));
                    $xml_callAttr->addChild('value', str_replace('&', '&amp;', $tuplaAttr['value']));
                    $xml_callAttr->addChild('order', str_replace('&', '&amp;', $tuplaAttr['order']));
                }
                break;
            case 'matching_contacts':
                $xml_contacts = $xml_GetCallInfoResponse->addChild($sKey);
                foreach ($valor as $id_contact => $tuplaContact) {
                    $xml_callAttrlist = $xml_contacts->addChild('contact');
                    $xml_callAttrlist->addAttribute('id', $id_contact);
                    foreach ($tuplaContact as $tuplaAttr) {
                        $xml_callAttr = $xml_callAttrlist->addChild('attribute');
                        $xml_callAttr->addChild('label', str_replace('&', '&amp;', $tuplaAttr['label']));
                        $xml_callAttr->addChild('value', str_replace('&', '&amp;', $tuplaAttr['value']));
                        $xml_callAttr->addChild('order', str_replace('&', '&amp;', $tuplaAttr['order']));
                    }
                }
                break;
            case 'call_survey':
                $xml_callFormlist = $xml_GetCallInfoResponse->addChild($sKey);
                foreach ($valor as $id_form => $valoresForm) {
                    $xml_callForm = $xml_callFormlist->addChild('form');
                    $xml_callForm->addAttribute('id', $id_form);
                    foreach ($valoresForm as $tuplaValor) {
                        $xml_callFormField = $xml_callForm->addChild('field');
                        $xml_callFormField->addAttribute('id', $tuplaValor['id']);
                        $xml_callFormField->addChild('label', str_replace('&', '&amp;', $tuplaValor['label']));
                        $xml_callFormField->addChild('value', str_replace('&', '&amp;', $tuplaValor['value']));
                    }
                }
                break;
            default:
                if (!is_null($valor)) $xml_GetCallInfoResponse->addChild($sKey, str_replace('&', '&amp;', $valor));
                break;
            }
        }
    }

    private function _leerAgenteLlamada($sTipoCampania, $idLlamada)
    {
        switch ($sTipoCampania) {
        case 'incoming':
            $sPeticionSQL =
                'SELECT CONCAT(agent.type,"/",agent.number) AS agentchannel FROM call_entry, agent '.
                'WHERE call_entry.id_agent = agent.id AND call_entry.id = ?';
            break;
        case 'outgoing':
            $sPeticionSQL =
                'SELECT CONCAT(agent.type,"/",agent.number) AS agentchannel FROM calls, agent '.
                'WHERE calls.id_agent = agent.id AND calls.id = ?';
            break;
        default:
            return NULL;
        }
        $recordset = $this->_db->prepare($sPeticionSQL);
        $recordset->execute(array($idLlamada));
        $tupla = $recordset->fetch(PDO::FETCH_ASSOC);
        return $tupla ? $tupla['agentchannel'] : NULL;
    }

    private function Request_agentauth_setcontact($comando)
    {
        // Verificar que id de llamada está presente
        if (!isset($comando->call_id))
            return $this->_generarRespuestaFallo(400, 'Bad request');
        $idLlamada = (int)$comando->call_id;

        // Verificar que id de contacto está presente
        if (!isset($comando->contact_id))
            return $this->_generarRespuestaFallo(400, 'Bad request');
        $idContacto = (int)$comando->contact_id;

        $xml_response = new SimpleXMLElement('<response />');
        $xml_setContactResponse = $xml_response->addChild('setcontact_response');

        $bExito = TRUE;

        // Verificar que existe realmente la llamada entrante
        if ($bExito) {
            $recordset = $this->_db->prepare(
                'SELECT COUNT(*) AS N FROM call_entry WHERE id = ?');
            $recordset->execute(array($idLlamada));
            $tupla = $recordset->fetch(PDO::FETCH_ASSOC); $recordset->closeCursor();
            if ($tupla['N'] < 1) {
                $this->_agregarRespuestaFallo($xml_setContactResponse, 404, 'Call ID not found');
                $bExito = FALSE;
            }
        }

        // Verificar que el agente declarado realmente atendió esta llamada
        if ($bExito) {
            $sAgenteLlamada = $this->_leerAgenteLlamada('incoming', $idLlamada);
            if (is_null($sAgenteLlamada) || $sAgenteLlamada != (string)$comando->agent_number) {
                $this->_agregarRespuestaFallo($xml_setContactResponse, 401, 'Unauthorized agent');
                $bExito = FALSE;
            }
        }

        // Verificar que existe realmente el contacto indicado
        if ($bExito) {
            $recordset = $this->_db->prepare(
                'SELECT COUNT(*) AS N FROM contact WHERE id = ?');
            $recordset->execute(array($idContacto));
            $tupla = $recordset->fetch(PDO::FETCH_ASSOC); $recordset->closeCursor();
            if ($tupla['N'] < 1) {
                $this->_agregarRespuestaFallo($xml_setContactResponse, 404, 'Contact ID not found');
                $bExito = FALSE;
            }
        }

        if ($bExito) {
            $sth = $this->_db->prepare('UPDATE call_entry SET id_contact = ? WHERE id = ?');
            $sth->execute(array($idContacto, $idLlamada));
        }

        if ($bExito) {
            $xml_setContactResponse->addChild('success');
        }

        return $xml_response;
    }

    /*
    private function Request_eccpauth_dial($comando)
    {
        return $this->_generarRespuestaFallo(501, 'Not Implemented');
    }
    */

    private function Request_agentauth_saveformdata($comando)
    {
        // Si no hay un tipo de campaña, se asume saliente
        $sTipoCampania = 'outgoing';
        if (isset($comando->campaign_type)) {
            $sTipoCampania = (string)$comando->campaign_type;
        }
        if (!in_array($sTipoCampania, array('incoming', 'outgoing')))
            return $this->_generarRespuestaFallo(400, 'Bad request');

        // Verificar que id de llamada está presente
        if (!isset($comando->call_id))
            return $this->_generarRespuestaFallo(400, 'Bad request');
        $idLlamada = (int)$comando->call_id;

        // Verificar que elemento forms está presente
        if (!isset($comando->forms))
            return $this->_generarRespuestaFallo(400, 'Bad request');
        $infoDatos = array();
        foreach ($comando->forms->form as $xml_form) {
            $idForm = (int)$xml_form['id'];

            // No se permiten IDs duplicados de formulario
            if (isset($infoDatos[$idForm]))
                return $this->_generarRespuestaFallo(400, 'Bad request');

            $infoDatos[$idForm] = array();
            foreach ($xml_form->field as $xml_field) {
                $idField = (int)$xml_field['id'];
                $infoDatos[$idForm][$idField] = (string)$xml_field;
            }
        }

        $xml_response = new SimpleXMLElement('<response />');
        $xml_saveFormDataResponse = $xml_response->addChild('saveformdata_response');

        // Verificar que el agente declarado realmente atendió esta llamada
        $sAgenteLlamada = $this->_leerAgenteLlamada($sTipoCampania, $idLlamada);
        if (is_null($sAgenteLlamada) || $sAgenteLlamada != (string)$comando->agent_number) {
            $this->_agregarRespuestaFallo($xml_saveFormDataResponse, 401, 'Unauthorized agent');
            return $xml_response;
        }

        // Leer la información del formulario, para validación
        $infoFormulario = $this->_leerCamposFormulario(array_keys($infoDatos));
        if (is_null($infoFormulario)) {
            $this->_agregarRespuestaFallo($xml_saveFormDataResponse, 500, 'Cannot read form information');
        } else {
            $listaSQL = array();
            list($fdr_tabla, $fdr_campo) = nombresCamposFormulariosEstaticos($sTipoCampania);
            $recordset = $this->_db->prepare("SELECT COUNT(*) FROM $fdr_tabla WHERE $fdr_campo = ? AND id_form_field = ?");
            $sth_insert = $this->_db->prepare("INSERT INTO $fdr_tabla (value, $fdr_campo, id_form_field) VALUES (?, ?, ?)");
            $sth_update = $this->_db->prepare("UPDATE $fdr_tabla SET value = ? WHERE $fdr_campo = ? AND id_form_field = ?");


            /* Validación básica de los valores a guardar, combinada con
             * generación de las sentencias SQL para almacenar */
            $bDatosValidos = TRUE;
            foreach ($infoDatos as $idForm => $infoDatosForm) {
                foreach ($infoDatosForm as $idField => $sValor) {
                    if (!isset($infoFormulario[$idForm])) {
                        $bDatosValidos = FALSE;
                        $this->_agregarRespuestaFallo($xml_saveFormDataResponse, 404, 'Form ID not found: '.$idForm);
                    } elseif (!isset($infoFormulario[$idForm][$idField])) {
                        $bDatosValidos = FALSE;
                        $this->_agregarRespuestaFallo($xml_saveFormDataResponse, 404, 'Field ID not found in form: '.$idForm.' - '.$idField);
                    }
                    if (!$bDatosValidos) break;

                    $infoCampo = $infoFormulario[$idForm][$idField];
                    if ($infoCampo['type'] == 'LABEL') continue;

                    // TODO: extraer máxima longitud de base de datos
                    if (strlen($sValor) > 250) {
                        $bDatosValidos = FALSE;
                        $this->_agregarRespuestaFallo($xml_saveFormDataResponse, 413, 'Form value too large: '.$idForm.' - '.$idField);

                    // Validar que el campo de fecha tenga valor correcto
                    } elseif ($infoCampo['type'] == 'DATE' &&
                        $sValor != '' && !(preg_match('/^\d{4}-\d{2}-\d{2}$/', $sValor) || preg_match('/^\d{4}-\d{2}-\d{2} d{2}:\d{2}:\d{2}$/', $sValor))) {
                        $bDatosValidos = FALSE;
                        $this->_agregarRespuestaFallo($xml_saveFormDataResponse, 406,
                            'Date format not acceptable, must be yyyy-mm-dd or yyyy-mm-dd hh:mm:ss: '.$idForm.' - '.$idField);
                    } else {
                        if ($infoCampo['type'] == 'LIST') {
                            // OJO: PRIMERA FORMA ANORMAL!!!
                            // La implementación actual del código de formulario
                            // agrega una coma de más al final de la lista
                            if (strlen($infoCampo['value']) > 0 &&
                                substr($infoCampo['value'], strlen($infoCampo['value']) - 1, 1) == ',') {
                                $infoCampo['value'] = substr($infoCampo['value'], 0, strlen($infoCampo['value']) - 1);
                            }
                            if (!in_array($sValor, explode(',', $infoCampo['value']))) {
                                $bDatosValidos = FALSE;
                                $this->_agregarRespuestaFallo($xml_saveFormDataResponse, 406,
                                    'Value not in list of accepted values: '.$idForm.' - '.$idField);
                            }
                        }
                    }
                    if (!$bDatosValidos) break;

                    // En este punto este valor es válido y se puede generar SQL
                    if (!$recordset->execute(array($idLlamada, $idField))) {
                        $bDatosValidos = FALSE;
                        $this->_agregarRespuestaFallo($xml_saveFormDataResponse, 500,
                            'Unable to check previous form value');
                    } else {
                    	$tupla = $recordset->fetch(PDO::FETCH_NUM); $recordset->closeCursor();
                        if ($tupla[0] <= 0) {
                        	$listaSQL[] = array($sth_insert, array($sValor, $idLlamada, $idField));
                        } else {
                        	$listaSQL[] = array($sth_update, array($sValor, $idLlamada, $idField));
                        }
                    }
                }
                if (!$bDatosValidos) break;
            }

            // Se procede a guardar los datos del formulario
            if ($bDatosValidos) {
                foreach ($listaSQL as $infoSQL) {
                    $infoSQL[0]->execute($infoSQL[1]);
                    $infoSQL[0]->closeCursor();
                }
            }

            if ($bDatosValidos) {
                $xml_saveFormDataResponse->addChild('success');
            }
        }

        return $xml_response;
    }

    private function Request_eccpauth_getpauses($comando)
    {
        $xml_response = new SimpleXMLElement('<response />');
        $xml_getPausesResponse = $xml_response->addChild('getpauses_response');

        $recordset = $this->_db->query(
            "SELECT id, name, status, tipo, description FROM break WHERE tipo = 'B' ORDER BY id");
        foreach ($recordset as $tupla) {
            $xml_pause = $xml_getPausesResponse->addChild('pause');
            $xml_pause->addAttribute('id', $tupla['id']);
            $xml_pause->addChild('name', str_replace('&', '&amp;', $tupla['name']));
            $xml_pause->addChild('status', str_replace('&', '&amp;', $tupla['status']));
            $xml_pause->addChild('type', str_replace('&', '&amp;', $tupla['tipo']));
            $xml_pause->addChild('description', str_replace('&', '&amp;', $tupla['description']));
        }

        return $xml_response;
    }

    /**
     * Procedimiento que implementa el login de un agente estático al estilo
     * Agent/9000. Para esta versión se asume que el agente está asociado a una
     * extensión telefónica, a la cual se mandará una llamada que conecta tal
     * extensión con la cola. El comando regresa inmediatamente. Luego el cliente
     * debe de esperar el evento LoginAgent que indica que se ha completado
     * exitosamente el login del agente, y que empezará a recibir llamadas de la
     * campaña asociada a las colas del agente.
     *
     * Implementación: las tareas a hacer para iniciar el login del agente son:
     * 1) Verificar si el agente existe en el sistema. Si no existe, se devuelve
     *    error sin hacer otra operación.
     * 2) Verificar si la extensión indicada es válida. Si no existe, se devuelve
     *    error sin hacer otra operación.
     * 3) Verificar si el agente ya está logoneado. Si ya está logoneado, entonces
     *    se debe verificar si está logoneado en la extensión indicada en el
     *    parámetro. Si es la misma extensión se devuelve éxito sin hacer nada
     *    más. Si no es la misma extensión, se devuelve error informando la
     *    situación.
     * 4) Para agente no logoneado, se inicia un Originate entre la extensión
     *    y el canal de Agent/XXXX. Como Action-Id, se indica la cadena
     *    "ECCP:1.0:<PID>:AgentLogin:<canaldeagente>"
     *    para distinguir este login de los logines a colas por otros motivos.
     * Para el resto del procesamiento se debe ver el método OnAgentlogin
     * en la clase DialerProcess.
     *
     * @param   object   $comando    Comando de login
     *      <loginagent>
     *          <agent_number>Agent/9000</agent_number>
     *          <password>xxx</password> <!-- se ignora en implementación actual -->
     *          <extension>1064</extension>
     *      </loginagent>
     *
     * @return  object  Respuesta codificada como un SimpleXMLObject
     *      <loginagent_response>
     *          <status>logged-out|logging|logged-in</status>
     *          <failure>mensaje</failure>
     *      </loginagent_response>
     */
    private function Request_eccpauth_loginagent($comando)
    {
        if (is_null($this->_ami))
            return $this->_generarRespuestaFallo(500, 'No AMI connection');

        // Verificar que agente y extensión están presentes
        if (!isset($comando->agent_number) || !isset($comando->extension))
            return $this->_generarRespuestaFallo(400, 'Bad request');
        $sAgente = (string)$comando->agent_number;
        $sExtension = (string)$comando->extension;
        $iTimeout = NULL;
        if (isset($comando->timeout)) {
            if (!preg_match('/^\d+/', (string)$comando->timeout))
                return $this->_generarRespuestaFallo(400, 'Bad request');
            $iTimeout = (int)$comando->timeout;
            if ($iTimeout <= 0)
                return $this->_generarRespuestaFallo(400, 'Bad request');
        }

        // Verificar que la extensión y el agente son válidos en el sistema
        $listaExtensiones = $this->_listarExtensiones();
        if (!is_array($listaExtensiones)) {
            return $this->Response_LoginAgentResponse('logged-out', 500, 'Failed to list extensions');
        }
        if (!$this->_existeAgente($sAgente)) {
            return $this->Response_LoginAgentResponse('logged-out', 404, 'Specified agent not found');
        } elseif (!in_array($sExtension, array_keys($listaExtensiones))) {
            return $this->Response_LoginAgentResponse('logged-out', 404, 'Specified extension not found');
        }

        // Verificar el hash del agente
        if (!$this->_hashValidoAgenteECCP($comando, $comando['appcookie'])) {
            return $this->Response_LoginAgentResponse('logged-out', 401, 'Unauthorized agent');
        }

        $infoSeguimiento = $this->_tuberia->AMIEventProcess_infoSeguimientoAgente($sAgente);
        if (is_null($infoSeguimiento)) {
        	return $this->Response_LoginAgentResponse('logged-out', 404, 'Specified agent not found');
        }
        if (!is_null($infoSeguimiento['extension'])) {
            /* No se puede aceptar que el agente esté ya logoneado, incluso
             * con la extensión que se ha pedido, porque no se tiene la
             * información de estado del agente (Uniqueid, id_sesion, etc)
             * hasta que se implemente la recolección de tales variables
             * a partir de Asterisk y la base de datos call_center. La
             * excepción es si el programa ya hace seguimiento del agente
             * indicado. */
        	if ($infoSeguimiento['extension'] == $listaExtensiones[$sExtension]) {
                // Ya se ha iniciado el login del agente
                $sEstadoReportar = $infoSeguimiento['estado_consola'];
                if ($sEstadoReportar == 'logged-out') $sEstadoReportar = 'logging';
                return $this->Response_LoginAgentResponse($infoSeguimiento['estado_consola']);
        	} else {
                // Otra extensión ya ocupa el login del agente indicado
                return $this->Response_LoginAgentResponse('logged-out', 409,
                    'Specified agent already connected to extension: '.$infoSeguimiento['extension']);
        	}
        } else {
            // No hay canal de login. Se inicia login a través de Originate
            $r = $this->_loginAgente($listaExtensiones[$sExtension], $sAgente, $infoSeguimiento['name'], $iTimeout);
            return $r
                ? $this->Response_LoginAgentResponse('logging')
                : $this->Response_LoginAgentResponse('logged-out', 500,
                    'Failed to start login process on Asterisk');
        }
    }

    // Función que encapsula la generación de la respuesta
    private function Response_LoginAgentResponse($status, $iCodigo = NULL, $msg = NULL)
    {
        $xml_response = new SimpleXMLElement('<response />');
        $xml_loginAgentResponse = $xml_response->addChild('loginagent_response');

        $xml_loginAgentResponse->addChild('status', $status);
        if (!is_null($msg))
            $this->_agregarRespuestaFallo($xml_loginAgentResponse, $iCodigo, $msg);

        return $xml_response;
    }

    // TODO: encontrar manera elegante de tener una sola definición
    private function _abrirConexionFreePBX()
    {
        $sNombreConfig = '/etc/amportal.conf';  // TODO: vale la pena poner esto en config?

        // De algunas pruebas se desprende que parse_ini_file no puede parsear
        // /etc/amportal.conf, de forma que se debe abrir directamente.
        $dbParams = array();
        $hConfig = fopen($sNombreConfig, 'r');
        if (!$hConfig) {
            $this->_log->output('ERR: no se puede abrir archivo '.$sNombreConfig.' para lectura de parámetros FreePBX.');
            return NULL;
        }
        while (!feof($hConfig)) {
            $sLinea = fgets($hConfig);
            if ($sLinea === FALSE) break;
            $sLinea = trim($sLinea);
            if ($sLinea == '') continue;
            if ($sLinea{0} == '#') continue;

            $regs = NULL;
            if (preg_match('/^([[:alpha:]]+)[[:space:]]*=[[:space:]]*(.*)$/', $sLinea, $regs)) switch ($regs[1]) {
            case 'AMPDBHOST':
            case 'AMPDBUSER':
            case 'AMPDBENGINE':
            case 'AMPDBPASS':
                $dbParams[$regs[1]] = $regs[2];
                break;
            }
        }
        fclose($hConfig); unset($hConfig);

        // Abrir la conexión a la base de datos, si se tienen todos los parámetros
        if (count($dbParams) < 4) {
            $this->_log->output('ERR: archivo '.$sNombreConfig.
                ' de parámetros FreePBX no tiene todos los parámetros requeridos para conexión.');
            return NULL;
        }
        if ($dbParams['AMPDBENGINE'] != 'mysql' && $dbParams['AMPDBENGINE'] != 'mysqli') {
            $this->_log->output('ERR: archivo '.$sNombreConfig.
                ' de parámetros FreePBX especifica AMPDBENGINE='.$dbParams['AMPDBENGINE'].
                ' que no ha sido probado.');
            return NULL;
        }
        try {
            $dbConn = new PDO("mysql:host={$dbParams['AMPDBHOST']};dbname=asterisk",
                $dbParams['AMPDBUSER'], $dbParams['AMPDBPASS']);
            $dbConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $dbConn->setAttribute(PDO::ATTR_EMULATE_PREPARES, FALSE);
            return $dbConn;
        } catch (PDOException $e) {
            $this->_log->output("ERR: no se puede conectar a DB de FreePBX - ".
                $e->getMessage());
        	return NULL;
        }
    }

    /**
     * Método que lista todas las extensiones SIP e IAX que están definidas en
     * el sistema. Estas extensiones pueden ser usadas por el agente para
     * logonearse en el sistema. La lista se devuelve de la forma
     * (1000 => 'SIP/1000'), ...
     *
     * @return  mixed   La lista de extensiones.
     */
    private function _listarExtensiones()
    {
        $oDB = $this->_abrirConexionFreePBX();
        if (is_null($oDB)) return NULL;
        try {
            $sPeticion = 'SELECT user AS extension, dial from devices ORDER BY user';
            $recordset = $oDB->query($sPeticion);
            $listaExtensiones = array();
            foreach ($recordset as $tupla) {
                $listaExtensiones[$tupla['extension']] = $tupla['dial'];
            }
        } catch (PDOException $e) {
        	$this->_log->output('ERR: (internal) Cannot list extensions - '.$e->getMessage());
        }
        $oDB = NULL;
        return $listaExtensiones;
    }

    /**
     * Método que lista todos los agentes registrados en la base de datos. La
     * lista se devuelve de la forma (9000 => 'Over 9000!!!'), ...
     *
     * @return  mixed   La lista de agentes activos
     */
    private function _listarAgentes()
    {
        $sPeticion = "SELECT type, number, name FROM agent WHERE estatus = 'A'";
        foreach ($this->_db->query($sPeticion) as $tupla) {
            $listaAgentes[$tupla['type'].'/'.$tupla['number']] = $tupla['number'].' - '.$tupla['name'];
        }
        return $listaAgentes;
    }

    private function _existeAgente($sAgente)
    {
        $agentFields = $this->_parseAgent($sAgente);
        if (is_null($agentFields)) return FALSE;
        $recordset = $this->_db->prepare('SELECT COUNT(*) AS n FROM agent WHERE estatus = ? AND type = ? AND number = ?');
        $recordset->execute(array('A', $agentFields['type'], $agentFields['number']));
        $tupla = $recordset->fetch(PDO::FETCH_ASSOC);
        $recordset->closeCursor();
        return ($tupla['n'] > 0);
    }

    /**
     * Método para iniciar el login del agente con la extensión y el número de
     * agente que se indican. Se asume que el agente es válido en el sistema.
     *
     * @param   string  Extensión que está usando el agente, como "SIP/1064"
     * @param   string  Cadena del agente que se está logoneando: "Agent/9000"
     * @param   string  Nombre del agente
     * @param   int     NULL si no aplica timeout, o máxima inactividad en segundos
     *
     * @return  VERDADERO en éxito, FALSE en error
     */
    private function _loginAgente($sExtension, $sAgente, $name, $iTimeout)
    {
        $r = NULL;
        $agentFields = $this->_parseAgent($sAgente);
        if ($agentFields['type'] == 'Agent') {
            $this->_tuberia->AMIEventProcess_agregarIntentoLoginAgente($sAgente, $sExtension, $iTimeout);
            $r = $this->_ami->Originate(
                $sExtension,        // channel
                NULL, NULL, NULL,   // extension, context, priority
                'AgentLogin',       // application
                $agentFields['number'],        // data
                NULL,
                $sAgente.' Login', // CallerID
                NULL, NULL,
                TRUE,               // async
                'ECCP:1.0:'.posix_getpid().':AgentLogin:'.$sAgente     // action-id
                );
            if ($r['Response'] != 'Success') {
                $this->_tuberia->AMIEventProcess_cancelarIntentoLoginAgente($sAgente);
                return FALSE;
            }
        } else {
            /*
             * Las colas dinámicas a las que debe pertenecer el agente las sabe
             * AMIEventProcess. Si pertenece a al menos una, se quita al agente
             * de todas las colas actuales, y a continuación se lo ingresa a
             * todas las colas dinámicas reportadas por AMIEventProcess.
             */
            $listaColas = $this->_tuberia->AMIEventProcess_listarTotalColasTrabajoAgente(array($sAgente));
            if (count($listaColas[$sAgente][1]) <= 0) {
                // Este agente no tiene colas asociadas
                $this->_log->output('WARN: agente dinámico '.$sAgente.' no es miembro dinámico de ninguna cola, no se puede realizar login.');
                $r = array(
                    'Response'  =>  'Error',
                    'Message'   =>  'Extension not a dynamic member of any queue.',
                );
            } else {
                $this->_tuberia->AMIEventProcess_agregarIntentoLoginAgente($sAgente, $sExtension, $iTimeout);

                $bIngresoCola = FALSE;
                if (count($listaColas[$sAgente][0]) > 0) {
                    $this->_log->output('WARN: '.__METHOD__.': agente '.$sAgente.
                        ' que intenta logonearse ya está en colas: ['.
                        implode(' ', $listaColas[$sAgente][0]).']');
                }
                foreach ($listaColas[$sAgente][0] as $cola) {
                    // Lo saco de todas las colas ...
                    $r = $this->_ami->QueueRemove($cola, $sAgente);
                    if ($r['Response'] != 'Success') {
                        $this->_log->output('WARN: '.__METHOD__.': falla al quitar agente '.
                            $sAgente.' de cola '.$cola.': '.print_r($r, TRUE));
                    }
                }
                foreach ($listaColas[$sAgente][2] as $cola => $penalty) {
                    // Para volverlos a agregar aqui.
                    $r = $this->_ami->QueueAdd($cola, $sAgente, $penalty, $name);
                    if ($r['Response'] != 'Success') {
                        $this->_log->output('WARN: '.__METHOD__.': falla al ingresar agente '.
                            $sAgente.' a cola '.$cola.': '.print_r($r, TRUE));
                    } else $bIngresoCola = TRUE;
                }
                if (!$bIngresoCola) {
                    $this->_tuberia->AMIEventProcess_cancelarIntentoLoginAgente($sAgente);
                    return FALSE;
                }
            }
        }
        return TRUE;
    }

    /**
     * Procedimiento que implementa el logoff de un agente estático al estilo
     * Agent/9000.
     *
     * Implementación: las tareas a hacer para iniciar el login del agente son:
     * 1) Verificar si el agente existe en el sistema. Si no existe, se devuelve
     *    error sin hacer otra operación.
     * 2) El logoff sólo está implementado para agentes de tipo Agent/9000. Si
     *    se especifica otro tipo de agente, se rechaza con error de no
     *    implementado. De otro modo, se recoge el número de agente (9000)
     * 3) Se ejecuta el comando de AMI Agentlogoff() con el número de agente
     * Para el resto del procesamiento se debe ver el método OnAgentlogoff en
     * la clase DialerProcess.
     *
     * @param   object   $comando    Comando de logout
     *      <logoutagent>
     *          <agent_number>Agent/9000</agent_number>
     *      </logoutagent>
     *
     * @return  object  Respuesta codificada como un SimpleXMLObject
     *      <logoutagent_response>
     *          <status>logged-out</status>
     *          <failure>mensaje</failure>
     *      </logoutagent_response>
     */
    private function Request_eccpauth_logoutagent($comando)
    {
        if (is_null($this->_ami))
            return $this->_generarRespuestaFallo(500, 'No AMI connection');

        // Verificar que agente está presentes
        if (!isset($comando->agent_number))
            return $this->_generarRespuestaFallo(400, 'Bad request');
        $sAgente = (string)$comando->agent_number;

        /* Verificar que el agente sea válido en el sistema. Se duplica la
         * verificación de la decoración agentauth porque se debe de agregar
         * el estatus de agente 'logged-out'.
         */
        if (!$this->_existeAgente($sAgente)) {
            return $this->Response_LogoutAgentResponse('logged-out', 404, 'Specified agent not found');
        }

        // Verificar el hash del agente
        if (!$this->_hashValidoAgenteECCP($comando, $comando['appcookie'])) {
            return $this->Response_LogoutAgentResponse('logged-out', 401, 'Unauthorized agent');
        }

        // Canal que hizo el logoneo hacia la cola
        $infoAgente = $this->_tuberia->AMIEventProcess_infoSeguimientoAgente($sAgente);

        /* Ejecutar Agentlogoff. Esto asume que el agente está de la forma
         * Agent/9000. La actualización de las bases de datos de auditoría y
         * breaks se delega a los manejadores de eventos */
        $agentFields = $this->_parseAgent($sAgente);
        if ($agentFields['type'] == 'Agent') {
            $r = $this->_ami->Agentlogoff($agentFields['number']);

            /* Si el agente todavía no ha introducido la clave, el Agentlogoff
             * anterior no tiene efecto, así que se manda a colgar el canal
             * directamente.
             */
            if (!is_null($infoAgente) && $infoAgente['estado_consola'] == 'logging') {
                $sCanalExt = $infoAgente['login_channel'];
                if (is_null($sCanalExt)) $sCanalExt = $infoAgente['extension'];
                if (!is_null($sCanalExt)) $this->_ami->Hangup($sCanalExt);
            }
        } else {
            // Si hay cliente conectado, le cierro el canal.
            if (!is_null($infoAgente['clientchannel'])) {
                $this->_ami->Hangup($infoAgente['clientchannel']);
            }

            // AMIEventProcess sabe de qué colas hay que quitar al agente
            $listaColas = $this->_tuberia->AMIEventProcess_listarTotalColasTrabajoAgente(array($sAgente));
            foreach ($listaColas[$sAgente][0] as $cola) {
                $r = $this->_ami->QueueRemove($cola, $sAgente);
            }
        }
        return $this->Response_LogoutAgentResponse('logged-out');
    }

    // Función que encapsula la generación de la respuesta
    private function Response_LogoutAgentResponse($status, $iCodigo = NULL, $msg = NULL)
    {
        $xml_response = new SimpleXMLElement('<response />');
        $xml_loginAgentResponse = $xml_response->addChild('logoutagent_response');

        $xml_loginAgentResponse->addChild('status', $status);
        if (!is_null($msg))
            $this->_agregarRespuestaFallo($xml_loginAgentResponse, $iCodigo, $msg);
        return $xml_response;
    }

    private function _marcarInicioBreakAgente($idAgente, $idBreak, $iTimestampInicio)
    {
        // Ingreso de sesión del agente
        $sTimeStamp = date('Y-m-d H:i:s', $iTimestampInicio);
        try {
            $sth = $this->_db->prepare(
                    'INSERT INTO audit (id_agent, id_break, datetime_init) VALUES (?, ?, ?)');
            $sth->execute(array($idAgente, $idBreak, $sTimeStamp));
            return $this->_db->lastInsertId();
        } catch (PDOException $e) {
            $this->_stdManejoExcepcionDB($e, 'no se puede registrar inicio de sesión de agente');
            return NULL;
        }
    }

    private function Request_agentauth_pauseagent($comando)
    {
        if (is_null($this->_ami))
            return $this->_generarRespuestaFallo(500, 'No AMI connection');

        $sAgente = (string)$comando->agent_number;

        // Verificar que ID de break está presente
        if (!isset($comando->pause_type))
            return $this->_generarRespuestaFallo(400, 'Bad request');
        $idBreak = (int)$comando->pause_type;

        $xml_response = new SimpleXMLElement('<response />');
        $xml_pauseAgentResponse = $xml_response->addChild('pauseagent_response');

        // Verificar si la pausa indicada existe y está activa
        $recordset = $this->_db->prepare(
            'SELECT id, name FROM break WHERE tipo = "B" AND status = "A" AND id = ?');
        $recordset->execute(array($idBreak));
        $tupla = $recordset->fetch(PDO::FETCH_ASSOC);
        $recordset->closeCursor();
        if (!$tupla) {
            $this->_agregarRespuestaFallo($xml_pauseAgentResponse, 404, 'Break ID not found or not active');
            return $xml_response;
        }

        // Verificar si el agente está siendo monitoreado y que no esté en pausa
        $infoSeguimiento = $this->_tuberia->AMIEventProcess_infoSeguimientoAgente($sAgente);
        if (is_null($infoSeguimiento)) {
            $this->_agregarRespuestaFallo($xml_pauseAgentResponse, 404, 'Agent not found or not logged in through ECCP');
            return $xml_response;
        }
        if ($infoSeguimiento['estado_consola'] != 'logged-in') {
            $this->_agregarRespuestaFallo($xml_pauseAgentResponse, 417, 'Agent currently not logged in');
            return $xml_response;
        }
        if (!is_null($infoSeguimiento['id_break'])) {
            if ($infoSeguimiento['id_break'] != $idBreak) {
                // Agente ya estaba en otro break
                $this->_agregarRespuestaFallo($xml_pauseAgentResponse, 417, 'Agent already in incompatible break');
            } else {
                // Agente ya estaba en el mismo break
                $xml_pauseAgentResponse->addChild('success');
            }
            return $xml_response;
        }

        // Se escribe el inicio provisional de la pausa en la base de datos
        $iTimestampInicioPausa = time();
        $idAuditBreak = $this->_marcarInicioBreakAgente(
            $infoSeguimiento['id_agent'], $idBreak, $iTimestampInicioPausa);
        if (is_null($idAuditBreak)) {
            $this->_agregarRespuestaFallo($xml_pauseAgentResponse, 500, 'Unable to start agent break');
            return $xml_response;
        }

        // Se comunica a AMIEventProcess la pausa elegida para que la inicie.
        // Esto puede fallar si el estado del agente ha cambiado.
        list($errcode, $errdesc) = $this->_tuberia->AMIEventProcess_iniciarBreakAgente(
            $sAgente, $idBreak, $idAuditBreak);
        if ($errcode != 0) {
            // Ha fallado el inicio de pausa, se deshace auditoría
            try {
                $sth = $this->_db->prepare('DELETE FROM audit WHERE id = ?');
                $sth->execute(array($idAuditBreak));
                $sth = NULL;
            } catch (PDOException $e) {
                $this->_stdManejoExcepcionDB($e, 'no se puede quitar auditoría provisional!');
            }
            $this->_agregarRespuestaFallo($xml_pauseAgentResponse, $errcode, $errdesc.' (collision)');
            return $xml_response;
        }

        $xml_pauseAgentResponse->addChild('success');
        return array(
            'response'  =>  $xml_response,
            'eventos'   =>  array(
                array('PauseStart', array($sAgente, array(
                    'pause_class'   =>  'break',
                    'pause_type'    =>  $idBreak,
                    'pause_name'    =>  $tupla['name'],
                    'pause_start'   =>  date('Y-m-d H:i:s', $iTimestampInicioPausa),
                ))),
            ),
        );
    }

    private function Request_agentauth_pingagent($comando)
    {
        $sAgente = (string)$comando->agent_number;

        $xml_response = new SimpleXMLElement('<response />');
        $xml_pingAgentResponse = $xml_response->addChild('pingagent_response');
        $r = $this->_tuberia->AMIEventProcess_pingAgente($sAgente);
        if (!$r)
    	   $this->_agregarRespuestaFallo($xml_pingAgentResponse, 404, 'Specified agent not found');
        else $xml_pingAgentResponse->addChild('success');
        return $xml_response;
    }

    private function Request_agentauth_unpauseagent($comando)
    {
        if (is_null($this->_ami))
            return $this->_generarRespuestaFallo(500, 'No AMI connection');

        $sAgente = (string)$comando->agent_number;

        $xml_response = new SimpleXMLElement('<response />');
        $xml_unpauseAgentResponse = $xml_response->addChild('unpauseagent_response');

        // Verificar si el agente está siendo monitoreado
        $infoSeguimiento = $this->_tuberia->AMIEventProcess_infoSeguimientoAgente($sAgente);
        if (is_null($infoSeguimiento)) {
            $this->_agregarRespuestaFallo($xml_unpauseAgentResponse, 404, 'Agent not found or not logged in through ECCP');
            return $xml_response;
        }
        if ($infoSeguimiento['estado_consola'] != 'logged-in') {
            $this->_agregarRespuestaFallo($xml_unpauseAgentResponse, 417, 'Agent currently not logged in');
            return $xml_response;
        }
        if (is_null($infoSeguimiento['id_break'])) {
            // Si el agente no estaba en break, se devuelve éxito sin hacer nada
            $xml_unpauseAgentResponse->addChild('success');
        	return $xml_response;
        }

        $iTimestampFinalPausa = time();
        $this->_tuberia->msg_AMIEventProcess_quitarBreakAgente($sAgente);
        marcarFinalBreakAgente($this->_db,
            $infoSeguimiento['id_audit_break'], $iTimestampFinalPausa);

        $xml_unpauseAgentResponse->addChild('success');

        $ev = construirEventoPauseEnd($this->_db, $sAgente,
            $infoSeguimiento['id_audit_break'], 'break');

        return array(
            'response'  =>  $xml_response,
            'eventos'   =>  array($ev),
        );
    }

    /**
     * Procedimiento que implementa la verificación del estado de un agente
     * estático al estilo Agent/9000.
     *
     * @param   object   $comando    Comando
     *      <getagentstatus>
     *          <agent_number>Agent/9000</agent_number>
     *      </getagentstatus>
     *
     * @return  object  Respuesta codificada como un SimpleXMLObject
     *      <getagentstatus_response>
     *          <status>offline|online|oncall|paused</status>
     *          <channel>SIP/1064-000000001</channel>
     *          <extension>1064<extension/>
     *          <failure>mensaje</failure>
     *      </getagentstatus_response>
     */
    private function Request_eccpauth_getagentstatus($comando)
    {
        if (is_null($this->_ami))
            return $this->_generarRespuestaFallo(500, 'No AMI connection');

        $iTimestampInicio = microtime(TRUE);

        // Verificar que agente está presentes
        if (!isset($comando->agent_number))
            return $this->_generarRespuestaFallo(400, 'Bad request');
        $sAgente = (string)$comando->agent_number;

        $xml_response = new SimpleXMLElement('<response />');
        $xml_getAgentStatusResponse = $xml_response->addChild('getagentstatus_response');

        // El siguiente código asume formato Agent/9000
        if (is_null($this->_parseAgent($sAgente))) {
            $xml_getAgentStatusResponse->addChild('status', 'offline');
            $this->_agregarRespuestaFallo($xml_getAgentStatusResponse, 404, 'Invalid agent number');
            return $xml_response;
        }

        // Obtener la información del estado del agente según el marcador
        $infoSeguimiento = $this->_tuberia->AMIEventProcess_infoSeguimientoAgente($sAgente);
        if (is_null($infoSeguimiento)) {
            $xml_getAgentStatusResponse->addChild('status', 'offline');
            $this->_agregarRespuestaFallo($xml_getAgentStatusResponse, 404, 'Invalid agent number');
            return $xml_response;
        }

        $infoLlamada = $this->_tuberia->AMIEventProcess_reportarInfoLlamadaAtendida($sAgente);

        $recordset_breakinfo = NULL;
        cargarInfoPausa($this->_db, $infoSeguimiento, $recordset_breakinfo);
        $this->_agregarAgentStatusInfo($xml_getAgentStatusResponse,
            $infoSeguimiento, $infoLlamada);

        return $xml_response;
    }

    private function Request_eccpauth_getmultipleagentstatus($comando)
    {
        if (is_null($this->_ami))
            return $this->_generarRespuestaFallo(500, 'No AMI connection');

        // Verificar que agente está presente
        if (!isset($comando->agents))
            return $this->_generarRespuestaFallo(400, 'Bad request');

        $xml_response = new SimpleXMLElement('<response />');
        $xml_getAgentStatusResponse = $xml_response->addChild('getmultipleagentstatus_response');

        $agentlist = array();
        foreach ($comando->agents->agent_number as $agent_number) {
            $sAgente = (string)$agent_number;

            // El siguiente código asume formato Agent/9000
            if (is_null($this->_parseAgent($sAgente))) {
                $this->_agregarRespuestaFallo($xml_getAgentStatusResponse, 417, 'Invalid agent number');
                return $xml_response;
            }

            $agentlist[] = $sAgente;
        }

        // Verificar que todos los agentes existen en el sistema
        $listaAgentes = $this->_listarAgentes();
        $agentesExtras = array_diff($agentlist, array_keys($listaAgentes));
        if (count($agentesExtras) > 0) {
            $this->_agregarRespuestaFallo($xml_getAgentStatusResponse, 404, 'Specified agent not found');
            return $xml_response;
        }

        $is = $this->_tuberia->AMIEventProcess_infoSeguimientoAgente($agentlist);
        foreach ($is as $sAgente => $infoSeguimiento) {
            if (is_null($infoSeguimiento)) {
                $xml_getAgentStatusResponse->addChild('status', 'offline');
                $this->_agregarRespuestaFallo($xml_getAgentStatusResponse, 404, 'Invalid agent number');
                return $xml_response;
            }
        }
        $il = $this->_tuberia->AMIEventProcess_reportarInfoLlamadaAtendida($agentlist);

        $recordset_breakinfo = NULL;

        // Conversión a XML
        $xml_agents = $xml_getAgentStatusResponse->addChild('agents');
        foreach ($agentlist as $sAgente) {
            $xml_agent = $xml_agents->addChild('agent');
            $xml_agent->addChild('agent_number', str_replace('&', '&amp;', $sAgente));

            $infoSeguimiento = $is[$sAgente];
            $infoLlamada = $il[$sAgente];

            cargarInfoPausa($this->_db, $infoSeguimiento, $recordset_breakinfo);
            $this->_agregarAgentStatusInfo($xml_agent, $infoSeguimiento,
                $infoLlamada);
        }

        return $xml_response;
    }

    private function _agregarAgentStatusInfo($xml_agent, &$infoSeguimiento,
        &$infoLlamada)
    {
        list($sAgentStatus, $sExtension) = self::getcampaignstatus_setagent(
            $xml_agent, $infoSeguimiento, FALSE, $infoLlamada);

        if (!is_null($sAgentStatus)) {
            if ($sAgentStatus != 'offline' && is_null($sExtension)) {
                $this->_log->output("ERR: (internal) estado inconsistente de agente (status=$sAgentStatus extension=null)\n".
                    "\tinfoSeguimiento => ".print_r($infoSeguimiento, TRUE).
                    "\tinfoLlamada => ".print_r($infoLlamada, TRUE));
            }
        } else {
            $xml_agent->addChild('status', 'offline');
        }

    }

    private static function _agregarCallInfo($xml_callInfo, &$infoLlamada)
    {
        foreach (array('calltype', 'callid', 'campaign_id', 'queuenumber', 'callnumber') as $k) {
            if (!is_null($infoLlamada[$k])) $xml_callInfo->addChild($k, $infoLlamada[$k]);
        }
        $xml_callInfo->addChild('callstatus', $infoLlamada['status']);
        if (isset($infoLlamada['trunk']))
            $xml_callInfo->addChild('trunk', $infoLlamada['trunk']);

        $date_prefix = date('Y-m-d ');
        foreach (array('dialstart', 'dialend', 'queuestart', 'linkstart') as $k) {
            if (isset($infoLlamada[$k])) {
                $xml_callInfo->addChild($k, str_replace($date_prefix, '', $infoLlamada[$k]));
            }
        }
    }

    private function Request_agentauth_mixmonitormute($comando)
    {
       if (is_null($this->_ami))
            return $this->_generarRespuestaFallo(500, 'No AMI connection');

        // Verificar que agente está presente
        if (!isset($comando->agent_number))
            return $this->_generarRespuestaFallo(400, 'Bad request');
        $sAgente = (string)$comando->agent_number;

        $xml_response = new SimpleXMLElement('<response />');
        $xml_mixmonitormuteResponse = $xml_response->addChild('mixmonitormute_response');

        // El siguiente código asume formato Agent/9000
        if (is_null($this->_parseAgent($sAgente))) {
            $this->_agregarRespuestaFallo($xml_mixmonitormuteResponse, 404, 'Invalid agent number');
            return $xml_response;
        }

        // Timeout luego del cual quitar el silencio de la llamada, en segundos
        $timeout = NULL;
        if (isset($comando->timeout)) {
            $timeout = (int)$comando->timeout;
            if ($timeout <= 0) {
                $this->_agregarRespuestaFallo($xml_mixmonitormuteResponse, 417, 'Invalid timeout');
                return $xml_response;
            }
        }

        $infoLlamada = $this->_tuberia->AMIEventProcess_reportarInfoLlamadaAtendida($sAgente);

        if (is_null($infoLlamada) || is_null($infoLlamada['agentchannel'])) {
            $this->_agregarRespuestaFallo($xml_mixmonitormuteResponse, 417, 'Agent not in call');
            return $xml_response;
        }

        $r = $this->_ami->MixMonitorMute($infoLlamada['channel'], true);
        if ($r['Response'] != 'Success') {
            $this->_log->output('ERR: No se puede callar la grabacion para '.$sAgente.
                ' ('.$infoLlamada['channel'].') - '.$r['Message']);
            $this->_agregarRespuestaFallo($xml_mixmonitormuteResponse, 500, 'Cannot mute agent call');
            return $xml_response;
        }
        $this->_tuberia->msg_AMIEventProcess_llamadaSilenciada($sAgente, $infoLlamada['channel'], $timeout);

        $xml_mixmonitormuteResponse->addChild('success');
        return $xml_response;
    }

    private function Request_agentauth_mixmonitorunmute($comando)
    {
       if (is_null($this->_ami))
            return $this->_generarRespuestaFallo(500, 'No AMI connection');

        // Verificar que agente está presente
        if (!isset($comando->agent_number))
            return $this->_generarRespuestaFallo(400, 'Bad request');
        $sAgente = (string)$comando->agent_number;

        $xml_response = new SimpleXMLElement('<response />');
        $xml_mixmonitorunmuteResponse = $xml_response->addChild('mixmonitorunmute_response');

        // El siguiente código asume formato Agent/9000
        if (is_null($this->_parseAgent($sAgente))) {
            $this->_agregarRespuestaFallo($xml_mixmonitorunmuteResponse, 404, 'Invalid agent number');
            return $xml_response;
        }

        $infoLlamada = $this->_tuberia->AMIEventProcess_reportarInfoLlamadaAtendida($sAgente);

        if (is_null($infoLlamada) || is_null($infoLlamada['agentchannel'])) {
            $this->_agregarRespuestaFallo($xml_mixmonitorunmuteResponse, 417, 'Agent not in call');
            return $xml_response;
        }

        $c = 0;
        foreach ($infoLlamada['mutedchannels'] as $chan) {
            $r = $this->_ami->MixMonitorMute($chan, false);
            if ($r['Response'] != 'Success') {
                $this->_log->output('ERR: No se puede restaurar la grabacion para '.$sAgente.
                    ' ('.$chan.') - '.$r['Message']);
            } else {
                $c++;
            }
        }
        if ($c <= 0) {
            $this->_agregarRespuestaFallo($xml_mixmonitorunmuteResponse, 500, 'Cannot unmute agent call');
            return $xml_response;
        }
        $this->_tuberia->msg_AMIEventProcess_llamadaSinSilencio($sAgente);

        $xml_mixmonitorunmuteResponse->addChild('success');
        return $xml_response;
    }

    private function Request_agentauth_hangup($comando)
    {
        if (is_null($this->_ami))
            return $this->_generarRespuestaFallo(500, 'No AMI connection');

        // Verificar que agente está presentes
        $sAgente = (string)$comando->agent_number;
        $hangchannel = NULL;

        $xml_response = new SimpleXMLElement('<response />');
        $xml_hangupResponse = $xml_response->addChild('hangup_response');

        // El siguiente código asume formato Agent/9000
        $agentFields = $this->_parseAgent($sAgente);
        if (is_null($agentFields)) {
            $this->_agregarRespuestaFallo($xml_hangupResponse, 417, 'Invalid agent number');
            return $xml_response;
        }

        $infoLlamada = $this->_tuberia->AMIEventProcess_reportarInfoLlamadaAtendida($sAgente);
        if (!is_null($infoLlamada)) $hangchannel = $infoLlamada['agentchannel'];
        if (is_null($hangchannel)) {
            // Verificar si la llamada manual está en proceso de marcado
            $infoLlamada = $this->_tuberia->AMIEventProcess_reportarInfoLlamadaAgendada($sAgente);
            if (!is_null($infoLlamada) && in_array($infoLlamada['status'], array('Placing', 'Ringing'))) {
                /* Para agentes estáticos, el canal de agente se puede usar
                 * directamente para abortar. Los agentes dinámicos requieren
                 * el canal. */
                $hangchannel = ($agentFields['type'] == 'Agent') ? $sAgente : $infoLlamada['channel'];
            }
        }

        if (is_null($hangchannel)) {
            $this->_agregarRespuestaFallo($xml_hangupResponse, 417, 'Agent not in call');
            return $xml_response;
        }

        // Mandar a colgar la llamada usando el canal Agent/9000
        $r = $this->_ami->Hangup($hangchannel);
        if ($r['Response'] != 'Success') {
            $this->_log->output('ERR: No se puede colgar la llamada para '.$sAgente.
                ' ('.$hangchannel.') - '.$r['Message']);
            $this->_agregarRespuestaFallo($xml_hangupResponse, 500, 'Cannot hangup agent call');
            return $xml_response;
        }

        $xml_hangupResponse->addChild('success');
        return $xml_response;
    }

    private function Request_eccpauth_getcampaignstatus($comando)
    {
        if (is_null($this->_ami))
            return $this->_generarRespuestaFallo(500, 'No AMI connection');

        // Verificar que id y tipo está presente
        if (!isset($comando->campaign_id))
            return $this->_generarRespuestaFallo(400, 'Bad request');
        $idCampania = (int)$comando->campaign_id;
        $sTipoCampania = 'outgoing';
        if (isset($comando->campaign_type)) {
            $sTipoCampania = (string)$comando->campaign_type;
        }

        // Si hay fecha de inicio, verificar que sea correcta
        $sFechaInicio = NULL;
        if (isset($comando->datetime_start)) {
            $sFechaInicio = (string)$comando->datetime_start;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sFechaInicio))
                return $this->_generarRespuestaFallo(400, 'Bad request - invalid start date');
            $sFechaInicio .= ' 00:00:00';
        }

        // Leer resumen de llamadas completadas desde la base de datos
        switch ($sTipoCampania) {
        case 'outgoing':
            $statusCampania_DB = $this->_leerResumenCampaniaSaliente($idCampania, $sFechaInicio);
            break;
        case 'incoming':
            $statusCampania_DB = $this->_leerResumenCampaniaEntrante($idCampania, $sFechaInicio);
            break;
        default:
            return $this->_generarRespuestaFallo(400, 'Bad request');
        }

        $xml_response = new SimpleXMLElement('<response />');
        $xml_statusresponse = $xml_response->addChild('getcampaignstatus_response');
        if (count($statusCampania_DB) <= 0) {
            $this->_agregarRespuestaFallo($xml_statusresponse, 404, 'Campaign not found');
            return $xml_response;
        }

        // Leer información de las llamadas en curso para la campaña
        $statusCampania_AMI = $this->_tuberia->AMIEventProcess_reportarInfoLlamadasCampania($sTipoCampania, $idCampania);

        $this->_getcampaignstatus_format($xml_statusresponse, $statusCampania_DB, $statusCampania_AMI);
        return $xml_response;
    }

    private function Request_eccpauth_getincomingqueuestatus($comando)
    {
        if (is_null($this->_ami))
            return $this->_generarRespuestaFallo(500, 'No AMI connection');

        // Verificar que id y tipo está presente
        if (!isset($comando->queue))
            return $this->_generarRespuestaFallo(400, 'Bad request');
        $sCola = (string)$comando->queue;

        // Si hay fecha de inicio, verificar que sea correcta
        $sFechaInicio = NULL;
        if (isset($comando->datetime_start)) {
            $sFechaInicio = (string)$comando->datetime_start;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sFechaInicio))
                return $this->_generarRespuestaFallo(400, 'Bad request - invalid start date');
            $sFechaInicio .= ' 00:00:00';
        }

        // Leer resumen de llamadas completadas sin campaña desde la base de datos
        $statusCampania_DB = $this->_leerResumenColaEntrante($sCola, $sFechaInicio);

        $xml_response = new SimpleXMLElement('<response />');
        $xml_statusresponse = $xml_response->addChild('getincomingqueuestatus_response');
        if (count($statusCampania_DB) <= 0) {
            $this->_agregarRespuestaFallo($xml_statusresponse, 404, 'Queue not found');
            return $xml_response;
        }

        // Leer información de las llamadas en curso para la campaña
        $statusCampania_AMI = $this->_tuberia->AMIEventProcess_reportarInfoLlamadasColaEntrante($sCola);

        $this->_getcampaignstatus_format($xml_statusresponse, $statusCampania_DB, $statusCampania_AMI);
        return $xml_response;
    }

    private function _getcampaignstatus_format($xml_statusresponse, &$statusCampania_DB, &$statusCampania_AMI)
    {
        // Cuentas de estados de llamadas realizadas
        $xml_statusCount = $xml_statusresponse->addChild('statuscount');
        $xml_statusCount->addChild('total', array_sum($statusCampania_DB['status']));
        foreach ($statusCampania_DB['status'] as $statusKey => $statusCount)
            $xml_statusCount->addChild(strtolower($statusKey), $statusCount);

        $recordset_breakinfo = NULL;

        // Estado de los agentes
        $xml_agents = $xml_statusresponse->addChild('agents');
        foreach ($statusCampania_AMI['queuestatus'] as $sAgente => $infoAgente) {
            // Este código asume agentes de formato Agent/9000
            $xml_agent = $xml_agents->addChild('agent');
            $xml_agent->addChild('agentchannel', $sAgente);

            cargarInfoPausa($this->_db, $infoAgente, $recordset_breakinfo);
            self::getcampaignstatus_setagent($xml_agent, $infoAgente);
        }

        // Estado de los agentes logoneados en la cola, sin llamada en atención
        $infoAgentes = $this->_tuberia->AMIEventProcess_infoSeguimientoAgentesCola(
            $statusCampania_DB['queue'], array_keys($statusCampania_AMI['queuestatus']));
        foreach ($infoAgentes as $sAgente => $infoAgente) {
            $xml_agent = $xml_agents->addChild('agent');
            $xml_agent->addChild('agentchannel', $sAgente);

            cargarInfoPausa($this->_db, $infoAgente, $recordset_breakinfo);
            self::getcampaignstatus_setagent($xml_agent, $infoAgente);
        }

        // Estado de las llamadas pendientes de enlazar
        $xml_activecalls = $xml_statusresponse->addChild('activecalls');
        foreach ($statusCampania_AMI['activecalls'] as $infoLlamada) {
            $xml_activecall = $xml_activecalls->addChild('activecall');
            self::_agregarCallInfo($xml_activecall, $infoLlamada);
        }

        // Contadores para estadísticas
        $xml_stats = $xml_statusresponse->addChild('stats');
        foreach ($statusCampania_DB['stat'] as $statKey => $statCount)
            $xml_stats->addChild(strtolower($statKey), $statCount);
    }

    static function getcampaignstatus_setagent($xml_agent, $infoAgente, $flattened = TRUE, $infoLlamada = NULL)
    {
        // Canal que hizo el logoneo hacia la cola
        $sExtension = NULL;
        $sCanalExt = $infoAgente['login_channel'];
        if (is_null($sCanalExt)) $sCanalExt = $infoAgente['extension'];
        if (!is_null($sCanalExt)) {
            // Hay un canal de login. Se separa la extensión que hizo el login
            $sRegexp = "|^\w+/(\\d+)-?|"; $regs = NULL;
            if (preg_match($sRegexp, $sCanalExt, $regs)) {
                $sExtension = $regs[1];
            }
        }

        // Reportar los estados conocidos
        $sAgentStatus = NULL;
        if ($infoAgente['oncall']) {
            $sAgentStatus = 'oncall';
        } elseif ($infoAgente['num_pausas'] > 0) {
            $sAgentStatus = 'paused';
        } elseif ($infoAgente['estado_consola'] == 'logged-in') {
            $sAgentStatus = 'online';
        } else {
            $sAgentStatus = 'offline';
        }
        if (!is_null($sAgentStatus)) {
            $xml_agent->addChild('status', $sAgentStatus);
            if (!is_null($sCanalExt)) $xml_agent->addChild('channel', str_replace('&', '&amp;', $sCanalExt));
            if (!is_null($sExtension)) $xml_agent->addChild('extension', $sExtension);
        }

        // Reportar el canal remoto al cual está conectado el agente
        if (isset($infoAgente['clientchannel'])) {
            /* TODO: si clientchannel está definido, es idéntico a actualchannel de
             * Llamada::resumenLlamada() pero también está disponible en
             * Agente::resumenSeguimiento().
             */
            $xml_agent->addChild(($flattened ? 'callchannel' : 'remote_channel'),
                str_replace('&', '&amp;', $infoAgente['clientchannel']));
        }

        // Reportar la información de la llamada que el agente está esperando, si aplica
        if (!is_null($infoAgente['waitedcallinfo'])) {
            $xml_wci = $xml_agent->addChild('waitedcallinfo');
            foreach ($infoAgente['waitedcallinfo'] as $k => $v)
                $xml_wci->addChild($k, $v);
        }

        // Reportar el estado de hold, si aplica
        if ($infoAgente['estado_consola'] == 'logged-in')
            $xml_agent->addChild('onhold', is_null($infoAgente['id_hold']) ? 0 : 1);

        // Reportar los estados de break, si aplica
        if (!is_null($infoAgente['id_break'])) {
            $xml_pauseInfo = $flattened ? $xml_agent : $xml_agent->addChild('pauseinfo');
            $xml_pauseInfo->addChild('pauseid', $infoAgente['id_break']);
            if (isset($infoAgente['pausename']))
                $xml_pauseInfo->addChild('pausename', str_replace('&', '&amp;', $infoAgente['pausename']));
            if (isset($infoAgente['pausestart']))
                $xml_pauseInfo->addChild('pausestart', str_replace(date('Y-m-d '), '', $infoAgente['pausestart']));
        }

        if ($flattened) {
            // FIXME: compatibilidad requiere mezclar campos de callinfo y agent
            if (isset($infoAgente['callinfo'])) {
                self::_agregarCallInfo($xml_agent, $infoAgente['callinfo']);
                $infoLlamada = $infoAgente['callinfo'];
            }
        } else {
            if (!is_null($infoLlamada)) {
                $xml_callInfo = $xml_agent->addChild('callinfo');
                self::_agregarCallInfo($xml_callInfo, $infoLlamada);
            }
        }

        return array($sAgentStatus, $sExtension);
    }

    /**
     * Método que devuelve un resumen de la información de una campaña saliente
     * para ser mostrada en la interfaz de monitoreo.
     *
     * @param   int     $idCampania     ID de la campaña a interrogar
     * @param   string  $sFechaInicio   Si no es NULL, fecha inicial para llamadas
     *                                  de campaña a considerar.
     *
     * @return  mixed   NULL en error, o información de la campaña
     */
    private function _leerResumenCampaniaSaliente($idCampania, $sFechaInicio = NULL)
    {
        // Leer la información en el propio registro de la campaña
        $sPeticionSQL = <<<LEER_RESUMEN_CAMPANIA
SELECT id, name, datetime_init, datetime_end, daytime_init, daytime_end,
    retries, trunk, queue, estatus
FROM campaign WHERE id = ?
LEER_RESUMEN_CAMPANIA;
        $recordset = $this->_db->prepare($sPeticionSQL);
        $recordset->execute(array($idCampania));
        $tupla = $recordset->fetch(PDO::FETCH_ASSOC);
        $recordset->closeCursor();
        if (!$tupla) return array();

        // Leer la clasificación por estado de las llamadas de la campaña
        $sPeticionSQL = <<<CLASIFICAR_LLAMADAS
SELECT COUNT(*) AS n, status FROM calls
WHERE id_campaign = ? AND ((? IS NULL) OR (datetime_originate >= ?))
GROUP BY status
CLASIFICAR_LLAMADAS;
        $recordset = $this->_db->prepare($sPeticionSQL);
        $recordset->execute(array($idCampania, $sFechaInicio, $sFechaInicio));
        $recordset->setFetchMode(PDO::FETCH_ASSOC);
        $tupla['status'] = array(
            'Pending'   =>  0,  // Llamada no ha sido realizada todavía

            'Placing'   =>  0,  // Originate realizado, no se recibe OriginateResponse
            'Ringing'   =>  0,  // Se recibió OriginateResponse, no entra a cola
            'OnQueue'   =>  0,  // Entró a cola, no se asigna a agente todavía
            'Success'   =>  0,  // Conectada y asignada a un agente
            'OnHold'    =>  0,  // Llamada fue puesta en espera por agente
            'Failure'   =>  0,  // No se puede conectar llamada
            'ShortCall' =>  0,  // Llamada conectada pero duración es muy corta
            'NoAnswer'  =>  0,  // Llamada estaba Ringing pero no entró a cola
            'Abandoned' =>  0,  // Llamada estaba OnQueue pero no habían agentes
        );
        foreach ($recordset as $tuplaStatus) {
            if (is_null($tuplaStatus['status']))
                $tupla['status']['Pending'] = $tuplaStatus['n'];
            else $tupla['status'][$tuplaStatus['status']] = $tuplaStatus['n'];
        }

        // Leer estadísticas de la campaña
        $sPeticionSQL = <<<LEER_STATS_CAMPANIA
SELECT SUM(duration) AS total_sec, MAX(duration) AS max_duration FROM calls
WHERE id_campaign = ? AND status = 'Success' AND ((? IS NULL) OR (start_time >= ?)) AND end_time IS NOT NULL
LEER_STATS_CAMPANIA;
        $recordset = $this->_db->prepare($sPeticionSQL);
        $recordset->execute(array($idCampania, $sFechaInicio, $sFechaInicio));
        $recordset->setFetchMode(PDO::FETCH_ASSOC);
        $tupla['stat'] = array();
        foreach ($recordset as $tuplaStat) {
        	foreach ($tuplaStat as $k => $v) $tupla['stat'][$k] = is_null($v) ? 0 : (int)$v;
        }

        return $tupla;
    }

    private function _leerResumenCampaniaEntrante($idCampania, $sFechaInicio = NULL)
    {
        // Leer la información en el propio registro de la campaña
        $sPeticionSQL = <<<LEER_RESUMEN_CAMPANIA
SELECT ce.id, ce.name, ce.datetime_init, ce.datetime_end, ce.daytime_init,
    ce.daytime_end, qce.queue, ce.estatus
FROM campaign_entry ce, queue_call_entry qce
WHERE ce.id = ? AND ce.id_queue_call_entry = qce.id
LEER_RESUMEN_CAMPANIA;
        $recordset = $this->_db->prepare($sPeticionSQL);
        $recordset->execute(array($idCampania));
        $tupla = $recordset->fetch(PDO::FETCH_ASSOC);
        $recordset->closeCursor();
        if (!$tupla) return array();

        // Leer la clasificación por estado de las llamadas de la campaña
        $sPeticionSQL = <<<CLASIFICAR_LLAMADAS
SELECT COUNT(*) AS n, status FROM call_entry
WHERE id_campaign = ? AND ((? IS NULL) OR (datetime_entry_queue >= ?))
GROUP BY status
CLASIFICAR_LLAMADAS;
        $recordset = $this->_db->prepare($sPeticionSQL);
        $recordset->execute(array($idCampania, $sFechaInicio, $sFechaInicio));
        $recordset->setFetchMode(PDO::FETCH_ASSOC);
        $tupla['status'] = array(
            //'Pending'   =>  0,  // Llamada no ha sido realizada todavía

            //'Placing'   =>  0,  // Originate realizado, no se recibe OriginateResponse
            //'Ringing'   =>  0,  // Se recibió OriginateResponse, no entra a cola
            'OnQueue'   =>  0,  // Entró a cola, no se asigna a agente todavía
            'Success'   =>  0,  // Conectada y asignada a un agente
            'OnHold'    =>  0,  // Llamada fue puesta en espera por agente
            //'Failure'   =>  0,  // No se puede conectar llamada
            //'ShortCall' =>  0,  // Llamada conectada pero duración es muy corta
            //'NoAnswer'  =>  0,  // Llamada estaba Ringing pero no entró a cola
            'Abandoned' =>  0,  // Llamada estaba OnQueue pero no habían agentes
            'Finished'  =>  0,  // Llamada ha terminado luego de ser conectada a agente
            'LostTrack' =>  0,  // Programa fue terminado mientras la llamada estaba activa
        );
        $mapaEstados = array(
            'en-cola'       =>  'OnQueue',
            'activa'        =>  'Success',
            'hold'          =>  'OnHold',
            'abandonada'    =>  'Abandoned',
            'terminada'     =>  'Finished',
            'fin-monitoreo' =>  'LostTrack',
        );
        foreach ($recordset as $tuplaStatus) {
            $tupla['status'][$mapaEstados[$tuplaStatus['status']]] = $tuplaStatus['n'];
        }

        // Leer estadísticas de la campaña
        $sPeticionSQL = <<<LEER_STATS_CAMPANIA
SELECT SUM(duration) AS total_sec, MAX(duration) AS max_duration FROM call_entry
WHERE id_campaign = ? AND status = 'terminada'
    AND ((? IS NULL) OR (datetime_init >= ?)) AND datetime_end IS NOT NULL
LEER_STATS_CAMPANIA;
        $recordset = $this->_db->prepare($sPeticionSQL);
        $recordset->execute(array($idCampania, $sFechaInicio, $sFechaInicio));
        $recordset->setFetchMode(PDO::FETCH_ASSOC);
        $tupla['stat'] = array();
        foreach ($recordset as $tuplaStat) {
            foreach ($tuplaStat as $k => $v) $tupla['stat'][$k] = is_null($v) ? 0 : (int)$v;
        }

        return $tupla;
    }

    private function _leerResumenColaEntrante($sCola, $sFechaInicio = NULL)
    {
        $tupla['queue'] = $sCola;

        // Leer la clasificación por estado de las llamadas de la campaña
        $sPeticionSQL =
            'SELECT COUNT(*) AS n, status FROM call_entry, queue_call_entry '.
            'WHERE call_entry.id_campaign IS NULL '.
                'AND call_entry.id_queue_call_entry = queue_call_entry.id '.
                'AND queue_call_entry.queue = ? '.
                'AND ((? IS NULL) OR (call_entry.datetime_entry_queue >= ?)) '.
            'GROUP BY status';
        $recordset = $this->_db->prepare($sPeticionSQL);
        $recordset->execute(array($sCola, $sFechaInicio, $sFechaInicio));
        $recordset->setFetchMode(PDO::FETCH_ASSOC);
        $tupla['status'] = array(
            //'Pending'   =>  0,  // Llamada no ha sido realizada todavía

            //'Placing'   =>  0,  // Originate realizado, no se recibe OriginateResponse
            //'Ringing'   =>  0,  // Se recibió OriginateResponse, no entra a cola
            'OnQueue'   =>  0,  // Entró a cola, no se asigna a agente todavía
            'Success'   =>  0,  // Conectada y asignada a un agente
            'OnHold'    =>  0,  // Llamada fue puesta en espera por agente
            //'Failure'   =>  0,  // No se puede conectar llamada
            //'ShortCall' =>  0,  // Llamada conectada pero duración es muy corta
            //'NoAnswer'  =>  0,  // Llamada estaba Ringing pero no entró a cola
            'Abandoned' =>  0,  // Llamada estaba OnQueue pero no habían agentes
            'Finished'  =>  0,  // Llamada ha terminado luego de ser conectada a agente
            'LostTrack' =>  0,  // Programa fue terminado mientras la llamada estaba activa
        );
        $mapaEstados = array(
            'en-cola'       =>  'OnQueue',
            'activa'        =>  'Success',
            'hold'          =>  'OnHold',
            'abandonada'    =>  'Abandoned',
            'terminada'     =>  'Finished',
            'fin-monitoreo' =>  'LostTrack',
        );
        foreach ($recordset as $tuplaStatus) {
            $tupla['status'][$mapaEstados[$tuplaStatus['status']]] = $tuplaStatus['n'];
        }

        // Leer estadísticas de la campaña
        $sPeticionSQL = <<<LEER_STATS_CAMPANIA
SELECT SUM(duration) AS total_sec, MAX(duration) AS max_duration
FROM call_entry, queue_call_entry
WHERE id_campaign IS NULL AND id_queue_call_entry = queue_call_entry.id
    AND queue_call_entry.queue = ? AND status = 'terminada'
    AND datetime_end IS NOT NULL
    AND ((? IS NULL) OR (datetime_init >= ?))
LEER_STATS_CAMPANIA;
        $recordset = $this->_db->prepare($sPeticionSQL);
        $recordset->execute(array($sCola, $sFechaInicio, $sFechaInicio));
        $recordset->setFetchMode(PDO::FETCH_ASSOC);
        $tupla['stat'] = array();
        foreach ($recordset as $tuplaStat) {
            foreach ($tuplaStat as $k => $v) $tupla['stat'][$k] = is_null($v) ? 0 : (int)$v;
        }

        return $tupla;
    }

    private function Request_agentauth_schedulecall($comando)
    {
        if (is_null($this->_ami))
            return $this->_generarRespuestaFallo(500, 'No AMI connection');

        // Verificar que agente está presente
        $sAgente = (string)$comando->agent_number;

        $xml_response = new SimpleXMLElement('<response />');
        $xml_scheduleResponse = $xml_response->addChild('schedulecall_response');

        // Verificar si se especifica un callid explícito
        $sTipoCampania = NULL;
        $idLlamada = NULL;
        if (isset($comando->campaign_type) && isset($comando->call_id)) {
            $sTipoCampania = (string)$comando->campaign_type;
            if (!in_array($sTipoCampania, array('outgoing')))
                return $this->_generarRespuestaFallo(400, 'Bad request');
            $idLlamada = (int)$comando->call_id;
        }

        // Verificar si el agente está siendo monitoreado
        $infoSeguimiento = $this->_tuberia->AMIEventProcess_infoSeguimientoAgente($sAgente);
        if (is_null($infoSeguimiento)) {
            $this->_agregarRespuestaFallo($xml_scheduleResponse, 404, 'Agent not found or not logged in through ECCP');
            return $xml_response;
        }
        if ($infoSeguimiento['estado_consola'] != 'logged-in') {
            $this->_agregarRespuestaFallo($xml_scheduleResponse, 417, 'Agent currently not logged in');
            return $xml_response;
        }

        $bMismoAgente = FALSE;
        $horario = NULL;
        $sNuevoTelefono = NULL;
        $sNuevoNombre = NULL;

        // Verificar si se debe usar el mismo agente (requiere contexto especial)
        if (isset($comando->sameagent) && (int)$comando->sameagent != 0)
            $bMismoAgente = TRUE;

        // Verificar si se debe usar un nuevo teléfono
        if (isset($comando->newphone)) $sNuevoTelefono = (string)$comando->newphone;

        // Verificar si se debe usar un nuevo nombre de contacto
        if (isset($comando->newcontactname)) $sNuevoNombre = (string)$comando->newcontactname;

        // Verificar que se tiene un horario establecido
        if (isset($comando->schedule)) {
            if (isset($comando->schedule->date_init) && isset($comando->schedule->date_end) &&
                isset($comando->schedule->time_init) && isset($comando->schedule->time_end)) {
                $horario = array(
                    'date_init' =>  (string)$comando->schedule->date_init,
                    'date_end'  =>  (string)$comando->schedule->date_end,
                    'time_init' =>  (string)$comando->schedule->time_init,
                    'time_end'  =>  (string)$comando->schedule->time_end,
                );
            } else {
                $this->_agregarRespuestaFallo($xml_scheduleResponse, 400, 'Bad request: incomplete schedule');
                return $xml_response;
            }
        }

        if ($bMismoAgente && is_null($horario)) {
            $this->_agregarRespuestaFallo($xml_scheduleResponse, 400, 'Bad request: same-agent requires schedule');
            return $xml_response;
        }

        // Ejecutar el agendamiento de la llamada
        $errcode = $errdesc = NULL;
        $bExito = $this->_agendarLlamadaAgente($sTipoCampania, $idLlamada, $sAgente, $horario,
            $bMismoAgente, $sNuevoTelefono, $sNuevoNombre, $errcode, $errdesc);
        if (!$bExito) {
            $this->_agregarRespuestaFallo($xml_scheduleResponse, $errcode, $errdesc);
        } else {
            $xml_scheduleResponse->addChild('success');
        }

        return $xml_response;
    }

    /**
     * Procedimiento que crea una nueva llamada agendada en base a la llamada
     * que está atendiendo el agente indicado por el parámetro.
     *
     * @param   string  $sAgente        Agente en formato Agent/9000
     * @param   mixed   $horario        Arreglo que define el horario como sigue:
     *          date_init               Fecha en inicio de horario en formato YYYY-MM-DD
     *          date_end                Fecha de fin de horario en formato YYYY-MM-DD
     *          time_init               Hora de inicio de horario en formato HH:MM:SS
     *          time_end                Hora de fin de horario en formato HH:MM:SS
     *                                  NULL para agendar llamada al final de campaña
     *                                  a cualquier fecha y hora
     * @param   bool    $bMismoAgente   FALSO si se asigna llamada a cualquier agente
     *                                  VERDADERO para que el mismo agente deba atenderla
     *                                  Si VERDADERO, se requiere $horario.
     * @param   mixed   $sNuevoTelefono Teléfono nuevo al cual marcar llamada, o NULL para mismo anterior
     * @param   mixed   $sNuevoNombre   Nombre del nuevo contacto para llamada, o NULL para mismo anterior
     *
     * @return bool VERDADERO en caso de éxito, FALSO en caso de error
     */
    private function _agendarLlamadaAgente($calltype, $callid, $sAgente, $horario, $bMismoAgente,
        $sNuevoTelefono, $sNuevoNombre, &$errcode, &$errdesc)
    {
        $errcode = 0; $errdesc = 'Success';

        // Revisar teléfono nuevo, si existe
        if (!is_null($sNuevoTelefono) && !preg_match('/^\d+$/', $sNuevoTelefono)) {
            $errcode = 400; $errdesc = 'Bad request: invalid new phone';
            return FALSE;
        }

        // Revisar horarios
        if (is_array($horario)) {
            // Formatos correctos de fecha
            if (!isset($horario['date_init']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $horario['date_init'])) {
                $this->_log->output('ERR: al agendar llamada: fecha de inicio inválida, se espera YYYY-MM-DD');
                $errcode = 400; $errdesc = 'Bad request: invalid date_init';
                return FALSE;
            } elseif (!isset($horario['date_end']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $horario['date_end'])) {
                $this->_log->output('ERR: al agendar llamada: fecha de fin inválida, se espera YYYY-MM-DD');
                $errcode = 400; $errdesc = 'Bad request: invalid date_end';
                return FALSE;
            } elseif (!isset($horario['time_init']) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $horario['time_init'])) {
                $this->_log->output('ERR: al agendar llamada: hora de inicio inválida, se espera HH:MM:SS');
                $errcode = 400; $errdesc = 'Bad request: invalid time_init';
                return FALSE;
            } elseif (!isset($horario['time_end']) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $horario['time_end'])) {
                $this->_log->output('ERR: al agendar llamada: hora de fin inválida, se espera HH:MM:SS');
                $errcode = 400; $errdesc = 'Bad request: invalid time_end';
                return FALSE;
            }

            // Ordenamiento correcto
            if ($horario['date_init'] > $horario['date_end']) {
                $t = $horario['date_init'];
                $horario['date_init'] = $horario['date_end'];
                $horario['date_end'] = $t;
            }

            // Fecha debe estar en el futuro
            if ($horario['date_init'] < date('Y-m-d')) {
                $this->_log->output('ERR: al agendar llamada: fecha de inicio anterior a fecha actual');
                $errcode = 400; $errdesc = 'Bad request: date_init before current date';
                return FALSE;
            }
        } elseif (!is_null($horario)) {
            $this->_log->output('ERR: (internal) al agendar llamada: horario no es un arreglo');
            return FALSE;
        }

        // Información de la llamada atendida por el agente
        if (!is_null($calltype) && !is_null($callid)) {
            // Verificar si la llamada existe y el agente está autorizado
            switch ($calltype) {
            case 'outgoing':
                $sql = 'SELECT COUNT(*) AS N FROM calls WHERE id = ?';
                $params = array($callid);
                break;
            }
            $recordset = $this->_db->prepare($sql);
            $recordset->execute($params);
            $tuplaCheck = $recordset->fetch(PDO::FETCH_ASSOC);
            $recordset->closeCursor();
            if ($tuplaCheck['N'] <= 0) {
                $this->_log->output('WARN: '.__METHOD__.': llamada '.$calltype.' con callid='.$callid.
                    ' no se encuentra para agent='.$sAgente.', se ignoran valores...');
                $calltype = NULL;
                $callid = NULL;
            }
        }
        if (is_null($calltype) || is_null($callid)) {
            $infoLlamada = $this->_tuberia->AMIEventProcess_reportarInfoLlamadaAtendida($sAgente);
            if (is_null($infoLlamada)) {
                $errcode = 417; $errdesc = 'Not in outgoing call';
                return FALSE;
            }
            $calltype = $infoLlamada['calltype'];
            $callid = $infoLlamada['callid'];
        }

        switch ($calltype) {
        case 'outgoing':
            return $this->_agendarLlamadaAgente_outgoing($callid, $sAgente, $horario, $bMismoAgente,
                $sNuevoTelefono, $sNuevoNombre, $errcode, $errdesc);
        default:
            $errcode = 417; $errdesc = 'Not in outgoing call';
            return FALSE;
        }
    }

    private function _agendarLlamadaAgente_outgoing($callid, $sAgente, $horario, $bMismoAgente,
        $sNuevoTelefono, $sNuevoNombre, &$errcode, &$errdesc)
    {
        // Leer toda la información de la campaña y la cola
        $sqlLlamadaCampania = <<<SQL_LLAMADA_CAMPANIA_AGENDAMIENTO
SELECT campaign.datetime_init, campaign.datetime_end, campaign.daytime_init,
    campaign.daytime_end, calls.id_campaign, calls.phone
FROM campaign, calls
WHERE campaign.id = calls.id_campaign AND calls.id = ?
SQL_LLAMADA_CAMPANIA_AGENDAMIENTO;
        $recordset = $this->_db->prepare($sqlLlamadaCampania);
        $recordset->execute(array($callid));
        $tuplaCampania = $recordset->fetch(PDO::FETCH_ASSOC);
        $recordset->closeCursor();

        // Validar que el rango de fecha y hora requerido es compatible con campaña
        if (is_array($horario)) {
            if (!($tuplaCampania['datetime_init'] <= $horario['date_init'] &&
                $horario['date_end'] <= $tuplaCampania['datetime_end'])) {
                $errcode = 417; $errdesc = 'Supplied date range outside campaign range';
                return FALSE;
            }
            if (!($tuplaCampania['daytime_init'] <= $horario['time_init'] &&
                $horario['time_end'] <= $tuplaCampania['daytime_end'])) {
                $errcode = 417; $errdesc = 'Supplied time range outside campaign range';
                return FALSE;
            }
        }

        // Acumular los parámetros de la nueva llamada por insertar
        // DEBEN PERMANECER EN ESTE ORDEN
        $paramNuevaLlamadaSQL = array(
            $tuplaCampania['id_campaign'],  // TODO: se puede mandar llamada a otra campaña...
            is_null($sNuevoTelefono) ? $tuplaCampania['phone'] : $sNuevoTelefono,
            is_null($horario) ? NULL : $horario['date_init'],
            is_null($horario) ? NULL : $horario['date_end'],
            is_null($horario) ? NULL : $horario['time_init'],
            is_null($horario) ? NULL : $horario['time_end'],
        );

        // Leer los atributos a heredar de la llamada, para (opcionalmente) modificarlos
        $sqlLlamadaAtributos = <<<SQL_LLAMADA_ATRIBUTOS_AGENDAMIENTO
SELECT column_number, columna, value FROM call_attribute
WHERE id_call = ?
ORDER BY column_number
SQL_LLAMADA_ATRIBUTOS_AGENDAMIENTO;
        $recordset = $this->_db->prepare($sqlLlamadaAtributos);
        $recordset->execute(array($callid));
        $attrLlamada = array();
        foreach ($recordset as $tupla) {
        	$attrLlamada[$tupla['column_number']] = array($tupla['columna'], $tupla['value']);
        }
        if (!is_null($sNuevoNombre)) {
            // Columnas de propiedades se numeran desde 1
            if (!isset($attrLlamada[1])) $attrLlamada[1] = array('Campo1', $sNuevoNombre);
            $attrLlamada[1][1] = $sNuevoNombre;
        }

        // Leer los datos de los formularios para la llamada
        $sqlLlamadaForm = <<<SQL_LLAMADA_FORM_STATIC
SELECT id_form_field, value FROM form_data_recolected
WHERE id_calls = ?
SQL_LLAMADA_FORM_STATIC;
        $recordset = $this->_db->prepare($sqlLlamadaForm);
        $recordset->execute(array($callid));
        $formLlamada = array();
        foreach ($recordset as $tupla) {
            $formLlamada[$tupla['id_form_field']] = $tupla['value'];
        }

        // Validar que no exista una llamada por agendar al mismo número
        $sqlExistenciaLlamadaPrevia = <<<SQL_LLAMADA_PREVIA
SELECT COUNT(*) FROM calls
WHERE id_campaign = ? AND phone = ? AND date_init = ? AND date_end = ?
    AND time_init = ? AND time_end = ?
SQL_LLAMADA_PREVIA;
        $recordset = $this->_db->prepare($sqlExistenciaLlamadaPrevia);
        $recordset->execute($paramNuevaLlamadaSQL);
        $existe = $recordset->fetchColumn(0);
        $recordset->closeCursor();
        if ($existe > 0) {
            $errcode = 417; $errdesc = 'Found duplicate scheduled call';
            return FALSE;
        }

        try {
            // Inicio de transacción
            $this->_db->beginTransaction();

            // Agregar agente a agendar, si es necesario, e insertar
            $paramNuevaLlamadaSQL[] = $bMismoAgente ? $sAgente : NULL;
            $sqlInsertarLlamadaAgendada = <<<SQL_INSERTAR_AGENDAMIENTO
INSERT INTO calls (scheduled, id_campaign, phone, date_init, date_end, time_init, time_end, agent)
VALUES (1, ?, ?, ?, ?, ?, ?, ?)
SQL_INSERTAR_AGENDAMIENTO;
            $sth = $this->_db->prepare($sqlInsertarLlamadaAgendada);
            $sth->execute($paramNuevaLlamadaSQL);
            $idNuevaLlamada = $this->_db->lastInsertId();

            // Insertar atributos para la nueva llamada
            $sth = $this->_db->prepare(
                'INSERT INTO call_attribute (columna, value, column_number, id_call) '.
                'VALUES (?, ?, ?, ?)');
            foreach ($attrLlamada as $iColNum => $tuplaAttr) {
                // Se asume elemento 0 es 'columna', 1 es 'value' en call_attribute
                $tuplaAttr[] = $iColNum;        // Debería ser posición 2
                $tuplaAttr[] = $idNuevaLlamada; // Debería ser posición 3
                $sth->execute($tuplaAttr);
            }

            // Insertar valores de formularios
            $sth = $this->_db->prepare(
                'INSERT INTO form_data_recolected (value, id_form_field, id_calls) '.
                'VALUES (?, ?, ?)');
            foreach ($formLlamada as $id_ff => $value) {
                $sth->execute(array($value, $id_ff, $idNuevaLlamada));
            }

            // Final de transacción
            $this->_db->commit();
            return TRUE;
        } catch (PDOException $e) {
            $this->_log->output('ERR: '.__METHOD__.
                ': no se puede realizar inserción de llamada agendada: '.
                implode(' - ', $e->errorInfo));
            $errcode = 500; $errdesc = 'Failed to insert scheduled call';
        	$this->_db->rollBack();
            return FALSE;
        }
    }

    private function Request_agentauth_transfercall($comando)
    {
        if (is_null($this->_ami))
            return $this->_generarRespuestaFallo(500, 'No AMI connection');

        $sAgente = (string)$comando->agent_number;

        // Verificar que número de extensión está presente
        if (!isset($comando->extension))
            return $this->_generarRespuestaFallo(400, 'Bad request');
        $sExtension = (string)$comando->extension;
        if (!ctype_digit($sExtension))
            return $this->_generarRespuestaFallo(400, 'Bad request');

        $xml_response = new SimpleXMLElement('<response />');
        $xml_transferResponse = $xml_response->addChild('transfercall_response');

        // El siguiente código asume formato Agent/9000
        if (is_null($this->_parseAgent($sAgente))) {
            $this->_agregarRespuestaFallo($xml_transferResponse, 404, 'Specified agent not found');
            return $xml_response;
        }

        $infoSeguimiento = $this->_tuberia->AMIEventProcess_infoSeguimientoAgente($sAgente);
        if (is_null($infoSeguimiento)) {
            $this->_agregarRespuestaFallo($xml_transferResponse, 404, 'Specified agent not found');
            return $xml_response;
        }
        $sCanalRemoto = $infoSeguimiento['clientchannel'];
        if (is_null($sCanalRemoto)) {
            $this->_agregarRespuestaFallo($xml_transferResponse, 417, 'Agent not in call');
            return $xml_response;
        }

        // Obtener la información de la llamada atendida por el agente
        $infoLlamada = $this->_tuberia->AMIEventProcess_reportarInfoLlamadaAtendida($sAgente);
        if (is_null($infoLlamada) || is_null($infoLlamada['callid'])) {
            $this->_agregarRespuestaFallo($xml_transferResponse, 417, 'Agent not in call');
            return $xml_response;
        }

        // Mandar a transferir la llamada usando el canal Agent/9000
        $r = $this->_ami->Redirect(
            $sCanalRemoto,      // channel
            '',                 // extrachannel
            $sExtension,        // exten
            'from-internal',    // context
            1);                 // priority
        if ($r['Response'] != 'Success') {
            $this->_log->output('ERR: '.__METHOD__.': al transferir llamada: no se puede transferir '.
                $sCanalRemoto.' a '.$sExtension.' - '.$r['Message']);
            $this->_agregarRespuestaFallo($xml_transferResponse, 500, 'Unable to transfer call');
            return $xml_response;
        } else {
            $this->_registrarTransferencia($infoLlamada, $sExtension);
        }

        $xml_transferResponse->addChild('success');
        return $xml_response;
    }

    private function Request_agentauth_atxfercall($comando)
    {
        if (is_null($this->_ami))
            return $this->_generarRespuestaFallo(500, 'No AMI connection');

        $sAgente = (string)$comando->agent_number;

        // Verificar que número de extensión está presente
        if (!isset($comando->extension))
            return $this->_generarRespuestaFallo(400, 'Bad request');
        $sExtension = (string)$comando->extension;
        if (!ctype_digit($sExtension))
            return $this->_generarRespuestaFallo(400, 'Bad request');

        $xml_response = new SimpleXMLElement('<response />');
        $xml_transferResponse = $xml_response->addChild('atxfercall_response');

        // Obtener la información de la llamada atendida por el agente
        $infoLlamada = $this->_tuberia->AMIEventProcess_reportarInfoLlamadaAtendida($sAgente);
        if (is_null($infoLlamada)) {
            $this->_agregarRespuestaFallo($xml_transferResponse, 404, 'Specified agent not found');
            return $xml_response;
        }
        if (is_null($infoLlamada['agentchannel'])) {
            $this->_agregarRespuestaFallo($xml_transferResponse, 417, 'Agent not in call');
            return $xml_response;
        }

        // Mandar a transferir la llamada usando el canal Agent/9000
        $r = $this->_ami->Atxfer(
            $infoLlamada['agentchannel'],
            $sExtension.'#',    // exten
            'from-internal',    // context
            1);                 // priority
        if ($r['Response'] != 'Success') {
            $this->_log->output('ERR: '.__METHOD__.': al transferir llamada: no se puede transferir '.
                $infoLlamada['agentchannel'].' a '.$sExtension.' - '.$r['Message']);
            $this->_agregarRespuestaFallo($xml_transferResponse, 500, 'Unable to transfer call');
            return $xml_response;
        } else {
            $this->_registrarTransferencia($infoLlamada, $sExtension);
        }

        $xml_transferResponse->addChild('success');
        return $xml_response;
    }

    private function _registrarTransferencia($infoLlamada, $sExtension)
    {
    	$sth = $this->_db->prepare(
            'UPDATE '.(($infoLlamada['calltype'] == 'incoming') ? 'call_entry' : 'calls').
            ' SET transfer = ? WHERE id = ?');
        $sth->execute(array($sExtension, $infoLlamada['callid']));
    }

    private function Request_agentauth_hold($comando)
    {
        $sAgente = (string)$comando->agent_number;

        $xml_response = new SimpleXMLElement('<response />');
        $xml_holdResponse = $xml_response->addChild('hold_response');

        // Obtener el ID del break que corresponde al hold
        $recordset = $this->_db->prepare('SELECT id FROM break WHERE tipo = "H" AND status = "A"');
        $recordset->execute();
        $idHold = $recordset->fetchColumn(0);
        $recordset->closeCursor();

        // Verificar si el agente está siendo monitoreado
        $infoSeguimiento = $this->_tuberia->AMIEventProcess_infoSeguimientoAgente($sAgente);
        if (is_null($infoSeguimiento)) {
            $this->_agregarRespuestaFallo($xml_holdResponse, 404, 'Agent not found or not logged in through ECCP');
            return $xml_response;
        }
        if ($infoSeguimiento['estado_consola'] != 'logged-in') {
            $this->_agregarRespuestaFallo($xml_holdResponse, 417, 'Agent currently not logged in');
            return $xml_response;
        }
        $sCanalRemoto = $infoSeguimiento['clientchannel'];
        if (is_null($sCanalRemoto)) {
            $this->_agregarRespuestaFallo($xml_holdResponse, 417, 'Agent not in call');
            return $xml_response;
        }

        // Obtener la información de la llamada atendida por el agente
        $infoLlamada = $this->_tuberia->AMIEventProcess_reportarInfoLlamadaAtendida($sAgente);
        if (is_null($infoLlamada) || is_null($infoLlamada['callid'])) {
            $this->_agregarRespuestaFallo($xml_holdResponse, 417, 'Agent not in call');
            return $xml_response;
        }

        if (!is_null($infoSeguimiento['id_audit_hold'])) {
            // Agente ya estaba en hold
            $this->_agregarRespuestaFallo($xml_holdResponse, 417, 'Agent already in hold');
            return $xml_response;
        }

        // Se escribe el inicio provisional de la pausa en la base de datos
        $iTimestampInicioPausa = time();
        $idAuditHold = $this->_marcarInicioBreakAgente(
            $infoSeguimiento['id_agent'], $idHold, $iTimestampInicioPausa);
        if (is_null($idAuditHold)) {
            $this->_agregarRespuestaFallo($xml_holdResponse, 500, 'Unable to start agent hold');
            return $xml_response;
        }

        // Se comunica a AMIEventProcess la pausa elegida para que la inicie.
        // Esto puede fallar si el estado del agente ha cambiado.
        list($errcode, $errdesc) = $this->_tuberia->AMIEventProcess_iniciarHoldAgente(
            $sAgente, $idHold, $idAuditHold, $iTimestampInicioPausa);
        if ($errcode != 0) {
            // Ha fallado el inicio de pausa, se deshace auditoría
            try {
                $sth = $this->_db->prepare('DELETE FROM audit WHERE id = ?');
                $sth->execute(array($idAuditHold));
                $sth = NULL;
            } catch (PDOException $e) {
                $this->_stdManejoExcepcionDB($e, 'no se puede quitar auditoría provisional!');
            }
            $this->_agregarRespuestaFallo($xml_holdResponse, $errcode, $errdesc);
            return $xml_response;
        }

        $xml_holdResponse->addChild('success');
        return array(
            'response'  =>  $xml_response,
            'eventos'   =>  array(
                array('PauseStart', array($sAgente, array(
                    'pause_class'   =>  'hold',
                    'pause_start'   =>  date('Y-m-d H:i:s', $iTimestampInicioPausa),
                ))),
            ),
        );

    }

    private function Request_agentauth_unhold($comando)
    {
        if (is_null($this->_ami))
            return $this->_generarRespuestaFallo(500, 'No AMI connection');

        $sAgente = (string)$comando->agent_number;

        $xml_response = new SimpleXMLElement('<response />');
        $xml_unholdResponse = $xml_response->addChild('unhold_response');

        // Verificar si el agente está siendo monitoreado
        $infoSeguimiento = $this->_tuberia->AMIEventProcess_infoSeguimientoAgente($sAgente);
        if (is_null($infoSeguimiento)) {
            $this->_agregarRespuestaFallo($xml_unholdResponse, 404, 'Specified agent not found');
            return $xml_response;
        }
        if ($infoSeguimiento['estado_consola'] != 'logged-in') {
            $this->_agregarRespuestaFallo($xml_unholdResponse, 417, 'Agent currently not logged in');
            return $xml_response;
        }
        $sCanalRemoto = $infoSeguimiento['clientchannel'];
        if (is_null($sCanalRemoto)) {
            $this->_agregarRespuestaFallo($xml_unholdResponse, 417, 'Agent not in call');
            return $xml_response;
        }

        // Obtener la información de la llamada atendida por el agente
        $infoLlamada = $this->_tuberia->AMIEventProcess_reportarInfoLlamadaAtendida($sAgente);
        if (is_null($infoLlamada) || is_null($infoLlamada['callid'])) {
            $this->_agregarRespuestaFallo($xml_unholdResponse, 417, 'Agent not in call');
            return $xml_response;
        }

        // Si el agente no estaba en hold, se devuelve éxito sin hacer nada más
        if (is_null($infoSeguimiento['id_audit_hold'])) {
            $xml_unholdResponse->addChild('success');
            return $xml_response;
        }

        if (!is_null($infoLlamada['park_exten'])) {
            $sActionID = 'ECCP:1.0:'.posix_getpid().':RedirectFromHold';
            if ($this->DEBUG) {
                $this->_log->output("DEBUG: intentando recuperar llamada:\n".
                    "\tChannel      =>  $sAgente\n".
                    "\tExten        =>  {$infoLlamada['park_exten']}\n".
                    "\tContext      =>  from-internal\n".
                    "\tActionID     =>  $sActionID");
            }

            // Sacar la llamada del parqueo y redirigirla al agente pausado
            $r = $this->_ami->Originate(
                $sAgente,               // channel
                $infoLlamada['park_exten'],  // extension
                'from-internal',        // context
                '1',                    // priority
                NULL, NULL, NULL, NULL, NULL, NULL,
                TRUE,                   // async
                $sActionID
                );
            if ($r['Response'] != 'Success') {
                $this->_log->output('ERR: al terminar hold: no se puede retomar llamada - '.$r['Message']);
            }
            if ($this->DEBUG) {
                $this->_log->output('DEBUG: Originate para recuperar llamada devuelve: '.print_r($r, 1));
            }
        }

        // Se delega registro de final de HOLD a manejadores de eventos

        $xml_unholdResponse->addChild('success');
        return $xml_response;
    }

    private function Request_eccpauth_getagentqueues($comando)
    {
        if (is_null($this->_ami))
            return $this->_generarRespuestaFallo(500, 'No AMI connection');

        // Verificar que agente está presente
        if (!isset($comando->agent_number))
            return $this->_generarRespuestaFallo(400, 'Bad request');
        $sAgente = (string)$comando->agent_number;

        $xml_response = new SimpleXMLElement('<response />');
        $xml_getagentqueuesResponse = $xml_response->addChild('getagentqueues_response');

        // Verificar que la extensión y el agente son válidos en el sistema
        if (!$this->_existeAgente($sAgente)) {
            $this->_agregarRespuestaFallo($xml_getagentqueuesResponse, 404, 'Specified agent not found');
            return $xml_response;
        }

        // Reportar las colas a las que el agente está suscrito o puede suscribirse
        $listaColas = $this->_tuberia->AMIEventProcess_listarTotalColasTrabajoAgente(array($sAgente));
        $xml_agentQueues = $xml_getagentqueuesResponse->addChild('queues');
        if (is_array($listaColas) && isset($listaColas[$sAgente])) {
            // $listaColas[$sAgente][0] son colas suscritas actualmente
            // $listaColas[$sAgente][1] son colas dinámicas a las que puede suscribirse
            foreach (array_unique(array_merge($listaColas[$sAgente][0], $listaColas[$sAgente][1])) as $sCola) {
                $xml_agentQueues->addChild('queue', str_replace('&', '&amp;', $sCola));
            }
        }

        return $xml_response;
    }

    private function Request_eccpauth_getmultipleagentqueues($comando)
    {
        if (is_null($this->_ami))
            return $this->_generarRespuestaFallo(500, 'No AMI connection');

        // Verificar que agente está presente
        if (!isset($comando->agents))
            return $this->_generarRespuestaFallo(400, 'Bad request');

        $xml_response = new SimpleXMLElement('<response />');
        $xml_getagentqueuesResponse = $xml_response->addChild('getmultipleagentqueues_response');

        $agentlist = array();
        foreach ($comando->agents->agent_number as $agent_number) {
            $sAgente = (string)$agent_number;

            // El siguiente código asume formato Agent/9000
            $agentFields = $this->_parseAgent($sAgente);
            if (is_null($agentFields)) {
                $this->_agregarRespuestaFallo($xml_getagentqueuesResponse, 417, 'Invalid agent number');
                return $xml_response;
            }
            $agentFields['queues'] = array();

            $agentlist[$sAgente] = $agentFields;
        }

        // Verificar que todos los agentes existen en el sistema
        $listaAgentes = $this->_listarAgentes();
        $agentesExtras = array_diff(array_keys($agentlist), array_keys($listaAgentes));
        if (count($agentesExtras) > 0) {
            $this->_agregarRespuestaFallo($xml_getagentqueuesResponse, 404, 'Specified agent not found');
            return $xml_response;
        }

        // Acumular las colas estáticas y dinámicas para cada agente
        $listaColas = $this->_tuberia->AMIEventProcess_listarTotalColasTrabajoAgente(array_keys($agentlist));
        foreach ($listaColas as $sAgente => $queuelist) {
            if (isset($agentlist[$sAgente])) {
                // $queuelist[0] son colas suscritas actualmente
                // $queuelist[1] son colas dinámicas a las que puede suscribirse
                $agentlist[$sAgente]['queues'] = array_unique(array_merge($queuelist[0], $queuelist[1]));
            }
        }
        unset($listaColas);

        // Conversión de resultado a XML
        $xml_agents = $xml_getagentqueuesResponse->addChild('agents');
        foreach (array_keys($agentlist) as $sAgente) {
            $xml_agent = $xml_agents->addChild('agent');
            $xml_agent->addChild('agent_number', str_replace('&', '&amp;', $sAgente));
            $xml_agentQueues = $xml_agent->addChild('queues');
            foreach ($agentlist[$sAgente]['queues'] as $sCola) {
                $xml_agentQueues->addChild('queue', str_replace('&', '&amp;', $sCola));
            }
        }

        return $xml_response;
    }

    private function Request_eccpauth_getagentactivitysummary($comando)
    {
        // Fechas de inicio y fin
        $sFechaInicio = $sFechaFin = date('Y-m-d');
        if (isset($comando->datetime_start)) {
            $sFechaInicio = (string)$comando->datetime_start;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sFechaInicio))
                return $this->_generarRespuestaFallo(400, 'Bad request - invalid start date');
        }
        if (isset($comando->datetime_end)) {
            $sFechaFin = (string)$comando->datetime_end;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sFechaFin))
                return $this->_generarRespuestaFallo(400, 'Bad request - invalid end date');
        }
        if (!is_null($sFechaInicio) && !is_null($sFechaFin) && $sFechaFin < $sFechaInicio) {
            $t = $sFechaInicio;
            $sFechaInicio = $sFechaFin;
            $sFechaFin = $t;
        }

        $xml_response = new SimpleXMLElement('<response />');
        $xml_getagentactivitysummaryResponse = $xml_response->addChild('getagentactivitysummary_response');

        // Leer la información de los agentes conocidos y su historial de sesión
        $sPeticionSQL = <<<LEER_AGENTE_AUDIT
SELECT agent.id, agent.type, agent.number, agent.name, SUM(TIME_TO_SEC(duration)) AS total_login_time
FROM agent
LEFT JOIN audit
    ON agent.id = audit.id_agent AND audit.id_break IS NULL
    AND audit.datetime_init BETWEEN ? AND ?
WHERE estatus = 'A' GROUP BY agent.id
LEER_AGENTE_AUDIT;
        $recordset = $this->_db->prepare($sPeticionSQL);
        $recordset->execute(array($sFechaInicio.' 00:00:00', $sFechaFin.' 23:59:59'));
        $listaAgentes = $recordset->fetchAll(PDO::FETCH_ASSOC);
        $recordset->closeCursor();

        $sPeticionSQL_sumallamadasAgente = <<<LEER_HISTORIAL_ATENCION
(SELECT call_entry.id_agent, 'incoming' AS campaign_type, queue_call_entry.queue AS queue,
    SUM(call_entry.duration) AS sec_calls, COUNT(*) AS num_calls
FROM call_entry, queue_call_entry
WHERE call_entry.id_queue_call_entry = queue_call_entry.id
    AND call_entry.datetime_init BETWEEN ? AND ?
GROUP BY call_entry.id_agent, queue_call_entry.queue)
UNION
(SELECT calls.id_agent, 'outgoing' AS campaign_type, campaign.queue,
    SUM(calls.duration) AS sec_calls, COUNT(*) AS num_calls
FROM calls, campaign
WHERE calls.id_campaign = campaign.id
    AND calls.start_time BETWEEN ? AND ?
GROUP BY calls.id_agent, campaign.queue)
LEER_HISTORIAL_ATENCION;
        $recordset_sumallamadasAgente = $this->_db->prepare($sPeticionSQL_sumallamadasAgente);
        $recordset_sumallamadasAgente->execute(array(
            $sFechaInicio.' 00:00:00', $sFechaFin.' 23:59:59',
            $sFechaInicio.' 00:00:00', $sFechaFin.' 23:59:59'
        ));
        $historialAtencion = array();
        foreach ($recordset_sumallamadasAgente->fetchAll(PDO::FETCH_ASSOC) as $tupla) {
            $id_agent = array_shift($tupla);
            $historialAtencion[$id_agent][$tupla['campaign_type']][] = $tupla;
        }
        $recordset_sumallamadasAgente->closeCursor();

        $sPeticionSQL_ultimasesionAgente = <<<LEER_ULTIMA_SESION
SELECT a.id_agent, a.datetime_init, a.datetime_end
FROM audit a
LEFT OUTER JOIN audit b
	ON b.id_break IS NULL
	AND a.id_agent = b.id_agent
	AND ((a.datetime_init < b.datetime_init)
		OR (a.datetime_init = b.datetime_init AND a.id < b.id))
	AND b.datetime_init BETWEEN ? AND ?
WHERE a.id_break IS NULL
	AND a.datetime_init BETWEEN ? AND ?
	AND b.id_agent IS NULL
LEER_ULTIMA_SESION;
        $recordset_ultimasesionAgente = $this->_db->prepare($sPeticionSQL_ultimasesionAgente);
        $recordset_ultimasesionAgente->execute(array(
            $sFechaInicio.' 00:00:00', $sFechaFin.' 23:59:59',
            $sFechaInicio.' 00:00:00', $sFechaFin.' 23:59:59'
        ));
        $ultimasesion = array();
        foreach ($recordset_ultimasesionAgente->fetchAll(PDO::FETCH_ASSOC) as $tupla) {
            $ultimasesion[$tupla['id_agent']] = $tupla;
        }
        $recordset_ultimasesionAgente->closeCursor();

        $sPeticionSQL_ultimapausaAgente = <<<LEER_ULTIMA_SESION
SELECT a.id_agent, a.datetime_init, a.datetime_end
FROM audit a
LEFT OUTER JOIN audit b
    ON b.id_break IS NOT NULL
    AND a.id_agent = b.id_agent
    AND ((a.datetime_init < b.datetime_init)
        OR (a.datetime_init = b.datetime_init AND a.id < b.id))
    AND b.datetime_init BETWEEN ? AND ?
WHERE a.id_break IS NOT NULL
    AND a.datetime_init BETWEEN ? AND ?
    AND b.id_agent IS NULL
LEER_ULTIMA_SESION;
        $recordset_ultimapausaAgente = $this->_db->prepare($sPeticionSQL_ultimapausaAgente);
        $recordset_ultimapausaAgente->execute(array(
            $sFechaInicio.' 00:00:00', $sFechaFin.' 23:59:59',
            $sFechaInicio.' 00:00:00', $sFechaFin.' 23:59:59'
        ));
        $ultimapausa = array();
        foreach ($recordset_ultimapausaAgente->fetchAll(PDO::FETCH_ASSOC) as $tupla) {
            $ultimapausa[$tupla['id_agent']] = $tupla;
        }
        $recordset_ultimapausaAgente->closeCursor();

        // Construir el árbol de salida, y consultar el historial de atención de llamadas
        $xml_agents = $xml_getagentactivitysummaryResponse->addChild('agents');
        foreach ($listaAgentes as $infoAgente) {
        	$xml_agent = $xml_agents->addChild('agent');
            $xml_agent->addChild('agentchannel', $infoAgente['type'].'/'.$infoAgente['number']);
            $xml_agent->addChild('agentname', str_replace('&', '&amp;', $infoAgente['name']));
            $xml_agent->addChild('logintime', is_null($infoAgente['total_login_time']) ? 0 : $infoAgente['total_login_time']);

            $listaResumen = array('incoming' => array(), 'outgoing' => array());
            if (isset($historialAtencion[$infoAgente['id']])) foreach (array_keys($listaResumen) as $k) {
                if (isset($historialAtencion[$infoAgente['id']][$k]))
                    $listaResumen[$k] = $historialAtencion[$infoAgente['id']][$k];
            }

            $xml_callsummary = $xml_agent->addChild('callsummary');
            foreach (array('incoming', 'outgoing') as $k) {
            	if (!isset($listaResumen[$k])) $listaResumen[$k] = array();
                $xml_campaigntype = $xml_callsummary->addChild($k);
                foreach ($listaResumen[$k] as $queuesummary) {
                	$xml_queue = $xml_campaigntype->addChild('queue');
                    $xml_queue->addAttribute('id', (string)$queuesummary['queue']);
                    $xml_queue->addChild('sec_calls', $queuesummary['sec_calls']);
                    $xml_queue->addChild('num_calls', $queuesummary['num_calls']);
                }
            }

            // Información sobre inicio y final de sesión más reciente del agente
            if (isset($ultimasesion[$infoAgente['id']])) {
                $xml_agent->addChild('lastsessionstart', $ultimasesion[$infoAgente['id']]['datetime_init']);
                if (!is_null($ultimasesion[$infoAgente['id']]['datetime_end']))
                    $xml_agent->addChild('lastsessionend', $ultimasesion[$infoAgente['id']]['datetime_end']);
            }

            // Información sobre inicio y final de pausa más reciente del agente
            if (isset($ultimapausa[$infoAgente['id']])) {
                $xml_agent->addChild('lastpausestart', $ultimapausa[$infoAgente['id']]['datetime_init']);
                if (!is_null($ultimapausa[$infoAgente['id']]['datetime_end']))
                    $xml_agent->addChild('lastpauseend', $ultimapausa[$infoAgente['id']]['datetime_end']);
            }
        }
        return $xml_response;
    }

    private function Request_agentauth_getchanvars($comando)
    {
        if (is_null($this->_ami))
            return $this->_generarRespuestaFallo(500, 'No AMI connection');

        $sAgente = (string)$comando->agent_number;

        $xml_response = new SimpleXMLElement('<response />');
        $xml_getchanvarsResponse = $xml_response->addChild('getchanvars_response');

        // Verificar si el agente está siendo monitoreado
        $infoSeguimiento = $this->_tuberia->AMIEventProcess_infoSeguimientoAgente($sAgente);
        if (is_null($infoSeguimiento)) {
            $this->_agregarRespuestaFallo($xml_getchanvarsResponse, 404, 'Specified agent not found');
            return $xml_response;
        }
        if ($infoSeguimiento['estado_consola'] != 'logged-in') {
            $this->_agregarRespuestaFallo($xml_getchanvarsResponse, 417, 'Agent currently not logged in');
            return $xml_response;
        }
        $sCanalRemoto = $infoSeguimiento['clientchannel'];
        if (is_null($sCanalRemoto)) {
            $this->_agregarRespuestaFallo($xml_getchanvarsResponse, 417, 'Agent not in call');
            return $xml_response;
        }
        $xml_getchanvarsResponse->addChild('clientchannel', str_replace('&', '&amp;', $sCanalRemoto));
        $xml_chanvars = $xml_getchanvarsResponse->addChild('chanvars');

        // Listar la información disponible sobre las variables de canal
        $respuesta = $this->_ami->Command('core show channel '.$sCanalRemoto);
        if (isset($respuesta['data'])) {
        	$bSeccionVars = FALSE;
            foreach (explode("\n", $respuesta['data']) as $sLinea) {
            	$regs = NULL;
                if (preg_match('/^\s+Variables:\s*$/', $sLinea)) {
                    $bSeccionVars = TRUE;
                } elseif ($bSeccionVars && preg_match('/^(\w+)=(.*)$/', $sLinea, $regs)) {
                	$xml_chanvar = $xml_chanvars->addChild('chanvar');
                    $xml_chanvar->addChild('label', str_replace('&', '&amp;', $regs[1]));
                    $xml_chanvar->addChild('value', str_replace('&', '&amp;', $regs[2]));
                } elseif (trim($sLinea) == '') {
                	$bSeccionVars = FALSE;
                }
            }
        } else {
            $this->_log->output('ERR: lost synch with Asterisk AMI ("core show channel" response lacks "data").');
            return $this->_generarRespuestaFallo(500, 'No AMI connection');
        }
        return $xml_response;
    }

    private function Request_eccpauth_callprogress($comando)
    {
        $xml_response = new SimpleXMLElement('<response />');
        $xml_callprogress = $xml_response->addChild('callprogress_response');

        $xml_callprogress->addChild('success');
        return array(
            'response'          =>  $xml_response,
            'nuevos_valores'    =>  array(
                'progresollamada'   =>  ((int)$comando->enable != 0),
            ),
        );
    }

    private function Request_eccpauth_campaignlog($comando)
    {
        // Fechas de inicio y fin
        $sFechaInicio = $sFechaFin = date('Y-m-d');
        if (isset($comando->datetime_start)) {
            $sFechaInicio = (string)$comando->datetime_start;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sFechaInicio))
                return $this->_generarRespuestaFallo(400, 'Bad request - invalid start date');
        }
        if (isset($comando->datetime_end)) {
            $sFechaFin = (string)$comando->datetime_end;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sFechaFin))
                return $this->_generarRespuestaFallo(400, 'Bad request - invalid end date');
        }
        if (!is_null($sFechaInicio) && !is_null($sFechaFin) && $sFechaFin < $sFechaInicio) {
            $t = $sFechaInicio;
            $sFechaInicio = $sFechaFin;
            $sFechaFin = $t;
        }

        // Verificar que id y tipo está presente
        $idCampania = $sCola = NULL;
        if (!isset($comando->campaign_type))
            return $this->_generarRespuestaFallo(400, 'Bad request');
        $sTipoCampania = (string)$comando->campaign_type;
        if (!in_array($sTipoCampania, array('incoming', 'outgoing')))
            return $this->_generarRespuestaFallo(400, 'Bad request');
        if (isset($comando->campaign_id)) $idCampania = (int)$comando->campaign_id;
        if (isset($comando->queue)) $sCola = (string)$comando->queue;
        if ($sTipoCampania == 'outgoing' && is_null($idCampania))
            return $this->_generarRespuestaFallo(400, 'Bad request');
        if ($sTipoCampania == 'incoming' && (is_null($idCampania) && is_null($sCola)))
            return $this->_generarRespuestaFallo(400, 'Bad request');
        if (!is_null($idCampania)) $sCola = NULL;

        // Verificar si se requieren los últimos N desde el offset indicado
        $iUltimosN = NULL; $idBefore = NULL;
        if (isset($comando->last_n)) {
        	$iUltimosN = (int)$comando->last_n;
            if (isset($comando->idbefore)) $idBefore = (int)$comando->idbefore;
        }

        $xml_response = new SimpleXMLElement('<response />');
        $xml_campaignlogResponse = $xml_response->addChild('campaignlog_response');

        if ($sTipoCampania == 'incoming') {
    	   $sPeticionSQL_leerLog = <<<LOG_CAMPANIA_ENTRANTE
SELECT call_progress_log.id, call_progress_log.datetime_entry,
    call_entry.callerid AS phone, queue_call_entry.queue,
    "incoming" AS campaign_type, call_progress_log.id_campaign_incoming AS campaign_id,
    call_progress_log.id_call_incoming AS call_id, call_progress_log.new_status,
    call_progress_log.retry, call_progress_log.uniqueid, call_progress_log.trunk,
    call_progress_log.duration,
    CONCAT(agent.type, "/", agent.number) AS agentchannel
FROM (call_progress_log, call_entry, queue_call_entry)
LEFT JOIN (agent) ON (call_progress_log.id_agent = agent.id)
WHERE (id_campaign_incoming = ? OR (? IS NULL AND id_campaign_incoming IS NULL))
    AND (? IS NULL OR queue_call_entry.queue = ?)
    AND call_progress_log.id_call_incoming = call_entry.id
    AND call_entry.id_queue_call_entry = queue_call_entry.id
    AND call_progress_log.datetime_entry BETWEEN ? AND ?
    AND ((? IS NULL) OR (call_progress_log.id < ?))
ORDER BY id
LOG_CAMPANIA_ENTRANTE;
            $paramSQL = array($idCampania, $idCampania, $sCola, $sCola,
                $sFechaInicio.' 00:00:00', $sFechaFin.' 23:59:59',
                $idBefore, $idBefore);
        } else {
            $sPeticionSQL_leerLog = <<<LOG_CAMPANIA_SALIENTE
SELECT call_progress_log.id, call_progress_log.datetime_entry,
    calls.phone AS phone, campaign.queue,"outgoing" AS campaign_type,
    call_progress_log.id_campaign_outgoing AS campaign_id,
    call_progress_log.id_call_outgoing AS call_id,
    call_progress_log.new_status, call_progress_log.retry,
    call_progress_log.uniqueid, call_progress_log.trunk,
    call_progress_log.duration,
    CONCAT(agent.type, "/", agent.number) AS agentchannel
FROM (call_progress_log, calls, campaign)
LEFT JOIN (agent) ON (call_progress_log.id_agent = agent.id)
WHERE id_campaign_outgoing = ?
    AND call_progress_log.id_call_outgoing = calls.id
    AND calls.id_campaign = campaign.id
    AND call_progress_log.datetime_entry BETWEEN ? AND ?
    AND ((? IS NULL) OR (call_progress_log.id < ?))
ORDER BY id
LOG_CAMPANIA_SALIENTE;
            $paramSQL = array($idCampania,
                $sFechaInicio.' 00:00:00', $sFechaFin.' 23:59:59',
                $idBefore, $idBefore);
        }

        if (!is_null($iUltimosN)) {
        	$sPeticionSQL_leerLog .= ' DESC LIMIT ?';
            $paramSQL[] = $iUltimosN;
        }

        $sth = $this->_db->prepare($sPeticionSQL_leerLog);
        $sth->execute($paramSQL);
        $xml_logentries = $xml_campaignlogResponse->addChild('logentries');
        $recordset = $sth->fetchAll(PDO::FETCH_ASSOC);

        if (!is_null($iUltimosN)) {
        	// Ya que se pidió el orden inverso, se invierte el orden
            $recordset = array_reverse($recordset);
        }

        foreach ($recordset as $tupla) {
            $xml_logentry = $xml_logentries->addChild('logentry');
        	foreach ($tupla as $k => $v) if (!is_null($v)) {
        		$xml_logentry->addChild($k, str_replace('&', '&amp;', $v));
        	}
        }
        return $xml_response;
    }

    private function Request_eccpauth_dumpstatus($comando)
    {
        $xml_response = new SimpleXMLElement('<response />');
        $xml_dumpstatusResponse = $xml_response->addChild('dumpstatus_response');
        $this->_tuberia->AMIEventProcess_dumpstatus();
        $xml_dumpstatusResponse->addChild('success');
        return $xml_response;
    }

    private function Request_eccpauth_refreshagents($comando)
    {
        $xml_response = new SimpleXMLElement('<response />');
        $xml_dumpstatusResponse = $xml_response->addChild('refreshagents_response');
        $this->_tuberia->msg_SQLWorkerProcess_requerir_nuevaListaAgentes();
        $xml_dumpstatusResponse->addChild('success');
        return $xml_response;
    }
}
?>