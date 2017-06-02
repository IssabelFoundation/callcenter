<table width="99%" border="0" cellspacing="0" cellpadding="4" align="center">
    <tr class="letra12">
        <td width="10%">{$date_start.LABEL}: </td>
        <td>{$date_start.INPUT} <input class="button" type="submit" name="filter" value="{$Filter}" /></td>
        <td>{$calltype.LABEL}:</td>
        <td>{$calltype.INPUT} <input class="button" type="submit" name="filter" value="{$Filter}" /></td>
    </tr>
    <tr class="letra12">
        <td>{$date_end.LABEL}:</td>
        <td>{$date_end.INPUT}</td>
        <td>{$agent.LABEL}:</td>
        <td>{$agent.INPUT}</td>
    </tr>
    <tr>
        <td>{$phone.LABEL}:</td>
        <td>{$phone.INPUT}</td>
        <td>{$queue.LABEL}:</td>
        <td>{$queue.INPUT}</td>
    </tr>
{if $INCOMING_CAMPAIGN or $OUTGOING_CAMPAIGN}
    <tr>
        <td colspan="2">&nbsp;</td>
        {if $INCOMING_CAMPAIGN}
        <td>{$id_campaign_in.LABEL}:</td>
        <td>{$id_campaign_in.INPUT}</td>
        {/if}
        {if $OUTGOING_CAMPAIGN}
        <td>{$id_campaign_out.LABEL}:</td>
        <td>{$id_campaign_out.INPUT}</td>
        {/if}
    </tr>
{/if}
</table>

