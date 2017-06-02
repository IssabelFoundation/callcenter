<table width="99%" border="0" cellspacing="0" cellpadding="4" align="center">
{if !$FRAMEWORK_TIENE_TITULO_MODULO}
    <tr class="moduleTitle">
        <td class="moduleTitle" valign="middle">&nbsp;&nbsp;<img src="{$icon}" border="0" align="absmiddle" />&nbsp;&nbsp;{$title}</td>
        <td></td>
    </tr>
{/if}    
    <tr class="letra12">
        <td align="left"><input class="button" type="submit" name="save" value="{$SAVE}"></td>
        <td align="right" nowrap><span class="letra12"><span  class="required">*</span> {$REQUIRED_FIELD}</span></td>
    </tr>
</table>
<table class="tabForm" width="100%"><tr>
<td valign="top">
<table>
<tr class="letra12"><td><b>{$ASTERISK_CONNECT_PARAM}</b></td></tr>
<tr class="letra12"><td>{$asterisk_asthost.LABEL}:</td></tr>
<tr class="letra12"><td>{$asterisk_asthost.INPUT}</td></tr>
<tr class="letra12"><td>{$asterisk_astuser.LABEL}:</td></tr>
<tr class="letra12"><td>{$asterisk_astuser.INPUT}</td></tr>
<tr class="letra12"><td>{$asterisk_astpass_1.LABEL}:</td></tr>
<tr class="letra12"><td>{$asterisk_astpass_1.INPUT}</td></tr>
<tr class="letra12"><td>{$asterisk_astpass_2.LABEL}:</td></tr>
<tr class="letra12"><td>{$asterisk_astpass_2.INPUT}</td></tr>
<tr class="letra12"><td>{$asterisk_duracion_sesion.LABEL}:</td></tr>
<tr class="letra12"><td>{$asterisk_duracion_sesion.INPUT}</td></tr>
</table>
</td>
<td valign="top">
<table>
<tr class="letra12"><td><b>{$DIALER_PARAM}</b></td></tr>
<tr class="letra12"><td>{$dialer_llamada_corta.LABEL}:</td></tr>
<tr class="letra12"><td>{$dialer_llamada_corta.INPUT}</td></tr>
<tr class="letra12"><td>{$dialer_tiempo_contestar.LABEL}:</td></tr>
<tr class="letra12"><td>{$dialer_tiempo_contestar.INPUT}</td></tr>
<tr class="letra12"><td>{$dialer_qos.LABEL}:</td></tr>
<tr class="letra12"><td>{$dialer_qos.INPUT}</td></tr>
<tr class="letra12"><td>{$dialer_timeout_originate.LABEL}:</td></tr>
<tr class="letra12"><td>{$dialer_timeout_originate.INPUT}</td></tr>
<tr class="letra12"><td>{$dialer_timeout_inactivity.LABEL}:</td></tr>
<tr class="letra12"><td>{$dialer_timeout_inactivity.INPUT}</td></tr>
<tr class="letra12"><td>{$dialer_debug.INPUT} {$dialer_debug.LABEL}</td></tr>
<tr class="letra12"><td>{$dialer_allevents.INPUT} {$dialer_allevents.LABEL}</td></tr>
<tr class="letra12"><td>{$dialer_overcommit.INPUT} {$dialer_overcommit.LABEL}</td></tr>
<tr class="letra12"><td>{$dialer_predictivo.INPUT} {$dialer_predictivo.LABEL}</td></tr>
</table>
</td>
<td valign="top">
<table>
<tr class="letra12"><td><b>{$DIALER_STATUS_MESG}</b></td></tr>
<tr class="letra12"><td>{$CURRENT_STATUS}: <b>{$DIALER_STATUS}</b></td></tr>
<tr class="letra12"><td><input class="button" type="submit" name="dialer_action" value="{$DIALER_ACTION}"/></td></tr>
</table>
</td>
</tr></table>