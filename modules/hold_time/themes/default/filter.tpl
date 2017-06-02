<table width='100%' border='0'>
    <tr>
        <td align='left'>
            <table>
            <tr>
                <td class='letra12'>
                    {$date_start.LABEL}
                    <span  class='required'>*</span>
                </td>
                <td>
                    {$date_start.INPUT}
                </td>
                <td class='letra12'>
                    &nbsp;
                </td>
                <td class='letra12'>
                    {$date_end.LABEL}
                    <span  class='required'>*</span>
                </td>
                <td>
                    {$date_end.INPUT}
                </td>
                <td>&nbsp;</td>
            </tr>

            <tr>
                <td class='letra12' align='left'>{$call_type.LABEL}</td>
                <td>{$call_type.INPUT}</td>
                <td class='letra12'>
                    &nbsp;
                </td>
                <td class='letra12' align='left'>{$call_state.LABEL}</td>
                <td>{$call_state.INPUT}</td>
                <td>
                    <input type='submit' name='submit_fecha' value="{$LABEL_FIND}" class='button'>
                </td>
            </tr>
            </table>
        </td>
    </tr>
</table>
