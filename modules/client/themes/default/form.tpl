<script src="modules/{$MODULE_NAME}/libs/js/base.js"></script>
<table width="100%" cellpadding="1" cellspacing="1" height="100%" border=0>
{if !$FRAMEWORK_TIENE_TITULO_MODULO}
    <tr class="moduleTitle">
        <td colspan="4" class="moduleTitle" align="left">
            <img src="{$icon}" border="0" align="absmiddle" />&nbsp;&nbsp;{$title}
        </td>
    </tr>
{/if}
    <tr>
        <td>
            <form style='margin-bottom:0;' method="post" action="?menu={$MODULE_NAME}" enctype="multipart/form-data">
            <table align='left' border="0" class="filterForm" cellspacing="0" cellpadding="0" width="100%">
                <tr><td class="letra12" colspan="2"><b>{$LABEL_MESSAGE}</b></td></tr>
                <tr>
                    <td class="letra12" align="right" width="20%"><b>{$File}:</b></td>
                    <td align='left'><input name="fileCRM" type="file" /></td>
                </tr>
                <tr>
                    <td align='left' colspan="2"><input class='button' type = 'submit' name='cargar_datos' value='{$ETIQUETA_SUBMIT}' onClick="return validarFile(this.form.fileCRM.value)" /></td>
                </tr>
                <tr>
                	<td class="letra12" align='left'><b>{$Format_File}:</b></td>
                	<td class="letra12" align='left'>{$Format_Content}</td>
               	</tr>
               	<tr>
               		<td class="letra12" align='left' colspan="2"><b><a href="?menu={$MODULE_NAME}&amp;rawmode=yes&amp;action=csvdownload">{$ETIQUETA_DOWNLOAD}&nbsp;&raquo;</a></b></tr>
               	</tr>
            </table>
            </form>
        </td>
    </tr>
</table>

