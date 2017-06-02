    <table width='100%' border='0'>
        <tr>
            <td align='left'>
                <table>
                <tr>
                    <td class='letra12'>{$fecha_ini.LABEL}<span  class='required'>*</span></td>
                    <td>{$fecha_ini.INPUT}</td>
                    <td class='letra12'>{$fecha_fin.LABEL}<span  class='required'>*</span></td>
                    <td>{$fecha_fin.INPUT}</td>
                    <td class='letra12' colspan="2">&nbsp;</td>
                    <td><input type='submit' name='submit_fecha' value='{$LABEL_FIND}' class='button' /></td>
                </tr>
                <tr>
                    <td class='letra12' align='left'>{$tipo.LABEL}</td>
                    <td>{$tipo.INPUT}</td>
                    <td class='letra12' align='left'>{$estado.LABEL}</td>
                    <td>{$estado.INPUT}</td>
                    <td class='letra12' align='left'>{$queue.LABEL}</td>
                    <td>{$queue.INPUT}</td>
                    <td class='letra12'>&nbsp;</td>
                </tr>
                </table>
            </td>
        </tr>
        <tr align='left'>
            <td>
                <img src='?menu={$MODULE_NAME}&amp;rawmode=yes&amp;action=graph_histogram&amp;tipo={$TIPO}&amp;estado={$ESTADO}&amp;queue={$QUEUE}&amp;fecha_ini={$FECHA_INI}&amp;fecha_fin={$FECHA_FIN}' />
            </td>
        </tr>
    </table>

