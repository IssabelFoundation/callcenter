{* Este DIV se usa para mostrar los mensajes de error *}
<div
    id="elastix-callcenter-error-message"
    class="ui-state-error ui-corner-all">
    <p>
        <span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>
        <span id="elastix-callcenter-error-message-text"></span>
    </p>
</div>
<div id="campaignMonitoringApplication">
<script type="text/x-handlebars" data-template-name="campaign">

<b>{$ETIQUETA_CAMPANIA}:</b>
{literal}
{{view Ember.Select
            contentBinding="content"
            optionValuePath="content.key_campaign"
            optionLabelPath="content.desc_campaign"
            valueBinding="key_campaign" }}
{/literal}

{literal}{{outlet}}{/literal}

</script>


<script type="text/x-handlebars" data-template-name="campaign/details">
{* Atributos de la campaña elegida *}
<table width="100%" >
    <tr>
        <td><b>{$ETIQUETA_FECHA_INICIO}:</b></td>
        <td>{literal}{{fechaInicio}}{/literal}</td>
        <td><b>{$ETIQUETA_FECHA_FINAL}:</b></td>
        <td>{literal}{{fechaFinal}}{/literal}</td>
        <td><b>{$ETIQUETA_HORARIO}:</b></td>
        <td>{literal}{{horaInicio}} - {{horaFinal}}{/literal}</td>
    </tr>
    <tr>
        <td><b>{$ETIQUETA_COLA}:</b></td>
        <td>{literal}{{cola}}{/literal}</td>
        <td><b>{$ETIQUETA_INTENTOS}:</b></td>
        <td>{literal}{{maxIntentos}}{/literal}</td>
        <td></td>
        <td>&nbsp;</td>
    </tr>
</table>

{* Contadores de la campaña elegida *}
<table width="100%" >
    <tr>
        <td><b>{$ETIQUETA_TOTAL_LLAMADAS}:</b></td>
        <td>{literal}{{llamadas.total}}{/literal}</td>
        <td><b>{$ETIQUETA_LLAMADAS_COLA}:</b></td>
        <td>{literal}{{llamadas.encola}}{/literal}</td>
        <td><b>{$ETIQUETA_LLAMADAS_EXITO}:</b></td>
        <td>{literal}{{llamadas.conectadas}}{/literal}</td>
    </tr>
    {literal}{{#if outgoing }}{/literal}
    <tr>
        <td><b>{$ETIQUETA_LLAMADAS_PENDIENTES}:</b></td>
        <td>{literal}{{llamadas.pendientes}}{/literal}</td>
        <td><b>{$ETIQUETA_LLAMADAS_MARCANDO}:</b></td>
        <td>{literal}{{llamadas.marcando}}{/literal}</td>
        <td><b>{$ETIQUETA_LLAMADAS_TIMBRANDO}:</b></td>
        <td>{literal}{{llamadas.timbrando}}{/literal}</td>
    </tr>
    <tr>
        <td><b>{$ETIQUETA_LLAMADAS_FALLIDAS}:</b></td>
        <td>{literal}{{llamadas.fallidas}}{/literal}</td>
        <td><b>{$ETIQUETA_LLAMADAS_NOCONTESTA}:</b></td>
        <td>{literal}{{llamadas.nocontesta}}{/literal}</td>
        <td><b>{$ETIQUETA_LLAMADAS_ABANDONADAS}:</b></td>
        <td>{literal}{{llamadas.abandonadas}}{/literal}</td>
    </tr>
    <tr>
        <td><b>{$ETIQUETA_LLAMADAS_CORTAS}:</b></td>
        <td>{literal}{{llamadas.cortas}}{/literal}</td>
        <td colspan="4">&nbsp;</td>
    </tr>
    {literal}{{else}}{/literal}
    <tr>
        <td><b>{$ETIQUETA_LLAMADAS_SINRASTRO}:</b></td>
        <td>{literal}{{llamadas.sinrastro}}{/literal}</td>
        <td><b>{$ETIQUETA_LLAMADAS_ABANDONADAS}:</b></td>
        <td>{literal}{{llamadas.abandonadas}}{/literal}</td>
        <td><b>{$ETIQUETA_LLAMADAS_TERMINADAS}:</b></td>
        <td>{literal}{{llamadas.terminadas}}{/literal}</td>
    </tr>
    {literal}{{/if}}{/literal}
    <tr>
        <td><b>{$ETIQUETA_PROMEDIO_DURAC_LLAM}:</b></td>
        <td>{literal}{{llamadas.fmtpromedio}}{/literal}</td>
        <td><b>{$ETIQUETA_MAX_DURAC_LLAM}:</b></td>
        <td>{literal}{{llamadas.fmtmaxduration}}{/literal}</td>
    </tr>
</table>

{* Listado de llamadas y de agentes *}
<table width="100%" ><tr>
    <td width="50%" style="vertical-align: top;">
        <b>{$ETIQUETA_LLAMADAS_MARCANDO}:</b>
        <table class="titulo">
            <tr>
                <td width="20%" nowrap="nowrap">{$ETIQUETA_ESTADO}</td>
                <td width="30%" nowrap="nowrap">{$ETIQUETA_NUMERO_TELEFONO}</td>
                <td width="30%" nowrap="nowrap">{$ETIQUETA_TRONCAL}</td>
                <td width="20%" nowrap="nowrap">{$ETIQUETA_DESDE}</td>
            </tr>
        </table>
        <div class="llamadas" {literal}{{bindAttr style="alturaLlamada"}}{/literal}>
            <table>
                {literal}{{#view tagName="tbody"}}
                {{#each llamadasMarcando}}
                <tr {{bindAttr class="reciente"}}>
                    <td width="20%" nowrap="nowrap">{{estado}}</td>
                    <td width="30%" nowrap="nowrap">{{numero}}</td>
                    <td width="30%" nowrap="nowrap">{{troncal}}</td>
                    <td width="20%" nowrap="nowrap">{{desde}}</td>
                </tr>
                {{/each}}
                {{/view}}{/literal}
            </table>
        </div>
    </td>
    <td width="50%" style="vertical-align: top;">
        <b>{$ETIQUETA_AGENTES}:</b>
        <table class="titulo">
            <tr>
                <td width="20%" nowrap="nowrap">{$ETIQUETA_AGENTE}</td>
                <td width="14%" nowrap="nowrap">{$ETIQUETA_ESTADO}</td>
                <td width="23%" nowrap="nowrap">{$ETIQUETA_NUMERO_TELEFONO}</td>
                <td width="23%" nowrap="nowrap">{$ETIQUETA_TRONCAL}</td>
                <td width="20%" nowrap="nowrap">{$ETIQUETA_DESDE}</td>
            </tr>
        </table>
        <div class="llamadas" {literal}{{bindAttr style="alturaLlamada"}}{/literal}>
            <table>
                {literal}{{#view tagName="tbody"}}
                {{#each agentes}}
                <tr {{bindAttr class="reciente"}}>
                    <td width="20%" nowrap="nowrap">{{canal}}</td>
                    <td width="14%" nowrap="nowrap">{{estado}}</td>
                    <td width="23%" nowrap="nowrap">{{numero}}</td>
                    <td width="23%" nowrap="nowrap">{{troncal}}</td>
                    <td width="20%" nowrap="nowrap">{{desde}}</td>
                </tr>
                {{/each}}
                {{/view}}{/literal}
            </table>
        </div>
    </td>
</tr></table>

{* Registro de actividad de la campaña *}
{literal}{{view Ember.Checkbox checkedBinding="registroVisible"}}{/literal}
<b>{$ETIQUETA_REGISTRO}: </b><br/>
{literal}{{#if registroVisible}}
<button class="button" {{action "cargarprevios" }}>{/literal}{$PREVIOUS_N}{literal}</button>
{{#view App.RegistroView class="registro" }}
<table>
    {{#each registro}}
    <tr>
        <td>{{timestamp}}</td>
        <td>{{mensaje}}</td>
    </tr>
    {{/each}}
</table>
{{/view}}
{{/if}}{/literal}
</script>
</div>
