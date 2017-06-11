<?php
/* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Issabel version 1.2-2                                               |
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
  $Id: DialerConn.class.php,v 1.48 2009/03/26 13:46:58 alex Exp $ */

abstract class MultiplexConn
{
    public $multiplexSrv = NULL;
    public $sKey = NULL;

    // Todas las implementaciones asumen que $multiplexSrv y $sKey son válidos
    abstract public function procesarInicial();
    abstract public function procesarCierre();
    abstract public function parsearPaquetes($sDatos);
    abstract public function hayPaquetes();
    abstract public function procesarPaquete();
    abstract public function finalizarConexion();
}
?>