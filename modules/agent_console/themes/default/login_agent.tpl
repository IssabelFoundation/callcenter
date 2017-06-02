{* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Elastix version 0.8                                                  |
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
  $Id: default.conf.php,v 1.1.1.1 2007/03/23 00:13:58 elandivar Exp $
*}
{* Incluir todas las bibliotecas y CSS necesarios *}
{foreach from=$LISTA_JQUERY_CSS item=CURR_ITEM}
    {if $CURR_ITEM[0] == 'css'}
<link rel="stylesheet" href='{$CURR_ITEM[1]}' />
    {/if}
    {if $CURR_ITEM[0] == 'js'}
<script type="text/javascript" src='{$CURR_ITEM[1]}'></script>
    {/if}
{/foreach}

{if $NO_EXTENSIONS}
<p><h4 align="center">{$LABEL_NOEXTENSIONS}</h4></p>
{elseif $NO_AGENTS}
<p><h4 align="center">{$LABEL_NOAGENTS}</h4></p>
{else}
<form method="POST"  action="index.php?menu={$MODULE_NAME}" onsubmit="do_login(); return false;">

<p>&nbsp;</p>
<p>&nbsp;</p>
<table width="400" border="0" cellspacing="0" cellpadding="0" align="center">
  <tr>
    <td width="498"  class="menudescription">
      <table width="100%" border="0" cellspacing="0" cellpadding="4" align="center">
        <tr>
          <td class="menudescription2">
              <div align="left"><font color="#ffffff">&nbsp;&raquo;&nbsp;{$WELCOME_AGENT}</font></div>
          </td>
        </tr>
      </table>
    </td>
  </tr>
  <tr>
    <td width="498" bgcolor="#ffffff">
      <table width="100%" border="0" cellspacing="0" cellpadding="8" class="tabForm">
        <tr>
          <td colspan="2">
            <div align="center">{$ENTER_USER_PASSWORD}<br/><br/></div>
          </td>
        </tr>
        <tr id="login_fila_estado" {$ESTILO_FILA_ESTADO_LOGIN}>
          <td colspan="2">
            <div align="center" id="login_icono_espera" height='1'><img id="reloj" src="modules/{$MODULE_NAME}/images/loading.gif" border="0" alt=""></div>
            <div align="center" style="font-weight: bold;" id="login_msg_espera">{$MSG_ESPERA}</div>
            <div align="center" id="login_msg_error" style="color: #ff0000;"></div>
          </td>
        </tr>
        <tr>
          <td width="40%">
              <div align="right" id="label_agent_user">{$USERNAME}:</div>
              <div align="right" id="label_extension_callback">{$CALLBACK_EXTENSION}:</div>
          </td>
          <td width="60%">
                <select align="center" id="input_agent_user" name="input_agent_user">
                    {html_options options=$LISTA_AGENTES selected=$ID_AGENT}
                </select>
                <select align="center" id="input_extension_callback" name="input_extension_callback">
                    {html_options options=$LISTA_EXTENSIONES_CALLBACK selected=$ID_EXTENSION_CALLBACK}
                </select>
          </td>
        </tr>
        <tr>
          <td width="40%">
              <div align="right" id="label_extension">{$EXTENSION}:</div>
              <div align="right" id="label_password_callback">{$PASSWORD}:</div>
          </td>
          <td width="60%">
                <select align="center" name="input_extension" id="input_extension">
                    {html_options options=$LISTA_EXTENSIONES selected=$ID_EXTENSION}
                </select>
		<input type="password" name="input_password_callback" id="input_password_callback">
          </td>
        </tr>
<!-- Begin: CallbackLogin checkbox -->
	<tr>
          <td width="40%">
              <div align="center">{$CALLBACK_LOGIN}:</div>
          </td>
          <td width="60%">               
	      <input type="checkbox" name="input_callback" id="input_callback">
          </td>
        </tr>
<!-- End: CallbackLogin checkbox -->
        <tr>
          <td colspan="2" align="center">
            <input type="button" id="submit_agent_login" name="submit_agent_login" value="{$LABEL_SUBMIT}" class="button" />
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

</form>

{if $REANUDAR_VERIFICACION}
<script type="text/javascript">
do_checklogin();
</script>
{/if}
{/if}
