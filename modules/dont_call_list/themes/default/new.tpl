<form method="POST" name="form_formulario" enctype="multipart/form-data">
    <table width="99%" border="0" cellspacing="0" cellpadding="0" align="center">
        <tr>
            <td>
                <table width="100%" cellpadding="3" cellspacing="0" border="0">
                    <tr>
                        <td align="left">
                        <input class="button" type="submit" name="apply_changes" value="{$SAVE}" />
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
                        <td colspan="2">{$new_accion.INPUT}</td>
                    </tr>
                    <tr>
                        <td>{$file_number.LABEL}:<br/>{$LABEL_MAX_FILESIZE}</td>
                        <td>{$file_number.INPUT}</td>
                    </tr>
                    <tr>
                        <td>{$txt_new_number.LABEL}:</td>
                        <td>{$txt_new_number.INPUT}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    {$id_formulario.INPUT}
</form>
{literal}
<script type="text/javascript">
function new_accion_update(accion)
{
    if (accion == 'file') {
        $('input[name="txt_new_number"]').parents('tr:first').hide();
        $('input[name="file_number"]').parents('tr:first').show();
    }
    if (accion == 'text') {
        $('input[name="file_number"]').parents('tr:first').hide();
        $('input[name="txt_new_number"]').parents('tr:first').show();
    }
}

$(document).ready(function() {
	$('input[name="new_accion"]').on('change', function () {
		new_accion_update($('input[name="new_accion"]:checked').val());
	});
	
	new_accion_update($('input[name="new_accion"]:checked').val());
});
</script>
{/literal}