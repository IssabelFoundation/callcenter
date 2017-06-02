{if !$FRAMEWORK_TIENE_TITULO_MODULO}
    <table width="100%">
    <tr class="moduleTitle">
            <td class="moduleTitle" valign="middle">&nbsp;&nbsp;<img src="{$icon}" border="0" align="absmiddle" />&nbsp;&nbsp;{$title} 
            </td>
    </tr>
    </table>
{/if}    
<table width="100%" border="0" cellspacing="0" cellpadding="0" class="tabForm">
    <tr>
        <td width="20%">{$form_nombre.LABEL}: <span  class="required" {$style_field}>*</span></td>
        <td width="80%">{$form_nombre.INPUT}</td>
    </tr>
    <tr>
        <td width="20%">{$form_description.LABEL}:</td>
        <td width="80%">{$form_description.INPUT}</td>
    </tr>
</table>
<div style='padding:5px'>
    <fieldset >
        {$formulario}
    </fieldset>
</div>

