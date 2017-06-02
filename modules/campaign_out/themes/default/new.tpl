{if $no_queues}
<p><b>No queues have been defined</b></p>
<p>For an outgoing campaign to be created, it is necessary to configure at least one queue. You can add queues <a href="?menu=pbxconfig&amp;display=queues">here</a>.</p>
{elseif $no_outgoing_queues }
<p><b>No remaining queues for outgoing campaings</b></p>
<p>All queues are currently reserved for incoming campaigns. For an outgoing campaign to be created, it is necessary to have at least one free queue. You can add queues <a href="?menu=pbxconfig&amp;display=queues">here</a>.</p>
{elseif $no_forms }
<p><b>No active forms available</b></p>
<p>For an outgoing campaign to be created, it is necessary to have at least one active form. You can add forms <a href="?menu=form_designer">here</a>.</p>
{else}
<script language="JavaScript" type="text/javascript" src="{$relative_dir_rich_text}/richtext/html2xhtml.js"></script>
<script language="JavaScript" type="text/javascript" src="{$relative_dir_rich_text}/richtext/richtext_compressed.js"></script>
<script language="JavaScript" type="text/javascript">
//Usage: initRTE(imagesPath, includesPath, cssFile, genXHTML, encHTML)
initRTE("./{$relative_dir_rich_text}/richtext/images/", "./{$relative_dir_rich_text}/richtext/", "", true);
var rte_script = new richTextEditor('rte_script');
</script>

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
          <input class="button" type="submit" name="save" value="{$SAVE}" onclick="return enviar_datos();" />
          <input class="button" type="submit" name="cancel" value="{$CANCEL}" />
        </td>
        <td align="right" nowrap><span class="letra12"><span  class="required">*</span> {$REQUIRED_FIELD}</span></td>
          {elseif $mode eq 'edit'}
        <td align="left">
          <input class="button" type="submit" name="apply_changes" value="{$APPLY_CHANGES}" onclick="return enviar_datos();" />
          <input class="button" type="submit" name="cancel" value="{$CANCEL}" />
        </td>
          {else}
{* Removido para eliminar xajax *}
          {/if}
     </tr>
   </table>
  </td>
</tr>
<tr>
  <td>
    <table width="900" valign="top" border="0" cellspacing="0" cellpadding="0" class="tabForm">
      <tr height='50'>
          <td width="20%" align='right'>{$nombre.LABEL}: <span  class="required">*</span></td>
          <td colspan='2'>{$nombre.INPUT}</td>
      </tr>
      <tr>
          <td align='right'>{$fecha_str.LABEL}: <span  class="required">*</span></td>
          <td width="25%">{$fecha_ini.INPUT}&nbsp;{$fecha_ini.LABEL}</td>
          <td>{$fecha_fin.INPUT}&nbsp;{$fecha_fin.LABEL}</td>
      </tr>
      <tr height='10'>
          <td align='right' colspan='3'></td>
      </tr>
      <tr height='30'>
          <td align='right'>{$hora_str.LABEL}: <span  class="required">*</span></td>
          <td align='left' colspan='2'>{$hora_ini_HH.INPUT}&nbsp;:&nbsp;{$hora_ini_MM.INPUT}&nbsp;{$hora_ini_HH.LABEL}</td>
      </tr>
      <tr height='30'>
          <td>&nbsp;</td>
          <td align='left' colspan='2'>{$hora_fin_HH.INPUT}&nbsp;:&nbsp;{$hora_fin_MM.INPUT}&nbsp;{$hora_fin_HH.LABEL}</td>
      </tr>
      <tr height='10'>
          <td align='left' colspan='3'></td>
      </tr>
      <tr>
		<td align='right' valign='top'>
			{$formulario.LABEL}: <span  class="required">*</span>
			<br><br>
			<a href="?menu=form_designer">
			<b>{$label_manage_forms}</b>
			</a><br><br><hr>
		</td>
          <td  colspan='2'>
           {if $mode eq 'edit' or $mode eq 'input'}
                <table border='0' cellpadding='0' cellspacing='0'>
                    <tr>
                        <td rowspan='2'>{$formulario.INPUT}</td>
                        <td><input type='button' name='agregar_formulario' value="&gt;&gt;" onclick='add_form()'/></td>
                        <td width="50%" rowspan='2' >{$formularios_elegidos.INPUT}</td>
                        {if $label_manage_forms }
                        <td rowspan='2' align='right' valign='top'></td>
                        {/if}
                    </tr>
                    <tr>
                        <td><input type='button' name='quitar_formulario' value="&lt;&lt;" onclick='drop_form()'/></td>
                    </tr>
                </table>
           {else}
               {$formulario.INPUT}
            {/if}
            </td>
	  </tr>
      <tr  height='30'>
		<td align='right'>{$external_url.LABEL}:<br><br>
		<a href="?menu=external_url">
		<b>{$label_manage_external_url}</b></a><br><hr>
		</td>
		<td valign="top" colspan='2'>{$external_url.INPUT}{if $label_manage_external_url}&nbsp;{/if}</td>
      </tr>
      <tr  height='30'>
        <td align='right'>{$trunk.LABEL}: <span  class="required">*</span><br><br>
        <a href="?menu=pbxconfig&amp;display=trunks">
        <b>{$label_manage_trunks}</b></a><br><hr>
        </td>
        <td valign="top" colspan='2'>{$trunk.INPUT}{if $label_manage_trunks}&nbsp;{/if}</td>
      </tr>
      <tr  height='30'>
		<td align='right'>{$max_canales.LABEL}: <span  class="required">*</span></td>
		<td colspan='2'>{$max_canales.INPUT}&nbsp;{$LABEL_CHANNEL_ZERO_DISABLE}</td>
      </tr>
      <tr height='30'>
		<td align='right'>{$context.LABEL}: <span  class="required">*</span></td>
		<td colspan='2'>{$context.INPUT}</td>
      </tr>
      <tr height='30'>
		<td align='right'>{$queue.LABEL}: <span  class="required">*</span><br><br>
		<a href="?menu=pbxconfig&amp;display=queues">
		<b>{$label_manage_queues}</b></a><br><hr>
		</td>
		<td valign="top" colspan='2'>{$queue.INPUT}{if $label_manage_queues}&nbsp;{/if}</td>
      </tr>
      <tr height='30'>
	    <td align='right'>{$reintentos.LABEL}: <span  class="required">*</span></td>
	    <td  colspan='4'>{$reintentos.INPUT}</td>
      </tr>
      <tr>
        <td align='right' valign='top'>{$script.LABEL}: <span  class="required">*</span></td>
        <td  colspan='2'>
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
<input type="hidden" name="id_campaign" id='id_campaign' value="{$id_campaign}" />
<input type="hidden" name="values_form" id='values_form' value="" />
</form>

{literal}
<script type="text/javascript">
/* Función para recoger todas las variables del formulario y procesarlas. Sólo
   se requiere atención especial para el RTF del script, y para la lista de
   formularios elegidos. */
function enviar_datos()
{
	var lc = listaControlesFormularios();
	var select_form = lc[1]; /* Formularios elegidos */
    var values = "";

    for(var i=0; i < select_form.length; i++) {
        values = values + select_form[i].value + ",";
    }
    if(values != "")
        values = values.substring(0,values.length-1);
    document.getElementById("values_form").value = values;

    updateRTEs();
    return true;
}

function add_form()
{
	var lc = listaControlesFormularios();
	var select_formularios = lc[0];
	var select_formularios_elegidos = lc[1];

    for(var i=0;i<select_formularios.length;i++){
        if(select_formularios[i].selected){
            var option_tmp = document.createElement("option");
            option_tmp.value = select_formularios[i].value;
            option_tmp.appendChild(document.createTextNode(select_formularios[i].firstChild.data));
            select_formularios_elegidos.appendChild(option_tmp);
        }
    }

    for(var i=select_formularios.length-1;i>=0;i--){
        if(select_formularios[i].selected){
            select_formularios.removeChild(select_formularios[i]);
        }
    }
}


function drop_form()
{
	var lc = listaControlesFormularios();
	var select_formularios = lc[0];
	var select_formularios_elegidos = lc[1];

    for(var i=0;i<select_formularios_elegidos.length;i++){
        if(select_formularios_elegidos[i].selected){
            var option_tmp = document.createElement("option");
            option_tmp.value = select_formularios_elegidos[i].value;
            option_tmp.appendChild(document.createTextNode(select_formularios_elegidos[i].firstChild.data));
            select_formularios.appendChild(option_tmp);
        }
    }

    for(var i=select_formularios_elegidos.length-1;i>=0;i--){
        if(select_formularios_elegidos[i].selected){
            select_formularios_elegidos.removeChild(select_formularios_elegidos[i]);
        }
    }
}

/* Esta función es necesaria para lidiar con el cambio en los nombres de los
   controles generados por Elastix entre 1.6-12 y 1.6.2-1 */
function listaControlesFormularios()
{
	var listaControles;
	var select_formularios;
	var select_formularios_elegidos;

	listaControles = document.getElementsByName('formulario');
	if (listaControles.length == 0)
		listaControles = document.getElementsByName('formulario[]');
    select_formularios = listaControles[0];

	listaControles = document.getElementsByName('formularios_elegidos');
	if (listaControles.length == 0)
		listaControles = document.getElementsByName('formularios_elegidos[]');
    select_formularios_elegidos = listaControles[0];

	var lista = new Array();
	lista[0] = select_formularios;
	lista[1] = select_formularios_elegidos;
	return lista;
}
</script>
{/literal}
{/if} {* $no_queues *}
