<?php
$fp=@fsockopen('127.0.0.1',6379,$e,$es,5);
if(!$fp){echo "CONNECT_FAIL: $es\n"; exit;}
echo "PING: ";
fwrite($fp,"*1\r\n\$4\r\nPING\r\n"); echo fgets($fp);
echo "CONFIG: ";
fwrite($fp,"*2\r\n\$6\r\nCONFIG\r\n\$11\r\nrequirepass\r\n");
$l=fgets($fp); echo $l; 
while(true){ $l2=fgets($fp); if($l2===false||trim($l2)==='')break; echo "  ".$l2; }
fclose($fp);