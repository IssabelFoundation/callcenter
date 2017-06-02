#!/usr/bin/php
<?php
require_once ("/var/www/html/modules/agent_console/libs/ECCP.class.php");

if (count($argv) < 2) die("Use: {$argv[0]} agentchannel\n");
$agentname = $argv[1];

$x = new ECCP();
try {
    print "Connect...\n";
	$cr = $x->connect("localhost", "agentconsole", "agentconsole");
	if (isset($cr->failure)) die('Failed to connect to ECCP - '.$cr->failure->message."\n");
    $x->setAgentNumber($agentname);
    print_r($x->getAgentStatus());
    $a = microtime(TRUE); $i = 0;
    while (true) {
    	$r = $x->getAgentStatus();
        $i++;
        $b = microtime(TRUE);
        if ($b - $a > 5) {
            print "\rgetagentstatus request per second: ".($i / ($b - $a));
            $a = $b;
            $i = 0;
        }
    }
    print "Disconnect...\n";
    $x->disconnect();
} catch (Exception $e) {
    print_r($e);
    print_r($x->getParseError());
}
?>
