<script language="JavaScript" type="text/javascript" src="{$relative_dir_rich_text}/richtext/html2xhtml.js"></script>
<script language="JavaScript" type="text/javascript" src="{$relative_dir_rich_text}/richtext/richtext_compressed.js"></script>
<script language="JavaScript" type="text/javascript">
//Usage: initRTE(imagesPath, includesPath, cssFile, genXHTML, encHTML)
initRTE("./{$relative_dir_rich_text}/richtext/images/", "./{$relative_dir_rich_text}/richtext/", "", true);
var rte_script = new richTextEditor('rte_script');
</script>
<form method="POST" enctype="multipart/form-data" onsubmit="return submitForm();">
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
                <input class="button" type="submit" name="cancel" value="&laquo; {$CANCEL}" />
                <input class="button" type="submit" name="save"   value="{$SAVE}" />
            </td>
        </tr>
        </table>
    </td>
</tr>

<tr>
    <td>
        <table width="100%" cellspacing="0" cellpadding="0" class="tabForm">
        <tr>
            <td>{$select_queue.LABEL}<span  class="required">*</span></td>
            <td>{$select_queue.INPUT}</td>
        </tr>        
        <tr>
            <td>{$script.LABEL}: <span  class="required">*</span></td>
            <td> 
                {if $mode eq 'edit' or $mode eq 'input'}
                <script language="JavaScript" type="text/javascript">
                    rte_script.html ="{$rte_script}";
                    rte_script.toggleSrc = false;
                    rte_script.build();
                </script>
                {else}
                    {$script.INPUT}
                {/if} 
            </td>
        </tr>
        </table>
        
    </td>
</tr>
</table>
<input type="hidden" name="id_queue" id='id_queue' value="{$id_queue}" />
<input type="hidden" name="queue"    id='queue'    value="{$queue}"    />
<input type="hidden" name="estado" id='estado' value="{$estatus_cbo_estado}" />
</form>

{literal}
<script type="text/javascript">
function submitForm() {	
	updateRTEs();	
	return true;
}
</script>
{/literal}
