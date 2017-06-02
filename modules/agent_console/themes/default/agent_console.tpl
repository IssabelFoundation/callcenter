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

{* Este DIV se usa para mostrar los mensajes de éxito *}
<div
    id="elastix-callcenter-info-message"
    class="ui-state-highlight ui-corner-all">
    <p>
        <span class="ui-icon ui-icon-info" style="float: left; margin-right: .3em;"></span>
        <span id="elastix-callcenter-info-message-text"></span>
    </p>
</div>
{* Este DIV se usa para mostrar los mensajes de error *}
<div
    id="elastix-callcenter-error-message"
    class="ui-state-error ui-corner-all">
    <p>
        <span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>
        <span id="elastix-callcenter-error-message-text"></span>
    </p>
</div>
{* Marco principal de la consola de agente *}
<div id="elastix-callcenter-area-principal">
    {* Título con nombre del módulo *}
{if !$FRAMEWORK_TIENE_TITULO_MODULO}
    <div id="elastix-callcenter-titulo-consola" class="moduleTitle">&nbsp;<img src="{$icon}" border="0" align="absmiddle" alt="" />&nbsp;{$title}</div>
{/if}
	{* Estado del agente con número y nombre del agente *}
	<div id="elastix-callcenter-estado-agente" class="{$CLASS_ESTADO_AGENTE_INICIAL}">
	    <div id="elastix-callcenter-estado-agente-texto">{$TEXTO_ESTADO_AGENTE_INICIAL}</div>
        <div id="elastix-callcenter-cronometro">{$CRONOMETRO}</div>{* elastix-callcenter-cronometro *}
    </div>{* elastix-callcenter-estado-agente *}
    <div id="elastix-callcenter-wrap">
	    {* Los controles que aparecen en la parte superior de la interfaz *}
	    <div id="elastix-callcenter-controles">
	        <button id="btn_hangup" class="elastix-callcenter-boton-activo">{$BTN_COLGAR_LLAMADA}</button>
	        <button id="btn_togglebreak" class="{$CLASS_BOTON_BREAK}" >{$BTN_BREAK}</button>
	        <button id="btn_transfer" class="elastix-callcenter-boton-activo" >{$BTN_TRANSFER}</button>
            <button id="btn_agendar_llamada" {if $CALLINFO_CALLTYPE != 'outgoing'}disabled="disabled"{/if}>{$BTN_AGENDAR_LLAMADA}</button>
	        <button id="btn_guardar_formularios">{$BTN_GUARDAR_FORMULARIOS}</button>
{if $BTN_VTIGERCRM}
	        <button id="btn_vtigercrm" class="elastix-callcenter-boton-activo">{$BTN_VTIGERCRM}</button>
{/if}
	        <button id="btn_logout" class="elastix-callcenter-boton-activo">{$BTN_FINALIZAR_LOGIN}</button>
	    </div> {* elastix-callcenter-controles *}
	    {* El panel que aparece a la derecha como área principal del módulo *}
	    <div id="elastix-callcenter-contenido">
			{* Definición de las cejillas de información/script/formulario *}
			<div id="elastix-callcenter-cejillas-contenido">
			   <ul>
                   <li><a href="#elastix-callcenter-llamada-paneles">{$TAB_LLAMADA}</a></li>
                   {foreach from=$CUSTOM_PANELS item=HTML_PANEL}
                   <li><a href="#tabs-{$HTML_PANEL.panelname}">{$HTML_PANEL.title}</a></li>
                   {/foreach}
			   </ul>
                <div id="elastix-callcenter-llamada-paneles">
                    <div id="elastix-callcenter-llamada-paneles-izq" class="ui-layout-west">
                        <div class="ui-layout-center"><fieldset class="ui-widget-content ui-corner-all"><legend><b>{$TAB_LLAMADA_INFO}</b></legend><div id="elastix-callcenter-llamada-info">{$CONTENIDO_LLAMADA_INFORMACION}</div></fieldset></div>
                        <div class="ui-layout-south"><fieldset class="ui-widget-content ui-corner-all"><legend><b>{$TAB_LLAMADA_SCRIPT}</b></legend><div id="elastix-callcenter-llamada-script">{$CONTENIDO_LLAMADA_SCRIPT}</div></fieldset></div>
                    </div>
                    <div class="ui-layout-center"><fieldset class="ui-widget-content ui-corner-all"><legend><b>{$TAB_LLAMADA_FORM}</b></legend><div id="elastix-callcenter-llamada-form">{$CONTENIDO_LLAMADA_FORMULARIO}</div></fieldset></div>
                </div>
                {foreach from=$CUSTOM_PANELS item=HTML_PANEL}
                <div id="tabs-{$HTML_PANEL.panelname}">
                    {$HTML_PANEL.content}
                </div>
                {/foreach}
			</div>{* elastix-callcenter-cejillas-contenido *}
		</div>{* elastix-callcenter-contenido *}
	</div>
</div>{* elastix-callcenter-area-principal *}
<div id="elastix-callcenter-seleccion-break" title="{$TITLE_BREAK_DIALOG}">
    <form>
        <select
            name="break_select"
            id="break_select"
            class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only"
            style="width: 100%">{html_options options=$LISTA_BREAKS}
        </select>
    </form>
</div>{* elastix-callcenter-seleccion-break *}
<div id="elastix-callcenter-seleccion-transfer" title="{$TITLE_TRANSFER_DIALOG}">
    <form>
        <table border="0" cellpadding="0" style="width: 100%;">
            <tr>
                <td><input
                name="transfer_extension"
                id="transfer_extension"
                class="ui-widget-content ui-corner-all"
                style="width: 100%" /></td>
            </tr>
            <tr>
                <td>
                    <div align="center" id="transfer_type_radio">
                        <input type="radio" id="transfer_type_blind" name="transfer_type" value="blind" checked="checked"/><label for="transfer_type_blind">{$LBL_TRANSFER_BLIND}</label>
                        <input type="radio" id="transfer_type_attended" name="transfer_type" value="attended" /><label for="transfer_type_attended">{$LBL_TRANSFER_ATTENDED}</label>
                    </div>
                </td>
            </tr>
        </table>
    </form>
</div>{* elastix-callcenter-seleccion-transfer *}
<div id="elastix-callcenter-agendar-llamada" title="{$TITLE_SCHEDULE_CALL}">
	<div
	    id="elastix-callcenter-agendar-llamada-error-message"
	    class="ui-state-error ui-corner-all">
	    <p>
	        <span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>
	        <span id="elastix-callcenter-agendar-llamada-error-message-text"></span>
	    </p>
	</div>
    <form>
        <table border="0" cellpadding="0" style="width: 100%;">
            <tr>
                <td><label style="display: table-cell;" for="schedule_new_phone"><b>{$LBL_CONTACTO_TELEFONO}:&nbsp;</b></label></td>
                <td><input
                    name="schedule_new_phone"
                    id="schedule_new_phone"
                    class="ui-widget-content ui-corner-all"
                    maxlength="64"
                    style="display: table-cell; width: 100%;"
                    value="{$TEXTO_CONTACTO_TELEFONO|escape:"html"}" /></td>
            </tr>
            <tr>
                <td><label style="display: table-cell;" for="schedule_new_name"><b>{$LBL_CONTACTO_NOMBRES}:&nbsp;</b></label></td>
                <td><input
                    name="schedule_new_name"
                    id="schedule_new_name"
                    class="ui-widget-content ui-corner-all"
                    maxlength="250"
                    style="display: table-cell; width: 100%;"
                    value="{$TEXTO_CONTACTO_NOMBRES|escape:"html"}" /></td>
            </tr>
        </table>
        <hr />
        <div align="center" id="schedule_radio" style="width: 100%">
            <input type="radio" id="schedule_type_campaign_end" name="schedule_type" value="campaign_end" checked="checked"/><label for="schedule_type_campaign_end">{$LBL_SCHEDULE_CAMPAIGN_END}</label>
            <input type="radio" id="schedule_type_bydate" name="schedule_type" value="bydate" /><label for="schedule_type_bydate">{$LBL_SCHEDULE_BYDATE}</label>
        </div>
        <br/>
        <table id="schedule_date" border="0" cellpadding="0" style="width: 100%;">
            <tr>
                <td><label for="schedule_date_start"><b>{$LBL_SCHEDULE_DATE_START}:&nbsp;</b></label></td>
                <td><input type="text" class="ui-widget-content ui-corner-all" name="schedule_date_start" id="schedule_date_start" /></td>
                <td><label for="schedule_date_end"><b>{$LBL_SCHEDULE_DATE_END}:&nbsp;</b></label></td>
                <td><input type="text" class="ui-widget-content ui-corner-all" name="schedule_date_end" id="schedule_date_end" /></td>
            </tr>
            <tr>
                <td><label><b>{$LBL_SCHEDULE_TIME_START}:&nbsp;</b></label></td>
                <td><select
                        name="schedule_time_start_hh"
                        id="schedule_time_start_hh"
                        class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only">{html_options options=$SCHEDULE_TIME_HH}
                    </select>:<select
                        name="schedule_time_start_mm"
                        id="schedule_time_start_mm"
                        class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only">{html_options options=$SCHEDULE_TIME_MM}
                    </select></td>
                <td><label><b>{$LBL_SCHEDULE_TIME_END}:&nbsp;</b></label></td>
                <td><select
                        name="schedule_time_end_hh"
                        id="schedule_time_end_hh"
                        class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only">{html_options options=$SCHEDULE_TIME_HH}
                    </select>:<select
                        name="schedule_time_end_mm"
                        id="schedule_time_end_mm"
                        class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only">{html_options options=$SCHEDULE_TIME_MM}
                    </select></td>
            </tr>
            <tr>
                <td colspan="4"><input type="checkbox" id="schedule_same_agent" name="schedule_same_agent" /><label for="schedule_same_agent">{$LBL_SCHEDULE_SAME_AGENT}</label></td>
            </tr>
        </table>
    </form>
</div>
{literal}
<script type="text/javascript">
// Aplicar temas de jQueryUI a diversos elementos
$(document).ready(function() {
{/literal}
    apply_ui_styles({$APPLY_UI_STYLES});
    initialize_client_state({$INITIAL_CLIENT_STATE});
{literal}
});
</script>
{/literal}
