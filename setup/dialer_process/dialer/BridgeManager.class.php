<?php
/* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
 Codificación: UTF-8
 +----------------------------------------------------------------------+
 | Issabel version 1.2-2                                                |
 | http://www.issabel.org                                               |
 +----------------------------------------------------------------------+
 | Copyright (c) 2006 Palosanto Solutions S. A.                         |
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
 | The Initial Developer of the Original Code is PaloSanto Solutions    |
 +----------------------------------------------------------------------+
*/

class BridgeManager
{
	var $DEBUG = FALSE;
    private $_log;

    var $_bridges = array();

    function __construct($log)
    {
        $this->_log = $log;
    }

    function msg_BridgeCreate($params)
    {
        if (isset($this->_bridges[$params['BridgeUniqueid']])) {
            $this->_log->output('WARN: '.__METHOD__.': el Bridge '.$params['BridgeUniqueid'].' ya se encuentra registrado.');
            return;
        }

        $this->_bridges[$params['BridgeUniqueid']]['channels'] = array(
            'Channel1'   => NULL,
            'Channel2' => NULL
        );
    }

    function msg_BridgeEnter($params)
    {
        if (!isset($this->_bridges[$params['BridgeUniqueid']])) {
            $this->_log->output('WARN: '.__METHOD__.': el Bridge '.$params['BridgeUniqueid'].' no se encuentra registrado.');

            $this->_bridges[$params['BridgeUniqueid']]['channels'] = array(
	            'Channel1'   => array('Channel' => NULL, 'Uniqueid' => NULL ),
	            'Channel2' => array('Channel' => NULL, 'Uniqueid' => NULL )
	        );
        }

		if($params['BridgeNumChannels'] == 1)
        {
            $this->_bridges[$params['BridgeUniqueid']]['channels']['Channel1']['Channel'] = $params['Channel'];
            $this->_bridges[$params['BridgeUniqueid']]['channels']['Channel1']['Uniqueid'] = $params['Uniqueid'];
        }
        else
        {
            $this->_bridges[$params['BridgeUniqueid']]['channels']['Channel2']['Channel'] = $params['Channel'];
            $this->_bridges[$params['BridgeUniqueid']]['channels']['Channel2']['Uniqueid'] = $params['Uniqueid'];
        }
    }

    function msg_BridgeLeave($params)
    {
        if (!isset($this->_bridges[$params['BridgeUniqueid']])) {
            $this->_log->output('WARN: '.__METHOD__.': el Bridge '.$params['BridgeUniqueid'].' no se encuentra registrado.');
            return;
        }

		unset($this->_bridges[$params['BridgeUniqueid']]);
    }
}
?>