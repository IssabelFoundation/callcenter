#!/usr/bin/php
<?php
require_once ("/var/www/html/modules/agent_console/libs/ECCP.class.php");
$x = new ECCP();
try {
	print "Connect...\n";
	$cr = $x->connect("localhost", "agentconsole", "agentconsole");
	if (isset($cr->failure)) die('Failed to connect to ECCP - '.$cr->failure->message."\n");
    $x->setAgentNumber("Agent/9000");
    $x->setAgentPass("gatito");
	print_r($x->getAgentStatus());
	print "Seteando contacto...\n";
	$r = $x->setcontact($argv[1], $argv[2]);
	print_r($r);
	print "Disconnect...\n";
	$x->disconnect();
} catch (Exception $e) {
	print_r($e);
	print_r($x->getParseError());
}
?>
