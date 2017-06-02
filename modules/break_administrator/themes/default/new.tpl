<form method="POST" action="?menu={$MODULE_NAME}&amp;action={$ACTION}">
<table width="99%" border="0" cellspacing="0" cellpadding="0" align="center">
{if !$FRAMEWORK_TIENE_TITULO_MODULO}
<tr class="moduleTitle">
  <td class="moduleTitle" valign="middle">&nbsp;&nbsp;<img src="{$icon}" border="0" align="absmiddle" />&nbsp;&nbsp;{$title}</td>
</tr>
{/if}
<tr>
  <td>
    <table width="100%" cellpadding="4" cellspacing="0" border="0">
      <tr>
        <td align="left">
            <input class="button" type="submit" name="cancel" value="&laquo;&nbsp;{$CANCEL}" />
            <input class="button" type="submit" name="save" value="{$SAVE}" />
        </td>
     </tr>
   </table>
  </td>
</tr>
<tr>
  <td>
    <table width="100%" border="0" cellspacing="0" cellpadding="0" class="tabForm">
        <tr>
		<td width="20%">{$nombre.LABEL}: <span  class="required">*</span></td>
		<td width="80%">{$nombre.INPUT}</td>
        </tr>
        <tr>
		<td width="20%">{$descripcion.LABEL}: <span  class="required">*</span></td>
		<td width="80%">{$descripcion.INPUT}</td>
        </tr> 
      </table>
    </td>
  </tr>
</table>
{$id_break.INPUT}
</form>
