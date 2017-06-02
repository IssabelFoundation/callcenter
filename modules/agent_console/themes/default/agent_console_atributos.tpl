<table border="0">
    <tbody>
        <tr>
            <td><label>{$LBL_NOMBRE_CAMPANIA|escape:"html"}: </label></td>
            <td>{$TEXTO_NOMBRE_CAMPANIA|escape:"html"}</td>
        </tr>
        <tr>
            <td><label>{$LBL_CALL_ID|escape:"html"}: </label></td>
            <td>{$TEXTO_CALL_ID|escape:"html"}</td>
        </tr>
    {if $CALLINFO_CALLTYPE == 'incoming'}
        <tr>
            <td><label for="llamada_entrante_contacto_telefono">{$LBL_CONTACTO_TELEFONO|escape:"html"}: </label></td>
            <td><span id="llamada_entrante_contacto_telefono">{$TEXTO_CONTACTO_TELEFONO|escape:"html"}</span></td>
        </tr>
        <tr>
            <td><label for="llamada_entrante_contacto_id">{$LBL_CONTACTO_SELECT}: </label></td>
            <td>
              <select
                  name="llamada_entrante_contacto_id"
                  id="llamada_entrante_contacto_id"
                  class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only">
                  {html_options options=$LISTA_CONTACTOS}
              </select>
              <button id="btn_confirmar_contacto">{$BTN_CONFIRMAR_CONTACTO}</button>
            </td>
        </tr>
    {/if}
    {if $CALLINFO_CALLTYPE == 'outgoing'}
        <tr>
            <td><label for="llamada_entrante_contacto_telefono">{$LBL_CONTACTO_TELEFONO|escape:"html"}: </label></td>
            <td><span id="llamada_entrante_contacto_telefono">{$TEXTO_CONTACTO_TELEFONO|escape:"html"}</span></td>
        </tr>
        <tr>
            <td><label for="llamada_saliente_nombres">{$LBL_CONTACTO_NOMBRES|escape:"html"}: </label></td>
            <td><span id="llamada_saliente_nombres">{$TEXTO_CONTACTO_NOMBRES|escape:"html"}</span></td>
        </tr>
    {/if}
    {foreach from=$ATRIBUTOS_LLAMADA item=ATRIBUTO }
        <tr>
            <td><label>{$ATRIBUTO.label|escape:"html"}: </label></td>
            <td>{$ATRIBUTO.value}</td>{* No se escapa valor del atributo porque podría ser URLs *}
        </tr>
    {/foreach}
    </tbody>
</table>