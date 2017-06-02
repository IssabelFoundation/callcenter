<table width="100%" border=0 class="tabForm" height="400">
  <tr>
    <td valign='top'>
		<table cellpadding="2" cellspacing="0" width="100%" border="0">
		{foreach from=$listacampos item=campo}
		<tr>
		    <td height='15' width='15%' align="right" valign="top">{$campo.LABEL}</td>
		    <td height='15' width='85%'>{$campo.INPUT}</td>
		</tr>
		{/foreach}
		</table>
    </td>
  </tr>
</table>
