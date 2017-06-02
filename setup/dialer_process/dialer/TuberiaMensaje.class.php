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
  $Id: TuberiaMensaje.class.php,v 1.48 2009/03/26 13:46:58 alex Exp $ */


/**
 * Esta clase implementa una comunicación interproceso bidireccional orientada 
 * a mensajes usando stream_socket_pair y (un)serialize. Se soporta mensajes
 * asíncronos, y llamadas de procedimiento síncronas. Esta clase funciona en
 * cooperación con la clase HubServer. 
 */
class TuberiaMensaje extends MultiplexConn
{
    private $_nombreFuente;             // La fuente de mensajes que llegan
    private $_socks = NULL;             // Par de sockets antes de fork()
    private $_listaEventos = array();   // Eventos pendientes por procesar
    private $_respuesta = NULL;         // Respuesta recibida del último comando
    private $_hayRespuesta = FALSE;     // VERDADERO si hay respuesta
    private $_rutearRespuesta = FALSE;  // Si VERDADERO, rutear respuesta
    private $_log = NULL;               // Log de depuración
    
    // Manejadores de eventos: _manejadoresEventos[$fuente][$nombreMsg] = array($obj, $metodo)
    private $_manejadoresEventos = array();

    function __construct($sFuente)
    {
        $this->_nombreFuente = $sFuente;
        $this->_socks = stream_socket_pair(
            STREAM_PF_UNIX,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP);
    }

    function setLog($l) { $this->_log = $l; }

    // Registrar esta tubería a un MultiplexServer, lado padre
    function registrarMultiplexPadre($multiplexSrv)
    {
    	if (!is_null($this->_socks)) {
            fclose($this->_socks[1]);  // Se cierra el socket del hijo
            $multiplexSrv->agregarNuevaConexion($this, $this->_socks[0]);
            $this->_socks = NULL;
            $this->_rutearRespuesta = TRUE;
        }
    }
    
    // Registrar esta tubería a un MultiplexServer, lado hijo
    function registrarMultiplexHijo($multiplexSrv)
    {
        if (!is_null($this->_socks)) {
            fclose($this->_socks[0]);  // Se cierra el socket del padre
            $multiplexSrv->agregarNuevaConexion($this, $this->_socks[1]);
            $this->_socks = NULL;
        }
    }

    // Datos a mandar a escribir apenas se inicia la conexión
    function procesarInicial() {}

    function finalizarConexion()
    {
        if (!is_null($this->sKey)) {
            $this->multiplexSrv->marcarCerrado($this->sKey);
        }
    }

    /**
     * Procedimiento que intenta descomponer el búfer de lectura indicado por
     * $sDatos en una secuencia de paquetes del protocolo interno de RPC. Cada
     * paquete del protocolo interno consiste de una cabecera de 4 bytes que 
     * indica la longitud, seguida de los datos reales, serializados. La lista 
     * de paquetes obtenida se devuelve como una lista. Además el búfer de 
     * lectura se modifica para eliminar los datos que fueron ya procesados como
     * parte de los paquetes. Esta función sólo devuelve paquetes completos, y 
     * deja cualquier fracción de paquetes incompletos en el búfer.
     *
     * @param   string  $sDatos     Cadena de datos a procesar
     *
     * @return  array   Lista de paquetes que fueron extraídos del texto.
     */
    private function _encontrarPaquetes(&$sDatos)
    {
        $listaPaquetes = array();
    	$bHayDatos = TRUE;
        do {
        	$bHayDatos = (strlen($sDatos) >= 4);
            if ($bHayDatos) {
            	$l = unpack('Llongitud', $sDatos);
                $bHayDatos = ($l['longitud'] + 4 <= strlen($sDatos));
            }
            if ($bHayDatos) {
            	$l = unpack("Llongitud/a{$l['longitud']}data", $sDatos);
                $sDatos = substr($sDatos, $l['longitud'] + 4);
                $listaPaquetes[] = unserialize($l['data']);
            }
        } while ($bHayDatos);
        return $listaPaquetes;
    }
    
    // Generar un paquete serializado para RPC
    private function _generarPaquete(&$v)
    {
    	$s = serialize($v);
        return pack("La*", strlen($s), $s);
    }

    // Separar flujo de datos en paquetes, devuelve número de bytes de paquetes 
    // aceptados.
    function parsearPaquetes($sDatos)
    {
        $iLongInicial = strlen($sDatos);

        // Encontrar los paquetes y determinar longitud de búfer procesado
        $listaPaquetes = $this->_encontrarPaquetes($sDatos);
        $iLongFinal = strlen($sDatos);
        foreach ($listaPaquetes as $paquete) {
/*
        	if (!is_null($this->_log))
                $this->_log->output('DEBUG: recibido paquete: '.print_r($paquete, 1));
*/
            if (!$this->_rutearRespuesta && $paquete['mensaje'] == '__RESPUESTA__') {
                $this->_respuesta = $paquete['datos'];
                $this->_hayRespuesta = TRUE;
            }
            else $this->_listaEventos[] = $paquete;
        }
        return $iLongInicial - $iLongFinal;
    }

    // Procesar cierre de la conexión
    function procesarCierre()
    {
        $this->sKey = NULL;
    }

    // Preguntar si hay paquetes pendientes de procesar
    function hayPaquetes() { return (count($this->_listaEventos) > 0); }

    // Procesar un solo paquete de la cola de paquetes
    function procesarPaquete()
    {
        $paquete = array_shift($this->_listaEventos);
        if (isset($paquete['fuente']) && isset($paquete['destino']) && 
            isset($paquete['mensaje'])) {
        	$sFuente = $paquete['fuente'];
            $sDestino = $paquete['destino'];
            $sNombreMensaje = $paquete['mensaje'];
            
            // Buscar el manejador
            $hFuente = isset($this->_manejadoresEventos[$sFuente])
                ? $this->_manejadoresEventos[$sFuente]
                : NULL;
            $hCualquiera = isset($this->_manejadoresEventos['*'])
                ? $this->_manejadoresEventos['*']
                : NULL;
            // Fuente específica, mensaje específico
            if (!is_null($hFuente) && isset($hFuente[$sNombreMensaje]))
                $handler = $hFuente[$sNombreMensaje];
            // Fuente cualquiera, mensaje específico
            elseif (!is_null($hCualquiera) && isset($hCualquiera[$sNombreMensaje]))
                $handler = $hCualquiera[$sNombreMensaje];
            // Fuente específica, cualquier mensaje
            elseif (!is_null($hFuente) && isset($hFuente['*']))
                $handler = $hFuente['*'];
            // Fuente cualquiera, cualquier mensaje
            elseif (!is_null($hCualquiera) && isset($hCualquiera['*']))
                $handler = $hCualquiera['*'];
            else {
                if (!is_null($this->_log))
                    $this->_log->output("ERR: no se ha registrado manejador para $sNombreMensaje($sFuente-->$sDestino)");
                return;
            }

            if ((is_array($handler) && count($handler) >= 2 && is_object($handler[0]) && 
                method_exists($handler[0], $handler[1])) || (!is_array($handler) && function_exists($handler))) {
                call_user_func($handler, $sFuente, $sDestino, $sNombreMensaje, $paquete['timestamp'], $paquete['datos']);
            } else {
                if (!is_null($this->_log))
                    $this->_log->output("ERR: no se encuentra manejador registrado para $sNombreMensaje($sFuente-->$sDestino)");
            }
        } else {
            if (!is_null($this->_log))
                $this->_log->output("ERR: se descarta paquete mal formado: ".print_r($paquete, 1));
        }
    }
    
    /* Registrar manejador para un mensaje que viene de una fuente determinada.
     * Para aceptar cualquier fuente, o cualquier mensaje, se debe especificar
     * un asterisco ('*') */
    function registrarManejador($sFuente, $sMensaje, $callback)
    {
    	$this->_manejadoresEventos[$sFuente][$sMensaje] = $callback;
    }
    
    /* Enviar un mensaje a través del HubServer a un destino determinado. */
    function enviarMensajeDesdeFuente($sFuente, $sDestino, $sMensaje, $datos)
    {
        if (!is_null($this->sKey)) {
            $datosEnviar = array(
                'timestamp' =>  microtime(TRUE),
                'fuente'    =>  $sFuente,
                'destino'   =>  $sDestino,
                'mensaje'   =>  $sMensaje,
                'datos'     =>  $datos,
            );
            $req = $this->_generarPaquete($datosEnviar);
            $this->multiplexSrv->encolarDatosEscribir($this->sKey, $req);
        }
    }
    
    function enviarMensaje($sDestino, $sMensaje, $datos)
    {
    	$this->enviarMensajeDesdeFuente($this->_nombreFuente, $sDestino, $sMensaje, $datos);
    }
    
    // Enviar una respuesta a un RPC como un mensaje
    function enviarRespuesta($sDestino, $datos)
    {
    	$this->enviarMensaje($sDestino, '__RESPUESTA__', $datos);
    }
    
    // Iniciar un RPC (procedimiento remoto) sobre el destino indicado
    function RPC($sDestino, $sProc, $datos)
    {
    	$this->enviarMensaje($sDestino, $sProc, $datos);
        while (!is_null($this->sKey) && !$this->_hayRespuesta) {
            $this->multiplexSrv->procesarActividad(); 
        }
        if ($this->_hayRespuesta) {
            $r = $this->_respuesta;
            $this->_respuesta = NULL;
            $this->_hayRespuesta = FALSE;
            return $r;
        }
        return NULL;
    }
    
    /*
     * Definición para envolver las llamadas a procedimientos y los envíos de
     * mensajes, como métodos del objeto. Los métodos que se mapean como RPC
     * tienen el patrón 'DESTINO_PROC'. Los métodos que se mapean como posteo
     * de mensajes asíncronos tienen el patrón 'msg_DESTINO_PROC'.
     */
    function __call($sMetodo, $args)
    {
    	if (substr($sMetodo, 0, 4) == 'msg_') {
            $l = explode('_', $sMetodo, 3);
            $this->enviarMensaje($l[1], $l[2], $args);
        } else {
            $l = explode('_', $sMetodo, 2);
            return $this->RPC($l[0], $l[1], $args);
        }
    }
}
?>