<table width="100%" border="0">
<tr>
	<td class="letra12" width="10%" align="right"><b>{$LABEL_STATE}:</b></td>
    <td>{html_options name=cbo_estado id=cbo_estado options=$estados selected=$estado_sel onchange='submit();'}</td>
</tr>
</table>
<script language='JavaScript' type='text/javascript'>
var pregunta_borrar_agente_conf = "{$PREGUNTA_BORRAR_AGENTE_CONF}";
var pregunta_agregar_agente_conf = "{$PREGUNTA_AGREGAR_AGENTE_CONF}";
</script>