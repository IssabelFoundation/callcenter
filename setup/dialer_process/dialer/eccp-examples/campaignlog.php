#!/usr/bin/php
<?php
require_once ("/var/www/html/modules/agent_console/libs/ECCP.class.php");
$x = new ECCP();

if (count($argv) < 2) die("Uso: {$argv[0]} campaigntype [campaignid [queue [startdate [enddate]]]]\n");

try {
    print "Connect...\n";
	$cr = $x->connect("localhost", "agentconsole", "agentconsole");
	if (isset($cr->failure)) die('Failed to connect to ECCP - '.$cr->failure->message."\n");
    print_r($x->campaignlog(
        $argv[1],
        (count($argv) > 2 && trim($argv[2]) != '') ? $argv[2] : NULL,
        (count($argv) > 3 && trim($argv[3]) != '') ? $argv[3] : NULL,
        (count($argv) > 4 && trim($argv[4]) != '') ? $argv[4] : NULL,
        (count($argv) > 5 && trim($argv[5]) != '') ? $argv[5] : NULL,
        (count($argv) > 6 && trim($argv[6]) != '') ? $argv[6] : NULL,
        (count($argv) > 7 && trim($argv[7]) != '') ? $argv[7] : NULL
        ));
    print "Disconnect...\n";
    $x->disconnect();
} catch (Exception $e) {
    print_r($e);
    print_r($x->getParseError());
}
?>
