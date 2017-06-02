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

// Para obtener los estados de miembros definidos en Agente.class.php
require_once 'Agente.class.php';

class QueueShadow
{
    var $DEBUG = FALSE;
    private $_log;

    private $_queues = array();
    private $_queueflags = NULL;

    function __construct($log)
    {
        $this->_log = $log;
    }

    /**
     * Método para iniciar el modo de enumeración de la información de las
     * colas. Se invalidan todos los elementos esperando que los eventos
     * recibidos los vuelvan a validar.
     */
    function QueueStatus_start($queueflags)
    {
        $this->_queueflags = $queueflags;
        foreach (array_keys($this->_queues) as $q) {
            $this->_queues[$q]['removed'] = TRUE;
            foreach (array_keys($this->_queues[$q]['members']) as $m) {
                $this->_queues[$q]['members'][$m]['removed'] = TRUE;
            }
        }
    }

    function msg_QueueParams($params)
    {
        if (!isset($this->_queues[$params['Queue']])) {
            $this->_queues[$params['Queue']] = array(
                'removed'           => FALSE,
                'members'           => array(),
                'callers'           => 0,
                'eventmemberstatus' =>  FALSE,
                'eventwhencalled'   =>  FALSE,
            );
        } else {
            $this->_queues[$params['Queue']]['removed'] = FALSE;
            $this->_queues[$params['Queue']]['callers'] = 0;

            // ¿Cómo puedo saber si es seguro heredar event* ?
            $this->_queues[$params['Queue']]['eventmemberstatus'] = FALSE;
            $this->_queues[$params['Queue']]['eventwhencalled'] = FALSE;
        }

        if (isset($this->_queueflags[$params['Queue']])) {
            foreach (array('eventmemberstatus', 'eventwhencalled') as $k)
                $this->_queues[$params['Queue']][$k] = $this->_queueflags[$params['Queue']][$k];
        }
    }

    function msg_QueueMember($params)
    {
        if (!isset($this->_queues[$params['Queue']][$params['Location']])) {
            $this->_queues[$params['Queue']]['members'][$params['Location']] = array(
                'removed'   => FALSE,
                'Status'    => $params['Status'],
                'Paused'    => ($params['Paused'] != 0),
                'LinkStart' => NULL,
            );
        } else {
            $this->_queues[$params['Queue']]['members'][$params['Location']]['removed'] = FALSE;
            $this->_queues[$params['Queue']]['members'][$params['Location']]['Status'] = $params['Status'];
            $this->_queues[$params['Queue']]['members'][$params['Location']]['Paused'] = ($params['Paused'] != 0);
            // Voy a asumir que puedo conservar el valor de LinkStart
        }
    }

    function msg_QueueEntry($params)
    {
        $this->_queues[$params['Queue']]['callers']++;
    }

    /**
     * Quitar todo elemento que no haya sido re-validado en enumeración.
     */
    function msg_QueueStatusComplete($params)
    {
        $colasSinEventos = array();

        foreach (array_keys($this->_queues) as $q) {
            if ($this->_queues[$q]['removed']) {
                unset($this->_queues[$q]);
            } else {
                // Acumular colas sin banderas activas
                if (!($this->_queues[$q]['eventwhencalled'] && $this->_queues[$q]['eventmemberstatus'])) {
                    if ($q != 'default') $colasSinEventos[] = $q;
                }

                foreach (array_keys($this->_queues[$q]['members']) as $m) {
                    if ($this->_queues[$q]['members'][$m]['removed']) {
                        unset($this->_queues[$q]['members'][$m]);
                    }
                }
            }
        }
        $this->_queueflags = NULL;
        if ($this->DEBUG) {
            $this->_log->output('DEBUG: '.__METHOD__.': estado de colas: '.print_r($this->_queues, TRUE));
        }
        if (count($colasSinEventos) > 0) {
            sort($colasSinEventos);
            $this->_log->output('WARN: '.__METHOD__.': para mejorar el desempeño de '.
                'campañas salientes, se recomienda activar eventwhencalled y '.
                'eventmemberstatus en las siguientes colas: ['.
                implode(' ', $colasSinEventos).']');
        }
    }

    /**************************************************************************/

    function msg_QueueMemberAdded($params)
    {
        if (!isset($this->_queues[$params['Queue']])) {
            $this->_log->output('WARN: '.__METHOD__.': no se encuentra cola '.$params['Queue']);
            return;
        }

        $this->_queues[$params['Queue']]['members'][$params['Location']] = array(
            'removed'   => FALSE,
            'Status'    => $params['Status'],
            'Paused'    => ($params['Paused'] != 0),
            'LinkStart' => NULL,
        );
    }

    function msg_QueueMemberRemoved($params)
    {
        if (!isset($this->_queues[$params['Queue']])) {
            $this->_log->output('WARN: '.__METHOD__.': no se encuentra cola '.$params['Queue']);
            return;
        }

        unset($this->_queues[$params['Queue']]['members'][$params['Location']]);
    }

    function msg_QueueMemberPaused($params)
    {
        if (!isset($this->_queues[$params['Queue']])) {
            $this->_log->output('WARN: '.__METHOD__.': no se encuentra cola '.$params['Queue']);
            return;
        }

        if (isset($this->_queues[$params['Queue']]['members'][$params['Location']])) {
            $this->_queues[$params['Queue']]['members'][$params['Location']]['Paused'] = ($params['Paused'] != 0);
        } else {
            $this->_log->output('WARN: '.__METHOD__.': no se encuentra miembro '.$params['Location'].
                    ' en cola '.$params['Queue']);
        }
    }

    function msg_QueueMemberStatus($params)
    {
        if (!isset($this->_queues[$params['Queue']])) {
            $this->_log->output('WARN: '.__METHOD__.': no se encuentra cola '.$params['Queue']);
            return;
        }

        // Cola validada que tiene eventmemberstatus activo
        $this->_queues[$params['Queue']]['eventmemberstatus'] = TRUE;

        if (isset($this->_queues[$params['Queue']]['members'][$params['Location']])) {
            $this->_queues[$params['Queue']]['members'][$params['Location']]['Status'] = $params['Status'];
            $this->_queues[$params['Queue']]['members'][$params['Location']]['Paused'] = ($params['Paused'] != 0);
            // Voy a asumir que puedo conservar el valor de LinkStart
        } else {
           $this->_log->output('WARN: '.__METHOD__.': no se encuentra miembro '.$params['Location'].
               ' en cola '.$params['Queue'].', se agrega');
            $this->_queues[$params['Queue']]['members'][$params['Location']] = array(
                'removed'   => FALSE,
                'Status'    => $params['Status'],
                'Paused'    => ($params['Paused'] != 0),
                'LinkStart' => NULL,
            );
        }
    }
    function msg_Join($params)
    {
        if (!isset($this->_queues[$params['Queue']])) {
            $this->_log->output('WARN: '.__METHOD__.': no se encuentra cola '.$params['Queue']);
            return;
        }

        $this->_queues[$params['Queue']]['callers']++;
    }

    function msg_Leave($params)
    {
        if (!isset($this->_queues[$params['Queue']])) {
            $this->_log->output('WARN: '.__METHOD__.': no se encuentra cola '.$params['Queue']);
            return FALSE;
        }

        $this->_queues[$params['Queue']]['callers']--;

        if ($this->_queues[$params['Queue']]['callers'] < 0) {
            $this->_queues[$params['Queue']]['callers'] = 0;
            return FALSE;
        }
        return TRUE;
    }

    function msg_AgentCalled($params)
    {
        if (!isset($this->_queues[$params['Queue']])) {
            $this->_log->output('WARN: '.__METHOD__.': no se encuentra cola '.$params['Queue']);
            return;
        }

        // Cola validada que tiene eventwhencalled activo
        $this->_queues[$params['Queue']]['eventwhencalled'] = TRUE;

        // TODO: qué se puede hacer aquí?
    }

    function msg_AgentComplete($params)
    {
        if (!isset($this->_queues[$params['Queue']])) {
            $this->_log->output('WARN: '.__METHOD__.': no se encuentra cola '.$params['Queue']);
            return;
        }

        // Cola validada que tiene eventwhencalled activo
        $this->_queues[$params['Queue']]['eventwhencalled'] = TRUE;

        if (isset($this->_queues[$params['Queue']]['members'][$params['Member']])) {
            $this->_queues[$params['Queue']]['members'][$params['Member']]['LinkStart'] = NULL;

            // La actualización de Status debería hacerse en un QueueMemberStatus próximo
        } else {
            $this->_log->output('WARN: '.__METHOD__.': no se encuentra miembro '.$params['Member'].
                ' en cola '.$params['Queue']);
        }
    }

    function msg_AgentConnect($params)
    {
        if (!isset($this->_queues[$params['Queue']])) {
            $this->_log->output('WARN: '.__METHOD__.': no se encuentra cola '.$params['Queue']);
            return;
        }

        // Cola validada que tiene eventwhencalled activo
        $this->_queues[$params['Queue']]['eventwhencalled'] = TRUE;

        if (isset($this->_queues[$params['Queue']]['members'][$params['Member']])) {
            $this->_queues[$params['Queue']]['members'][$params['Member']]['LinkStart'] = $params['local_timestamp_received'];

            // La actualización de Status debería hacerse en un QueueMemberStatus próximo
        } else {
            $this->_log->output('WARN: '.__METHOD__.': no se encuentra miembro '.$params['Member'].
                ' en cola '.$params['Queue']);
        }
    }

    function msg_AgentDump($params)
    {
        if (!isset($this->_queues[$params['Queue']])) {
            $this->_log->output('WARN: '.__METHOD__.': no se encuentra cola '.$params['Queue']);
            return;
        }

        // Cola validada que tiene eventwhencalled activo
        $this->_queues[$params['Queue']]['eventwhencalled'] = TRUE;

        // TODO: qué se puede hacer aquí?
    }

    /**************************************************************************/

    function llamadasEnEspera()
    {
        $t = array();

        foreach ($this->_queues as $q => $info) {
            $t[$q] = $info['callers'];
        }
        return $t;
    }

    function infoPrediccionCola($queue)
    {
        if (!isset($this->_queues[$queue])) {
            $this->_log->output('WARN: '.__METHOD__.': no se encuentra cola '.$queue);
            return NULL;
        }

        if (!($this->_queues[$queue]['eventwhencalled'] && $this->_queues[$queue]['eventmemberstatus'])) {
            if ($this->DEBUG) {
                $this->_log->output('DEBUG: '.__METHOD__.': cola '.$queue.' no ha recibido eventos que '.
                    'indiquen activación de eventwhencalled y eventmemberstatus');
            }
            return NULL;
        }

        $iNumLlamadasColocar = array(
            'AGENTES_LIBRES'        =>  0,
            'AGENTES_POR_DESOCUPAR' =>  array(),
            'CLIENTES_ESPERA'       =>  $this->_queues[$queue]['callers'],
        );
        foreach ($this->_queues[$queue]['members'] as $miembro) {

            // Se ignora miembro en pausa
            if ($miembro['Paused']) continue;

            // Miembro definitivamente libre
            if (in_array($miembro['Status'], array(AST_DEVICE_NOT_INUSE, AST_DEVICE_RINGING)))
                $iNumLlamadasColocar['AGENTES_LIBRES']++;

            // Miembro ocupado, se verifica si se desocupará
            if (in_array($miembro['Status'], array(AST_DEVICE_INUSE, AST_DEVICE_BUSY, AST_DEVICE_RINGINUSE)) &&
                !is_null($miembro['LinkStart'])) {
                $iNumLlamadasColocar['AGENTES_POR_DESOCUPAR'][] = microtime(TRUE) - $miembro['LinkStart'];
            }
        }
        if ($this->DEBUG) {
            $this->_log->output('DEBUG: a punto de devolver '.print_r($iNumLlamadasColocar, true));
        }
        return $iNumLlamadasColocar;
    }
}