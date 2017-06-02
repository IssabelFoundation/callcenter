#!/usr/bin/php
<?php
if (count($argv) < 3) {
    fprintf(STDERR, $argv[0]." [incoming|outgoing] [campaign-id]\n");
	exit(0);
}

require_once ("/var/www/html/modules/agent_console/libs/ECCP.class.php");
$x = new ECCP();
try {
	print "Connect...\n";
	$cr = $x->connect("localhost", "agentconsole", "agentconsole");
	if (isset($cr->failure)) die('Failed to connect to ECCP - '.$cr->failure->message."\n");
    print "Pidiendo información de campaña...\n";
    $r = $x->getcampaigninfo($argv[1], $argv[2]);
    print_r($r);
	print "Pidiendo estado de campaña...\n";
	$r = $x->getcampaignstatus($argv[1], $argv[2]);
    print_r($r);
	print "Disconnect...\n";
	$x->disconnect();
} catch (Exception $e) {
	print_r($e);
	print_r($x->getParseError());
}
?>
