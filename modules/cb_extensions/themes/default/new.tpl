<form method="post">
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
          {if $mode eq 'input'}
          <input class="button" type="submit" name="submit_save_agent" value="{$SAVE}" />
          <input class="button" type="submit" name="cancel" value="{$CANCEL}"/>
          {elseif $mode eq 'edit'}
          <input class="button" type="submit" name="submit_apply_changes" value="{$APPLY_CHANGES}" />
          <input class="button" type="submit" name="cancel" value="{$CANCEL}"/>
          {else}
          <input class="button" type="submit" name="edit" value="{$EDIT}"/>
          <input class="button" type="submit" name="delete" value="{$DELETE}"  onClick="return confirmSubmit('{$CONFIRM_CONTINUE}')" />
          <input class="button" type="submit" name="cancel" value="{$CANCEL}"/>
          {/if}
        </td>
        <td align="right" nowrap><span  class="required">*</span> <span class="letra12">{$REQUIRED_FIELD}</span></td>
     </tr>
   </table>
  </td>
</tr>
<tr>
  <td>
    <table width="100%" border="0" cellspacing="0" cellpadding="0" class="tabForm">
      <tr>
    <td width="20%">{$extension.LABEL}: <span class="required">*</span></td>
    <td width="30%">{$extension.INPUT}</td>
	<td width="15%">{$description.LABEL}: <span  class="required">*</span></td>
	<td width="35%">{$description.INPUT}</td>
      </tr>
{if $mode ne 'view'}
      <tr>
	<td width="20%">{$password1.LABEL}: <span  class="required">*</span></td>
	<td width="30%">{$password1.INPUT}</td>
	<td width="20%">{$password2.LABEL}: <span class="required">*</span></td>
	<td width="30%">{$password2.INPUT}</td>
      </tr>
      <tr>
    <td width="20%">{$eccpwd1.LABEL}:</td>
    <td width="30%">{$eccpwd1.INPUT}</td>
    <td width="20%">{$eccpwd2.LABEL}:</td>
    <td width="30%">{$eccpwd2.INPUT}</td>
      </tr>
{/if}
    </table>
  </td>
</tr>
</table>
<input type="hidden" name="id_agent" value="{$id_agent}" />
</form>




