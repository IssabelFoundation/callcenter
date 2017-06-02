#!/usr/bin/php
<?php
require_once ("/var/www/html/modules/agent_console/libs/ECCP.class.php");

if (count($argv) < 3) die("Use: {$argv[0]} agentchannel agentpassword [sameagent [newphone [newcontactname [campaigntype callid]]]]\n");
$agentname = $argv[1];
$agentpass = $argv[2];

$x = new ECCP();
try {
    print "Connect...\n";
	$cr = $x->connect("localhost", "agentconsole", "agentconsole");
	if (isset($cr->failure)) die('Failed to connect to ECCP - '.$cr->failure->message."\n");
    $x->setAgentNumber($agentname);
    $x->setAgentPass($agentpass);
    $sameagent      = (count($argv) > 3 && trim($argv[3]) != '') ? trim($argv[3]) : 0;
    $newphone       = (count($argv) > 4 && trim($argv[4]) != '') ? trim($argv[4]) : NULL;
    $newcontactname = (count($argv) > 5 && trim($argv[5]) != '') ? trim($argv[5]) : NULL;
    $campaigntype   = (count($argv) > 6 && trim($argv[6]) != '') ? trim($argv[6]) : NULL;
    $callid         = (count($argv) > 7 && trim($argv[7]) != '') ? trim($argv[7]) : NULL;

    print "Agendando llamada...\n";
    $r = $x->schedulecall(
/*
        array(
            'date_init' =>  date('Y-m-d'),
            'date_end'  =>  '2011-08-31',
            'time_init' =>  '00:00:00',
            'time_end'  =>  '23:00:01'
        ),   // schedule
*/
        NULL,
        $sameagent,
        $newphone,
        $newcontactname,
        $campaigntype,
        $callid
    );
    print_r($r);
    print "Disconnect...\n";
    $x->disconnect();
} catch (Exception $e) {
    print_r($e);
    print_r($x->getParseError());
}
?>
