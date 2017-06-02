<?php
/* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
 Codificación: UTF-8
 +----------------------------------------------------------------------+
 | Elastix version 1.2-2                                               |
 | http://www.elastix.org                                               |
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

class SQLWorkerProcess extends TuberiaProcess
{
    private $DEBUG = FALSE; // VERDADERO si se activa la depuración

    private $_log;      // Log abierto por framework de demonio
    private $_dsn;      // Cadena que representa el DSN, estilo PDO
    private $_db;       // Conexión a la base de datos, PDO
    private $_configDB; // Objeto de configuración desde la base de datos

    private $_iTimestampInicioProceso;

    // Contadores para actividades ejecutadas regularmente
    private $_iTimestampActualizacion = 0;          // Última actualización remota
    private $_iTimestampUltimaRevisionConfig = 0;   // Última revisión de configuración

    /* Lista de acciones pendientes encargadas por otros procesos. Cada elemento
     * de este arreglo es una tupla cuyo primer elemento es callable y el segundo
     * elemento es la lista de parámetros con los que se debe invocar el callable.
     * Ya que todos los callables usan la base de datos, es posible que la
     * ejecución arroje excepciones PDOException. Todos los callables se invocan
     * dentro de una transacción de la base de datos, la cual se hará commit()
     * en caso de que no se arrojen excepciones. De lo contrario, y si la conexión
     * sigue siendo válida, se realizará un rollback() y se reintentará la operación
     * en un momento posterior. Todos los callables deben de devolver un arreglo
     * que contiene los eventos a ser lanzados como resultado de haber completado
     * las operaciones correspondientes.
     */
    private $_accionesPendientes = array();

    private $_finalizandoPrograma = FALSE;

    public function inicioPostDemonio($infoConfig, &$oMainLog)
    {
    	$this->_log = $oMainLog;
        $this->_multiplex = new MultiplexServer(NULL, $this->_log);
        $this->_tuberia->registrarMultiplexHijo($this->_multiplex);
        $this->_tuberia->setLog($this->_log);

        $this->_iTimestampInicioProceso = time();

        // Interpretar la configuración del demonio
        $this->_dsn = $this->_interpretarConfiguracion($infoConfig);
        if (!$this->_iniciarConexionDB()) return FALSE;

        // Leer el resto de la configuración desde la base de datos
        try {
            $this->_configDB = new ConfigDB($this->_db, $this->_log);
        } catch (PDOException $e) {
            $this->_log->output("FATAL: no se puede leer configuración DB - ".$e->getMessage());
            return FALSE;
        }

        $this->_repararAuditoriasIncompletas();

        // Registro de manejadores de eventos desde AMIEventProcess
        foreach (array('sqlinsertcalls', 'sqlupdatecalls',
            'sqlinsertcurrentcalls', 'sqldeletecurrentcalls',
            'sqlupdatecurrentcalls', 'sqlupdatestatcampaign', 'finalsql',
            'verificarFinLlamadasAgendables', 'agregarArchivoGrabacion',
            'AgentLogin', 'AgentLogoff', 'AgentLinked', 'AgentUnlinked',
            'marcarFinalHold', 'nuevaMembresiaCola', 'notificarProgresoLlamada',
            'requerir_credencialesAsterisk',) as $k)
            $this->_tuberia->registrarManejador('AMIEventProcess', $k, array($this, "msg_$k"));

        // Registro de manejadores de eventos desde ECCPWorkerProcess
        foreach (array('requerir_nuevaListaAgentes') as $k)
            $this->_tuberia->registrarManejador('*', $k, array($this, "msg_$k"));

        // Registro de manejadores de eventos desde HubProcess
        $this->_tuberia->registrarManejador('HubProcess', 'finalizando', array($this, "msg_finalizando"));

        $this->DEBUG = $this->_configDB->dialer_debug;

        // Informar a AMIEventProcess la configuración de Asterisk
        $this->_informarCredencialesAsterisk(FALSE);

        return TRUE;
    }

    private function _informarCredencialesAsterisk($por_pedido)
    {
        $this->_tuberia->AMIEventProcess_informarCredencialesAsterisk(array(
            'asterisk'  =>  array(
                'asthost'           =>  $this->_configDB->asterisk_asthost,
                'astuser'           =>  $this->_configDB->asterisk_astuser,
                'astpass'           =>  $this->_configDB->asterisk_astpass,
                'duracion_sesion'   =>  $this->_configDB->asterisk_duracion_sesion,
            ),
            'dialer'    =>  array(
                'llamada_corta'     =>  $this->_configDB->dialer_llamada_corta,
                'tiempo_contestar'  =>  $this->_configDB->dialer_tiempo_contestar,
                'debug'             =>  $this->_configDB->dialer_debug,
                'allevents'         =>  $this->_configDB->dialer_allevents,
            ),
        ), $por_pedido);
    }

    private function _interpretarConfiguracion($infoConfig)
    {
        $dbHost = 'localhost';
        $dbUser = 'asterisk';
        $dbPass = 'asterisk';
        if (isset($infoConfig['database']) && isset($infoConfig['database']['dbhost'])) {
            $dbHost = $infoConfig['database']['dbhost'];
            $this->_log->output('Usando host de base de datos: '.$dbHost);
        } else {
            $this->_log->output('Usando host (por omisión) de base de datos: '.$dbHost);
        }
        if (isset($infoConfig['database']) && isset($infoConfig['database']['dbuser']))
            $dbUser = $infoConfig['database']['dbuser'];
        if (isset($infoConfig['database']) && isset($infoConfig['database']['dbpass']))
            $dbPass = $infoConfig['database']['dbpass'];

        return array("mysql:host=$dbHost;dbname=call_center", $dbUser, $dbPass);
    }

    private function _iniciarConexionDB()
    {
        try {
            $this->_db = new PDO($this->_dsn[0], $this->_dsn[1], $this->_dsn[2]);
            $this->_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->_db->setAttribute(PDO::ATTR_EMULATE_PREPARES, FALSE);
            return TRUE;
        } catch (PDOException $e) {
            $this->_db = NULL;
            $this->_log->output("FATAL: no se puede conectar a DB - ".$e->getMessage());
            return FALSE;
        }
    }

    public function procedimientoDemonio()
    {
        // Lo siguiente NO debe de iniciar operaciones DB, sólo acumular acciones
        $bPaqProcesados = $this->_multiplex->procesarPaquetes();
        $this->_multiplex->procesarActividad(($bPaqProcesados || (count($this->_accionesPendientes) > 0)) ? 0 : 1);

        // Verificar posible desconexión de la base de datos
        if (is_null($this->_db)) {
            if (count($this->_accionesPendientes) > 0) {
                $this->_log->output('INFO: falta conexión DB y hay '.count($this->_accionesPendientes).' acciones pendientes.');
                if ($this->DEBUG) {
                    foreach ($this->_accionesPendientes as $accion)
                        $this->_volcarAccion($accion);
                }
            }
            $this->_log->output('INFO: intentando volver a abrir conexión a DB...');
            if (!$this->_iniciarConexionDB()) {
                $this->_log->output('ERR: no se puede restaurar conexión a DB, se espera...');

                $t1 = time();
                do {
                    $this->_multiplex->procesarPaquetes();
                    $this->_multiplex->procesarActividad(1);
                } while (time() - $t1 < 5);
            } else {
                $this->_log->output('INFO: conexión a DB restaurada, se reinicia operación normal.');
                $this->_configDB->setDBConn($this->_db);
            }
        } else {
            $this->_procesarUnaAccion();
        }

        return TRUE;
    }

    private function _procesarUnaAccion()
    {
        try {
            if (!$this->_finalizandoPrograma) {
                // Verificar si se ha cambiado la configuración
                $this->_verificarCambioConfiguracion();

                // Verificar si hay que refrescar agentes disponibles
                $this->_verificarActualizacionAgentes();
            }

            /* Por ahora se intenta ejecutar todas las operaciones, incluso
             * si se intenta finalizar el programa. */
            if (count($this->_accionesPendientes) > 0) {
                if ($this->DEBUG) {
                    $this->_volcarAccion($this->_accionesPendientes[0]);
                }

                $t_1 = microtime(TRUE);
                $this->_db->beginTransaction();

                $eventos = call_user_func_array(
                    $this->_accionesPendientes[0][0],
                    $this->_accionesPendientes[0][1]);

                /* El commit también puede arrojar excepción. Se debe pasar más
                 * allá del commit antes de quitar la acción pendiente y lanzar
                 * los eventos. */
                $this->_db->commit();
                $t_2 = microtime(TRUE);
                if ($this->DEBUG) {
                    $this->_log->output('DEBUG: '.__METHOD__.' acción ejecutada correctamente.');
                }
                if ($this->DEBUG || ($t_2 - $t_1 >= 1.0)) {
                    $this->_log->output('DEBUG: '.__METHOD__.' acción '.
                        $this->_accionesPendientes[0][0][1].' tomó '.
                        sprintf('%.2f s.', $t_2 - $t_1));
                }

                array_shift($this->_accionesPendientes);
                $this->_lanzarEventos($eventos);
            }
        } catch (PDOException $e) {
            if ($this->DEBUG || !esReiniciable($e)) {
                $this->_log->output('ERR: '.__METHOD__.
                    ': no se puede realizar operación de base de datos: '.
                    implode(' - ', $e->errorInfo));
                $this->_log->output("ERR: traza de pila: \n".$e->getTraceAsString());
            }
            if ($e->errorInfo[0] == 'HY000' && $e->errorInfo[1] == 2006) {
                // Códigos correspondientes a pérdida de conexión de base de datos
                $this->_log->output('WARN: '.__METHOD__.
                    ': conexión a DB parece ser inválida, se cierra...');
                $this->_db = NULL;
            } else {
                $this->_db->rollBack();
            }
        }
    }

    private function _volcarAccion(&$accion)
    {
        $this->_log->output('DEBUG: acción pendiente '.$accion[0][1].': '.print_r($accion[1], TRUE));
    }

    private function _lanzarEventos(&$eventos)
    {
        foreach ($eventos as $ev) {
            list($target, $msg, $args) = $ev;
            call_user_func_array(
                array($this->_tuberia, 'msg_'.$target.'_'.$msg),
                $args);
        }
    }

    public function limpiezaDemonio($signum)
    {
        // Mandar a cerrar todas las conexiones activas
        $this->_multiplex->finalizarServidor();

        // Se intentan evacuar acciones pendientes
        if (count($this->_accionesPendientes) > 0)
            $this->_log->output('WARN: todavía hay '.count($this->_accionesPendientes).' acciones pendientes.');
        $t1 = time();
        while (time() - $t1 < 10 && !is_null($this->_db) &&
            count($this->_accionesPendientes) > 0) {
            $this->_procesarUnaAccion();

            // No se hace I/O y por lo tanto no se lanzan eventos
        }
        if (count($this->_accionesPendientes) > 0)
            $this->_log->output('ERR: no se pueden evacuar las siguientes acciones: '.
                print_r($this->_accionesPendientes, TRUE));

        // Desconectarse de la base de datos
        $this->_configDB = NULL;
        if (!is_null($this->_db)) {
            $this->_log->output('INFO: desconectando de la base de datos...');
            $this->_db = NULL;
        }
    }

    private function _verificarCambioConfiguracion()
    {
        $iTimestamp = time();
        if ($iTimestamp - $this->_iTimestampUltimaRevisionConfig > 3) {
            $this->_configDB->leerConfiguracionDesdeDB();
            $listaVarCambiadas = $this->_configDB->listaVarCambiadas();
            if (count($listaVarCambiadas) > 0) {
                foreach ($listaVarCambiadas as $k) {
                    if (in_array($k, array('asterisk_asthost', 'asterisk_astuser', 'asterisk_astpass'))) {
                        $this->_tuberia->msg_AMIEventProcess_actualizarConfig(
                            'asterisk_cred', array(
                                $this->_configDB->asterisk_asthost,
                                $this->_configDB->asterisk_astuser,
                                $this->_configDB->asterisk_astpass,
                            ));
                    } elseif (in_array($k, array('asterisk_duracion_sesion',
                        'dialer_llamada_corta', 'dialer_tiempo_contestar',
                        'dialer_debug', 'dialer_allevents'))) {
                        $this->_tuberia->msg_AMIEventProcess_actualizarConfig(
                            $k, $this->_configDB->$k);
                    }

                    if (in_array($k, array('dialer_debug'))) {
                        $this->_tuberia->msg_ECCPProcess_actualizarConfig(
                            $k, $this->_configDB->$k);
                    }
                }

                if (in_array('dialer_debug', $listaVarCambiadas))
                    $this->DEBUG = $this->_configDB->dialer_debug;
                $this->_configDB->limpiarCambios();
            }
            $this->_iTimestampUltimaRevisionConfig = $iTimestamp;
        }
    }

    /* Mandar a los otros procedimientos la información que no pueden leer
     * directamente porque no tienen conexión de base de datos. */
    private function _verificarActualizacionAgentes()
    {
        $iTimestamp = time();
        if ($iTimestamp - $this->_iTimestampActualizacion >= 5 * 60) {
            $this->_actualizarInformacionRemota_agentes();

            $this->_iTimestampActualizacion = $iTimestamp;
        }
    }

    function _actualizarInformacionRemota_agentes()
    {
        $eventos = $this->_requerir_nuevaListaAgentes();
        $this->_lanzarEventos($eventos);
    }

    /**************************************************************************/

    private function _encolarAccionPendiente($method, $params)
    {
        array_push($this->_accionesPendientes, array(
            array($this, $method),    // callable
            $params,    // params
        ));

    }

    public function msg_requerir_nuevaListaAgentes($sFuente, $sDestino, $sNombreMensaje, $iTimestamp, $datos)
    {
        $this->_log->output("INFO: $sFuente requiere refresco de lista de agentes");
        $this->_encolarAccionPendiente('_requerir_nuevaListaAgentes', $datos);
    }

    public function msg_requerir_credencialesAsterisk($sFuente, $sDestino, $sNombreMensaje, $iTimestamp, $datos)
    {
        $this->_log->output("INFO: $sFuente requiere envío de credenciales Asterisk");
        $this->_informarCredencialesAsterisk(TRUE); // <-- no requiere acceso inmediato a base de datos
    }

    public function msg_sqlinsertcalls($sFuente, $sDestino, $sNombreMensaje, $iTimestamp, $datos)
    {
        if ($this->DEBUG) {
            $this->_log->output('DEBUG: '.__METHOD__.' - '.print_r($datos, 1));
        }
        $this->_encolarAccionPendiente('_sqlinsertcalls', $datos);
    }

    public function msg_sqlupdatecalls($sFuente, $sDestino, $sNombreMensaje, $iTimestamp, $datos)
    {
        if ($this->DEBUG) {
            $this->_log->output('DEBUG: '.__METHOD__.' - '.print_r($datos, 1));
        }
        $this->_encolarAccionPendiente('_sqlupdatecalls', $datos);
    }

    public function msg_sqlupdatecurrentcalls($sFuente, $sDestino, $sNombreMensaje, $iTimestamp, $datos)
    {
        if ($this->DEBUG) {
            $this->_log->output('DEBUG: '.__METHOD__.' - '.print_r($datos, 1));
        }
        $this->_encolarAccionPendiente('_sqlupdatecurrentcalls', $datos);
    }

    public function msg_sqlinsertcurrentcalls($sFuente, $sDestino, $sNombreMensaje, $iTimestamp, $datos)
    {
        if ($this->DEBUG) {
            $this->_log->output('DEBUG: '.__METHOD__.' - '.print_r($datos, 1));
        }
        $this->_encolarAccionPendiente('_sqlinsertcurrentcalls', $datos);
    }

    public function msg_sqldeletecurrentcalls($sFuente, $sDestino, $sNombreMensaje, $iTimestamp, $datos)
    {
        if ($this->DEBUG) {
            $this->_log->output('DEBUG: '.__METHOD__.' - '.print_r($datos, 1));
        }
        $this->_encolarAccionPendiente('_sqldeletecurrentcalls', $datos);
    }

    public function msg_sqlupdatestatcampaign($sFuente, $sDestino, $sNombreMensaje, $iTimestamp, $datos)
    {
        if ($this->DEBUG) {
            $this->_log->output('DEBUG: '.__METHOD__.' - '.print_r($datos, 1));
        }
        $this->_encolarAccionPendiente('_sqlupdatestatcampaign', $datos);
    }

    public function msg_agregarArchivoGrabacion($sFuente, $sDestino,
        $sNombreMensaje, $iTimestamp, $datos)
    {
        if ($this->DEBUG) {
            $this->_log->output('DEBUG: '.__METHOD__.' - '.print_r($datos, 1));
        }
        $this->_encolarAccionPendiente('_agregarArchivoGrabacion', $datos);
    }

    public function msg_AgentLogin($sFuente, $sDestino, $sNombreMensaje, $iTimestamp, $datos)
    {
        if ($this->DEBUG) {
            $this->_log->output('DEBUG: '.__METHOD__.' - '.print_r($datos, 1));
        }
        $this->_encolarAccionPendiente('_AgentLogin', $datos);
    }

    public function msg_AgentLogoff($sFuente, $sDestino, $sNombreMensaje, $iTimestamp, $datos)
    {
        if ($this->DEBUG) {
            $this->_log->output('DEBUG: '.__METHOD__.' - '.print_r($datos, 1));
        }
        $this->_encolarAccionPendiente('_AgentLogoff', $datos);
    }

    public function msg_AgentLinked($sFuente, $sDestino, $sNombreMensaje, $iTimestamp, $datos)
    {
        if ($this->DEBUG) {
            $this->_log->output('DEBUG: '.__METHOD__.' - '.print_r($datos, 1));
        }
        $this->_encolarAccionPendiente('_AgentLinked', $datos);
    }

    public function msg_AgentUnlinked($sFuente, $sDestino, $sNombreMensaje, $iTimestamp, $datos)
    {
        if ($this->DEBUG) {
            $this->_log->output('DEBUG: '.__METHOD__.' - '.print_r($datos, 1));
        }
        $this->_encolarAccionPendiente('_AgentUnlinked', $datos);
    }

    public function msg_marcarFinalHold($sFuente, $sDestino, $sNombreMensaje, $iTimestamp, $datos)
    {
        if ($this->DEBUG) {
            $this->_log->output('DEBUG: '.__METHOD__.' - '.print_r($datos, 1));
        }
        $this->_encolarAccionPendiente('_marcarFinalHold', $datos);
    }

    public function msg_nuevaMembresiaCola($sFuente, $sDestino, $sNombreMensaje, $iTimestamp, $datos)
    {
        if ($this->DEBUG) {
            $this->_log->output('DEBUG: '.__METHOD__.' - '.print_r($datos, 1));
        }
        $this->_encolarAccionPendiente('_nuevaMembresiaCola', $datos);
    }

    public function msg_notificarProgresoLlamada($sFuente, $sDestino, $sNombreMensaje, $iTimestamp, $datos)
    {
        if ($this->DEBUG) {
            $this->_log->output('DEBUG: '.__METHOD__.' - '.print_r($datos, 1));
        }
        $this->_encolarAccionPendiente('_notificarProgresoLlamada', $datos);
    }

    public function msg_finalizando($sFuente, $sDestino, $sNombreMensaje, $iTimestamp, $datos)
    {
        $this->_log->output('INFO: recibido mensaje de finalización...');
        $this->_finalizandoPrograma = TRUE;
    }

    public function msg_finalsql($sFuente, $sDestino, $sNombreMensaje, $iTimestamp, $datos)
    {
        if (!$this->_finalizandoPrograma) {
            $this->_log->output('WARN: AMIEventProcess envió mensaje antes que HubProcess');
        }
        $this->_finalizandoPrograma = TRUE;
        $this->_tuberia->msg_HubProcess_finalizacionTerminada();
    }

    /**************************************************************************/

    // Mandar a AMIEventProcess una lista actualizada de los agentes activos
    private function _requerir_nuevaListaAgentes()
    {
        // El ORDER BY del query garantiza que estatus A aparece antes que I
        $recordset = $this->_db->query(
            'SELECT id, number, name, estatus, type FROM agent ORDER BY number, estatus');
        $lista = array(); $listaNum = array();
        foreach ($recordset as $tupla) {
            if (!in_array($tupla['number'], $listaNum)) {
                $lista[] = array(
                    'id'        =>  $tupla['id'],
                    'number'    =>  $tupla['number'],
                    'name'      =>  $tupla['name'],
                    'estatus'   =>  $tupla['estatus'],
                    'type'      =>  $tupla['type'],
                );
                $listaNum[] = $tupla['number'];
            }
        }

        /* Leer el estado de las banderas de activación de eventos de las colas
         * a partir del archivo de configuración. El código a continuación
         * depende de la existencia de queues_additional.conf de una instalación
         * FreePBX, y además asume Asterisk 11 o inferior. Se debe modificar
         * esto cuando se migre a una versión superior de Asterisk que siempre
         * emite los eventos. */
        $queueflags = array();
        if (file_exists('/etc/asterisk/queues_additional.conf')) {
            $queue = NULL;
            foreach (file('/etc/asterisk/queues_additional.conf') as $s) {
                $regs = NULL;
                if (preg_match('/^\[(\S+)\]/', $s, $regs)) {
                    $queue = $regs[1];
                    $queueflags[$queue]['eventmemberstatus'] = FALSE;
                    $queueflags[$queue]['eventwhencalled'] = FALSE;
                } elseif (preg_match('/^(\w+)\s*=\s*(.*)/', trim($s), $regs)) {
                    if (in_array($regs[1], array('eventmemberstatus', 'eventwhencalled'))) {
                        $queueflags[$queue][$regs[1]] = in_array($regs[2], array('yes', 'true', 'y', 't', 'on', '1'));
                    } elseif ($regs[1] == 'member' && (stripos($regs[2], 'SIP/') === 0 || stripos($regs[2], 'IAX2/') === 0)) {
                        $this->_log->output('WARN: '.__METHOD__.': agente estático '.
                            $regs[2].' encontrado en cola '.$queue.' - puede causar problemas.');
                    }
                }
            }
        }

        // Mandar el recordset a AMIEventProcess como un mensaje
        return array(
            array('AMIEventProcess', 'nuevaListaAgentes', array($lista, $queueflags)),
        );
    }

    private function _sqlinsertcalls($paramInsertar)
    {
        $eventos = array();

        // Porción que identifica la tabla a modificar
        $tipo_llamada = $paramInsertar['tipo_llamada'];
        unset($paramInsertar['tipo_llamada']);
        switch ($tipo_llamada) {
        case 'outgoing':
            $sqlTabla = 'INSERT INTO calls ';
            break;
        case 'incoming':
            $sqlTabla = 'INSERT INTO call_entry ';
            break;
        default:
            $this->_log->output('ERR: '.__METHOD__.' no debió haberse recibido para '.
                print_r($paramInsertar, TRUE));
            return $eventos;
        }

        // Caso especial: llamada entrante requiere ID de contacto
        if ($tipo_llamada == 'incoming') {
            /* Se consulta el posible contacto en base al caller-id. Si hay
             * exactamente un contacto, su ID se usa para la inserción. */
            $recordset = $this->_db->prepare('SELECT id FROM contact WHERE telefono = ?');

            $recordset->execute(array($paramInsertar['callerid']));
            $listaIdContactos = $recordset->fetchAll(PDO::FETCH_COLUMN, 0);
            if (count($listaIdContactos) == 1) {
                $paramInsertar['id_contact'] = $listaIdContactos[0];
            }
        }

        $sqlCampos = array();
        $params = array();
        foreach ($paramInsertar as $k => $v) {
            $sqlCampos[] = $k;
            $params[] = $v;
        }
        $sql = $sqlTabla.'('.implode(', ', $sqlCampos).') VALUES ('.
            implode(', ', array_fill(0, count($params), '?')).')';

        $sth = $this->_db->prepare($sql);
        $sth->execute($params);
        $idCall = $this->_db->lastInsertId();

        // Mandar de vuelta el ID de inserción a AMIEventProcess
        $eventos[] = array('AMIEventProcess', 'idnewcall',
            array($tipo_llamada, $paramInsertar['uniqueid'], $idCall));

        // Para llamada entrante se debe de insertar el log de progreso
        if ($tipo_llamada == 'incoming') {
            // Notificar el progreso de la llamada
            $infoProgreso = array(
                'datetime_entry'        =>  $paramInsertar['datetime_entry_queue'],
                'new_status'            =>  'OnQueue',
                'id_campaign_incoming'  =>  $paramInsertar['id_campaign'],
                'id_call_incoming'      =>  $idCall,
                'uniqueid'              =>  $paramInsertar['uniqueid'],
                'trunk'                 =>  $paramInsertar['trunk'],
            );

            list($id_campaignlog, $eventos_forward) = $this->_construirEventoProgresoLlamada($infoProgreso);
            $eventos[] = array('ECCPProcess', 'emitirEventos',
                array($eventos_forward));
        }

        return $eventos;
    }

    // Procedimiento que actualiza una sola llamada de la tabla calls o call_entry
    private function _sqlupdatecalls($paramActualizar)
    {
        $eventos = array();

        $sql_list = array();
        $id_llamada = NULL;

        // Porción que identifica la tabla a modificar
        $tipo_llamada = $paramActualizar['tipo_llamada'];
        unset($paramActualizar['tipo_llamada']);
        switch ($tipo_llamada) {
        case 'outgoing':
            $sqlTabla = 'UPDATE calls SET ';
            break;
        case 'incoming':
            $sqlTabla = 'UPDATE call_entry SET ';
            break;
        default:
            $this->_log->output('ERR: '.__METHOD__.' no debió haberse recibido para '.
                print_r($paramActualizar, TRUE));
            return $eventos;
        }

        // Porción que identifica la tupla a modificar
        $sqlWhere = array();
        $paramWhere = array();
        if (isset($paramActualizar['id_campaign'])) {
            if (!is_null($paramActualizar['id_campaign'])) {
                $sqlWhere[] = 'id_campaign = ?';
                $paramWhere[] = $paramActualizar['id_campaign'];
            }
            unset($paramActualizar['id_campaign']);
        }
        if (isset($paramActualizar['id'])) {
            $sqlWhere[] = 'id = ?';
            $paramWhere[] = $paramActualizar['id'];
            $id_llamada = $paramActualizar['id'];
            unset($paramActualizar['id']);
        }

        // Parámetros a modificar
        $sqlCampos = array();
        $paramCampos = array();

        // TODO: revisar si es necesario inc_retries, porque campañas
        // salientes incrementan directamente al cambiar a Placing
        //
        // Caso especial: retries se debe de incrementar
        if (isset($paramActualizar['inc_retries'])) {
            $sqlCampos[] = 'retries = retries + ?';
            $paramCampos[] = $paramActualizar['inc_retries'];
            unset($paramActualizar['inc_retries']);
        }
        foreach ($paramActualizar as $k => $v) {
            $sqlCampos[] = "$k = ?";
            $paramCampos[] = $v;
        }
        $sql_list[] = array(
            $sqlTabla.implode(', ', $sqlCampos).' WHERE '.implode(' AND ', $sqlWhere),
            array_merge($paramCampos, $paramWhere),
        );

        $id_contact = NULL;
        $failstates = array('Failure', 'NoAnswer', 'ShortCall', 'Abandoned');

        foreach ($sql_list as $sql_item) {
            $sth = $this->_db->prepare($sql_item[0]);
            $sth->execute($sql_item[1]);
        }

        return $eventos;
    }

    // Procedimiento que inserta un solo registro en current_calls o current_call_entry
    private function _sqlinsertcurrentcalls($paramInsertar)
    {
        $eventos = array();

        // Porción que identifica la tabla a modificar
        $tipo_llamada = $paramInsertar['tipo_llamada'];
        unset($paramInsertar['tipo_llamada']);
        switch ($tipo_llamada) {
        case 'outgoing':
            $sqlTabla = 'INSERT INTO current_calls ';
            break;
        case 'incoming':
            $sqlTabla = 'INSERT INTO current_call_entry ';
            break;
        default:
            $this->_log->output('ERR: '.__METHOD__.' no debió haberse recibido para '.
                print_r($paramInsertar, TRUE));
            return $eventos;
        }

        $sqlCampos = array();
        $params = array();
        foreach ($paramInsertar as $k => $v) {
            $sqlCampos[] = $k;
            $params[] = $v;
        }
        $sql = $sqlTabla.'('.implode(', ', $sqlCampos).') VALUES ('.
            implode(', ', array_fill(0, count($params), '?')).')';

        $sth = $this->_db->prepare($sql);
        $sth->execute($params);

        // Mandar de vuelta el ID de inserción a AMIEventProcess
        $eventos[] = array('AMIEventProcess', 'idcurrentcall', array(
            $tipo_llamada,
            isset($paramInsertar['id_call_entry'])
            ? $paramInsertar['id_call_entry']
            : $paramInsertar['id_call'],
            $this->_db->lastInsertId())
        );

        return $eventos;
    }

    // Procedimiento que actualiza un solo registro en current_calls o current_call_entry
    private function _sqlupdatecurrentcalls($paramActualizar)
    {
        $eventos = array();

        // Porción que identifica la tabla a modificar
        switch ($paramActualizar['tipo_llamada']) {
        case 'outgoing':
            $sqlTabla = 'UPDATE current_calls SET ';
            break;
        case 'incoming':
            $sqlTabla = 'UPDATE current_call_entry SET ';
            break;
        default:
            $this->_log->output('ERR: '.__METHOD__.' no debió haberse recibido para '.
                print_r($paramActualizar, TRUE));
            return $eventos;
        }
        unset($paramActualizar['tipo_llamada']);

        // Porción que identifica la tupla a modificar
        $sqlWhere = array();
        $paramWhere = array();
        if (isset($paramActualizar['id'])) {
            $sqlWhere[] = 'id = ?';
            $paramWhere[] = $paramActualizar['id'];
            unset($paramActualizar['id']);
        }

        // Parámetros a modificar
        $sqlCampos = array();
        $paramCampos = array();

        foreach ($paramActualizar as $k => $v) {
            $sqlCampos[] = "$k = ?";
            $paramCampos[] = $v;
        }

        $sql = $sqlTabla.implode(', ', $sqlCampos).' WHERE '.implode(' AND ', $sqlWhere);
        $params = array_merge($paramCampos, $paramWhere);

        $sth = $this->_db->prepare($sql);
        $sth->execute($params);

        return $eventos;
    }

    private function _sqldeletecurrentcalls($paramBorrar)
    {
        $eventos = array();

        // Esto no debería pasar (manualdialing)
        if (!in_array($paramBorrar['tipo_llamada'], array('incoming', 'outgoing'))) {
            $this->_log->output('ERR: '.__METHOD__.' no debió haberse recibido para '.
                print_r($paramBorrar, TRUE));
            return $eventos;
        }

        // Porción que identifica la tabla a modificar
        $sth = $this->_db->prepare(($paramBorrar['tipo_llamada'] == 'outgoing')
            ? 'DELETE FROM current_calls WHERE id = ?'
            : 'DELETE FROM current_call_entry WHERE id = ?');
        $sth->execute(array($paramBorrar['id']));

        return $eventos;
    }

    private function _sqlupdatestatcampaign($id_campaign, $num_completadas,
            $promedio, $desviacion)
    {
        $eventos = array();

        $sth = $this->_db->prepare(
            'UPDATE campaign SET num_completadas = ?, promedio = ?, desviacion = ? WHERE id = ?');
        $sth->execute(array($num_completadas, $promedio, $desviacion, $id_campaign));

        return $eventos;
    }

    private function _agregarArchivoGrabacion($tipo_llamada, $id_llamada, $uniqueid, $channel, $recordingfile)
    {
        $eventos = array();

        // TODO: configurar prefijo de monitoring
        $sDirBaseMonitor = '/var/spool/asterisk/monitor/';

        // Quitar el prefijo de monitoring de todos los archivos
        if (strpos($recordingfile, $sDirBaseMonitor) === 0)
            $recordingfile = substr($recordingfile, strlen($sDirBaseMonitor));

        // Se asume que el archivo está completo con extensión
        $field = 'id_call_'.$tipo_llamada;
        $recordset = $this->_db->prepare("SELECT COUNT(*) AS N FROM call_recording WHERE {$field} = ? AND recordingfile = ?");
        $recordset->execute(array($id_llamada, $recordingfile));
        $iNumDuplicados = $recordset->fetch(PDO::FETCH_COLUMN, 0);
        $recordset->closeCursor();
        if ($iNumDuplicados <= 0) {
            // El archivo no constaba antes - se inserta con los datos actuales
            $sth = $this->_db->prepare(
                "INSERT INTO call_recording (datetime_entry, {$field}, uniqueid, channel, recordingfile) ".
                'VALUES (NOW(), ?, ?, ?, ?)');
            $sth->execute(array($id_llamada, $uniqueid, $channel, $recordingfile));
        }

        return $eventos;
    }

    private function _AgentLogin($sAgente, $iTimestampLogin, $id_agent)
    {
        $eventos = array();
        $eventos_forward = array();

        if (is_null($id_agent)) {
            // Ha fallado un intento de login
            $eventos_forward[] = array('AgentLogin', array($sAgente, FALSE));
        } else {
            $id_sesion = $this->_marcarInicioSesionAgente($id_agent, $iTimestampLogin);
            if (!is_null($id_sesion)) {
                $eventos[] = array('AMIEventProcess', 'idNuevaSesionAgente', array($sAgente, $id_sesion));

                // Notificar a todas las conexiones abiertas
                $eventos_forward[] = array('AgentLogin', array($sAgente, TRUE));
            }
        }

        $eventos[] = array('ECCPProcess', 'emitirEventos', array($eventos_forward));
        return $eventos;
    }

    private function _AgentLogoff($sAgente, $iTimestampLogout, $id_agent, $id_sesion, $pausas)
    {
        $eventos = array();
        $eventos_forward = array();

        // Escribir la información de auditoría en la base de datos
        foreach ($pausas as $tipo_pausa => $id_pausa) if (!is_null($id_pausa)) {
            // TODO: ¿Qué ocurre con la posible llamada parqueada?
            marcarFinalBreakAgente($this->_db, $id_pausa, $iTimestampLogout);
            $eventos_forward[] = construirEventoPauseEnd($this->_db, $sAgente, $id_pausa, $tipo_pausa);
        }
        marcarFinalBreakAgente($this->_db, $id_sesion, $iTimestampLogout);

        // Notificar a todas las conexiones abiertas
        $eventos_forward[] = array('AgentLogoff', array($sAgente));

        $eventos[] = array('ECCPProcess', 'emitirEventos', array($eventos_forward));
        return $eventos;
    }

    /**
     * Método para marcar en las tablas de auditoría que el agente ha iniciado
     * la sesión. Esta implementación verifica si el agente ya ha sido marcado
     * previamente como que inició la sesión, y sólo marca el inicio si no está
     * ya marcado antes.
     *
     * @param   string  $sAgente    Canal del agente que se verifica sesión
     * @param   int     $id_agent   ID en base de datos del agente
     * @param   float   $iTimestampLogin timestamp devuelto por microtime() de login
     *
     * @return  mixed   NULL en error, o el ID de la auditoría de inicio de sesión
     */
    private function _marcarInicioSesionAgente($idAgente, $iTimestampLogin)
    {
        // Verificación de sesión activa
        $sPeticionExiste = <<<SQL_EXISTE_AUDIT
SELECT id FROM audit
WHERE id_agent = ? AND datetime_init >= ? AND datetime_end IS NULL
    AND duration IS NULL AND id_break IS NULL
ORDER BY datetime_init DESC
SQL_EXISTE_AUDIT;
        $recordset = $this->_db->prepare($sPeticionExiste);
        $recordset->execute(array($idAgente, date('Y-m-d H:i:s', $this->_iTimestampInicioProceso)));
        $tupla = $recordset->fetch();
        $recordset->closeCursor();

        // Se indica éxito de inmediato si ya hay una sesión
        $idAudit = NULL;
        if ($tupla) {
            $idAudit = $tupla['id'];
            $this->_log->output('WARN: '.__METHOD__.": id_agente={$idAgente} ".
                    'inició sesión en '.date('Y-m-d H:i:s', $iTimestampLogin).
                    " pero hay sesión abierta ID={$idAudit}, se reusa.");
        } else {
            // Ingreso de sesión del agente
            $sTimeStamp = date('Y-m-d H:i:s', $iTimestampLogin);
            $sth = $this->_db->prepare('INSERT INTO audit (id_agent, datetime_init) VALUES (?, ?)');
            $sth->execute(array($idAgente, $sTimeStamp));
            $idAudit = $this->_db->lastInsertId();
        }

        return $idAudit;
    }

    private function _AgentLinked($sTipoLlamada, $idCampania, $idLlamada,
        $sChannel, $sRemChannel, $sFechaLink, $id_agent, $trunk, $queue)
    {
        $eventos = array();
        $eventos_forward = array();

        $infoLlamada = leerInfoLlamada($this->_db, $sTipoLlamada, $idCampania, $idLlamada);
        /* Ya que la escritura a la base de datos es asíncrona, puede
         * ocurrir que se lea la llamada en el estado OnQueue y sin fecha
         * de linkstart. */
        $infoLlamada['status'] = ($infoLlamada['calltype'] == 'incoming') ? 'activa' : 'Success';
        if (!isset($infoLlamada['queue']) && !is_null($queue))
            $infoLlamada['queue'] = $queue;
        $infoLlamada['datetime_linkstart'] = $sFechaLink;
        if (!isset($infoLlamada['trunk']) || is_null($infoLlamada['trunk']))
            $infoLlamada['trunk'] = $trunk;

        // Notificar el progreso de la llamada
        $paramProgreso = array(
            'datetime_entry'    =>  $sFechaLink,
            'new_status'        =>  'Success',
            'id_agent'          =>  $id_agent,
        );
        $paramProgreso['id_call_'.$sTipoLlamada] = $idLlamada;
        if (!is_null($idCampania)) $paramProgreso['id_campaign_'.$sTipoLlamada] = $idCampania;

        list($infoLlamada['campaignlog_id'], $eventos_forward) = $this->_construirEventoProgresoLlamada($paramProgreso);
        $eventos_forward[] = array('AgentLinked', array($sChannel, $sRemChannel, $infoLlamada));

        $eventos[] = array('ECCPProcess', 'emitirEventos', array($eventos_forward));
        return $eventos;
    }

    private function _AgentUnlinked($sAgente, $sTipoLlamada, $idCampaign,
        $idLlamada, $sPhone, $sFechaFin, $iDuracion, $bShortFlag, $paramProgreso)
    {
        $eventos = array();
        $eventos_forward = array();

        $infoLlamada = array(
            'calltype'      =>  $sTipoLlamada,
            'campaign_id'   =>  $idCampaign,
            'call_id'       =>  $idLlamada,
            'phone'         =>  $sPhone,
            'datetime_linkend'  =>  $sFechaFin,
            'duration'      =>  $iDuracion,
            'shortcall'     =>  $bShortFlag ? 1 : 0,
            'campaignlog_id'=>  NULL,
            'queue'         =>  $paramProgreso['queue'],
        );

        list($infoLlamada['campaignlog_id'], $eventos_forward) = $this->_construirEventoProgresoLlamada($paramProgreso);
        $eventos_forward[] = array('AgentUnlinked', array($sAgente, $infoLlamada));

        $eventos[] = array('ECCPProcess', 'emitirEventos', array($eventos_forward));
        return $eventos;
    }

    private function _marcarFinalHold($iTimestampFinalPausa, $sAgente, $infoLlamada, $infoSeguimiento)
    {
        $eventos = array();
        $eventos_forward = array();

        // Actualizar las tablas de calls y current_calls
        // TODO: esto es equivalente a SQLWorkerProcess->sqlupdatecurrentcalls
        if ($infoLlamada['calltype'] == 'incoming') {
            $sth = $this->_db->prepare(
                'UPDATE current_call_entry SET hold = ? WHERE id = ?');
            $sth->execute(array('N', $infoLlamada['currentcallid']));
            $sth = $this->_db->prepare('UPDATE call_entry set status = ? WHERE id = ?');
            $sth->execute(array('activa', $infoLlamada['callid']));
        } elseif ($infoLlamada['calltype'] == 'outgoing') {
            $sth = $this->_db->prepare(
                'UPDATE current_calls SET hold = ? WHERE id = ?');
            $sth->execute(array('N', $infoLlamada['currentcallid']));
            $sth = $this->_db->prepare('UPDATE calls set status = ? WHERE id = ?');
            $sth->execute(array('Success', $infoLlamada['callid']));
        }

        // Auditoría del fin del hold
        marcarFinalBreakAgente($this->_db, $infoSeguimiento['id_audit_hold'], $iTimestampFinalPausa);
        $eventos_forward[] = construirEventoPauseEnd($this->_db, $sAgente, $infoSeguimiento['id_audit_hold'], 'hold');

        $eventos[] = array('ECCPProcess', 'emitirEventos', array($eventos_forward));
        return $eventos;
    }

    private function _nuevaMembresiaCola($sAgente, $infoSeguimiento, $listaColas)
    {
        $eventos = array();
        $eventos_forward = array();

        $recordset_breakinfo = NULL;
        cargarInfoPausa($this->_db, $infoSeguimiento, $recordset_breakinfo);
        $eventos_forward[] = array('QueueMembership', array($sAgente, $infoSeguimiento, $listaColas));

        $eventos[] = array('ECCPProcess', 'emitirEventos', array($eventos_forward));
        return $eventos;
    }

    private function _notificarProgresoLlamada($prop)
    {
        $eventos = array();
        $eventos_forward = array();

        // Para asegurar orden estricto de eventos
        if (isset($prop['extra_events'])) {
            $eventos_forward = array_merge($eventos_forward, $prop['extra_events']);
            unset($prop['extra_events']);
        }

        list($id_campaignlog, $eventos_progreso) = $this->_construirEventoProgresoLlamada($prop);
        $eventos_forward = array_merge($eventos_forward, $eventos_progreso);

        $eventos[] = array('ECCPProcess', 'emitirEventos', array($eventos_forward));
        return $eventos;
    }

    private function _construirEventoProgresoLlamada($prop)
    {
        $id_campaignlog = NULL;
        $ev = NULL;
        $evlist = array();

        $campaign_type = NULL;
        foreach (array('incoming', 'outgoing') as $ct) {
            if (isset($prop['id_call_'.$ct])) {
                $campaign_type = $ct;
                break;
            }
        }

        /* Se leen las propiedades del último log de la llamada, o NULL si no
         * hay cambio de estado previo. */
        $recordset = $this->_db->prepare(
            "SELECT retry, uniqueid, trunk, id_agent, duration ".
            "FROM call_progress_log WHERE id_call_{$campaign_type} = ? ".
            "ORDER BY datetime_entry DESC, id DESC LIMIT 0,1");
        $recordset->execute(array($prop['id_call_'.$campaign_type]));
        $tuplaAnterior = $recordset->fetch(PDO::FETCH_ASSOC);
        $recordset->closeCursor();
        if (!is_array($tuplaAnterior) || count($tuplaAnterior) <= 0) {
            $tuplaAnterior = array(
                'retry'             =>  0,
                'uniqueid'          =>  NULL,
                'trunk'             =>  NULL,
                'id_agent'          =>  NULL,
                'duration'          =>  NULL,
            );
        }

        // Obtener agente agendado avisado por CampaignProcess o AMIEventProcess
        $agente_agendado = NULL;
        if (isset($prop['agente_agendado'])) {
            $agente_agendado = $prop['agente_agendado'];
            unset($prop['agente_agendado']);
        }

        // Si el número de reintento es distinto, se anulan datos anteriores
        if (isset($prop['retry']) && $tuplaAnterior['retry'] != $prop['retry']) {
            $tuplaAnterior['uniqueid'] = NULL;
            $tuplaAnterior['trunk'] = NULL;
            $tuplaAnterior['id_agent'] = NULL;
            $tuplaAnterior['duration'] = NULL;
        }
        $tuplaAnterior = array_merge($tuplaAnterior, $prop);

        // Escribir los valores nuevos en un nuevo registro
        unset($tuplaAnterior['queue']);
        $columnas = array_keys($tuplaAnterior);
        $paramSQL = array();
        foreach ($columnas as $k) $paramSQL[] = $tuplaAnterior[$k];
        $sPeticionSQL = 'INSERT INTO call_progress_log ('.
                implode(', ', $columnas).') VALUES ('.
                implode(', ', array_fill(0, count($columnas), '?')).')';
        $sth = $this->_db->prepare($sPeticionSQL);
        $sth->execute($paramSQL);

        $id_campaignlog = $tuplaAnterior['id'] = $this->_db->lastInsertId();

        // Avisar el inicio del marcado de la llamada saliente agendada
        if ($campaign_type == 'outgoing' && !is_null($agente_agendado)) {
            if ($tuplaAnterior['new_status'] == 'Placing') {
                $ev = array('ScheduledCallStart', array($agente_agendado, $campaign_type,
                    $tuplaAnterior['id_campaign_outgoing'], $tuplaAnterior['id_call_outgoing']));
                $evlist[] = $ev;
            }
            if (in_array($tuplaAnterior['new_status'], array('NoAnswer', 'Failure'))) {
                $ev = array('ScheduledCallFailed', array($agente_agendado, $campaign_type,
                    $tuplaAnterior['id_campaign_outgoing'], $tuplaAnterior['id_call_outgoing']));
                $evlist[] = $ev;
            }
        }

        /* Emitir el evento a las conexiones ECCP. Para mantener la
         * consistencia con el resto del API, se quitan los valores de
        * id_call_* y id_campaign_*, y se sintetiza tipo_llamada. */
        if (!in_array($tuplaAnterior['new_status'], array('Success', 'Hangup', 'ShortCall'))) {
            // Todavía no se soporta emitir agente conectado para OnHold/OffHold
            unset($tuplaAnterior['id_agent']);

            $tuplaAnterior['campaign_type'] = $campaign_type;
            if (isset($tuplaAnterior['id_campaign_'.$campaign_type]))
                $tuplaAnterior['campaign_id'] = $tuplaAnterior['id_campaign_'.$campaign_type];
            $tuplaAnterior['call_id'] = $tuplaAnterior['id_call_'.$campaign_type];
            unset($tuplaAnterior['id_campaign_'.$campaign_type]);
            unset($tuplaAnterior['id_call_'.$campaign_type]);

            // Agregar el teléfono callerid o marcado
            $sql = array(
                'outgoing'  =>
                    'SELECT calls.phone, campaign.queue '.
                    'FROM calls, campaign '.
                    'WHERE calls.id_campaign = campaign.id AND calls.id = ?',
                'incoming'  =>
                    'SELECT call_entry.callerid AS phone, queue_call_entry.queue '.
                    'FROM call_entry, queue_call_entry '.
                    'WHERE call_entry.id_queue_call_entry = queue_call_entry.id AND call_entry.id = ?',
            );
            $recordset = $this->_db->prepare($sql[$tuplaAnterior['campaign_type']]);
            $recordset->execute(array($tuplaAnterior['call_id']));
            $tuplaNumero = $recordset->fetch(PDO::FETCH_ASSOC);
            $recordset->closeCursor();
            $tuplaAnterior['phone'] = $tuplaNumero['phone'];
            $tuplaAnterior['queue'] = $tuplaNumero['queue'];
            $ev = array('CallProgress', array($tuplaAnterior));
            $evlist[] = $ev;
        }

        return array($id_campaignlog, $evlist);
    }

    /**************************************************************************/

    /**
     * Procedimiento que intenta reparar los registros de auditoría que no están
     * correctamente cerrados, es decir, que tiene NULL como fecha de cierre.
     * Primero se identifican los agentes para los cuales existen auditorías
     * incompletas, y luego se intenta reparar para cada agente. Se asume que
     * este método se invoca ANTES de empezar a escuchar peticiones ECCP, y que
     * la base de datos es modificada únicamente por este proceso, y no por
     * otras copias concurrentes del dialer (lo cual no está soportado
     * actualmente).
     */
    private function _repararAuditoriasIncompletas()
    {
        try {
            $sPeticionSQL = <<<AGENTES_AUDIT_INCOMPLETO
SELECT DISTINCT agent.id, agent.type, agent.number, agent.name, agent.estatus
FROM audit, agent
WHERE agent.id = audit.id_agent AND audit.id_break IS NULL AND audit.datetime_end IS NULL
ORDER BY agent.id
AGENTES_AUDIT_INCOMPLETO;
            $recordset = $this->_db->prepare($sPeticionSQL);
            $recordset->execute();
            $agentesReparar = $recordset->fetchAll(PDO::FETCH_ASSOC);
            $recordset->closeCursor();
            foreach ($agentesReparar as $row) {
            	$this->_log->output('INFO: se ha detectado auditoría incompleta '.
                    "para {$row['type']}/{$row['number']} - {$row['name']} ".
                    "(id_agent={$row['id']} ".(($row['estatus'] == 'A') ? 'ACTIVO' : 'INACTIVO').")");
                $this->_repararAuditoriaAgente($row['id']);
            }
        } catch (PDOException $e) {
            $this->_stdManejoExcepcionDB($e, 'no se puede terminar de reparar auditorías');
        }
    }

    private function _repararAuditoriaAgente($idAgente)
    {
        // Listar todas las auditorías incompletas para este agente
        $sPeticionAuditorias = <<<LISTA_AUDITORIAS_AGENTE
SELECT id, datetime_init FROM audit
WHERE id_agent = ? AND id_break IS NULL AND datetime_end IS NULL
ORDER BY datetime_init
LISTA_AUDITORIAS_AGENTE;
        $recordset = $this->_db->prepare($sPeticionAuditorias);
        $recordset->execute(array($idAgente));
        $listaAudits = $recordset->fetchAll(PDO::FETCH_ASSOC);
        $recordset->closeCursor();

        foreach ($listaAudits as $auditIncompleto) {
            /* Se intenta examinar la base de datos para obtener la fecha
             * máxima para la cual hay evidencia de actividad entre el inicio
             * de este registro y el inicio del siguiente registro. */
            $this->_log->output("INFO:\tSesión ID={$auditIncompleto['id']} iniciada en {$auditIncompleto['datetime_init']}");

            $sFechaSiguienteSesion = NULL;
            $idUltimoBreak = NULL;
            $sFechaInicioBreak = NULL;
            $sFechaFinalBreak = NULL;
            $sFechaInicioLlamada = NULL;
            $sFechaFinalLlamada = NULL;

            // El inicio de la siguiente sesión es un tope máximo para el final de la sesión incompleta.
            $recordset = $this->_db->prepare(
                'SELECT datetime_init FROM audit WHERE id_agent = ? AND id_break IS NULL '.
                'AND datetime_init > ? ORDER BY datetime_init LIMIT 0,1');
            $recordset->execute(array($idAgente, $auditIncompleto['datetime_init']));
            $tupla = $recordset->fetch(PDO::FETCH_ASSOC);
            $recordset->closeCursor();
            if (!$tupla) {
                $this->_log->output("INFO:\tNo hay sesiones posteriores a esta sesión incompleta.");
            } else {
                $this->_log->output("INFO:\tSiguiente sesión iniciada en {$tupla['datetime_init']}");
                $sFechaSiguienteSesion = $tupla['datetime_init'];
            }

            /* La sesión sólo puede extenderse hasta el final de la pausa antes de
             * la siguiente sesión, o la fecha actual */
            $recordset = $this->_db->prepare(
                'SELECT id, datetime_init, datetime_end FROM audit WHERE id_agent = ? '.
                    'AND id_break IS NOT NULL AND datetime_init > ? AND datetime_init < ? ' .
                'ORDER BY datetime_init DESC LIMIT 0,1');
            $recordset->execute(array($idAgente, $auditIncompleto['datetime_init'],
                (is_null($sFechaSiguienteSesion) ? date('Y-m-d H:i:s') : $sFechaSiguienteSesion)));
            $tupla = $recordset->fetch(PDO::FETCH_ASSOC);
            $recordset->closeCursor();
            if (!$tupla) {
                $this->_log->output("INFO:\tNo hay breaks pertenecientes a esta sesión incompleta.");
            } else {
                $this->_log->output("INFO:\tÚltimo break de sesión incompleta inicia en {$tupla['datetime_init']}, ".
                    (is_null($tupla['datetime_end']) ? 'está incompleto' : 'termina en '.$tupla['datetime_end']));
                $idUltimoBreak = $tupla['id'];
                $sFechaInicioBreak = $tupla['datetime_init'];
                $sFechaFinalBreak = $tupla['datetime_end'];
            }

            /* La sesión sólo puede extenderse hasta el final de la última llamada
             * atendida antes de la siguiente sesión, si existe, o hasta la fecha
             * actual */
            $recordset = $this->_db->prepare(
                'SELECT start_time, end_time FROM calls '.
                'WHERE id_agent = ? AND start_time >= ? AND start_time < ? '.
                'ORDER BY start_time DESC LIMIT 0,1');
            $recordset->execute(array($idAgente, $auditIncompleto['datetime_init'],
                (is_null($sFechaSiguienteSesion) ? date('Y-m-d H:i:s') : $sFechaSiguienteSesion)));
            $tupla = $recordset->fetch(PDO::FETCH_ASSOC);
            $recordset->closeCursor();
            if (!$tupla) {
                $this->_log->output("INFO:\tNo hay llamadas salientes pertenecientes a esta sesión incompleta.");
            } else {
                $this->_log->output("INFO:\tÚltima llamada saliente de sesión incompleta inicia en {$tupla['start_time']}, ".
                    (is_null($tupla['end_time']) ? 'está incompleta' : 'termina en '.$tupla['end_time']));
                $sFechaInicioLlamada = $tupla['start_time'];
                $sFechaFinalLlamada = $tupla['end_time'];
            }
            $recordset = $this->_db->prepare(
                'SELECT datetime_init, datetime_end FROM call_entry '.
                'WHERE id_agent = ? AND datetime_init >= ? AND datetime_init < ? '.
                'ORDER BY datetime_init DESC LIMIT 0,1');
            $recordset->execute(array($idAgente, $auditIncompleto['datetime_init'],
                (is_null($sFechaSiguienteSesion) ? date('Y-m-d H:i:s') : $sFechaSiguienteSesion)));
            $tupla = $recordset->fetch(PDO::FETCH_ASSOC);
            $recordset->closeCursor();
            if (!$tupla) {
                $this->_log->output("INFO:\tNo hay llamadas entrantes pertenecientes a esta sesión incompleta.");
            } else {
                $this->_log->output("INFO:\tÚltima llamada entrante de sesión incompleta inicia en {$tupla['datetime_init']}, ".
                    (is_null($tupla['datetime_end']) ? 'está incompleta' : 'termina en '.$tupla['datetime_end']));
                if (is_null($sFechaInicioLlamada) || $sFechaInicioLlamada < $tupla['datetime_init'])
                    $sFechaInicioLlamada = $tupla['datetime_init'];
                if (is_null($sFechaFinalLlamada) || $sFechaFinalLlamada < $tupla['datetime_end'])
                    $sFechaFinalLlamada = $tupla['datetime_end'];
            }

            /* De entre todas las fecha recogidas, se elige la más reciente como
             * la fecha de final de auditoría. Esto incluye a la fecha de inicio
             * de auditoría, con lo que una auditoría sin otros indicios quedará
             * de longitud cero. */
            $sFechaFinal = $auditIncompleto['datetime_init'];
            if (!is_null($sFechaInicioBreak) && $sFechaInicioBreak > $sFechaFinal)
                $sFechaFinal = $sFechaInicioBreak;
            if (!is_null($sFechaFinalBreak) && $sFechaFinalBreak > $sFechaFinal)
                $sFechaFinal = $sFechaFinalBreak;
            if (!is_null($sFechaInicioLlamada) && $sFechaInicioLlamada > $sFechaFinal)
                $sFechaFinal = $sFechaInicioLlamada;
            if (!is_null($sFechaFinalLlamada) && $sFechaFinalLlamada > $sFechaFinal)
                $sFechaFinal = $sFechaFinalLlamada;

            $this->_log->output("INFO:\t\\--> Fecha estimada de final de sesión es $sFechaFinal, se actualiza...");
            $sth = $this->_db->prepare(
                'UPDATE audit SET datetime_end = ?, duration = TIMEDIFF(?, datetime_init) WHERE id = ?');
            if (!is_null($idUltimoBreak) && is_null($sFechaFinalBreak)) {
                $sth->execute(array($sFechaFinal, $sFechaFinal, $idUltimoBreak));
            }
            $sth->execute(array($sFechaFinal, $sFechaFinal, $auditIncompleto['id']));
        }
    }

    private function _stdManejoExcepcionDB($e, $s)
    {
        $this->_log->output('ERR: '.__METHOD__. ": $s: ".implode(' - ', $e->errorInfo));
        $this->_log->output("ERR: traza de pila: \n".$e->getTraceAsString());
        if ($e->errorInfo[0] == 'HY000' && $e->errorInfo[1] == 2006) {
            // Códigos correspondientes a pérdida de conexión de base de datos
            $this->_log->output('WARN: '.__METHOD__.
                ': conexión a DB parece ser inválida, se cierra...');
            $this->_db = NULL;
        }
    }
}
