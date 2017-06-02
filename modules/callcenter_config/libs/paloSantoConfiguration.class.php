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
  $Id: default.conf.php,v 1.1 2008-09-03 01:09:56 Alex Villacís Lasso Exp $ */

class paloSantoConfiguration {
    var $_DB;
    var $errMsg;

    function paloSantoConfiguration(&$pDB)
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
    }

    function ObtainConfiguration()
    {
        $listaConf = $this->_DB->fetchTable('SELECT config_key, config_value FROM valor_config');
        if (is_array($listaConf)) {
            $t = array(
                'asterisk.asthost' => '127.0.0.1',
                'asterisk.astuser' => '',
                'asterisk.astpass' => '',
                'asterisk.duracion_sesion' => '0',
                'dialer.llamada_corta' => '10',
                'dialer.tiempo_contestar' => '8',
                'dialer.debug' => '0',
                'dialer.allevents' => '0',
                'dialer.overcommit' => '0',
                'dialer.qos' => '0.97',
                'dialer.predictivo' => '1',
                'dialer.timeout_originate' => '0',
            );
            foreach ($listaConf as $tupla) $t[$tupla[0]] = $tupla[1];
            $listaConf = $t;
        } else {
            // TODO: qué debe mostrarse si falla query?
            $listaConf = array();
        }
        $listaConf['dialer.qos'] *= 100.0;
        return $listaConf;
    }

    function SaveConfiguration($config)
    {
        $bContinuar = TRUE;
        foreach ($config as $dbfield => $valor) {
            if ($dbfield == 'dialer.qos') $valor /= 100.0;
            $bContinuar = $this->_DB->genQuery('DELETE FROM valor_config WHERE config_key = ?', array($dbfield));
            if (!$bContinuar) {
                $this->errMsg = $this->_DB->errMsg;
                break;
            }
            if ($dbfield == 'dialer.debug' || $dbfield == 'dialer.allevents') {
                if ($valor) {
                    $bContinuar = $this->_DB->genQuery(
                        'INSERT INTO valor_config (config_key, config_value) VALUES (?, ?)', 
                        array($dbfield, 1));
                }
            } else {
                $bContinuar = $this->_DB->genQuery(
                    'INSERT INTO valor_config (config_key, config_value) VALUES (?, ?)', 
                    array($dbfield, $valor));
            }
            if (!$bContinuar) {
                $this->errMsg = $this->_DB->errMsg;
                break;
            }
        }
        return $bContinuar;
    }

    function getStatusDialer($pd)
    {
         // Determinar el status del dialer, verificando el archivo dialerd.pid
        $bDialerActivo = FALSE;
        if (file_exists($pd)) {
            $pid = file_get_contents($pd);
            $regs = NULL;
            if (preg_match('/^([[:digit:]]+)/', $pid, $regs)) {
                if (file_exists("/proc/$regs[1]")) {
                    $bDialerActivo = TRUE;
                }
            }
        }
        return $bDialerActivo;
    }

    function setStatusDialer($bNuevoEstado)
    {
        $output = NULL;
        $retval = 1;
        if ($bNuevoEstado) {
            if (file_exists('/etc/init.d/generic-cloexec')) {
                exec('sudo -u root service generic-cloexec elastixdialer start 1>/dev/null 2>/dev/null', $output, $retval);
            } else {
                exec('sudo -u root service elastixdialer start 1>/dev/null 2>/dev/null', $output, $retval);            	
            }
        } else {
            exec('sudo -u root service elastixdialer stop', $output, $retval);
        }
    }
}
?>
