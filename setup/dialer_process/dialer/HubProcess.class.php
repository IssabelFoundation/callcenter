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

define('MIN_ECCP_WORKERS', 2);  // Número mínimo de ECCPWorkerProcess a mantener

class HubProcess extends AbstractProcess implements iRoutedMessageHook
{
    private $_log;      // Log abierto por framework de demonio
    private $_config;   // Información de configuración copiada del archivo
    private $_hub;      // Hub de mensajes entre todos los procesos
    private $_tareas;   // Lista de tareas, nombreClase => PID

    // Estas tareas deben estar siempre en ejecución
    private $_tareasFijas = array('AMIEventProcess', 'CampaignProcess',
        'ECCPProcess', 'SQLWorkerProcess');

    // Contador para garantizar unicidad de nombre de tarea dinámica
    private $_dynProcessCounter = 0;

    // Conexión debido a la cual se marcó como ocupada cada tarea dinámica
    private $_conexionTareaOcupada = array();

    // Lista de tareas dinámicas que avisaron que atendieron su última petición
    private $_tareasUltimaPeticion = array();

    // Último instante en que se verificó que los procesos estaban activos
    private $_iTimestampVerificacionProcesos = NULL;

    public function inicioPostDemonio($infoConfig, &$oMainLog)
    {
        $this->_log =& $oMainLog;
        $this->_config =& $infoConfig;
        $this->_tareas = array();
        $this->_hub = new HubServer($this->_log);
        $this->_hub->registrarInspectorMsg($this);
        return TRUE;
    }

    /* Verificar si la tarea indicada sigue activa. Devuelve VERDADERO si la
     * tarea sigue corriendo, FALSO si inactiva o si se detecta que terminó. */
    private function _revisarTareaActiva($sTarea, $finalizando = FALSE)
    {
        $bTareaActiva = FALSE;

        // Si está definido el PID del proceso, se verifica si se ejecuta.
        if (isset($this->_tareas[$sTarea])) {
            $iStatus = NULL;
            $iPidDevuelto = pcntl_waitpid($this->_tareas[$sTarea], $iStatus, WNOHANG);
            if ($iPidDevuelto > 0) {
                if (!$finalizando && in_array($sTarea, $this->_tareasFijas)) {
                    $this->_log->output("WARN: $sTarea (PID=$iPidDevuelto) ha terminado inesperadamente (status=$iStatus), se agenda reinicio...");
                }
                $iErrCode = pcntl_wifexited($iStatus) ? pcntl_wexitstatus($iStatus) : 255;
                $iRcvSignal = pcntl_wifsignaled($iStatus) ? pcntl_wtermsig($iStatus) : 0;
                if ($iRcvSignal != 0) { $this->_log->output("WARN: $sTarea terminó debido a señal $iRcvSignal..."); }
                if ($iErrCode != 0) { $this->_log->output("WARN: $sTarea devolvió código de error $iErrCode..."); }
                $this->_manejarCaidaECCPWorker($sTarea);
                unset($this->_tareas[$sTarea]);
                $this->_tareasUltimaPeticion = array_diff($this->_tareasUltimaPeticion, array($sTarea));

                // Quitar la tubería del proceso que ha terminado
                $this->_hub->quitarTuberia($sTarea);
            } else {
                $bTareaActiva = TRUE;
            }
        }

        return $bTareaActiva;
    }

    public function procedimientoDemonio()
    {
        $bHayNuevasTareas = FALSE;

        // Si la tarea ha finalizado o no existe, se debe iniciar
        if (is_null($this->_iTimestampVerificacionProcesos) || time() - $this->_iTimestampVerificacionProcesos > 0) {
            foreach (array_keys($this->_tareas) as $sTarea) {
                // Si está definido el PID del proceso, se verifica si se ejecuta.
                $this->_revisarTareaActiva($sTarea);
            }
            foreach ($this->_tareasFijas as $sTarea) {
                // Si no está definido el PID del proceso, se intenta iniciar
                if (!isset($this->_tareas[$sTarea])) {
                    $this->_tareas[$sTarea] = $this->_iniciarTarea($sTarea);
                    $bHayNuevasTareas = TRUE;
                }
            }
            if ($this->_revisarTareasDinamicasActivas('ECCPWorkerProcess', MIN_ECCP_WORKERS))
                $bHayNuevasTareas = TRUE;
            $this->_iTimestampVerificacionProcesos = time();
        }

        // Registrar el multiplex con todas las conexiones nuevas
        if ($bHayNuevasTareas) $this->_hub->registrarMultiplexPadre();

        $this->propagarSIGHUP();

        // Rutear todos los mensajes pendientes entre tareas
        if ($this->_hub->procesarPaquetes())
            $this->_hub->procesarActividad(0);
        else $this->_hub->procesarActividad(1);

        return TRUE;
    }

    public function propagarSIGHUP()
    {
        global $gsNombreSignal;

        if (!is_null($gsNombreSignal) && $gsNombreSignal == SIGHUP) {
            // Mandar la señal a todos los procesos controlados
            $this->_log->output("PID = ".posix_getpid().", se ha recibido señal #$gsNombreSignal, ".
                (($gsNombreSignal == SIGHUP) ? 'cambiando logs' : 'terminando')."...");
            $this->_propagarSIG($gsNombreSignal);
        }
    }

    /* Iniciar una tarea específica en un proceso separado. Para el proceso
     * padre, devuelve el PID del proceso hijo. */
    private function _iniciarTarea($sNombreTarea)
    {
        return $this->_iniciarTareaClase($sNombreTarea, $sNombreTarea);
    }

    private function _iniciarTareaClase($sNombreTarea, $sNombreClase)
    {
        global $gsNombreSignal;

        // Verificar que el nombre de la clase que implementa el proceso es válido
        if (!class_exists($sNombreClase)) {
            $this->_log->output("FATAL: (internal) Invalid process classname '$sNombreClase'");
            die("(internal) Invalid process classname '$sNombreClase'\n");
        }

        // Nueva tubería con el nombre de la tarea
        $oTuberia = $this->_hub->crearTuberia($sNombreTarea);
        $oTuberia->setLog($this->_log);

        // Iniciar tarea en proceso separado
        $iPidProceso = pcntl_fork();
        if ($iPidProceso != -1) {
            if ($iPidProceso == 0) {
                $this->_log->prefijo($sNombreTarea);
                $this->_log->output("iniciando proceso...");

                // Instalar los manejadores de señal para el proceso hijo
                pcntl_signal(SIGTERM, 'manejadorPrimarioSignal');
                pcntl_signal(SIGQUIT, 'manejadorPrimarioSignal');
                pcntl_signal(SIGINT, 'manejadorPrimarioSignal');
                pcntl_signal(SIGHUP, 'manejadorPrimarioSignal');

                // Elegir la tarea que debe de ejecutarse
                $oProceso = NULL;
                try {
                    $oProceso = new $sNombreClase($oTuberia);
                    if (!($oProceso instanceof TuberiaProcess)) throw new Exception('Not a subclass of TuberiaProcess!');
                } catch (Exception $ex) {
                    $this->_log->output("ERR: al crear $sNombreTarea - excepción no manejada: ".$ex->getMessage());
                    die("ERR: al crear $sNombreTarea - ".$ex->getMessage()."\n");
                }

                // Realizar inicialización adicional de la tarea
                try {
                    $bContinuar = $oProceso->inicioPostDemonio($this->_config, $this->_log);
                    if ($bContinuar) $this->_log->output("PID = ".posix_getpid().", proceso iniciado normalmente");
                } catch (Exception $ex) {
                    $bContinuar = FALSE;
                    $this->_log->output("ERR: al inicializar $sNombreTarea - excepción no manejada: ".$ex->getMessage());
                }

                // Continuar la tarea hasta que se finalice
                while ($bContinuar) {
                    // Ejecutar el procedimiento de trabajo del demonio
                    if (is_null($gsNombreSignal)) {
                        try {
                            $bContinuar = $oProceso->procedimientoDemonio();
                        } catch (Exception $ex) {
                            $bContinuar = FALSE;
                            $this->_log->output("ERR: al ejecutar $sNombreTarea - excepción no manejada: ".$ex->getMessage());
                        }
                    }

                    // Revisar si existe señal que indique finalización del programa
                    if (!is_null($gsNombreSignal)) {
                        if (in_array($gsNombreSignal, array(SIGTERM, SIGINT, SIGQUIT))) {
                            $this->_log->output("PID = ".posix_getpid().", proceso recibió señal $gsNombreSignal, terminando...");
                            $bContinuar = FALSE;
                        } elseif ($gsNombreSignal == SIGHUP) {
                            $this->_log->output("PID = ".posix_getpid().", proceso recibió señal $gsNombreSignal, cambiando logs...");
                            $this->_log->reopen();
                            $this->_log->output("PID = ".posix_getpid().", proceso recibió señal $gsNombreSignal, usando nuevo log.");
                            $gsNombreSignal = NULL;
                        }
                    }
                }

                // Indicar al módulo de trabajo por qué se está finalizando
                try {
                    $oProceso->limpiezaDemonio($gsNombreSignal);
                } catch (Exception $ex) {
                    $this->_log->output("ERR: al finalizar $sNombreTarea - excepción no manejada: ".$ex->getMessage());
                }
                $this->_log->output("PID = ".posix_getpid().", proceso terminó normalmente.");
                $this->_log->close();

                exit(0);   // Finalizar el proceso hijo
            }
        } else {
            // Avisar que no se puede iniciar la tarea requerida
            $this->_log->output("Unable to fork $sNombreTarea - $!");
        }
        return $iPidProceso;
    }

    private function _revisarTareasDinamicasActivas($sNombreClase, $min_workers)
    {
        $bHayNuevasTareas = FALSE;

        $i = 0;
        foreach (array_keys($this->_tareas) as $sTarea)
            if (strpos($sTarea, $sNombreClase.'-') === 0) $i++;
        for (; $i < $min_workers; $i++) {
            $this->_iniciarTareaDinamica($sNombreClase);
            $bHayNuevasTareas = TRUE;
        }
        return $bHayNuevasTareas;
    }

    private function _iniciarTareaDinamica($sNombreClase)
    {
        $sTarea = $sNombreClase.'-'.$this->_dynProcessCounter;
        $this->_dynProcessCounter++;
        $this->_tareas[$sTarea] = $this->_iniciarTareaClase($sTarea, $sNombreClase);
        return $sTarea;
    }

    // Por interfaz iRoutedMessageHook
    public function inspeccionarMensaje(&$sFuente, &$sDestino, &$sNombreMensaje, &$datos)
    {
        if ($sFuente == 'ECCPProcess' && $sDestino == 'ECCPWorkerProcess') {
            /* Al manejar ECCP, ECCPProcess requiere que el requerimiento lo maneje
             * una instancia de ECCPWorkerProcess, pero no puede saber cuál.
             * Aquí debe elegirse la instancia, y una vez elegida, asignar el
             * estado de ocupado hasta que mande una respuesta. Si es necesario,
             * debe de crearse un nuevo proceso. */
            $sTareaElegida = NULL;
            foreach (array_keys($this->_tareas) as $sTarea) {
                if ($this->_revisarTareaActiva($sTarea) &&
                    strpos($sTarea, 'ECCPWorkerProcess-') === 0 &&
                    !isset($this->_conexionTareaOcupada[$sTarea]) &&
                    !in_array($sTarea, $this->_tareasUltimaPeticion)) {
                    $sTareaElegida = $sTarea;
                    break;
                }
            }

            // Iniciar nuevo proceso si no hay tarea elegida
            if (is_null($sTareaElegida)) {
                $sTarea = $this->_iniciarTareaDinamica('ECCPWorkerProcess');
                $this->_hub->registrarMultiplexPadre();
                $sTareaElegida = $sTarea;
            }

            // El primer parámetro es la conexión proxy
            $this->_conexionTareaOcupada[$sTareaElegida] = $datos[0];
            $sDestino = $sTareaElegida;
        } elseif (strpos($sFuente, 'ECCPWorkerProcess-') === 0 && $sDestino == 'ECCPProcess'
                && $sNombreMensaje == 'eccpresponse') {
            /* Al mandar la respuesta asíncrona desde un ECCPWorkerProcess, se
             * debe de limpiar el estado de ocupado para esa instancia en
             * particular. */
            unset($this->_conexionTareaOcupada[$sFuente]);

            /* El ECCPWorkerProcess manda como primer parámetro una bandera que
             * indica si finaliza por haber atendido su última petición. Si es
             * así, se lo agrega a la lista negra de procesos que terminan. */
            $bUltimaPeticion = array_shift($datos);
            if ($bUltimaPeticion) {
                $this->_tareasUltimaPeticion[] = $sFuente;

                // Permitir al proceso ECCPWorkerProcess que finalice
                $this->_hub->rutearMensaje(
                    'HubProcess',
                    $sFuente,
                    'finalizarWorker',
                    microtime(TRUE),
                    array());
            }
        }
    }

    private function _manejarCaidaECCPWorker($sTarea)
    {
        if (isset($this->_conexionTareaOcupada[$sTarea])) {
            $this->_log->output("WARN: tarea $sTarea con PID=".
                $this->_tareas[$sTarea]." ha terminado mientras procesaba una ".
                "petición ECCP para la conexión ".$this->_conexionTareaOcupada[$sTarea]);
            $sCrashMsg = <<<XML_CRASH_MSG
<?xml version="1.0"?>
<response><failure><code>503</code><message>Internal server error - worker crash while handling request</message></failure></response>
XML_CRASH_MSG;
            $this->_hub->rutearMensaje(
                $sTarea,
                'ECCPProcess',
                'eccpresponse',
                microtime(TRUE),
                array(
                    TRUE,
                    $this->_conexionTareaOcupada[$sTarea],
                    $sCrashMsg,
                    array(
                        'usuarioeccp'   =>  NULL,
                        'appcookie'     =>  NULL,
                        'finalizando'   =>  TRUE,
                    ),
                    NULL,
                ));
        }
    }

    public function limpiezaDemonio($signum)
    {
        // Propagar la señal si no es NULL
        if (!is_null($signum)) {
            // Mandar la señal a todos los procesos controlados
            $this->_log->output("PID = ".posix_getpid().", se ha recibido señal #$signum, terminando...");
        } else {
            $signum = SIGTERM;
            $this->_log->output("Término normal del programa, se terminará procesos hijos...");
        }

        // Avisar a todos los procesos que se terminará el programa
        $this->_log->output('INFO: avisando de finalización a todos los procesos...');
        $this->_hub->enviarFinalizacion();
        $this->_log->output('INFO: esperando respuesta de todos los procesos...');
        while ($this->_hub->numFinalizados() < count(array_filter($this->_tareas))) {
            foreach (array_keys($this->_tareas) as $sTarea)
                $this->_revisarTareaActiva($sTarea, TRUE);
            if ($this->_hub->procesarPaquetes())
                $this->_hub->procesarActividad(0);
            else $this->_hub->procesarActividad(1);
        }

        $this->_propagarSIG($signum);

        $this->_log->output('INFO: esperando a que todas las tareas terminen...');
        $bTodosTerminaron = FALSE;
        $t1 = time();
        do {
            $bTodosTerminaron = TRUE;
            foreach (array_keys($this->_tareas) as $sTarea) {
                // Si está definido el PID del proceso, se verifica si se ejecuta.
                if ($this->_revisarTareaActiva($sTarea, TRUE)) {
                    // Este proceso aún no termina...
                    $bTodosTerminaron = FALSE;
                }
            }

            if (!$bTodosTerminaron) {
                $t2 = time();
                if ($t2 - $t1 >= 4) {
                    $this->_log->output('WARN: no todas las tareas han terminado, se vuelve a enviar señal...');
                    $this->_propagarSIG($signum);
                    $t1 = $t2;
                }

                // Rutear todos los mensajes pendientes entre tareas
                if ($this->_hub->procesarPaquetes())
                    $this->_hub->procesarActividad(0);
                else $this->_hub->procesarActividad(1);
            }
        } while (!$bTodosTerminaron);
        $this->_log->output('INFO: todas las tareas han terminado.');

        // Mandar a cerrar todas las conexiones activas
        $this->_hub->finalizarServidor();
    }

    // Propagar la señal recibida o sintetizada
    private function _propagarSIG($signum)
    {
        foreach (array_keys($this->_tareas) as $sTarea) {
            $this->_log->output("Propagando señal #$signum a $sTarea...");
            posix_kill($this->_tareas[$sTarea], $signum);
            $this->_log->output("Completada propagación de señal a $sTarea");
        }
    }
}
?>