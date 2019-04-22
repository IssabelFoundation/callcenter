<?php
include("/var/www/html/libs/misc.lib.php");
include("/var/www/html/configs/default.conf.php");
include("/var/www/html/libs/paloSantoACL.class.php");
include_once("/var/www/html/libs/paloSantoDB.class.php");
session_name("issabelSession");
session_start();
$pDB  = new paloDB($arrConf["issabel_dsn"]["acl"]);
$pACL = new paloACL($pDB);

if(isset($_SESSION["issabel_user"])){
    $issabel_user = $_SESSION["issabel_user"];
} else {
    $issabel_user = "";
echo " no eres usuario autorizado";
    exit;
}

$extension_verificada = $pACL->getUserExtension($issabel_user);
//usamos solo la primera extensión como la valida para escuchar aunque el usuario tenga varias extensiones segun ampliacion codigo hgmnetwork.com 20-01-2019
$array_extensiones=explode(";",$extension_verificada);
$extension=$array_extensiones[0];//la primera por defecto
//echo " la extension verificada es $extension_escucha y el id de usuario de sesion es $issabel_user<br>";
//a la extension le quitamos el SIP/ o AGENT/ y dejamos solo el numero
$extension=preg_replace("/(SIP\/|AGENT\/i)/","",$extension);//dejamos solo el numero
//echo "<hr> la extension del usuario es $array_extensiones[0] y  la del usuario actual es ".$_GET['agente']." <hr>";
//lo mismo con el agente
$agente =trim($_GET['agente']);
$agente=preg_replace("/(SIP\/|AGENT\/)/i","",$agente);//dejamos solo el numero

#permit=127.0.0.1/255.255.255.0,xxx.xxx.xxx.xxx ;(the ip address of the server this page is running on)
$strHost = "127.0.0.1";

#specify the username you want to login with (these users are defined in /etc/asterisk/manager.conf) o en manager_custom.conf
#por defecto usamos el usuario de php que viene
$strUser = "phpconfig";

#specify the password for the above user
$strSecret = "php[onfig";

$oSocket = fsockopen($strHost, 5038, $errnum, $errdesc) or die("Connection to host failed libs/escuchar_agente.php");

$from = $extension;//quien escucha
 $to = $agente;//quien es escuchado el agente
 fputs($oSocket, "Action: Login\r\n");
 fputs($oSocket, "UserName: $strUser\r\n");
 fputs($oSocket, "Secret: $strSecret\r\n\r\n");
 $wrets=fgets($oSocket,128);
 fputs($oSocket, "Action: Originate\r\n" );
fputs($oSocket, "CallerId: Whisper Agente: $agente\r\n");
 //fputs($oSocket, "Channel: SIP/".$from."\r\n" );
 fputs($oSocket, "Channel: local/".$from."\r\n" );
 fputs($oSocket, "Application: ChanSpy\r\n" );
 fputs($oSocket, "Data: SIP/".$to."\r\n\r\n" );
 $wrets=fgets($oSocket,128);
 sleep(3);

fclose($oSocket);
echo "Realizando Whisper al Agente $agente y a la extensión $extension";
