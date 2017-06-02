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
$Id: ECCPWorkerProcess.class.php,v 1.48 2009/03/26 13:46:58 alex Exp $ */

/* Número máximo de peticiones que serán atendidas por proceso. Ya que se crean
 * procesos adicionales en condiciones de tráfico pesado, esto garantiza que los
 * procesos no permanecerán indefinidamente en ejecución. */
define('MAX_PETICIONES_ATENDIDAS', 16384);

class ECCPWorkerProcess extends TuberiaProcess
{
    private $DEBUG = FALSE; // VERDADERO si se activa la depuración

    private $_log;      // Log abierto por framework de demonio
    private $_dsn;      // Cadena que representa el DSN, estilo PDO
    private $_db;       // Conexión a la base de datos, PDO
    private $_ami = NULL;       // Conexión AMI a Asterisk
    private $_configDB; // Objeto de configuración desde la base de datos

    // Contadores para actividades ejecutadas regularmente
    private $_iTimestampUltimaRevisionConfig = 0;       // Última revisión de configuración

    /* Si se pone a VERDADERO, el programa intenta finalizar y no deben
     * aceptarse conexiones nuevas. Todas las conexiones existentes serán
    * desconectadas. */
    private $_finalizandoPrograma = FALSE;

    private $_eccpconn;
    private $_numPeticionesAtendidas = 0;

    public function inicioPostDemonio($infoConfig, &$oMainLog)
    {
        $this->_log = $oMainLog;
        $this->_multiplex = new MultiplexServer(NULL, $this->_log);
        $this->_tuberia->registrarMultiplexHijo($this->_multiplex);
        $this->_tuberia->setLog($this->_log);

        $this->_eccpconn = new ECCPConn($this->_log, $this->_tuberia);

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
        $this->DEBUG = $this->_configDB->dialer_debug;
        $this->_eccpconn->DEBUG = $this->DEBUG;

        // Iniciar la conexión Asterisk
        if (!$this->_iniciarConexionAMI()) return FALSE;

        // Registro de manejadores de eventos
        foreach (array('eccprequest') as $k)
            $this->_tuberia->registrarManejador('ECCPProcess', $k, array($this, "msg_$k"));

        // Registro de manejadores de eventos desde HubProcess
        foreach (array('finalizando', 'finalizarWorker') as $k)
            $this->_tuberia->registrarManejador('HubProcess', $k, array($this, "msg_$k"));

        return TRUE;
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
            $this->_eccpconn->setDbConn($this->_db);
            return TRUE;
        } catch (PDOException $e) {
            $this->_db = NULL;
            $this->_log->output("FATAL: no se puede conectar a DB - ".$e->getMessage());
            return FALSE;
        }
    }

    public function procedimientoDemonio()
    {
        // Verificar posible desconexión de la base de datos
        if (is_null($this->_db)) {
            $this->_log->output('INFO: intentando volver a abrir conexión a DB...');
            if (!$this->_iniciarConexionDB()) {
                $this->_log->output('ERR: no se puede restaurar conexión a DB, se espera...');
                usleep(5000000);
            } else {
                $this->_log->output('INFO: conexión a DB restaurada, se reinicia operación normal.');
                $this->_configDB->setDBConn($this->_db);
                $this->_eccpconn->setDbConn($this->_db);
            }
        }

        // Verificar si la conexión AMI sigue siendo válida
        if (!is_null($this->_ami) && is_null($this->_ami->sKey)) {
            $this->_ami = NULL;
        }
        if (is_null($this->_ami) && !$this->_finalizandoPrograma) {
            if (!$this->_iniciarConexionAMI()) {
                $this->_log->output('ERR: no se puede restaurar conexión a Asterisk, se espera...');
                if (!is_null($this->_db)) {
                    if ($this->_multiplex->procesarPaquetes())
                        $this->_multiplex->procesarActividad(0);
                    else $this->_multiplex->procesarActividad(5);
                } else {
                    usleep(5000000);
                }
            } else {
                $this->_log->output('INFO: conexión a Asterisk restaurada, se reinicia operación normal.');
            }
        }

        if (!is_null($this->_db) && !is_null($this->_ami) && !$this->_finalizandoPrograma) {
            try {
                $this->_verificarCambioConfiguracion();
            } catch (PDOException $e) {
                $this->_stdManejoExcepcionDB($e, 'no se puede verificar cambio en configuración');
            }
        }

        // Rutear los mensajes si hay DB
        if (!is_null($this->_db)) {
            // Rutear todos los mensajes pendientes entre tareas y agentes
            if ($this->_multiplex->procesarPaquetes())
                $this->_multiplex->procesarActividad(0);
            else $this->_multiplex->procesarActividad(1);
        }

        return !$this->_finalizandoPrograma;
    }

    public function limpiezaDemonio($signum)
    {

        // Mandar a cerrar todas las conexiones activas
        $this->_multiplex->finalizarServidor();

        // Desconectarse de la base de datos
        $this->_configDB = NULL;
        if (!is_null($this->_db)) {
            $this->_log->output('INFO: desconectando de la base de datos...');
            $this->_db = NULL;
        }
    }

    /**************************************************************************/

    private function _iniciarConexionAMI()
    {
        if (!is_null($this->_ami)) {
            $this->_log->output('INFO: Desconectando de sesión previa de Asterisk...');
            $this->_ami->disconnect();
            $this->_ami = NULL;
            $this->_eccpconn->setAstConn(NULL, NULL);
        }
        $astman = new AMIClientConn($this->_multiplex, $this->_log);

        $this->_log->output('INFO: Iniciando sesión de control de Asterisk...');
        if (!$astman->connect(
                $this->_configDB->asterisk_asthost,
                $this->_configDB->asterisk_astuser,
                $this->_configDB->asterisk_astpass)) {
            $this->_log->output("FATAL: no se puede conectar a Asterisk Manager");
            return FALSE;
        } else {
            // Averiguar la versión de Asterisk que se usa
            $asteriskVersion = array(1, 4, 0, 0);
            $r = $astman->CoreSettings(); // Sólo disponible en Asterisk >= 1.6.0
            if ($r['Response'] == 'Success' && isset($r['AsteriskVersion'])) {
                $asteriskVersion = explode('.', $r['AsteriskVersion']);
                $this->_log->output("INFO: CoreSettings reporta Asterisk ".implode('.', $asteriskVersion));
            } else {
                $this->_log->output("INFO: no hay soporte CoreSettings en Asterisk Manager, se asume Asterisk 1.4.x.");
            }

            // ECCPWorkerProcess no tiene manejadores de eventos AMI
            $astman->Events('off');

            $this->_eccpconn->setAstConn($astman, $asteriskVersion);
            $this->_ami = $astman;
            return TRUE;
        }
    }

    private function _verificarCambioConfiguracion()
    {
        $iTimestamp = time();
        if ($iTimestamp - $this->_iTimestampUltimaRevisionConfig > 3) {
            $this->_configDB->leerConfiguracionDesdeDB();
            $listaVarCambiadas = $this->_configDB->listaVarCambiadas();
            if (count($listaVarCambiadas) > 0) {
                if (in_array('dialer_debug', $listaVarCambiadas)) {
                    $this->DEBUG = $this->_configDB->dialer_debug;
                    $this->_eccpconn->DEBUG = $this->_configDB->dialer_debug;
                }
                $this->_configDB->limpiarCambios();
            }
            $this->_iTimestampUltimaRevisionConfig = $iTimestamp;
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
            $this->_eccpconn->setDbConn(NULL);
        }
    }

    /**************************************************************************/

    public function msg_eccprequest($sFuente, $sDestino, $sNombreMensaje,
        $iTimestamp, $datos)
    {
        if ($this->DEBUG) {
            $this->_log->output('DEBUG: '.__METHOD__.' - '.print_r($datos, 1));
        }

        $this->_numPeticionesAtendidas++;
        list($connkey, $request, $connvars) = $datos;
        list($s, $nuevos_valores, $eventos) = $this->_eccpconn->do_eccprequest($request, $connvars);
        $this->_tuberia->msg_ECCPProcess_eccpresponse(
            ($this->_numPeticionesAtendidas >= MAX_PETICIONES_ATENDIDAS),
            $connkey, $s, $nuevos_valores, $eventos);
    }

    public function msg_finalizando($sFuente, $sDestino, $sNombreMensaje, $iTimestamp, $datos)
    {
        $this->_log->output('INFO: recibido mensaje de finalización, se desconectan conexiones...');
        $this->_finalizandoPrograma = TRUE;
        $this->_tuberia->msg_HubProcess_finalizacionTerminada();
    }

    public function msg_finalizarWorker($sFuente, $sDestino, $sNombreMensaje, $iTimestamp, $datos)
    {
        $this->_log->output('INFO: se permite terminar luego de última petición ECCP...');
        $this->_finalizandoPrograma = TRUE;
    }
}
?>