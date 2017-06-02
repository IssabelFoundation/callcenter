#!/usr/bin/php
<?php
require_once ("/var/www/html/modules/agent_console/libs/ECCP.class.php");
$x = new ECCP();
try {
    print "Connect...\n";
	$cr = $x->connect("localhost", "agentconsole", "agentconsole");
	if (isset($cr->failure)) die('Failed to connect to ECCP - '.$cr->failure->message."\n");
    $r = $x->getpauses();
    print_r($r);
    $a = time(); $i = 0;
    while (true) {
    	$r = $x->getpauses();
        $i++;
        $b = time();
        if ($a != $b) {
            print "\rgetpauses request per second: $i    ";
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
