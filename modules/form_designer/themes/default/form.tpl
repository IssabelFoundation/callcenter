<!-- end of Message board -->
<form method="POST" name="form_formulario">
    <table width="99%" border="0" cellspacing="0" cellpadding="0" align="center">
        <tr>
            <td>
                <table width="100%" cellpadding="3" cellspacing="0" border="0">
                    <tr>
                        <td align="left">
                        <input class="button" type="button" name="apply_changes" value="{$SAVE}" />
                        <input class="button" type="submit" name="cancel" value="{$CANCEL}" />
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td>
                <table width="100%" border="0" cellspacing="0" cellpadding="0" class="tabForm">
                    <tr>
                        <td align="right" valign="top">{$form_nombre.LABEL}: <span  class="required" {$style_field}>*</span></td>
                        <td valign="top">{$form_nombre.INPUT}</td>
                    </tr>
                    <tr>
	                    <td align="right" valign="top">{$form_description.LABEL}:</td>
	                    <td valign="top">{$form_description.INPUT}</td>
                    </tr>
                    <tr><td colspan="2">
<table class="formfield_list" border='0' cellspacing='0' cellpadding='0' width='100%' align='center'>
<thead>
<tr>
    <td width="50">{$LABEL_ORDER|escape:html}</td>
    <td>{$LABEL_NAME|escape:html}</td>
    <td>{$LABEL_TYPE|escape:html}</td>
    <td>{$LABEL_ENUMVAL|escape:html}</td>
    <td width="40">&nbsp;</td>
</tr>
</thead>
<tbody id="tbody_fieldlist">
<tr title="{$TOOLTIP_DRAGDROP}">
    <td valign="top"><span class="formfield_order">?</span><input type="hidden" name="formfield_id" value="" /></td>
    <td valign="top" class='formfield_name'><input type="text" name="formfield_name" value="(no name)" placeholder="{$LABEL_NEWFIELD|escape:html}" /></td>
    <td valign="top" class='formfield_type'><select>{$CMB_TIPO}</select></td>
    <td valign="top" class='formfield_enumval'>
        <span class="formfield_enumval_wrap">
            <span class="formfield_enumval_passive"></span>
            <div class="formfield_enumval_active">
                <table width="100%" border="0" cellspacing="0" cellpadding="0">
                    <tr>
                        <td rowspan='2' valign="top" width="50"><input type="text" name="formfield_enumlist_newitem" id='formfield_enumlist_newitem' value="" /></td>
                        <td valign="top" width="40"><input class="button" type="button" name="formfield_additem" value=">>"/></td>
                        <td rowspan='2' valign="top">
                            <select name="formfield_enumlist_items" size="4" class="formfield_enumlist_items" style="width:120px"></select>
                        </td>
                    </tr>
                    <tr>
                        <td width="40"><input class="button" type="button" name="formfield_delitem" value="<<" /></td>
                    </tr>
                </table>
            </div>
        </span>
    </td>
    <td class='formfield_order'><input class="button" type="button" name="formfield_add" value="{$LABEL_FFADD|escape:html}" /><input class="button" type="button" name="formfield_del" value="{$LABEL_FFDEL|escape:html}" /></td>
</tr>
</tbody>
</table>            
                    </td></tr>
                </table>
            </td>
        </tr>
    </table>
    {$id_formulario.INPUT}
</form>
<script type="text/javascript">
CAMPOS_FORM = {$CAMPOS_FORM};
</script>