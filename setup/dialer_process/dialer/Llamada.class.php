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

class Llamada
{
    // Relaciones con otros objetos conocidos
    private $_log;
    private $_tuberia;

    // Referencia a contenedor de llamadas e índice dentro del contenedor
    private $_listaLlamadas;

    // Agente que está atendiendo la llamada, o NULL para llamada sin atender
    var $agente = NULL;

    // Campaña a la que pertenece la llamada, o NULL para llamada entrante sin campaña
    var $campania = NULL;


    // Propiedades específicas de la llamada

    // Tipo de llamada, 'incoming', 'outgoing'
    private $_tipo_llamada;

    /* ID en la base de datos de la llamada, o NULL para llamada entrante sin
     * registrar. Esta propiedad es una de las propiedades indexables en
     * ListaLlamadas, junto con _tipo_llamada */
    private $_id_llamada = NULL;

    /* Cadena de marcado que se ha usado para la llamada saliente. Esta
     * propiedad es una de las propiedades indexables en ListaLlamadas. */
    private $_dialstring = NULL;

    /* Valor de Uniqueid proporcionado por Asterisk para la llamada. Esta
     * propiedad es una de las propiedades indexables en ListaLlamadas. */
    private $_uniqueid = NULL;

    /* Canal indicado por OriginateResponse o Join que puede usarse para
     * redirigir la llamada. Se usa para redirigir la llamada en caso de llamada
     * agendada, y puede que también sirva para manipular en caso de hold y
     * transferencia. Esta propiedad es una de las propiedades indexables en
     * ListaLlamadas. */
    private $_channel = NULL;
    private $_actualchannel = NULL;

    /* Cadena usada en Originate para el valor de ActionID, para identificar
     * esta llamada al recibir el OriginateResponse, en el caso de troncal
     * específica (sin usar plan de marcado). Esta propiedad es una de las
     * propiedades indexables en ListaLlamadas. */
    private $_actionid = NULL;

    /* Estimación de troncal de la llamada, obtenida a partir de Channel de
     * OriginateResponse o Join. Se usa para llamadas entrantes. */
    private $_trunk = NULL;

    /* Estado de la llamada. Para llamadas salientes, el estado puede ser:
     * NULL     Estado inicial, llamada recién ha sido avisada
     * Placing  Se ha iniciado Originate para esta llamada. En este estado debe
     *          de tenerse un valor para timestamp_originate_start, que se
     *          supone fue escrito por CampaignProcess.
     * Ringing  Se ha recibido OriginateResponse para esta llamada. En este
     *          estado debe de tenerse un valor para timestamp_originate_end.
     *          Si no se ha recibido ya Link para la llamada, se escribe Ringing
     *          en la base de datos.
     * OnQueue  Se ha recibido Join para esta llamada. En este estado debe de
     *          tenerse un valor para timestamp_enterqueue. Si no se ha recibido
     *          ya Link para la llamada, se escribe OnQueue en la base de datos.
     * Success  Se ha recibido Link para esta llamada. En este estado debe de
     *          tenerse un valor para timestamp_start. La propiedad duration_wait
     *          se calcula entre timestamp_start y timestamp_enterqueue. Se
     *          escribe Success en la base de datos.
     * OnHold   La llamada ha sido enviada a hold. Se escribe OnHold en la base
     *          de datos.
     * Hangup   La llamada ha sido colgada. En este estado debe de tenerse un
     *          valor para timestamp_end. La propiedad duration_call se calcula
     *          entre timestamp_end y timestamp_start
     * Failure  No se puede colocar la llamada. Este estado puede ocurrir por:
     *          - Llamada falla de inmediato el Originate
     *          - Llamada ha pasado demasiado tiempo en estado Placing
     *          - Se ha recibido OriginateResponse fallido
     *          - Se ha recibido OriginateResponse exitoso, pero se había recibido
     *            previamente un Hangup sobre la misma llamada.
     * ShortCall La llamada ha sido correctamente conectada en Link, pero luego
     *          se cuelga en un tiempo menor al indicado en la configuración
     *          de llamada corta.
     * NoAnswer La llamada fue colgada sin ser conectada antes de entrar a la cola
     * Abandoned La llamada fue colgada sin ser conectada luego de entrar a la cola
     *
     * Puede ocurrir que se reciban los eventos Join y Link antes que
     * OriginateResponse. Entonces se siguen las reglas detalladas arriba para
     * la escritura del estado. Los timestamps siempre se escriben al llegar
     * el respectivo mensaje.
     *
     * Para llamadas entrantes, los estados válidos son:
     * OnQueue  Se escribe 'en-cola' en la base de datos
     * Success  Se escribe 'activa' en la base de datos
     * OnHold   Se escribe 'hold' en la base de datos
     * Hangup   Se escribe 'terminada' si la llamada recibió Link, o 'abandonada'
     *          si no lo hizo.
     */
    private $_status = NULL;

    /* Canal completo del lado de agente. Para agentes estáticos (Agent/xxxx)
     * este valor es idéntico al Agent/xxxx del agente asignado. Para agentes
     * dinámicos, es de la forma (SIP|IAX2)/xxxx-zzzz . Este valor es requerido
     * para realizar la transferencia asistida. */
    private $_agentchannel = NULL;

    var $phone;     // Número marcado para llamada saliente o Caller-ID para llamada entrante
    private $_id_current_call;   // ID del registro correspondiente en current_call[_entry]
    private $_waiting_id_current_call = FALSE;  // Se pone a VERDADERO cuando se espera el id_current_call
    var $request_hold = FALSE;  // Se asigna a VERDADERO al invocar requerimiento hold, y se verifica en Unlink
    private $_park_exten = NULL;// Extensión de lote de parqueo de llamada enviada a hold

    // Timestamps correspondientes a diversos eventos de la llamada
    private $_timestamp_originatestart = NULL;   // Inicio de Originate en CampaignProcess
    private $_timestamp_originateend = NULL;     // Recepción de OriginateResponse
    var $timestamp_enterqueue = NULL;       // Recepción de Join
    var $timestamp_link = NULL;             // Recepción de primer Link
    var $timestamp_hangup = NULL;           // Recepción de Hangup

    // Lista de canales auxiliares asociados a la llamada.
    var $AuxChannels = array();

    // ID de la cola de campaña entrante. Sólo para llamadas entrantes
    var $id_queue_call_entry = NULL;

    private $_queuenumber = NULL;

    // Referencia al agente agendado
    var $agente_agendado = NULL;

    // Actualizaciones pendientes en la base de datos por faltar id_llamada
    private $_actualizacionesPendientes = array();

    /* Esta bandera indica si se ha señalado final de procesamiento de la
     * llamada, y por lo tanto, candidata a ser quitada del seguimiento, cuando
     * todavía no ha llegado el aviso del inicio de Originate. */
    private $_stillborn = FALSE;

    /* Código y texto de causa de fallo al marcar llamada. Una llamada fallida
     * sólo puede quitarse de la lista de llamadas cuando se tiene un valor de
     * fallo válido. */
    private $_failure_cause = NULL;
    private $_failure_cause_txt = NULL;

    // Canales silenciados via mixmonitormute
    private $_mutedchannels = array();

    // Este constructor sólo debe invocarse desde ListaLlamadas::nuevaLlamada()
    function __construct(ListaLlamadas $lista, $tipo_llamada, $tuberia, $log)
    {
    	$this->_listaLlamadas = $lista;
        $this->_tipo_llamada = $tipo_llamada;
        $this->_tuberia = $tuberia;
        $this->_log = $log;
    }

    private function _nul($i) { return is_null($i) ? '(ninguno)' : "$i"; }
    private function _nultime($i) { return is_null($i) ? '----/--/-- --:--:--' : date('Y/m/d H:i:s', $i); }
    private function _agentecorto($a) { return is_null($a) ? '(ninguno)' : $a->__toString(); }

    public function __toString()
    {
        return "ID=".($this->id_llamada).
            " tipo=".($this->tipo_llamada).
            " uniqueid=".($this->uniqueid).
            " channel=".($this->channel).
            " actualchannel=".($this->actualchannel);
    }

    public function dump($log)
    {
        $s = "----- LLAMADA -----\n";
        $s .= "\ttipo_llamada.................".$this->tipo_llamada."\n";
        $s .= "\tid_llamada...................".$this->_nul($this->id_llamada)."\n";
        $s .= "\tphone........................".$this->_nul($this->phone)."\n";
        $s .= "\tdialstring...................".$this->_nul($this->dialstring)."\n";
        $s .= "\tuniqueid.....................".$this->_nul($this->uniqueid)."\n";
        $s .= "\tchannel......................".$this->_nul($this->channel)."\n";
        $s .= "\tactualchannel................".$this->_nul($this->actualchannel)."\n";
        $s .= "\tagentchannel.................".$this->_nul($this->agentchannel)."\n";
        $s .= "\ttrunk........................".$this->_nul($this->trunk)."\n";
        $s .= "\tstatus.......................".$this->_nul($this->status)."\n";
        if (!is_null($this->failure_cause))
            $s .= "\tfailure_cause................".$this->_nul($this->failure_cause)."\n";
        if (!is_null($this->failure_cause_txt))
            $s .= "\tfailure_cause_txt............".$this->_nul($this->failure_cause_txt)."\n";
        $s .= "\tactionid.....................".$this->_nul($this->actionid)."\n";
        if ($this->_waiting_id_current_call) $s .= "\tESPERANDO id_current_call\n";
        $s .= "\tid_current_call..............".$this->_nul($this->id_current_call)."\n";
        $s .= "\tduration.....................".$this->_nul($this->duration)."\n";
        if ($this->_stillborn) $s .= "\tSTILLBORN\n";
        $s .= "\ttimestamp_originatestart.....".$this->_nultime($this->timestamp_originatestart)."\n";
        $s .= "\ttimestamp_originateend.......".$this->_nultime($this->timestamp_originateend)."\n";
        $s .= "\ttimestamp_enterqueue.........".$this->_nultime($this->timestamp_enterqueue)."\n";
        $s .= "\ttimestamp_link...............".$this->_nultime($this->timestamp_link)."\n";
        $s .= "\ttimestamp_hangup.............".$this->_nultime($this->timestamp_hangup)."\n";
        $s .= "\tduration_wait................".$this->_nul($this->duration_wait)."\n";
        $s .= "\tduration_answer..............".$this->_nul($this->duration_answer)."\n";
        $s .= "\tesperando_contestar..........".($this->esperando_contestar ? 'SI' : 'NO')."\n";
        $s .= "\trequest_hold.................".($this->request_hold ? 'SI' : 'NO')."\n";
        $s .= "\tid_queue_call_entry..........".$this->_nul($this->id_queue_call_entry)."\n";
        $s .= "\t_queuenumber.................".$this->_nul($this->_queuenumber)."\n";
        $s .= "\tagente.......................".$this->_agentecorto($this->agente)."\n";
        $s .= "\tagente_agendado..............".$this->_agentecorto($this->agente_agendado)."\n";
        $s .= "\tcampania.....................".(is_null($this->campania) ? '(ninguna)' : $this->campania->__toString())."\n";

        $s .= "\tAuxChannels..................".print_r($this->AuxChannels, TRUE)."\n";
        $s .= "\t_actualizacionesPendientes...".print_r($this->_actualizacionesPendientes, TRUE)."\n";

        $log->output($s);
    }

    public function __get($s)
    {
        switch ($s) {
        case 'tipo_llamada':    return $this->_tipo_llamada;
        case 'id_llamada':      return $this->_id_llamada;
        case 'dialstring':      return $this->_dialstring;
        case 'Uniqueid':
        case 'uniqueid':        return $this->_uniqueid;
        case 'channel':         return $this->_channel;
        case 'actualchannel':   return $this->_actualchannel;
        case 'agentchannel':    return $this->_agentchannel;
        case 'trunk':           return $this->_trunk;
        case 'status':          return $this->_status;
        case 'actionid':        return $this->_actionid;
        case 'stillborn':       return $this->_stillborn;
        case 'failure_cause':   return $this->_failure_cause;
        case 'failure_cause_txt':return $this->_failure_cause_txt;
        case 'timestamp_originatestart':return $this->_timestamp_originatestart;
        case 'timestamp_originateend':  return $this->_timestamp_originateend;
        case 'duration':        return (!is_null($this->timestamp_link) && !is_null($this->timestamp_hangup))
                                        ? $this->timestamp_hangup - $this->timestamp_link : NULL;
        case 'duration_wait':   return (!is_null($this->timestamp_link) && !is_null($this->timestamp_enterqueue))
                                        ? $this->timestamp_link - $this->timestamp_enterqueue : NULL;
        case 'duration_answer': return (!is_null($this->timestamp_link) && !is_null($this->timestamp_originatestart))
                                        ? $this->timestamp_link - $this->timestamp_originatestart : NULL;
        case 'esperando_contestar':
                                return (!is_null($this->timestamp_originatestart) && is_null($this->timestamp_originateend));
        case 'id_current_call': return $this->_id_current_call;
        case 'waiting_id_current_call':
                                return $this->_waiting_id_current_call;
        case 'mutedchannels':   return $this->_mutedchannels;
        case 'park_exten':      return $this->_park_exten;
        default:
            $this->_log->output('ERR: '.__METHOD__.' - propiedad no implementada: '.$s);
            die(__METHOD__.' - propiedad no implementada: '.$s."\n");
        }
    }

    public function __set($s, $v)
    {
        switch ($s) {
        case 'tipo_llamada':
            if (in_array($v, array('incoming', 'outgoing')))
                $this->_tipo_llamada = (string)$v;
            break;
        case 'status':
            if (in_array($v, array('Placing', 'Dialing', 'Ringing', 'OnQueue',
                'Success', 'OnHold', 'Hangup', 'Failure', 'ShortCall', 'NoAnswer',
                'Abandoned')))
                $this->_status = (string)$v;
            break;
        case 'id_llamada':
            $v = (int)$v;
            if (is_null($this->_id_llamada) || $this->_id_llamada != $v) {
                /* El índice id_llamada_* se usa únicamente para manejar
                 * actualización a la tabla obsoleta current_call[_entry]
                 * y no se usa para otros tipos de llamadas, como por ejemplo
                 * manualdialing */
                if (in_array($this->_tipo_llamada, array('incoming', 'outgoing'))) {
                    $sIndice = ($this->_tipo_llamada == 'incoming') ? 'id_llamada_entrante' : 'id_llamada_saliente';
                    if (!is_null($this->_id_llamada))
                        $this->_listaLlamadas->removerIndice($sIndice, $this->_id_llamada);
                    $this->_listaLlamadas->agregarIndice($sIndice, $v, $this);
                }
                $this->_id_llamada = $v;

                // Si la llamada era entrante, entonces puede que hayan actualizaciones pendientes
                if (count($this->_actualizacionesPendientes) > 0) {
                    if (isset($this->_actualizacionesPendientes['sqlupdatecalls'])) {
                        //$this->_log->output('INFO: '.__METHOD__.': ya se tiene ID de llamada, actualizando call_entry...');
                        $paramActualizar = $this->_actualizacionesPendientes['sqlupdatecalls'];
                        unset($this->_actualizacionesPendientes['sqlupdatecalls']);

                        $paramActualizar['id'] = $this->id_llamada;
                        $this->_tuberia->msg_SQLWorkerProcess_sqlupdatecalls($paramActualizar);

                        // Lanzar evento ECCP en ECCPProcess
                        $this->_tuberia->msg_SQLWorkerProcess_AgentLinked($this->tipo_llamada,
                            is_null($this->campania) ? NULL : $this->campania->id,
                            $this->id_llamada, $this->agente->channel,
                            is_null($this->actualchannel) ? $this->channel : $this->actualchannel,
                            date('Y-m-d H:i:s', $this->timestamp_link), $paramActualizar['id_agent'],
                            $this->trunk, $this->_queuenumber);
                    }
                    if (isset($this->_actualizacionesPendientes['sqlinsertcurrentcalls'])) {
                        //$this->_log->output('INFO: '.__METHOD__.': ya se tiene ID de llamada, insertando current_call_entry...');
                        $paramInsertarCC = $this->_actualizacionesPendientes['sqlinsertcurrentcalls'];
                        unset($this->_actualizacionesPendientes['sqlinsertcurrentcalls']);

                        $paramInsertarCC[($this->tipo_llamada == 'incoming') ? 'id_call_entry' : 'id_call'] =
                            $this->id_llamada;
                        $this->_tuberia->msg_SQLWorkerProcess_sqlinsertcurrentcalls($paramInsertarCC);
                        $this->_waiting_id_current_call = TRUE;
                    }
                    if (isset($this->_actualizacionesPendientes['recording'])) {
                        $listaRecording = $this->_actualizacionesPendientes['recording'];
                        unset($this->_actualizacionesPendientes['recording']);

                        foreach ($listaRecording as $tupla) {
                            $this->_tuberia->msg_SQLWorkerProcess_agregarArchivoGrabacion(
                                $this->tipo_llamada, $this->id_llamada, $tupla['uniqueid'],
                                $tupla['channel'], $tupla['recordingfile']);
                        }
                    }
                    if (count($this->_actualizacionesPendientes) > 0) {
                        $this->_log->output('ERR: '.__METHOD__.': actualización pendiente no implementada');
                    }
                }
            }
            break;
        case 'dialstring':
            $v = (string)$v;
            if (is_null($this->_dialstring) || $this->_dialstring != $v) {
                if (!is_null($this->_dialstring))
                    $this->_listaLlamadas->removerIndice('dialstring', $this->_dialstring);
                $this->_dialstring = $v;
                $this->_listaLlamadas->agregarIndice('dialstring', $this->_dialstring, $this);
            }
            break;
        case 'actionid':
            $v = (string)$v;
            if (is_null($this->_actionid) || $this->_actionid != $v) {
                if (!is_null($this->_actionid))
                    $this->_listaLlamadas->removerIndice('actionid', $this->_actionid);
                $this->_actionid = $v;
                $this->_listaLlamadas->agregarIndice('actionid', $this->_actionid, $this);
            }
            break;
        case 'channel':
            $v = (string)$v;
            if (is_null($this->_channel) || $this->_channel != $v) {
                if (!is_null($this->_channel))
                    $this->_listaLlamadas->removerIndice('channel', $this->_channel);
                $this->_channel = $v;
                $this->_listaLlamadas->agregarIndice('channel', $this->_channel, $this);

                // El valor de trunk es derivado de channel
                $regs = NULL;
                if (preg_match('/^(.+)-[0-9a-fA-F]+$/', $this->_channel, $regs)) {
                	$this->_trunk = $regs[1];

                }

                // Si el canal de la llamada no es Local, es el actualchannel
                if (strpos($this->_channel, 'Local/') !== 0) {
                	$this->actualchannel = $v;
                }
            }
            break;
        case 'actualchannel':
            $v = (string)$v;
            if (is_null($this->_actualchannel) || $this->_actualchannel != $v) {
                if (!is_null($this->_actualchannel))
                    $this->_listaLlamadas->removerIndice('actualchannel', $this->_actualchannel);
                $this->_actualchannel = $v;
                $this->_listaLlamadas->agregarIndice('actualchannel', $this->_actualchannel, $this);

                // El valor de trunk es derivado de channel
                if ((is_null($this->_trunk) || strpos($this->_trunk, 'Local/') === 0)
                    && strpos($v, 'Local/') !== 0) {
                    $this->_trunk = NULL;
                    $regs = NULL;
                    if (preg_match('/^(.+)-[0-9a-fA-F]+$/', $this->_actualchannel, $regs)) {
                        $this->_trunk = $regs[1];
                    }
                }
            }
            break;
        case 'uniqueid':
        case 'Uniqueid':
            $v = (string)$v;
            if (is_null($this->_uniqueid) || $this->_uniqueid != $v) {
                if (!is_null($this->_uniqueid))
                    $this->_listaLlamadas->removerIndice('uniqueid', $this->_uniqueid);
                $this->_uniqueid = $v;
                $this->_listaLlamadas->agregarIndice('uniqueid', $this->_uniqueid, $this);

                // Actualizar el Uniqueid en la base de datos
                if (!is_null($this->_id_llamada)) {
                	$paramActualizar = array(
                        'tipo_llamada'  =>  $this->tipo_llamada,
                        'id_campaign'   =>  is_null($this->campania) ? NULL : $this->campania->id,
                        'id'            =>  $this->_id_llamada,
                        'uniqueid'      =>  $this->_uniqueid,
                    );
                    $this->_tuberia->msg_SQLWorkerProcess_sqlupdatecalls($paramActualizar);
                }
                if (!is_null($this->id_current_call)) {
                    $paramActualizar = array(
                        'tipo_llamada'  =>  $this->tipo_llamada,
                        'id'            =>  $this->id_current_call,
                        'uniqueid'      =>  $this->_uniqueid,
                    );
                    $this->_tuberia->msg_SQLWorkerProcess_sqlupdatecurrentcalls($paramActualizar);
                }
            }
            break;
        case 'timestamp_originatestart':
            $this->_timestamp_originatestart = $v;
            if ($this->_stillborn && !is_null($this->timestamp_originateend)) {
                /* Esta asignación se hace al ejecutar el callback _cb_Originate.
                 * Por lo tanto, si la llamada ya recibió el Hangup, se la debe
                 * quitar de la lista de seguimiento. */
                if (!($this->_status == 'Failure' && is_null($this->_failure_cause))) {
                    $this->_listaLlamadas->remover($this);
                }
            }
            break;
        case 'id_current_call':
            $this->_id_current_call = (int)$v;
            $this->_waiting_id_current_call = FALSE;
            break;
        default:
            $this->_log->output('ERR: '.__METHOD__.' - propiedad no implementada: '.$s);
            die(__METHOD__.' - propiedad no implementada: '.$s."\n");
        }
    }

    public function registerAuxChannels()
    {
    	foreach (array_keys($this->AuxChannels) as $k)
            $this->_listaLlamadas->agregarIndice('auxchannel', $k, $this);
    }

    public function unregisterAuxChannels()
    {
        foreach (array_keys($this->AuxChannels) as $k)
            $this->_listaLlamadas->removerIndice('auxchannel', $k);
    }

    public function resumenLlamada()
    {
        $resumen = array(
            'calltype'              =>  $this->tipo_llamada,
            'campaign_id'           =>  is_null($this->campania) ? NULL : $this->campania->id,
            'callnumber'            =>  $this->phone,
            'callid'                =>  $this->id_llamada,
            'currentcallid'         =>  $this->id_current_call,
            'queuenumber'           =>  $this->_queuenumber,
            'agentchannel'          =>  $this->_agentchannel,
            'status'                =>  $this->_status,
            'channel'               =>  $this->channel,
            'actualchannel'         =>  $this->actualchannel,
            'mutedchannels'         =>  $this->mutedchannels,
        );
        if (is_null($resumen['queuenumber']) && !is_null($this->campania)) {
            // $this->campania->queue es NULL en caso manualdialing
            $resumen['queuenumber'] = $this->campania->queue;
        }
        if (!is_null($this->trunk))
            $resumen['trunk'] = $this->trunk;

        if (!is_null($this->_park_exten))
            $resumen['park_exten'] = $this->park_exten;

        foreach (array(
            'timestamp_originatestart'  =>  'dialstart',
            'timestamp_originateend'    =>  'dialend',
            'timestamp_enterqueue'      =>  'queuestart',
            'timestamp_link'            =>  'linkstart',
        ) as $k => $v) if (!is_null($this->$k)) {
            $resumen[$v] = date('Y-m-d H:i:s', $this->$k);
        }
        return $resumen;
    }

    public function actualizarCausaFallo($iCause, $sCauseTxt)
    {
        // Una llamada entrante no tiene las columnas para guardar failure_cause
        if ($this->tipo_llamada == 'incoming') return;

        // Una causa de colgado de 0 no sirve.
        if (!is_null($iCause) && $iCause == 0) return;
/*
        if (is_null($iCause)) foreach ($this->AuxChannels as $eventosAuxiliares) {
            if (isset($eventosAuxiliares['Hangup']) && $eventosAuxiliares['Hangup']['Cause'] != 0) {
                $iCause = $eventosAuxiliares['Hangup']['Cause'];
                $sCauseTxt = $eventosAuxiliares['Hangup']['Cause-txt'];
            }
        }
*/
        if (!is_null($iCause)) {
            $this->_failure_cause = $iCause;
            $this->_failure_cause_txt = $sCauseTxt;
            $paramActualizar = array(
                'tipo_llamada'      =>  $this->tipo_llamada,
                'id_campaign'       =>  $this->campania->id,
                'id'                =>  $this->id_llamada,
                'failure_cause'     =>  $iCause,
                'failure_cause_txt' =>  $sCauseTxt,
            );

            // Actualizar asíncronamente las propiedades de la llamada
            $this->_tuberia->msg_SQLWorkerProcess_sqlupdatecalls($paramActualizar);

            if (!is_null($this->timestamp_hangup) &&
                !($this->_stillborn && is_null($this->_timestamp_originatestart))) {
                $this->_listaLlamadas->remover($this);
            }
        }
    }

    public function marcarLlamada($ami, $sFuente, $iTimeoutOriginate,
        $timestamp, $sContext, $sCID, $sCadenaVar, $retry, $trunk, $precall_events)
    {
        if (!in_array($this->tipo_llamada, array('outgoing')))
            return FALSE;

        // Notificar el progreso de la llamada
        $paramProgreso = array(
            'datetime_entry'                    =>  date('Y-m-d H:i:s', $timestamp),
            'new_status'                        =>  'Placing',
            'retry'                             =>  $retry,
            'trunk'                             =>  $trunk,
            'id_campaign_'.$this->tipo_llamada  =>  $this->campania->id,
            'id_call_'.$this->tipo_llamada      =>  $this->id_llamada,
            'extra_events'                      =>  $precall_events,
        );
        if (!is_null($this->agente_agendado)) {
            $paramProgreso['agente_agendado'] = $this->agente_agendado->channel; // para emitir ScheduledCallStart
            $paramProgreso['id_agent'] = $this->agente_agendado->id_agent;
        }
        $this->_tuberia->msg_SQLWorkerProcess_notificarProgresoLlamada($paramProgreso);

        $sExten = NULL; $sDialstring = NULL;
        if ($this->tipo_llamada == 'outgoing') {
            $sExten = is_null($this->agente_agendado)
                ? $this->campania->queue
                : $this->agente_agendado->number;
            $sDialstring = $this->dialstring;
        }

        $callable = array($this, '_cb_Originate');
        $callable_params = array($sFuente, $timestamp, $retry, $trunk);
        $ami->asyncOriginate(
            $callable, $callable_params,
            $sDialstring,
            $sExten, $sContext, 1,
            NULL, NULL, $iTimeoutOriginate, $sCID, $sCadenaVar,
            NULL, TRUE, $this->actionid);

        return TRUE;
    }

    public function _cb_Originate($r, $sFuente, $timestamp, $retry, $trunk)
    {
        $bExito = ($r['Response'] == 'Success');

        // Respuesta para ECCPConn o CampaignProcess que esperaba...
        $this->_tuberia->enviarRespuesta($sFuente, $bExito);
        if (!$bExito) {
            $this->_log->output('ERR: '.__METHOD__.
                "campania {$this->tipo_llamada} ID={$this->campania->id} ".
                (($this->tipo_llamada == 'outgoing') ? " cola {$this->campania->queue} " : '').
                "no se puede llamar a número: ".print_r($r, TRUE));
            if ($this->status == 'Placing') $this->status = 'Failure';

            // Notificar el progreso de la llamada
            $paramProgreso = array(
                'datetime_entry'                    =>  time() /*$this->timestamp_originatestart*/,
                'new_status'                        =>  $this->status,
                'retry'                             =>  $retry,
                'trunk'                             =>  $trunk,
                'id_campaign_'.$this->tipo_llamada  =>  $this->campania->id,
                'id_call_'.$this->tipo_llamada      =>  $this->id_llamada,
            );
            if (!is_null($this->agente_agendado)) {
                $paramProgreso['agente_agendado'] = $this->agente_agendado->channel; // para emitir ScheduledCallStart
                $paramProgreso['id_agent'] = $this->agente_agendado->id_agent;
            }
            $this->_tuberia->msg_SQLWorkerProcess_notificarProgresoLlamada($paramProgreso);

            $this->_listaLlamadas->remover($this);

            if (!is_null($this->agente_agendado)) {
                $a = $this->agente_agendado;
                $this->agente_agendado = NULL;
                $a->llamada_agendada = NULL;

                /* Se debe quitar la reservación únicamente si no hay más
                 * llamadas agendadas para este agente. Si se cumple esto,
                 * CampaignProcess lanzará el evento quitarReservaAgente
                 * el cual quita asíncronamente la pausa del agente. */
                $this->_tuberia->msg_CampaignProcess_verificarFinLlamadasAgendables(
                    $a->channel, $this->campania->id);
            }
        } else {
            $this->timestamp_originatestart = $timestamp;

            /* Una llamada recién creada empieza con status == NULL. Si antes
             * de eso se recibió OriginateResponse(Failure) entonces se seteó
             * a Failure el estado. No se debe sobreescribir este Failure
             * para que se pueda limpiar la llamada en caso de que no se
             * reciba nunca un Hangup. */
            if (!is_null($this->status)) $this->status = 'Placing';
        }
    }

    public function llamadaIniciaDial($timestamp, $destination)
    {
        $this->actualchannel = $destination;

        // Notificar el progreso de la llamada
        $paramProgreso = array(
            'datetime_entry'=>  date('Y-m-d H:i:s', $timestamp),
            'new_status'    =>  'Dialing',
            'trunk'         =>  $this->trunk,
        );
        $paramProgreso['id_call_'.$this->tipo_llamada] = $this->id_llamada;
        if (!is_null($this->campania))
            $paramProgreso['id_campaign_'.$this->tipo_llamada] = $this->campania->id;
        $this->_tuberia->msg_SQLWorkerProcess_notificarProgresoLlamada($paramProgreso);
    }

    public function llamadaFueOriginada($timestamp, $uniqueid, $channel,
        $sStatus)
    {
        $sAgente_agendado = NULL;
        $this->_timestamp_originateend = $timestamp;

        /* No se acepta un canal NULL ni el mismo canal del agente (para
         * llamadas manuales). */
        if (is_null($this->channel) && !is_null($channel) &&
            (is_null($this->agente_agendado) || strpos($channel, $this->agente_agendado->channel) !== 0)) {
            $this->channel = $channel;
        }

        if ($sStatus == 'Success') $sStatus = 'Ringing';
        if (is_null($this->status) || $this->status == 'Placing' || $sStatus == 'Failure')
            $this->status = $sStatus;
        if (!in_array($this->status, array('Placing', 'Ringing', 'Dialing', 'Failure'))) {
            $this->_log->output("WARN: ".__METHOD__." llamada recibe OriginateResponse con status=".
                $this->status." inesperado, se asume Ringing");
            $this->status = 'Ringing';
        }

        if ($uniqueid == '<null>') $uniqueid = NULL;
        if (is_null($this->uniqueid) && !is_null($uniqueid))
            $this->uniqueid = $uniqueid;

        /*
        if ($this->DEBUG) {
            // Desactivado porque rellena el log
            $this->_log->output("DEBUG: llamada identificada es: {$this->actionid} : ".
                print_r($this, TRUE));
        }
        */

        // Preparar propiedades a actualizar en DB
        $paramActualizar = array(
            'tipo_llamada'  =>  $this->tipo_llamada,
            'id_campaign'   =>  $this->campania->id,
            'id'            =>  $this->id_llamada,

            'status'        =>  $this->status,
            'Uniqueid'      =>  $this->uniqueid,
            'fecha_llamada' =>  date('Y-m-d H:i:s', $this->timestamp_originateend),
        );

        /* En caso de fallo de Originate, y si se tienen canales auxiliares, el
         * Hangup registrado en el canal auxiliar puede tener la causa del fallo
         */
        $iSegundosEspera = $this->timestamp_originateend - $this->timestamp_originatestart;
        if ($sStatus == 'Failure') {
            $this->campania->agregarTiempoContestar($iSegundosEspera);

            if (!is_null($this->agente_agendado)) {
                $a = $this->agente_agendado;
                $this->agente_agendado = NULL;
                $a->llamada_agendada = NULL;

                /* Se debe quitar la reservación únicamente si no hay más
                 * llamadas agendadas para este agente. Si se cumple esto,
                 * CampaignProcess lanzará el evento quitarReservaAgente
                 * luego de quitar la pausa del agente. */
                $this->_tuberia->msg_CampaignProcess_verificarFinLlamadasAgendables(
                    $a->channel, $this->campania->id, $a->resumenSeguimiento());
                $sAgente_agendado = $a->channel;
            }

            /* Remover llamada que no se pudo colocar si ya se ejecutó callback
             * _cb_Originate, y si se tiene una causa de fallo válida. */
            if (!($this->_stillborn && is_null($this->timestamp_originatestart))) {
                if (!is_null($this->failure_cause)) {
                    $this->_listaLlamadas->remover($this);
                }
            }
        } else {
            // Verificar si Onnewchannel procesó pata equivocada
            if ($this->uniqueid != $uniqueid) {
                $this->_log->output("ERR: se procesó pata equivocada en evento Newchannel ".
                    "anterior, pata procesada es {$this->uniqueid}, ".
                    "pata real es {$uniqueid}");

                $this->unregisterAuxChannels();
                $this->AuxChannels = array();
                $this->uniqueid = $uniqueid;
                $paramActualizar['Uniqueid'] = $this->uniqueid;
            }

            /*
            if ($this->DEBUG) {
                $this->_log->output("DEBUG: llamada colocada luego de $iSegundosEspera s. de espera.");
            }
            */
            if ($this->_stillborn) {
                $this->_log->output("WARN: ".__METHOD__." llamada recibió Hangup pero OriginateResponse indica status=$sStatus");
                if (!is_null($this->timestamp_originatestart)) $this->_listaLlamadas->remover($this);
            }
        }

        // Actualizar asíncronamente las propiedades de la llamada
        $this->_tuberia->msg_SQLWorkerProcess_sqlupdatecalls($paramActualizar);

        // Notificar el progreso de la llamada
        $paramProgreso = array(
            'datetime_entry'    =>  $paramActualizar['fecha_llamada'],
            'new_status'        =>  $paramActualizar['status'],
            'uniqueid'          =>  $paramActualizar['Uniqueid'],
        );
        if (!is_null($this->_trunk)) $paramProgreso['trunk'] = $this->_trunk;
        $paramProgreso['id_call_'.$this->tipo_llamada] = $this->id_llamada;
        if (!is_null($this->campania))
            $paramProgreso['id_campaign_'.$this->tipo_llamada] = $this->campania->id;
        if (!is_null($sAgente_agendado))
            $paramProgreso['agente_agendado'] = $sAgente_agendado;
        $this->_tuberia->msg_SQLWorkerProcess_notificarProgresoLlamada($paramProgreso);
    }

    public function llamadaEntraEnCola($timestamp, $channel, $sQueueNumber)
    {
        $this->timestamp_enterqueue = $timestamp;
        $this->_queuenumber = $sQueueNumber;
        if (is_null($this->channel)) $this->channel = $channel;
        if (is_null($this->status) || in_array($this->status, array('Placing', 'Ringing')))
            $this->status = 'OnQueue';

        if ($this->tipo_llamada == 'outgoing') {
            // Preparar propiedades a actualizar en DB
            $paramActualizar = array(
                'tipo_llamada'          =>  $this->tipo_llamada,
                'id_campaign'           =>  $this->campania->id,
                'id'                    =>  $this->id_llamada,

                'status'                =>  $this->status,
                'datetime_entry_queue'  =>  date('Y-m-d H:i:s', $this->timestamp_enterqueue),
                'trunk'                 =>  $this->trunk,
            );
            $this->_tuberia->msg_SQLWorkerProcess_sqlupdatecalls($paramActualizar);

            // Notificar el progreso de la llamada
            $this->_tuberia->msg_SQLWorkerProcess_notificarProgresoLlamada(array(
                'datetime_entry'        =>  $paramActualizar['datetime_entry_queue'],
                'id_campaign_outgoing'  =>  $this->campania->id,
                'id_call_outgoing'      =>  $this->id_llamada,
                'new_status'            =>  'OnQueue',
                'trunk'                 =>  $this->trunk,
            ));
        } elseif ($this->tipo_llamada == 'incoming') {
        	// Preparar propiedades a insertar en DB
            $paramInsertar = array(
                'tipo_llamada'          =>  $this->tipo_llamada,
                'id_campaign'           =>  is_null($this->campania) ? NULL : $this->campania->id,
                'id_queue_call_entry'   =>  $this->id_queue_call_entry,
                'callerid'              =>  $this->phone,
                'datetime_entry_queue'  =>  date('Y-m-d H:i:s', $this->timestamp_enterqueue),
                'status'                =>  'en-cola',
                'uniqueid'              =>  $this->uniqueid,

                // Un trunk NULL ocurre en caso de Channel Local/XXX@yyyy-zzzz
                'trunk'                 =>  is_null($this->trunk) ? '' : $this->trunk,
            );
            $this->_tuberia->msg_SQLWorkerProcess_sqlinsertcalls($paramInsertar);

            // La notificación de progreso se realiza en CampaignProcess ANTES
            // de devolver el ID de inserción.
        }
    }

    public function llamadaEnlazadaAgente($timestamp, $agent, $sRemChannel,
        $uniqueid_agente, $sAgentChannel)
    {
        $this->agente = $agent;
        $this->agente->asignarLlamadaAtendida($this, $uniqueid_agente);
        $this->agente_agendado = NULL;
        $this->agente->llamada_agendada = NULL;

        $this->_agentchannel = $sAgentChannel;
        $this->status = 'Success';
        $this->timestamp_link = $timestamp;
        if (!is_null($this->campania) && $this->campania->tipo_campania == 'outgoing')
            $this->campania->agregarTiempoContestar($this->duration_answer);

        /*
        if ($this->DEBUG) {
            // Desactivado porque rellena el log
            $this->_log->output("DEBUG: llamadaEnlazadaAgente: llamada  => ".print_r($this, TRUE));
        }
        */

        /* Si a estas alturas no se tiene un channel, se usa $sRemChannel. Esto
         * puede ocurrir si el canal fue rechazado en OriginateResponse porque
         * era el canal del agente de llamada manual. */
        if (is_null($this->channel)) $this->channel = $sRemChannel;

        // El canal verdadero es más util que Local/XXX para las operaciones
        if (strpos($sRemChannel, 'Local/') === 0 && !is_null($this->channel)
            && $sRemChannel != $this->channel) {
            $sRemChannel = $this->channel;
        }
        if (strpos($sRemChannel, 'Local/') === 0 && !is_null($this->actualchannel)
            && $sRemChannel != $this->actualchannel) {
            $sRemChannel = $this->actualchannel;
        }

        $paramActualizar = array(
            'tipo_llamada'          =>  $this->tipo_llamada,
            'id_campaign'           =>  is_null($this->campania) ? NULL : $this->campania->id,

            'id_agent'              =>  is_null($this->agente) ? NULL : $this->agente->id_agent,
            'duration_wait'         =>  $this->duration_wait,
        );
        $paramInsertarCC = array(
            'tipo_llamada'      =>  $this->tipo_llamada,

            'uniqueid'          =>  $this->uniqueid,
            'ChannelClient'     =>  $sRemChannel,
        );
        if ($this->tipo_llamada == 'incoming') {
        	$paramActualizar['status'] = 'activa';
            $paramActualizar['datetime_init'] = date('Y-m-d H:i:s', $this->timestamp_link);
            $paramInsertarCC['datetime_init'] = date('Y-m-d H:i:s', $this->timestamp_link);
            $paramInsertarCC['id_agent'] = $this->agente->id_agent;
            $paramInsertarCC['callerid'] = $this->phone;
            $paramInsertarCC['id_queue_call_entry'] = $this->id_queue_call_entry;
        } else {
            $paramActualizar['status'] = $this->status;
            $paramActualizar['start_time'] = date('Y-m-d H:i:s', $this->timestamp_link);
            $paramInsertarCC['fecha_inicio'] = date('Y-m-d H:i:s', $this->timestamp_link);
            $paramInsertarCC['queue'] = $this->campania->queue;
            $paramInsertarCC['agentnum'] = $this->agente->number;
            $paramInsertarCC['event'] = 'Link';
            $paramInsertarCC['Channel'] = $this->agente->channel;
        }

        if (!is_null($this->id_llamada)) {
            // Ya se tiene el ID de la llamada
            $paramActualizar['id'] = $this->id_llamada;
            $paramInsertarCC[($this->tipo_llamada == 'incoming') ? 'id_call_entry' : 'id_call'] =
                $this->id_llamada;
            $this->_tuberia->msg_SQLWorkerProcess_sqlupdatecalls($paramActualizar);
            $this->_tuberia->msg_SQLWorkerProcess_sqlinsertcurrentcalls($paramInsertarCC);
            $this->_waiting_id_current_call = TRUE;

            // Lanzar evento ECCP en ECCPProcess
            $this->_tuberia->msg_SQLWorkerProcess_AgentLinked($this->tipo_llamada,
                is_null($this->campania) ? NULL : $this->campania->id,
                $this->id_llamada, $this->agente->channel, $sRemChannel,
                date('Y-m-d H:i:s', $this->timestamp_link), $paramActualizar['id_agent'],
                $this->trunk, $this->_queuenumber);
        } else {
            /* En el caso de llamadas entrantes, puede ocurrir que el evento
             * Link se reciba ANTES de haber recibido el ID de inserción en
             * call_entry. Entonces no se puede mandar a actualizar hasta tener
             * este ID, ni tampoco lanzar el evento AgentLinked. Se delegan las
             * actualizaciones hasta que se asigne a la propiedad id_llamada. */
            $this->_actualizacionesPendientes['sqlupdatecalls'] = $paramActualizar;
            $this->_actualizacionesPendientes['sqlinsertcurrentcalls'] = $paramInsertarCC;
            //$this->_log->output('INFO: '.__METHOD__.': actualizaciones pendientes por faltar id_llamada.');
        }

        // Verificación de consistencia
        if ($this->agente->estado_consola != 'logged-in') {
            $this->_log->output("WARN: llamada ha sido asignada a agente en estado ".
                $this->agente->estado_consola.'. Esto no debería haber pasado: ');
            $this->dump($this->_log);
        }
    }

    public function llamadaEnviadaHold($parkexten, $uniqueid_nuevo)
    {
        if (!$this->request_hold) return;

        $this->status = 'OnHold';
        $this->request_hold = FALSE;
        $this->_park_exten = $parkexten;

        // Se quita Uniqueid de agente obsoleto
        if (!is_null($this->agente)) $this->agente->UniqueidAgente = NULL;

        // Esta asignación manda a escribir a la base de datos
        $this->uniqueid = $uniqueid_nuevo;
    }

    public function llamadaRegresaHold($ami, $iTimestamp, $sAgentChannel = NULL, $uniqueid_agente = NULL)
    {
        /* Para agentes dinámicos, el Originate de recuperación de la llamada
         * ocasiona que se asigne un nuevo canal de agente SIP/xxxx-abcde que
         * debe de ser recogido y asignado. */
        if (!is_null($sAgentChannel)) $this->_agentchannel = $sAgentChannel;

        if (!is_null($this->agente)) {
            $a = $this->agente;
            $a->UniqueidAgente = $uniqueid_agente;
            $this->_tuberia->msg_SQLWorkerProcess_marcarFinalHold(
                $iTimestamp, $a->channel,
                $this->resumenLlamada(),
                $a->resumenSeguimiento());
            $a->clearHold($ami);
        }

        $this->_park_exten = NULL;

        /* Actualizar el estado de salida de hold en la base de datos. Por
         * compatibilidad, también se pasa el uniqueid, aunque no se haya vuelto
         * a cambiar. */
        $this->_status = 'Success';
        if (!is_null($this->_id_llamada)) {
            $paramActualizar = array(
                'tipo_llamada'  =>  $this->tipo_llamada,
                'id_campaign'   =>  is_null($this->campania) ? NULL : $this->campania->id,
                'id'            =>  $this->id_llamada,
                'uniqueid'      =>  $this->uniqueid,
                'status'        =>  ($this->tipo_llamada == 'incoming') ? 'activa' : 'Success',
            );
            $this->_tuberia->msg_SQLWorkerProcess_sqlupdatecalls($paramActualizar);

            // Notificar el progreso de la llamada
            $paramProgreso = array(
                'datetime_entry'    =>  date('Y-m-d H:i:s', $iTimestamp),
                'uniqueid'          =>  $this->uniqueid,
                'new_status'        =>  'OffHold',
            );
            $paramProgreso['id_call_'.$this->tipo_llamada] = $this->id_llamada;
            if (!is_null($this->campania))
                $paramProgreso['id_campaign_'.$this->tipo_llamada] = $this->campania->id;
            $this->_tuberia->msg_SQLWorkerProcess_notificarProgresoLlamada($paramProgreso);
        }
        if (!is_null($this->id_current_call)) {
            $paramActualizar = array(
                'tipo_llamada'  =>  $this->tipo_llamada,
                'id'            =>  $this->id_current_call,
                'uniqueid'      =>  $this->uniqueid,
                'hold'          =>  'N',
            );
            $this->_tuberia->msg_SQLWorkerProcess_sqlupdatecurrentcalls($paramActualizar);
        }
    }

    public function llamadaFinalizaSeguimiento($timestamp, $iUmbralLlamadaCorta)
    {
        $sAgente_agendado = NULL;

        if (is_null($this->id_llamada)) {
        	$this->_log->output('ERR: '.__METHOD__.': todavía no ha llegado '.
                'ID de llamada, no se garantiza integridad de datos para esta llamada.');
        }

    	$this->timestamp_hangup = $timestamp;
        $this->_agentchannel = NULL;
        if ($this->tipo_llamada == 'outgoing' && is_null($this->timestamp_originatestart))
            $this->_stillborn = TRUE;

        // Mandar a borrar el registro de current_calls
        if (!is_null($this->id_current_call)) {
            $this->_tuberia->msg_SQLWorkerProcess_sqldeletecurrentcalls(array(
                'tipo_llamada'      =>  $this->tipo_llamada,
                'id'                =>  $this->id_current_call,
            ));
        } elseif (isset($this->_actualizacionesPendientes['sqlinsertcurrentcalls'])) {
            unset($this->_actualizacionesPendientes['sqlinsertcurrentcalls']);
        }
        $this->_id_current_call = NULL;

        $paramActualizar = array(
            'tipo_llamada'          =>  $this->tipo_llamada,
            'id_campaign'           =>  is_null($this->campania) ? NULL : $this->campania->id,
        );
        $paramProgreso = array(
            'datetime_entry'    =>  date('Y-m-d H:i:s', $this->timestamp_hangup),
            'queue'             =>  $this->_queuenumber,
        );

        /* Si la llamada nunca fue enlazada, entonces se actualiza el tiempo de
         * contestado entre hangup y el inicio del Originate */
        if (is_null($this->timestamp_link)) {
        	if ($this->tipo_llamada == 'outgoing') {
                if (!$this->_stillborn) {
                    $this->campania->agregarTiempoContestar($this->timestamp_hangup - $this->timestamp_originatestart);
        	    } else {
        	        $this->campania->agregarTiempoContestar(0);
        	    }
            }
            if (is_null($this->timestamp_enterqueue)) {
            	// Llamada nunca fue contestada
                $paramActualizar['status'] = 'NoAnswer';
                $paramProgreso['new_status'] = 'NoAnswer';
            } else {
            	// Llamada entró a cola pero fue abandonada antes de enlazarse
                if ($this->tipo_llamada == 'incoming') {
                	$paramActualizar['status'] = 'abandonada';
                    $paramActualizar['datetime_end'] = date('Y-m-d H:i:s', $this->timestamp_hangup);
                } else {
                    $paramActualizar['status'] = 'Abandoned';
                    $paramActualizar['end_time'] = date('Y-m-d H:i:s', $this->timestamp_hangup);
                }
                $paramActualizar['duration_wait'] = $this->timestamp_hangup - $this->timestamp_enterqueue;
                $paramProgreso['new_status'] = 'Abandoned';
            }
        } else {
        	// Llamada fue enlazada normalmente
            $paramActualizar['duration'] = $this->duration;
            $paramProgreso['duration'] = $this->duration;
            if ($this->tipo_llamada != 'incoming') {
                $paramActualizar['end_time'] = date('Y-m-d H:i:s', $this->timestamp_hangup);
                if ($this->duration <= $iUmbralLlamadaCorta) {
                	$this->status = 'ShortCall';
                    $paramActualizar['status'] = 'ShortCall';
                    $paramProgreso['new_status'] = 'ShortCall';
                } else {
                    $this->status = 'Hangup';
                    $this->campania->actualizarEstadisticas($this->duration);
                    $paramProgreso['new_status'] = 'Hangup';
                }
            } else {
                $paramActualizar['datetime_end'] = date('Y-m-d H:i:s', $this->timestamp_hangup);
            	$paramActualizar['status'] = 'terminada';
                $paramProgreso['new_status'] = 'Hangup';
            }
        }
        if (!is_null($this->id_llamada)) {
            $paramActualizar['id'] = $this->id_llamada;
            $paramProgreso['id_call_'.$this->tipo_llamada] = $this->id_llamada;
            if (!is_null($this->campania)) $paramProgreso['id_campaign_'.$this->tipo_llamada] = $this->campania->id;
            $this->_tuberia->msg_SQLWorkerProcess_sqlupdatecalls($paramActualizar);

            if (!is_null($this->agente)) {
                $this->_tuberia->msg_SQLWorkerProcess_AgentUnlinked(
                    $this->agente->channel, $this->tipo_llamada,
                    is_null($this->campania) ? NULL : $this->campania->id,
                    $this->id_llamada, $this->phone,
                    date('Y-m-d H:i:s', $this->timestamp_hangup),
                    $this->duration, ($this->status == 'ShortCall'),
                    $paramProgreso);
                $paramProgreso = NULL;
            }
        } else {
            // Esto no debería ocurrir en condiciones normales
            $paramProgreso = NULL;
        }

        if (!is_null($this->agente_agendado)) {
            // Sacar de pausa al agente cuya llamada ha terminado
            $a = $this->agente_agendado;
            if ($a->reservado) {
                /* Se debe quitar la reservación únicamente si no hay más
                 * llamadas agendadas para este agente. Si se cumple esto,
                 * CampaignProcess lanzará el evento quitarReservaAgente
                 * luego de quitar la pausa del agente. */
                $this->_tuberia->msg_CampaignProcess_verificarFinLlamadasAgendables(
                    $a->channel, $this->campania->id, $a->resumenSeguimiento());
            }
        }
        if (!is_null($this->agente)) {
            $a = $this->agente;
            $this->agente->llamada_agendada = NULL;
            $this->agente->quitarLlamadaAtendida();
            $this->agente = NULL;

            if ($a->reservado) {
                /* Se debe quitar la reservación únicamente si no hay más
                 * llamadas agendadas para este agente. Si se cumple esto,
                 * CampaignProcess lanzará el evento quitarReservaAgente
                 * luego de quitar la pausa del agente. */
                $this->_tuberia->msg_CampaignProcess_verificarFinLlamadasAgendables(
                    $a->channel, $this->campania->id, $a->resumenSeguimiento());
            }
        } elseif (!is_null($this->agente_agendado)) {
            /* Si la llamada agendada falla se requiere desconectar al agente
             * agendado para que esté libre para el siguiente intento. Esto es
             * necesario para poder reportar correctamente el progreso de una
             * llamada manual. */
            $this->agente_agendado->llamada_agendada = NULL;
            $this->agente_agendado->quitarLlamadaAtendida();
            $sAgente_agendado = $this->agente_agendado->channel;
        }
        $this->agente_agendado = NULL;

        if (!is_null($paramProgreso)) {
            // Emitir el evento directamente en caso necesario
            if (!is_null($sAgente_agendado))
                $paramProgreso['agente_agendado'] = $sAgente_agendado;
            $this->_tuberia->msg_SQLWorkerProcess_notificarProgresoLlamada($paramProgreso);
        }

        /* Para las llamadas exitosas, ya se ha recibido OriginateResponse y
         * por lo tanto, ya se tiene timestamp_originateend. Si no está, la
         * llamada ha fallado antes de que el OriginateResponse reciba Failure,
         * y se tiene que delegar el quitado de la llamada hasta ese momento, o
         * incluso más tarde si la causa del fallo es desconocida. */
        if (!$this->_stillborn &&
            !($this->tipo_llamada == 'outgoing' && (is_null($this->timestamp_originateend) || is_null($this->timestamp_originatestart))) &&
            !($this->status == 'Failure' && is_null($this->failure_cause))) {
            $this->_listaLlamadas->remover($this);
        }
    }

    public function agregarArchivoGrabacion($uniqueid, $channel, $recordingfile)
    {
        if (!is_null($this->id_llamada)) {
            // Se tiene id_llamada, se manda mensaje directamente
            $this->_tuberia->msg_SQLWorkerProcess_agregarArchivoGrabacion(
                $this->tipo_llamada, $this->id_llamada, $uniqueid, $channel,
                $recordingfile);
        } else {
            // No se tiene id_llamada, se guarda en reserva.
            // Esto sólo puede ocurrir para incoming
            $this->_actualizacionesPendientes['recording'][] = array(
                'uniqueid'      =>  $uniqueid,
                'channel'       =>  $channel,
                'recordingfile' =>  $recordingfile
            );
        }
    }

    public function agregarCanalSilenciado($chan)
    {
        if (in_array($chan, $this->_mutedchannels)) return FALSE;
        if (count($this->_mutedchannels) == 0 && !is_null($this->agente)) {
            // Primer canal silenciado, se emite evento
            $this->_tuberia->msg_ECCPProcess_recordingMute(
                $this->agente->channel, $this->tipo_llamada,
                is_null($this->campania) ? NULL : $this->campania->id,
                $this->id_llamada);
        }
        $this->_mutedchannels[] = $chan;
        return TRUE;
    }

    public function borrarCanalesSilenciados()
    {
        if (!is_null($this->agente)) {
            $this->_tuberia->msg_ECCPProcess_recordingUnmute(
                $this->agente->channel, $this->tipo_llamada,
                is_null($this->campania) ? NULL : $this->campania->id,
                $this->id_llamada);
        }
        $this->_mutedchannels = array();
    }

    public function mandarLlamadaHold($ami, $sFuente, $timestamp)
    {
        $callable = array($this, '_cb_Park');
        $call_params = array($sFuente, $ami, $timestamp);
        //$this->_log->output('DEBUG: '.__METHOD__.": asyncPark({$this->actualchannel}, {$this->agentchannel})");
        $ami->asyncPark(
            $callable, $call_params,
            $this->actualchannel,
            $this->agentchannel);
    }

    public function _cb_Park($r, $sFuente, $ami, $timestamp)
    {
        //$this->_log->output('DEBUG: '.__METHOD__.': r='.print_r($r, TRUE));
        $this->_tuberia->enviarRespuesta($sFuente,
            ($r['Response'] == 'Success')
                ? array(0, '')
                : array(500, 'Unable to start agent hold - '.$r['Message']));
        if ($r['Response'] == 'Success') {
            // Actualizar current_calls
            if (!is_null($this->id_current_call)) {
                $paramActualizar = array(
                    'tipo_llamada'  =>  $this->tipo_llamada,
                    'id'            =>  $this->id_current_call,
                    'hold'          =>  'S',
                );
                $this->_tuberia->msg_SQLWorkerProcess_sqlupdatecurrentcalls($paramActualizar);
            }

            // Emitir progreso de llamada
            $paramProgreso = array(
                'datetime_entry'                =>  date('Y-m-d H:i:s', $timestamp),
                'new_status'                    =>  'OnHold',
                'id_call_'.$this->tipo_llamada  =>  $this->id_llamada,
                //'uniqueid'          =>  $paramActualizar['Uniqueid'],
            );
            if (!is_null($this->campania)) {
                $paramProgreso['id_campaign_'.$this->tipo_llamada] = $this->campania->id;
            }
            $this->_tuberia->msg_SQLWorkerProcess_notificarProgresoLlamada($paramProgreso);
        } else {
            $this->agente->clearHold($ami);
        }
    }
}
?>