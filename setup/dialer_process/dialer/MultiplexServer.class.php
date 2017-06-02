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
  $Id: MultiplexServer.class.php,v 1.48 2009/03/26 13:46:58 alex Exp $ */

class MultiplexServer
{
    protected $_oLog;        // Objeto log para reportar problemas
    protected $_hEscucha;    // Socket de escucha para nuevas conexiones
    private $_conexiones;    // Lista de conexiones atendidas con clientes
    private $_uniqueid;

    // Lista de objetos escucha, de tipos variados
    protected $_listaConn = array();

    /**
     * Constructor del objeto. Se esperan los siguientes parámetros:
     * @param    string    $sUrlSocket        Especificación del socket de escucha
     * @param    object    $oLog            Referencia a objeto de log
     *
     * El constructor abre el socket de escucha (p.ej. tcp://127.0.0.1:20005
     * o unix:///tmp/dialer.sock) y desactiva el bloqueo para poder usar
     * stream_select() sobre el socket.
     */
    function __construct($sUrlSocket, &$oLog)
    {
        $this->_oLog =& $oLog;
        $this->_conexiones = array();
        $this->_uniqueid = 0;
        $errno = $errstr = NULL;
        $this->_hEscucha = FALSE;
        if (!is_null($sUrlSocket)) {
            $this->_hEscucha = stream_socket_server($sUrlSocket, $errno, $errstr);
            if (!$this->_hEscucha) {
                $this->_oLog->output("ERR: no se puede iniciar socket de escucha $sUrlSocket: ($errno) $errstr");
            } else {
                // No bloquearse en escucha de conexiones
                stream_set_blocking($this->_hEscucha, 0);
                $this->_oLog->output("INFO: escuchando peticiones en $sUrlSocket ...");
            }
        }
    }

    /**
     * Función que verifica si la escucha está activa.
     *
     * @return    bool    VERDADERO si escucha está activa, FALSO si no.
     */
    function escuchaActiva()
    {
        return ($this->_hEscucha !== FALSE);
    }

    /**
     * Procedimiento que revisa los sockets para llenar los búferes de lectura
     * y vaciar los búferes de escritura según sea necesario. También se
     * verifica si hay nuevas conexiones para preparar.
     *
     * @return    VERDADERO si alguna conexión tuvo actividad
     */
    function procesarActividad($tv_sec = 1, $tv_usec = 0)
    {
        $bNuevosDatos = $this->_ejecutarIO($tv_sec, $tv_usec, TRUE);

        if ($bNuevosDatos) {
            foreach ($this->_conexiones as $sKey => &$conexion) {
                if ($conexion['nuevos_datos_leer']) {
                    $this->_procesarNuevosDatos($sKey);
                    $conexion['nuevos_datos_leer'] = FALSE;
                    $this->_ejecutarIO(0, 0);
                }
            }
        }

        return $bNuevosDatos;
    }

    private function _ejecutarIO($tv_sec, $tv_usec, $listen = FALSE)
    {
        $bNuevosDatos = FALSE;
        $listoLeer = array();
        $listoEscribir = array();
        $listoErr = NULL;

        // Se recogen bandera de datos no procesados pendientes
        foreach ($this->_conexiones as $sKey => &$conexion) {
            if ($conexion['nuevos_datos_leer']) $bNuevosDatos = TRUE;
        }

        // Si ya hay datos pendientes, no hay que esperar en select
        if ($bNuevosDatos) {
            $tv_sec = 0;
            $tv_usec = 0;
        }

        // Recolectar todos los descriptores que se monitorean
        if ($listen && $this->_hEscucha)
            $listoLeer[] = $this->_hEscucha;        // Escucha de nuevas conexiones
        foreach ($this->_conexiones as &$conexion) {
            if (!$conexion['exit_request']) $listoLeer[] = $conexion['socket'];
            if (strlen($conexion['pendiente_escribir']) > 0) {
                $listoEscribir[] = $conexion['socket'];
            }
        }
        $iNumCambio = (count($listoLeer) + count($listoEscribir) > 0)
            ? @stream_select($listoLeer, $listoEscribir, $listoErr, $tv_sec, $tv_usec)
            : 0;
        if ($iNumCambio === false) {
            // Interrupción, tal vez una señal
            $this->_oLog->output("INFO: select() finaliza con fallo - señal pendiente?");
        } elseif ($iNumCambio > 0 || count($listoLeer) > 0 || count($listoEscribir) > 0) {
            if (in_array($this->_hEscucha, $listoLeer)) {
                // Entra una conexión nueva
                $this->_procesarConexionNueva();
                $bNuevosDatos = TRUE;
            }
            foreach ($this->_conexiones as $sKey => &$conexion) {
                if (in_array($conexion['socket'], $listoEscribir)) {
                    // Escribir lo más que se puede de los datos pendientes por mostrar
                    $iBytesEscritos = @fwrite($conexion['socket'], $conexion['pendiente_escribir']);
                    if ($iBytesEscritos === FALSE) {
                        $this->_oLog->output("ERR: error al escribir datos a ".$conexion['socket']);
                        $this->_cerrarConexion($sKey);
                    } else {
                        $conexion['pendiente_escribir'] = substr($conexion['pendiente_escribir'], $iBytesEscritos);
                        $bNuevosDatos = TRUE;
                    }
                }
                if (in_array($conexion['socket'], $listoLeer)) {
                    // Leer datos de la conexión lista para leer
                    $sNuevaEntrada = fread($conexion['socket'], 128 * 1024);
                    if ($sNuevaEntrada == '') {
                        // Lectura de cadena vacía indica que se ha cerrado la conexión remotamente
                        $this->_cerrarConexion($sKey);
                    } else {
                        $conexion['pendiente_leer'] .= $sNuevaEntrada;
                        $conexion['nuevos_datos_leer'] = TRUE;
                    }
                    $bNuevosDatos = TRUE;
                }
            }

            // Cerrar todas las conexiones que no tienen más datos que mostrar
            // y que han marcado que deben terminarse
            foreach ($this->_conexiones as $sKey => &$conexion) {
                if (is_array($conexion) && $conexion['exit_request'] && strlen($conexion['pendiente_escribir']) <= 0) {
                    $this->_cerrarConexion($sKey);
                }
            }

            // Remover todos los elementos seteados a FALSE
            $this->_conexiones = array_filter($this->_conexiones);
        }

        return $bNuevosDatos;
    }

    /**
     * Procedimiento para agregar un objeto instancia de MultiplexConn, que abre
     * un socket arbitrario y desea estar asociado con tal socket.
     *
     * @param   object      $oNuevaConn Objeto que hereda de DialerConn
     * @param   resource    $hSock      Conexión a un socket TCP o UNIX
     *
     * @return void
     */
    function agregarNuevaConexion($oNuevaConn, $hSock)
    {
        if (!is_a($oNuevaConn, 'MultiplexConn')) {
            die(__METHOD__.' - $oNuevaConn no es subclase de MultiplexConn');
        }

        $sKey = $this->agregarConexion($hSock);
        $oNuevaConn->multiplexSrv = $this;
        $oNuevaConn->sKey = $sKey;
        $this->_listaConn[$sKey] = $oNuevaConn;
        $this->_listaConn[$sKey]->procesarInicial();
    }

    /* Enviar los datos recibidos para que sean procesados por la conexión */
    private function _procesarNuevosDatos($sKey)
    {
        if (isset($this->_listaConn[$sKey])) {
            $sDatos = $this->obtenerDatosLeidos($sKey);
            $iLongProcesado = $this->_listaConn[$sKey]->parsearPaquetes($sDatos);

            if (!isset($this->_conexiones[$sKey])) return;
            if ($iLongProcesado < 0) return;
            $this->_conexiones[$sKey]['pendiente_leer'] =
                (strlen($this->_conexiones[$sKey]['pendiente_leer']) > $iLongProcesado)
                ? substr($this->_conexiones[$sKey]['pendiente_leer'], $iLongProcesado)
                : '';
        }
    }

    function procesarCierre($sKey)
    {
        if (isset($this->_listaConn[$sKey])) {
            $this->_listaConn[$sKey]->procesarCierre();
            unset($this->_listaConn[$sKey]);
        }
    }

    function procesarPaquetes()
    {
        $bHayProcesados = FALSE;
        foreach ($this->_listaConn as &$oConn) {
            if ($oConn->hayPaquetes()) {
                $bHayProcesados = TRUE;
                $oConn->procesarPaquete();
                $this->_ejecutarIO(0, 0);
            }
        }
        return $bHayProcesados;
    }


    // Procesar una nueva conexión que ingresa al servidor
    private function _procesarConexionNueva()
    {
        $hConexion = stream_socket_accept($this->_hEscucha);
        $sKey = $this->agregarConexion($hConexion);
        $this->procesarInicial($sKey);
    }

    /**
     * Procedimiento que agrega una conexión socket arbitraria a la lista de los
     * sockets que hay que monitorear para escucha.
     *
     * @param mixed $hConexion Conexión socket a agregar a la lista
     *
     * @return Clave a usar para identificar la conexión
     */
    protected function agregarConexion($hConexion)
    {
        $nuevaConn = array(
            'socket'                =>  $hConexion,
            'pendiente_leer'        =>  '',
            'pendiente_escribir'    =>  '',
            'exit_request'          =>  FALSE,
            'nuevos_datos_leer'     =>  FALSE,
        );
        stream_set_blocking($nuevaConn['socket'], 0);

        $sKey = "K_{$this->_uniqueid}";
        $this->_uniqueid++;
        $this->_conexiones[$sKey] =& $nuevaConn;
        return $sKey;
    }

    /**
     * Recuperar los primeros $iMaxBytes del búfer de lectura. Por omisión se
     * devuelve la totalidad del búfer.
     * @param    string    $sKey        Clave de la conexión pasada a procesarNuevosDatos()
     * @param    int        $iMaxBytes    Longitud máxima en bytes a devolver (por omisión todo)
     *
     * @return    string    Cadena con los datos del bufer
     */
    protected function obtenerDatosLeidos($sKey, $iMaxBytes = 0)
    {
        $iMaxBytes = (int)$iMaxBytes;
        if (!isset($this->_conexiones[$sKey])) return NULL;
        return ($iMaxBytes > 0)
            ? substr($this->_conexiones[$sKey]['pendiente_leer'], 0, $iMaxBytes)
            : $this->_conexiones[$sKey]['pendiente_leer'];
    }

    /**
     * Agregar datos al búfer de escritura pendiente, los cuales serán escritos
     * al cliente durante la siguiente llamada a procesarActividad()
     * @param    string    $sKey    Clave de la conexión pasada a procesarNuevosDatos()
     * @param    string    $s        Búfer de datos a agregar a los datos a escribir.
     *
     * @return    void
     */
    public function encolarDatosEscribir($sKey, &$s)
    {
        if (!isset($this->_conexiones[$sKey])) return;
        $this->_conexiones[$sKey]['pendiente_escribir'] .= $s;
    }

    /**
     * Marcar que el socket indicado debe de cerrarse. Ya no se procesarán más
     * datos de entrada del socket indicado, desde el punto de vista de la
     * aplicación. Todos los datos pendientes por escribir se escribirán antes
     * de cerrar el socket.
     * @param    string    $sKey    Clave de la conexión pasada a procesarNuevosDatos()
     *
     * @return    void
     */
    public function marcarCerrado($sKey)
    {
        if (!isset($this->_conexiones[$sKey])) return;
        $this->_conexiones[$sKey]['exit_request'] = TRUE;
    }

    // Procesar realmente una conexión que debe cerrarse
    private function _cerrarConexion($sKey)
    {
        fclose($this->_conexiones[$sKey]['socket']);
        $this->_conexiones[$sKey] = FALSE;  // Será removido por array_map()
        $this->procesarCierre($sKey);
    }

    /**
     * Procedimiento que se debe implementar en la subclase, para manejar la
     * apertura inicial del socket, para poder escribir datos antes de recibir
     * peticiones del cliente. En este punto no hay hay datos leidos del
     * cliente.
     * @param    string    $sKey    Clave de la conexión recién creada.
     *
     * @return    void
     */
    protected function procesarInicial($sKey) {}

    function finalizarServidor()
    {
        if ($this->_hEscucha !== FALSE) {
            fclose($this->_hEscucha);
            $this->_hEscucha = FALSE;
        }
        foreach ($this->_listaConn as &$oConn) {
            $oConn->finalizarConexion();
        }
        $this->procesarActividad();
    }

    /**
     * Procedimiento que se debe implementar en la subclase, para manejar datos
     * nuevos enviados desde el cliente.
     * @param    string    $sKey    Clave de la conexión con datos nuevos
     *
     * @return    void
     */
    //abstract protected function procesarNuevosDatos($sKey);

    /**
     * Procedimiento que se debe implementar en la subclase, para manejar el
     * cierre de la conexión.
     * @param   string  $sKey   Clave de la conexión cerrada
     *
     * @return  void
     */
    //abstract protected function procesarCierre($sKey);
}
?>