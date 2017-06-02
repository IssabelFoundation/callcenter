<?php
/* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Elastix version 0.5                                                  |
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
*/
include_once("libs/paloSantoDB.class.php");

class paloSantoUploadFile
{
	private $_DB;
	var $errMsg;
    private $_numInserciones;
    private $_numActualizaciones;
	
	function paloSantoUploadFile(&$pDB)
	{
        // Se recibe como parámetro una referencia a una conexión paloDB
        if (is_object($pDB)) {
            $this->_DB =& $pDB;
            $this->errMsg = $this->_DB->errMsg;
        } else {
            $dsn = (string)$pDB;
            $this->_DB = new paloDB($dsn);

            if (!$this->_DB->connStatus) {
                $this->errMsg = $this->_DB->errMsg;
                // debo llenar alguna variable de error
            } else {
                // debo llenar alguna variable de error
            }
        }
        $this->_numInserciones = 0;
        $this->_numActualizaciones = 0;
	}

    /**
     * Procedimiento para agregar los números de teléfono indicados por la
     * ruta de archivo indicada a la tabla de contactos. Si el número de 
     * cédula ya existe en el sistema, su información se sobreescribe con la
     * información presente en el archivo.
     *
     * Esta función está construida en base a parseCampaignNumbers() y 
     * addCampaignNumbers()
     *
     * @param	int		$idCampaign	ID de la campaña a modificar
     * @param	string	$sFilePath	Archivo local a leer para los números
     *
     * @return bool		VERDADERO si éxito, FALSO si ocurre un error
     */
    function addCampaignNumbersFromFile($sFilePath, &$sEncoding)
    {
    	$bExito = FALSE;
    	
    	$listaNumeros = $this->parseCampaignNumbers($sFilePath, $sEncoding); 
    	if (is_array($listaNumeros)) {
    		$bExito = $this->addCampaignNumbers($listaNumeros);
    	}
    	return $bExito;
    }

    /**
     * Procedimiento que carga un archivo CSV con números y parámetros en memoria
     * y devuelve la matriz de datos obtenida. El formato del archivo es CSV, 
     * con campos separados por comas. La primera columna contiene el número
     * telefónico, el cual consiste de cualquier cadena numérica. El resto de
     * columnas contienen parámetros que se agregan como campos adicionales. Las
     * líneas vacías se ignoran, al igual que las líneas que empiecen con #
     *
     * @param	string	$sFilePath	Archivo local a leer para la lista
     * @param   string  $sEncoding  (SALIDA) Codificación detectada para archivo
     *
     * @return	mixed	Matriz cuyas tuplas contienen los contenidos del archivo,
     *					en el orden en que fueron leídos, o NULL en caso de error.
     */
    private function parseCampaignNumbers($sFilePath, &$sEncoding)
    {
    	$listaNumeros = NULL;

        // Detectar codificación para procesar siempre como UTF-8 (bug #325)
        $sEncoding = $this->_adivinarCharsetArchivo($sFilePath);    	

    	$hArchivo = fopen($sFilePath, 'rt');
    	if (!$hArchivo) {
    		$this->errMsg = _tr("Invalid CSV File");//'No se puede abrir archivo especificado para leer CSV';
    	} else {
    		$iNumLinea = 0;
    		$listaNumeros = array();
    		$clavesColumnas = array();
    		while ($tupla = fgetcsv($hArchivo, 2048,",")) {
    			$iNumLinea++;
    			foreach ($tupla as $k => $v) $tupla[$k] = mb_convert_encoding($tupla[$k], "UTF-8", $sEncoding);
                $tupla[0] = trim($tupla[0]);
    			if (count($tupla) == 1 && trim($tupla[0]) == '') {
    				// Línea vacía
    			} elseif (strlen($tupla[0]) > 0 && $tupla[0]{0} == '#') {
    				// Línea que empieza por numeral
    			} elseif (!ereg('^[[:digit:]#*]+$', $tupla[0])) {
                    if ($iNumLinea == 1) {
                        // Podría ser una cabecera de nombres de columnas
                        array_shift($tupla);
                        $clavesColumnas = $tupla;
                    } else {
                        // Teléfono no es numérico
                        $this->errMsg =  _tr('Line').' '.$iNumLinea.' - '._tr("Invalid phone number").': '.$tupla[0];
                        return NULL;
                    }
    			} else {
                    // Como efecto colateral, $tupla pierde su primer elemento
                    $tuplaLista = array(
                        'POSICION'  => $iNumLinea,
                        'NUMERO'    =>  array_shift($tupla),
                        'ATRIBUTOS' =>  array(),
                    );
                    for ($i = 0; $i < count($tupla); $i++) {
                    	$tuplaLista['ATRIBUTOS'][$i + 1] = array(
                            'CLAVE' =>  ($i < count($clavesColumnas) && $clavesColumnas[$i] != '') ? $clavesColumnas[$i] : ($i + 1),
                            'VALOR' =>  $tupla[$i],
                        );
                    }
  					$listaNumeros[] = $tuplaLista;
    			}
    		}
    		fclose($hArchivo);
    	}
    	return $listaNumeros;
    }

    // Función que intenta adivinar la codificación de caracteres del archivo
    private function _adivinarCharsetArchivo($sFilePath)
    {
        // Agregar a lista para detectar más encodings. ISO-8859-15 debe estar
        // al último porque toda cadena de texto es válida como ISO-8859-15.
        $listaEncodings = array(
            "ASCII",
            "UTF-8",
            //"EUC-JP",
            //"SJIS",
            //"JIS",
            //"ISO-2022-JP",
            "ISO-8859-15"
        );
        $sContenido = file_get_contents($sFilePath);
        $sEncoding = mb_detect_encoding($sContenido, $listaEncodings);
        return $sEncoding;
    }
    
    /**
     * Procedimiento que agrega números a una campaña existente. La lista de
     * números consiste en un arreglo de tuplas, cuyo elemento NUMERO es el 
     * número de teléfono, el elemento POSICION contiene el número de línea del
     * cual procece la información, y el resto de claves es el conjunto 
     * clave->valor a guardar en la tabla call_attribute para cada llamada.
     *
     * Actualmente se rechazan cabeceras de columnas, para insertar, a pesar
     * de que se pueden parsear, porque el esquema de base de datos no tiene
     * la capacidad de guardar atributos arbitrarios del contacto (véase tabla
     * 'contact'). Sólo se aceptan claves numéricas. Se asume que el valor de
     * clave 1 es CÉDULA, el valor 2 es NOMBRE, y el valor 3 es APELLIDO. La 
     * información se considera única con respeto a CÉDULA.
     *
     * @param int $idCampaign   ID de Campaña
     * @param array $listaNumeros   Lista de números como se describe arriba
     *      array('NUMERO' => '1234567',
     *          'POSICION' => 45, 
     *          ATRIBUTOS => array(
     *              array( CLAVE => 1, VALOR => 0911111111), 
     *              array( CLAVE => 2, VALOR => Fulano),
     *              array( CLAVE => 3, VALOR => De Tal),
     *          ))
     *
     * @return bool VERDADERO si todos los números fueron insertados, FALSO en error
     */
    private function addCampaignNumbers($listaNumeros)
    {
        if (!is_array($listaNumeros)) {
            // TODO: internacionalizar
    		$this->errMsg = '(internal) Lista de números tiene que ser un arreglo';
    		return FALSE;
    	} else {
            $this->_numInserciones = 0;
            $this->_numActualizaciones = 0;
            $this->errMsg = '';

    	    // Realizar inserción de número y atributos
    	    $bValido = TRUE;
    	    foreach ($listaNumeros as $tuplaNumero) {
                $sCedula = NULL;
                $sNombre = NULL;
                $sApellido = NULL;
                
                // Recolectar los atributos y rechazar los atributos desconocidos
                foreach ($tuplaNumero['ATRIBUTOS'] as $atributo) {
                    if ($atributo['CLAVE'] == 1) 
                        $sCedula = $atributo['VALOR'];
                    elseif ($atributo['CLAVE'] == 2)
                        $sNombre = $atributo['VALOR'];
                    elseif ($atributo['CLAVE'] == 3)
                        $sApellido = $atributo['VALOR'];
                    else {
                        $this->errMsg = _tr('Line').' '.$tuplaNumero['POSICION'].' - '._tr('Unsupported attribute').': '.$sAtributo['CLAVE'];
                        $bValido = FALSE;
                        break;
                    }
                }

    	        // Validar que la CÉDULA sea una cadena numérica
    	        if (!preg_match('/^\d+$/', $sCedula)) {
    	            $this->errMsg = _tr('Line').' '.$tuplaNumero['POSICION'].' - '._tr('Invalid Cedula/RUC').': '.$sCedula;
    	            $bValido = FALSE;
                    break;
    	        }
    	        if (is_null($sNombre) || $sNombre == '') {
    	            $this->errMsg = _tr('Line').' '.$tuplaNumero['POSICION'].' - '._tr('Missing or empty name').': '.$sCedula;
    	            $bValido = FALSE;
                    break;
    	        }
    	        if (is_null($sApellido) || $sApellido == '') {
    	            $this->errMsg = _tr('Line').' '.$tuplaNumero['POSICION'].' - '._tr('Missing or empty surname').': '.$sCedula;
    	            $bValido = FALSE;
                    break;
    	        }
    	    }
    	    
    	    if ($bValido) {
        	    $sOrigen = 'file'; // Fuente de los datos insertados
        	    $sPeticionSQL_Cedula = 'SELECT id FROM contact WHERE cedula_ruc = ?';
    	    
                // Insertar cada uno de los valores ya validados
        	    foreach ($listaNumeros as $tuplaNumero) {
                    $sCedula = NULL;
                    $sNombre = NULL;
                    $sApellido = NULL;
                    
                    foreach ($tuplaNumero['ATRIBUTOS'] as $atributo) {
                        if ($atributo['CLAVE'] == 1) $sCedula = $atributo['VALOR'];
                        elseif ($atributo['CLAVE'] == 2) $sNombre = $atributo['VALOR'];
                        elseif ($atributo['CLAVE'] == 3) $sApellido = $atributo['VALOR'];
                    }
                    $r = $this->_DB->getFirstRowQuery($sPeticionSQL_Cedula, TRUE, array($sCedula));
                    if (!is_array($r)) {
                        $this->errMsg = '(internal) Unable to lookup cedula - '.$this->_DB->errMsg;
                        return FALSE;
                    }
                    if (count($r) <= 0) {
                        $sql = 
                            'INSERT INTO contact (name, apellido, telefono, origen, cedula_ruc) '.
                            'VALUES (?, ?, ?, ?, ?)';
                        $this->_numInserciones++;
                    } else {
                        $sql =
                            'UPDATE contact SET name = ?, apellido = ?, telefono = ?, origen = ? '.
                            'WHERE cedula_ruc = ?';
                        $this->_numActualizaciones++;
                    }
                    $params = array($sNombre, $sApellido, $tuplaNumero['NUMERO'], $sOrigen, $sCedula);
                    $r = $this->_DB->genQuery($sql, $params);
                    if (!$r) {
                        $this->errMsg = '(internal) Unable to insert/update - '.$this->_DB->errMsg;
                        return FALSE;
                    }
        	    }
        	}
        	return $bValido;
    	}
    }
    
    function obtenerContadores()
    {
        return array($this->_numInserciones, $this->_numActualizaciones);
    }

    /**
     * Procedimiento para leer toda la información de contactos almacenada en la
     * base de datos de CallCenter. Actualmente la información que se almacena
     * sólo consiste de nombre, apellido y cédula, en el mismo orden que se 
     * acepta para la subida de datos.
     *
     * @return  NULL en caso de error, o matriz con los datos.
     */
    function leerContactos()
    {
        $sPeticionSQL = 'SELECT telefono, cedula_ruc, name, apellido FROM contact';
        $r = $this->_DB->fetchTable($sPeticionSQL);
        if (!is_array($r)) {
            $this->errMsg = '(internal) Unable to read contacts - '.$this->_DB->errMsg;
            return NULL;
        }
        return $r;
    }
}
?>
