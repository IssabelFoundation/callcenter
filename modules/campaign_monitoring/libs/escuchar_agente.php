
<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);
//hgmnetwork.com 24-08-2018 fichero para realizar una llamada al chanspy con el agenet pasado
//$_GET['agente'] nos da el canal a escuchar sip/5001 o sip/loquesea si es callback y a/5001 o lo que sea si es agente.
//echo "escuchar al agente: ".$_GET['agente']." en la extension ".$_GET['extension'];
//obtenemos la primera extension del usuario, en nuestro codigo podemos tener varias extensiones por usuario 7001;7002;7003 o solo una 7001 por derfecto al escucha es en la primera o la unica.
$array_extensiones=explode(";",$_GET['extension']);//pasamos los valores a un array ya que creamos extra para poder tener un usuario varias extensiones separadas por ;

$extension=trim($array_extensiones[0]);
//a la extension le quitamos el SIP/ o AGENT/ y dejamos solo el numero
$extension=preg_replace("/(SIP\/|AGENT\/i)/","",$extension);//dejamos solo el numero
//echo "<hr> la extension del usuario es $array_extensiones[0] y  la del usuario actual es ".$_GET['agente']." <hr>";
//lo mismo con el agente
$agente =trim($_GET['agente']);
$agente=preg_replace("/(SIP\/|AGENT\/)/i","",$agente);//dejamos solo el numero

#permit=127.0.0.1/255.255.255.0,xxx.xxx.xxx.xxx ;(the ip address of the server this page is running on)
$strHost = "127.0.0.1";

#specify the username you want to login with (these users are defined in /etc/asterisk/manager.conf)
$strUser = "usuario de manager.conf";

#specify the password for the above user
$strSecret = "clave de manager.conf";


$oSocket = fsockopen($strHost, 5038, $errnum, $errdesc) or die("Connection to host failed libs/escuchar_agente.php");

$from = $extension;//quien escucha
 $to = $agente;//quien es escuchado el agente
 fputs($oSocket, "Action: Login\r\n");
 fputs($oSocket, "UserName: $strUser\r\n");
 fputs($oSocket, "Secret: $strSecret\r\n\r\n");
 $wrets=fgets($oSocket,128);
 fputs($oSocket, "Action: Originate\r\n" );
fputs($oSocket, "CallerId: Whisper Agente: $agente\r\n");
 fputs($oSocket, "Channel: SIP/".$from."\r\n" );
 fputs($oSocket, "Application: ChanSpy\r\n" );
 fputs($oSocket, "Data: SIP/".$to."\r\n\r\n" );
 $wrets=fgets($oSocket,128);
 sleep(3);



fclose($oSocket);
//echo "Realizando Whisper al Agente $agente y a la extensión $extension";

?>
