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
  $Id: iRoutedMessageHook.class.php,v 1.48 2009/03/26 13:46:58 alex Exp $ */

/**
 * Esta interfaz define un solo método, el cual se llama cada vez que se
 * rutea un mensaje. En este método se puede tomar cualquier acción que modifica
 * cualquiera de los parámetros provistos.
 */

interface iRoutedMessageHook
{
    public function inspeccionarMensaje(&$sFuente, &$sDestino, &$sNombreMensaje, &$datos);
}
?>