#!/usr/bin/php
<?php
require_once ("/var/www/html/modules/agent_console/libs/ECCP.class.php");

if (count($argv) < 4) die("Use: {$argv[0]} agentchannel agentpassword extension\n");
$agentname = $argv[1];
$agentpass = $argv[2];

$x = new ECCP();
try {
	print "Connect...\n";
	$cr = $x->connect("localhost", "agentconsole", "agentconsole");
	if (isset($cr->failure)) die('Failed to connect to ECCP - '.$cr->failure->message."\n");
	$x->setAgentNumber($agentname);
	$x->setAgentPass($agentpass);
	print_r($x->getAgentStatus());
	print "Login agent\n";
	$r = $x->loginagent($argv[3]);
	print_r($r);
	$bFalloLogin = FALSE;
	if (!isset($r->failure) && !isset($r->loginagent_response->failure)) while (!$bFalloLogin) {
		$x->wait_response(1);
		while ($e = $x->getEvent()) {
			print_r($e);
			foreach ($e->children() as $ee) $evt = $ee;
			if ($evt->getName() == 'agentfailedlogin') {
				$bFalloLogin = TRUE;
				break;
			}
		}
	}
	print "Disconnect...\n";
	$x->disconnect();
} catch (Exception $e) {
	print_r($e);
	print_r($x->getParseError());
}
?>
