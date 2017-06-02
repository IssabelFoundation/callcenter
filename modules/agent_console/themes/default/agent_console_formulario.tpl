{* Definición de las cejillas de información/script/formulario *}
<div id="elastix-callcenter-cejillas-formulario">
   <ul>
{foreach from=$FORMS key=ID_FORM item=FORM}
       <li><a href="#elastix-callcenter-formulario-{$ID_FORM}">{$FORM.name}</a></li>
{/foreach}
   </ul>
{foreach from=$FORMS key=ID_FORM item=FORM}
   <div id="elastix-callcenter-formulario-{$ID_FORM}">
    <table border="0" cellpadding="0">
            <tr>
                <th colspan="2">{$FORM.description|escape:"html"}</th>
            </tr>
{foreach from=$FORM.fields item=FIELD}
            <tr>
{if $FIELD.type != "LABEL"}
                <td><label>{$FIELD.label|escape:"html"}: </label></td>
{/if}
                <td {if $FIELD.type == "LABEL"}colspan="2"{/if}>
{if $FIELD.type eq "LIST"}
{html_options
    name="field-`$ID_FORM`-`$FIELD.id`"
    id="field-`$ID_FORM`-`$FIELD.id`"
    class="elastix-callcenter-field ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only"
    output=$FIELD.options
    values=$FIELD.options
    selected=$FIELD.current_value}
{elseif $FIELD.type eq "DATE"}
{* Depende de ejecutar datepicker sobre .elastix-callcenter-field-date *}
<input
    type="text"
    name="field-{$ID_FORM}-{$FIELD.id}"
    id="field-{$ID_FORM}-{$FIELD.id}"
    class="elastix-callcenter-field elastix-callcenter-field-date ui-widget-content ui-corner-all"
    value="{if $FIELD.current_value == ''}{$FIELD.default_value|escape:"html"}{else}{$FIELD.current_value|escape:"html"}{/if}" />
{elseif $FIELD.type eq "LABEL"}
                <label>{$FIELD.label|escape:"html"}</label>
{elseif $FIELD.type eq "TEXTAREA"}
<textarea
    name="field-{$ID_FORM}-{$FIELD.id}"
    id="field-{$ID_FORM}-{$FIELD.id}"
    rows="3" cols="50"
    maxlength="{$FIELD.maxsize}"
    class="elastix-callcenter-field ui-widget-content ui-corner-all">{if $FIELD.current_value == ''}{$FIELD.default_value|escape:"html"}{else}{$FIELD.current_value|escape:"html"}{/if}</textarea>
{else}
<input
    type="text"
    name="field-{$ID_FORM}-{$FIELD.id}"
    id="field-{$ID_FORM}-{$FIELD.id}"
    size="{if $FIELD.maxsize > 64}64{else}{$FIELD.maxsize}{/if}"
    maxlength="{$FIELD.maxsize}"
    class="elastix-callcenter-field ui-widget-content ui-corner-all"
    value="{if $FIELD.current_value == ''}{$FIELD.default_value|escape:"html"}{else}{$FIELD.current_value|escape:"html"}{/if}" />
{/if}
                </td>
            </tr>
{/foreach}{* from=$FORM.fields *}
    </table>
   </div>
{/foreach}{* from=$FORMS *}
</div>{* elastix-callcenter-cejillas-formulario *}