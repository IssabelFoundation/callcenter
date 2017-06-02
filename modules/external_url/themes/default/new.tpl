<form method="post" enctype="multipart/form-data">
<table width="99%" border="0" cellspacing="0" cellpadding="0" align="center">
{if !$FRAMEWORK_TIENE_TITULO_MODULO}
<tr class="moduleTitle">
  <td class="moduleTitle" valign="middle">&nbsp;&nbsp;<img src="{$icon}" border="0" align="absmiddle" />&nbsp;&nbsp;{$title}</td>
</tr>
{/if}
<tr>
  <td>
    <table width="100%" valign="top" cellpadding="4" cellspacing="0" border="0">
      <tr>
          {if $mode eq 'input'}
        <td align="left">
          <input class="button" type="submit" name="save" value="{$SAVE}" />
          <input class="button" type="submit" name="cancel" value="{$CANCEL}" />
        </td>
        <td align="right" nowrap><span class="letra12"><span  class="required">*</span> {$REQUIRED_FIELD}</span></td>
          {elseif $mode eq 'edit'}
        <td align="left">
          <input class="button" type="submit" name="apply_changes" value="{$APPLY_CHANGES}" />
          <input class="button" type="submit" name="cancel" value="{$CANCEL}" />
        </td>
          {/if}          
     </tr>
   </table>
  </td>
</tr>
<tr>
  <td>
    <table valign="top" border="0" cellspacing="0" cellpadding="0" class="tabForm">
      <tr>
          <td align='right'>{$urltemplate.LABEL}: <span  class="required">*</span></td>
          <td>{$urltemplate.INPUT}</td>
      </tr>
      <tr>
          <td align='right'>{$description.LABEL}: <span  class="required">*</span></td>
          <td>{$description.INPUT}</td>
      </tr>
      {if $id_url}
      <tr>
          <td align='right'>{$active.LABEL}: <span  class="required">*</span></td>
          <td>{$active.INPUT}</td>
      </tr>
      {/if}
      <tr>
          <td align='right'>{$opentype.LABEL}: <span  class="required">*</span></td>
          <td>{$opentype.INPUT}</td>
      </tr>
      </table>
    </td>
  </tr>
</table>
<input type="hidden" name="id_url" id='id_url' value="{$id_url}" />
</form>
