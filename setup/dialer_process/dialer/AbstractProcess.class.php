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
  $Id: AbstractProcess.class.php,v 1.2 2008/09/08 18:29:36 alex Exp $ */

class AbstractProcess
{
    function inicioPostDemonio()
    {
        throw new Exception("AbstractProcess::inicioPostDemonio() llamado sin sobrecarga");
    }

    function procedimientoDemonio()
    {
        throw new Exception("AbstractProcess::procedimientoDemonio() llamado sin sobrecarga");
    }

    function limpiezaDemonio()
    {
        throw new Exception("AbstractProcess::limpiezaDemonio() llamado sin sobrecarga");
    }

    function demonioSoportaReconfig()
    {
        return FALSE;
    }

    function reinicioDemonio($param)
    {
        throw new Exception("AbstractProcess::reinicioDemonio() llamado sin sobrecarga");
    }
}
?>
