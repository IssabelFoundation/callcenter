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
  $Id: ClaseCampania.class.php,v 1.48 2009/03/26 13:46:58 alex Exp $ */

// Enumeración para informar fuente de conexión Asterisk
define('ASTCONN_CRED_DESCONOCIDO', 0);  // No se ha seteado todavía
define('ASTCONN_CRED_CONF', 1);         // Credenciales provienen de manager.conf
define('ASTCONN_CRED_DB', 2);           // Credenciales provienen de DB

/**
 * La clase ConfigDB maneja las claves de configuración obtenidas desde la base
 * de datos, y provee métodos para reconocer si la configuración ha cambiado
 * en tiempo de ejecución. Cada uno de las configuraciones es accesible como
 * una propiedad del objeto.
 */
class ConfigDB
{
    private $_dbConn;
    private $_log;
    
	/* Variables conocidas que serán leídas de la base de datos. Para cada 
	 * variable de configuración, se reconoce la siguiente información:
	 * descripcion:		Propósito de la variable en el programa
	 * regex:			Si no es NULL, la variable debe cumplir este regex
	 * valor_omision:	Valor a usar si no se ha asignado otro en la base
	 * valor_viejo:		El valor que tenía la variable antes de la verificación
	 * valor_actual:	El valor que tiene la variable ahora en la base de datos
	 * mostrar_valor:	Si FALSO, el valor se reemplaza en el log por asteriscos
	 * cast:			Tipo de dato PHP a usar para la conversión desde string.
	 */
	private $_infoConfig = array(
		// Variables concernientes a la conexión a Asterisk
		'asterisk'	=>	array(			
			'asthost'	=>	array(
				'descripcion'	=>	'host de Asterisk Manager',
				'regex'			=>	NULL,
				'valor_omision'	=>	'127.0.0.1',
				'valor_viejo'	=>	NULL,
				'valor_actual'	=>	NULL,
				'mostrar_valor'	=>	TRUE,
				'cast'			=>	'string',
			),
			'astuser'	=>	array(
				'descripcion'	=>	'usuario de Asterisk Manager',
				'regex'			=>	NULL,
				'valor_omision'	=>	'',
				'valor_viejo'	=>	NULL,
				'valor_actual'	=>	NULL,
				'mostrar_valor'	=>	FALSE,
				'cast'			=>	'string',
			),
			'astpass'	=>	array(
				'descripcion'	=>	'contraseña de Asterisk Manager',
				'regex'			=>	NULL,
				'valor_omision'	=>	'',
				'valor_viejo'	=>	NULL,
				'valor_actual'	=>	NULL,
				'mostrar_valor'	=>	FALSE,
				'cast'			=>	'string',
			),
			'duracion_sesion' => array(
				'descripcion'	=>	'duración de sesión Asterisk',
				'regex'			=>	'^\d+$',
				'valor_omision'	=>	0,
				'valor_viejo'	=>	NULL,
				'valor_actual'	=>	NULL,
				'mostrar_valor'	=>	TRUE,
				'cast'			=>	'int',
			),
		),
		// Valores que afectan al comportamiento del marcador
		'dialer'	=>	array(
			'llamada_corta'	=>	array(
				'descripcion'	=>	'umbral de llamada corta',
				'regex'			=>	'^\d+$',
				'valor_omision'	=>	10,
				'valor_viejo'	=>	NULL,
				'valor_actual'	=>	NULL,
				'mostrar_valor'	=>	TRUE,
				'cast'			=>	'int',
			),
			'tiempo_contestar' => array(
				'descripcion'	=>	'tiempo de contestado (inicial)',
				'regex'			=>	'^\d+$',
				'valor_omision'	=>	8,
				'valor_viejo'	=>	NULL,
				'valor_actual'	=>	NULL,
				'mostrar_valor'	=>	TRUE,
				'cast'			=>	'int',
			),
			'debug'			=>	array(
				'descripcion'	=>	'mensajes de depuración',
				'regex'			=>	'^(0|1)$',
				'valor_omision'	=>	0,
				'valor_viejo'	=>	NULL,
				'valor_actual'	=>	NULL,
				'mostrar_valor'	=>	TRUE,
				'cast'			=>	'bool',
			),
			'allevents'		=>	array(
				'descripcion'	=>	'depuración de todos los eventos AMI',
				'regex'			=>	'^(0|1)$',
				'valor_omision'	=>	0,
				'valor_viejo'	=>	NULL,
				'valor_actual'	=>	NULL,
				'mostrar_valor'	=>	TRUE,
				'cast'			=>	'bool',
			),
            'overcommit'    =>  array(
				'descripcion'	=>	'sobre-colocación de llamadas',
				'regex'			=>	'^(0|1)$',
				'valor_omision'	=>	0,
				'valor_viejo'	=>	NULL,
				'valor_actual'	=>	NULL,
				'mostrar_valor'	=>	TRUE,
				'cast'			=>	'bool',
			),
            'qos'           =>  array(
				'descripcion'	=>	'porcentaje de atención',
				'regex'			=>	'^0\.\d+$',
				'valor_omision'	=>	0.97,
				'valor_viejo'	=>	NULL,
				'valor_actual'	=>	NULL,
				'mostrar_valor'	=>	TRUE,
				'cast'			=>	'float',
			),
            'predictivo'    =>  array(
                'descripcion'   =>  'predicción de llamadas por terminar',
                'regex'         =>  '^(0|1)$',
                'valor_omision' =>  1,
                'valor_viejo'   =>  NULL,
                'valor_actual'  =>  NULL,
                'mostrar_valor' =>  TRUE,
                'cast'          =>  'bool',
            ),
            'timeout_originate' => array(
                'descripcion'   =>  'tiempo de espera de marcado de llamadas',
                'regex'         =>  '^\d+$',
                'valor_omision' =>  0,
                'valor_viejo'   =>  NULL,
                'valor_actual'  =>  NULL,
                'mostrar_valor' =>  TRUE,
                'cast'          =>  'int',
            ),
		),
	);

    private $_fuenteCredAst = ASTCONN_CRED_DESCONOCIDO;

	// Constructor del objeto de configuración
    function ConfigDB(&$dbConn, &$log)
    {
        $this->_dbConn = $dbConn;
        $this->_log = $log;
		$this->leerConfiguracionDesdeDB();
		$this->limpiarCambios();
    }

    function setDBConn($dbConn) { $this->_dbConn = $dbConn; }

	// Leer todas las variables desde la base de datos
	public function leerConfiguracionDesdeDB()
	{
		$log = $this->_log;

		// Inicializar con los valores por omisión
		foreach ($this->_infoConfig as $seccion => $infoSeccion) {
			foreach ($infoSeccion as $clave => $infoValor) {
				$this->_infoConfig[$seccion][$clave]['valor_actual'] =
					$this->_infoConfig[$seccion][$clave]['valor_omision'];
			}
		}

    	// Leer valores de configuración desde la base de datos
        $listaConfig = array();
        foreach ($this->_dbConn->query('SELECT config_key, config_value FROM valor_config') as $tupla) {
        	$listaConfig[$tupla['config_key']] = $tupla['config_value'];
        }
		foreach ($this->_infoConfig as $seccion => $infoSeccion) {
			foreach ($infoSeccion as $clave => $infoValor) {
				$sClaveDB = "$seccion.$clave";
				if (isset($listaConfig[$sClaveDB])) {
					$this->_infoConfig[$seccion][$clave]['valor_actual'] = $listaConfig[$sClaveDB]; 
				}
			}
		}

    	// Caso especial: obtener valores de usuario/clave AMI
        if ((	$this->_infoConfig['asterisk']['asthost']['valor_actual'] == '127.0.0.1' || 
        		$this->_infoConfig['asterisk']['asthost']['valor_actual'] == 'localhost') &&
            $this->_infoConfig['asterisk']['astuser']['valor_actual'] == '' && 
            $this->_infoConfig['asterisk']['astpass']['valor_actual'] == '') {

            // Base de datos no tiene usuario explícito, se lee de manager.conf
            if ($this->_fuenteCredAst != ASTCONN_CRED_CONF)
                $log->output("INFO: AMI login no se ha configurado, se busca en configuración de Asterisk...");
            $amiConfig = $this->_leerConfigManager();
            if (is_array($amiConfig)) {
                if ($this->_fuenteCredAst != ASTCONN_CRED_CONF)
                    $log->output("INFO: usando configuración de Asterisk para AMI login.");
                $this->_infoConfig['asterisk']['astuser']['valor_actual'] = $amiConfig[0];
                $this->_infoConfig['asterisk']['astpass']['valor_actual'] = $amiConfig[1];
                $this->_fuenteCredAst = ASTCONN_CRED_CONF;
            }
        } else {
            if ($this->_fuenteCredAst == ASTCONN_CRED_DESCONOCIDO)
                $log->output("INFO: AMI login configurado en DB...");
            $this->_fuenteCredAst = ASTCONN_CRED_DB;
        }
        
        // Validar los valores de la base de datos
		foreach ($this->_infoConfig as $seccion => $infoSeccion) {
			foreach ($infoSeccion as $clave => $infoValor) {
				if (!is_null($this->_infoConfig[$seccion][$clave]['valor_actual'])) {
					// Se ha leído algún valor desde la base de datos
					if (is_null($infoValor['regex']) ||
						preg_match('/'.$infoValor['regex'].'/', $infoValor['valor_actual'])) {
						if (is_null($infoValor['valor_viejo'])) {
							$log->output('INFO: usando '.$infoValor['descripcion'].': '.
								($infoValor['mostrar_valor'] ? $infoValor['valor_actual'] : '*****'));
						}
					} else {
						// El valor no pasa el regex
						if (is_null($infoValor['valor_viejo'])) 
							$log->output('ERR: valor para '.$infoValor['descripcion'].' inválido: '.$infoValor['valor_actual']);
						$this->_infoConfig[$seccion][$clave]['valor_actual'] = 
							$this->_infoConfig[$seccion][$clave]['valor_omision'];
						if (is_null($infoValor['valor_viejo'])) 
							$log->output('INFO: usando '.$infoValor['descripcion'].' (por omisión): '.
							($infoValor['mostrar_valor'] ? $this->_infoConfig[$seccion][$clave]['valor_actual'] : '*****'));
					}
				} else {
					// Asignación inicial de las variables
					$this->_infoConfig[$seccion][$clave]['valor_actual'] = 
						$this->_infoConfig[$seccion][$clave]['valor_omision'];
					$log->output('INFO: usando '.$infoValor['descripcion'].' (por omisión): '.
						($infoValor['mostrar_valor'] ? $this->_infoConfig[$seccion][$clave]['valor_actual'] : '*****'));
				}
			}
		}
	}

    /* Leer el estado de /etc/asterisk/manager.conf y obtener el primer usuario 
     * que puede usar el dialer. Devuelve NULL en caso de error, o tupla 
     * user,password para conexión en localhost. */
    private function _leerConfigManager()
    {
		$log = $this->_log;

    	$sNombreArchivo = '/etc/asterisk/manager.conf';
        if (!file_exists($sNombreArchivo)) {
        	$log->output("WARN: $sNombreArchivo no se encuentra.");
            return NULL;
        }
        if (!is_readable($sNombreArchivo)) {
            $log->output("WARN: $sNombreArchivo no puede leerse por usuario de marcador.");
            return NULL;        	
        }
        //$infoConfig = parse_ini_file($sNombreArchivo, TRUE);
        $infoConfig = $this->parse_ini_file_literal($sNombreArchivo);
        if (is_array($infoConfig)) {
            foreach ($infoConfig as $login => $infoLogin) {
            	if ($login != 'general') {
            		if (isset($infoLogin['secret']) && 
            			isset($infoLogin['read']) && 
            			isset($infoLogin['write'])) {
            			return array($login, $infoLogin['secret']);
            		}
            	}
            }
        } else {
            $log->output("ERR: $sNombreArchivo no puede parsearse correctamente.");        	
        }
        return NULL;
    }

    private function parse_ini_file_literal($sNombreArchivo)
    {
    	$h = fopen($sNombreArchivo, 'r');
        if (!$h) return FALSE;
        $r = array();
        $seccion = NULL;
        while (!feof($h)) {
        	$s = fgets($h);
            $s = rtrim($s, " \r\n");
            $regs = NULL;
            if (preg_match('/^\s*\[(\w+)\]/', $s, $regs)) {
            	$seccion = $regs[1];
            } elseif (preg_match('/^(\w+)\s*=\s*(.*)/', $s, $regs)) {
            	if (is_null($seccion))
                    $r[$regs[1]] = $regs[2];
                else
                    $r[$seccion][$regs[1]] = $regs[2];
            }
        }
        fclose($h);
        return $r;
    }

	// Reporte de la lista de variables cambiadas
	public function listaVarCambiadas()
	{
		$l = array();
		foreach ($this->_infoConfig as $seccion => $infoSeccion) {
			foreach ($infoSeccion as $clave => $infoValor) {
				$sClave = "{$seccion}_{$clave}";
				if ($infoValor['valor_viejo'] != $infoValor['valor_actual']) {
					$l[] = $sClave;
				}
			}
		}
		return $l;
	}

	// Olvidar los valores viejos de las variables luego de cargar valores nuevos
	public function limpiarCambios()
	{
		foreach ($this->_infoConfig as $seccion => $infoSeccion) {
			foreach ($infoSeccion as $clave => $infoValor) {
				$sClave = "{$seccion}_{$clave}";
				$this->_infoConfig[$seccion][$clave]['valor_viejo'] = $infoValor['valor_actual'];
			}
		}
	}

	// Obtener el valor de la variable
	public function __get($s)
	{
		foreach ($this->_infoConfig as $seccion => $infoSeccion) {
			foreach ($infoSeccion as $clave => $infoValor) {
				$sClave = "{$seccion}_{$clave}";
				if ($s == $sClave) switch ($infoValor['cast']) {
				case 'string':	return $infoValor['valor_actual'];
				case 'int':		return (int)$infoValor['valor_actual'];
				case 'float':	return (float)$infoValor['valor_actual'];
				case 'bool':	return (bool)$infoValor['valor_actual'];
				}
			}
		}
		$log = $this->_log;
		$log->output("ERR: referencia inválida a propiedad: ConfigDB::$s");			
		foreach (debug_backtrace() as $traceElement) {
			$sNombreFunc = $traceElement['function'];
			if (isset($traceElement['type'])) {
				$sNombreFunc = $traceElement['class'].'::'.$sNombreFunc;
				if ($traceElement['type'] == '::')
					$sNombreFunc = '(static) '.$sNombreFunc;
			}
			$log->output("\ten {$traceElement['file']}:{$traceElement['line']} en función {$sNombreFunc}()");
		}			
		return NULL;
	}
}
?>