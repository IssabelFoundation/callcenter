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
        <td align="left">
          <input class="button" type="submit" name="save" value="{$SAVE}" />
          <input class="button" type="submit" name="cancel" value="{$CANCEL}" />
        </td>
        <td align="right" nowrap><span class="letra12"><span  class="required">*</span> {$REQUIRED_FIELD}</span></td>
     </tr>
   </table>
  </td>
</tr>
<tr>
  <td>
    {$uploader.LABEL}: {$uploader.INPUT}<br/>
    <fieldset>
        <legend>{$LBL_CAMPAIGN}</legend>
        <table width="900" valign="top" border="0" cellspacing="0" cellpadding="0" class="tabForm">
        <tr>
          <td align='right'>{$list_name.LABEL}: {if $mode eq 'input'}<span  class="required">*</span>{/if}</td>
          <td  colspan='4'>{$list_name.INPUT}</td>
        </tr>
        <tr>
          <td align='right'>{$id_campaign.LABEL}: {if $mode eq 'input'}<span  class="required">*</span>{/if}</td>
          <td  colspan='4'>{$id_campaign.INPUT}</td>
        </tr>
      </table>
    </fieldset>
    <fieldset>
        <legend>{$LBL_OPTIONS_UPLOADER}</legend>
        {$CONTENT_UPLOADER}
    </fieldset>
  </td>
</tr>
</table>
</form>
