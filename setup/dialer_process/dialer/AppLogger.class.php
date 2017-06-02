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
  $Id: AppLogger.class.php,v 1.3 2009/03/06 16:06:22 alex Exp $ */

class AppLogger
{
    private $LOGHANDLE;
    private $PREFIJO;
    private $sNombreArchivo;

    // Crear una nueva instancia de AppLogger
    function AppLogger()
    {
        $this->LOGHANDLE = NULL;
        $this->PREFIJO = NULL;
    }

    // Abrir una bitácora, dado el nombre de archivo
    function open($sNombreArchivo)
    {
        // Intentar la apertura del archivo de bitácora
        if (is_null($this->LOGHANDLE)) {
            $hLogHandle = fopen($sNombreArchivo, 'at');
            if (!$hLogHandle) {
                if (function_exists('error_get_last'))
                    $e = error_get_last();
                else $e = array('message' => 'Failed to open file, error_get_last() not available.');
                throw new Exception("AppLogger::open() - No se puede abrir archivo de log '$sNombreArchivo' - $e[message]");
            }
            stream_set_write_buffer($hLogHandle, 0);
            $this->LOGHANDLE = $hLogHandle;
            $this->sNombreArchivo = $sNombreArchivo;
        }
    }

    // Cerrar y volver a abrir el archivo de bitácora bajo el mismo nombre.
    // Pensado para usar en rotación de logs con logrotate.
    function reopen()
    {
        if (!is_null($this->LOGHANDLE)) {
            $sTempNombre = $this->sNombreArchivo;
            $this->close();
            $this->open($sTempNombre);
        }
    }

    // Definir el prefijo a mostrar en cada mensaje
    function prefijo($sNuevoPrefijo = false)
    {
        if ($sNuevoPrefijo !== false) $this->PREFIJO = "$sNuevoPrefijo";
        return $this->PREFIJO;
    }

    // Escribir una cadena en la bitácora, precedida por la fecha del sistema en
    // formato YYYY-MM-DD hh:mm
    function output($sCadena)
    {
        fprintf($this->LOGHANDLE, "%s PID=%6d : %s%s\n",
            date('Y-m-d H:i:s'), posix_getpid(),
            (is_null($this->PREFIJO) ? '' : "($this->PREFIJO) "),
            $sCadena);
    }

    // Cerrar la bitácora del programa
    function close()
    {
        // Mandar a cerrar el archivo de bitácora
        if (!is_null($this->LOGHANDLE)) {
            fclose ($this->LOGHANDLE);
            $this->LOGHANDLE = NULL;
        }
    }
}
?>
