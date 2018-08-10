<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_NOTICE);
/*
CREADO POR HGMNETWORK.COM 31-07-2018 para permitir hacer cambios en las llamadas agendadas en modulo call center
estos cambios pueden ser por el cambio de la fecha de la llamada ( fecha inicial, final y horas ) y cambio en el agente que recibe la llamada. Este puede ser SIP/xxx o Agent/xxxx la variable cambio indica lo que se cambia, cambio=fecha o cambio=agente por defecto cambiamos siempre agente y fecha y hora
*/

$module_name="campaign_monitoring";
require_once  "/var/www/html/modules/$module_name/configs/default.conf.php";

include_once("/var/www/html/libs/paloSantoDB.class.php");
// se conecta a la base
    $pDB = new paloDB($arrConfModule['cadena_dsn']);
if ($pDB->connStatus){ echo "ERR: failed to connect to database: ".$pDB->errMsg; };

//miramos si agente tiene indicado algo o nulo osea cualquier agente recibira la llamada agendada
if ($_GET['agente'] !="cualquiera") {$sql_agente=" agent='".$_GET['agente']."'";} else {$sql_agente=" agent = NULL";};
switch ($_GET['cambio']){
 default:
 $sPeticionSQL = "UPDATE calls set $sql_agente, date_init='".$_GET['date_init']."',date_end='".$_GET['date_end']."', time_init='".$_GET['time_init']."',time_end='".$_GET['time_end']."' where scheduled = 1 and id ='".$_GET['id']."'";
};
//        $recordset = $pDB->fetchTable($sPeticionSQL, FALSE);
//        $recordset = $pDB->genExec($sPeticionSQL);
$result = $pDB-> getFirstRowQuery($sPeticionSQL, true);

echo "Actualizada Llamada ".$_GET['id']." al agente ".$_GET['agente']."";
?>
