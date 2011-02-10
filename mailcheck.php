<?php
// Plesk Mailbox checkscript door Ramon Verhagen (mooiesite.nl)
// (c) 2006

// English version and modifications by George Hazlewood (www.layer1.co.uk)
// With permission April 2007
// Added some extra stuff to show actual usage in the emails.
// Defaults to only sending notification to the postmaster account but switch the config to deliver to the end user too.

/* See below for config */

$k = 1024;
$m = 1048576;
$g = 1073741824;

function to_bytes($value, $unit) {
  global $k, $m, $g;
  switch (trim($unit))
  {
    case "b": //bytes
      return ($value / 8);
    case "Kb": //Kilobits
      return (($value * $k) / 8);
    case "Mb": // Megabits
      return (($value * $m) / 8);
    case "Gb": // Gigabits
      return (($value * $g) / 8);
    case "B": // Bytes
      return $value;
    case "KB": // Kilobytes
      return ($value * $k);
    case "MB": // Megabytes
      return ($value * $m);
    case "GB": // Gigabytes
      return ($value * $g);
    default: return 0;
  }
}

function bytes_to($value, $unit) {
  global $k, $m, $g;
  switch (trim($unit)) {
    case "b": //bytes
      return ($value * 8);
    case "Kb": //Kilobits
      return (($value * 8) / $k);
    case "Mb": // Megabits
      return (($value * 8) / $m);
    case "Gb": // Gigabits
      return (($value * 8) / $g);
    case "B": // Bytes
      return $value;
    case "KB": // Kilobytes
      return ($value / $k);
    case "MB": // Megabytes
      return ($value / $m);
    case "GB": // Gigabytes
      return ($value / $g);
    default: return 0;
  }
}

function dirsize($dir) {
  $cmd = "/usr/bin/du -bs ".$dir."/";
  if (is_dir($dir)) {
     $res = `$cmd`;
     $res = explode(" ", $res);
     return $res[0];
  } else {
     echo "Couldn\'t run command: ".$cmd." Not a directory: ".$dir."\n";
     return 0;
  }
}

/* CHANGE ME !
Just change the settings below: 
*/

define("MAILNAMES", "/var/qmail/mailnames/");
define("DOMAIN", "example.com");
define("ACCOUNT", "postmaster");
define("FROM", ACCOUNT."@".DOMAIN);
define("RECIPIENT", ACCOUNT."@".DOMAIN);

// Uncomment the line below and users will get an email
//define("DELIVER_TO_USER",true);
// Uncomment the line below and the admin will get an email for each mailbox nearing or at the quota limit
//define("DELIVER_TO_ADMIN",true);

define("LOWER_LIMIT", 90.0); // 90% full - approaching capacity
define("UPPER_LIMIT", 99.9); // 99.9% full - mailbox full

// You must set these!
$db_user = 'mailcheck';
$db_pass = '1234567890';
/* End of config */

$link = mysql_connect("localhost", $db_user, $db_pass);
mysql_select_db("psa", $link);

chdir(MAILNAMES);
ini_set('open_basedir', MAILNAMES);
ini_set('safe_mode',0);
chdir(MAILNAMES);

#echo 'mailnames accessible?:';
#var_dump(is_dir(MAILNAMES)) . "\n";
echo mysql_error($link);

$sql="SELECT domains.name, mail.mail_name, mail.mbox_quota FROM mail JOIN domains ON mail.dom_id = domains.id";

$result = mysql_query($sql);
$o = ''; $num_over = 0; $num_full = 0;
if (mysql_num_rows($result) > 0) {
  while ($row = mysql_fetch_assoc($result)) {
    $mailsdir = MAILNAMES.$row['name']."/".$row['mail_name']."/Maildir";
    $maildirsize= dirsize($mailsdir);
    $percentage= $maildirsize / $row['mbox_quota'];
    $sizemb = round(bytes_to($maildirsize,'MB'),2);
    $quotamb = round(bytes_to($row['mbox_quota'],'MB'),2);
    $o .= $row['mail_name'].'@'.$row['name'].": \t\t\t\tSize: ".$sizemb."MB \tQuota: ".$quotamb."MB \t".round($percentage*100,2)."%\n";
    $afrondperc=($percentage*100);
    $afrondperc=round($afrondperc,2);
    if ($percentage > LOWER_LIMIT) {
      
        if ($afrondperc > UPPER_LIMIT) { 
          $mailto=$row['mail_name']."@".$row['name'];
          $message="Return-Path: <".FROM.">\n";
          $message.="Delivered-To: ".$mailto."\n";
          $message.="Date: ".date("j M Y G:i:s")." +0200\n";
          $message.="X-Priority: 1\n";
          $msgid=date("YmdHis").".".rand(10000,99999).".qmail@".DOMAIN;
          $message.="Message-ID: <".$msgid.">\n";
          $message.="To: ".$mailto."\n";
          $message.="Subject: WARNING! \"".$mailto."\" mail delivery failing - mailbox full\n";
          $message.="From: Mailserver <".FROM.">\n";
          $message.="\n\n";
          $body="Dear mail user,\r\n\r\n";
          $body.="Your mailbox has reached maximum capacity, no more email can be delivered to it until some of the existing email is deleted.\r\n";
          $body.="To ensure that you can continue to receive email please download or delete some messages immediately using either
          your regular email client (Outlook, Outlook Express, Entourage etc.) or the webmail client at http://webmail.".$row['name']."\r\n\r\n";
          $body.= "Used:  ".$sizemb."MB\r\n";
          $body.= "Quota: ".$quotamb."MB\r\n";
          $body.= "(".$afrondperc."% full)\r\n\r\n";
          $body.="This is an automatically generated email.";
          $message .= wordwrap($body,80);
          if (defined(DELIVER_TO_ADMIN)) {
            $admindir=MAILNAMES.DOMAIN."/".ACCOUNT."/Maildir/new/".$msgid;
            file_put_contents($admindir, $message);
          }
          if (defined(DELIVER_TO_USER)) {
              $userdir = MAILNAMES.$row['name']."/".$row['mail_name']."/Maildir/new/".$msgid;
              file_put_contents($userdir, $message);
          }
          $full[] = array('mailbox'=>$row['mail_name'].'@'.$row['name'], 'quota'=>$quotamb, 'used'=>$sizemb);
          $num_full++;
        } else {
          $mailto=$row['mail_name']."@".$row['name'];
          $message="Return-Path: <".FROM.">\n";
          $message.="Delivered-To: ".$mailto."\n";
          $message.="Date: ".date("j M Y G:i:s")." +0200\n";
          $message.="X-Priority: 1\n";
          $msgid=date("YmdHis").".".rand(10000,99999).".qmail@".DOMAIN;             
          $message.="Message-ID: <".$msgid.">\n";
          $message.="To: ".$mailto."\n";
          $subject="WARNING! Mailbox: \"".$mailto."\" mailbox approaching capacity\n";
          $message .= "Subject: ".$subject;
          $message.="From: Mailserver <".FROM.">\n";
          $message.="\n\n";
          $body="Dear mail user,\r\n\r\n";
          $body.="Your mailbox has reached a capacity of ".$afrondperc."% full.\r\n";
          $body.="To ensure that you can continue to receive email please download or delete some messages immediately using either 
          your regular email client (Outlook, Outlook Express, Entourage etc.) or the webmail client at http://webmail.".$row['name']."\r\n\r\n";
          $body.= "Used:  ".$sizemb."MB\r\n";
          $body.= "Quota: ".$quotamb."MB\r\n"; 
          $body.= "(".$afrondperc."% full)\r\n\r\n";
          $body.="This is an automatically generated email.";
          $message.=wordwrap($body,80);
          if (defined(DELIVER_TO_ADMIN)) {
            $admindir=MAILNAMES.DOMAIN."/".ACCOUNT."/Maildir/new/".$msgid;
            file_put_contents($admindir, $message);
          }
          if (defined(DELIVER_TO_USER)) {
              $userdir = MAILNAMES.$row['name']."/".$row['mail_name']."/Maildir/new/".$msgid;
              file_put_contents($userdir, $message);
          }
          $over[] = array('mailbox'=>$row['mail_name'].'@'.$row['name'], 'quota'=>$quotamb, 'used'=>$sizemb);
          $num_over++;
        }
      } 
    }
  }
$e = '';
$mess = '';
if (!empty($num_over)) {
  $mess .= "There are ".$num_over." mailboxes near quota limit\r\n";
  $mess .= "Mailboxes Over 90% Full\n\n";
  foreach($over as $ov) {
    $mess .= $ov['mailbox']."\t\t\t".$ov['quota']."\t\t\t".$ov['used']."\n";
  }
  $mess .= "\n\n";
} else {
  $o .= "No mailboxes near quota\r\n";
}

if (!empty($num_full)) {
  $mess .= "There are ".$num_full." mailboxes which are full\r\n";
  $mess .= "Full Mailboxes\n\n";
  foreach($full as $f) {
    $mess .= $f['mailbox']."\t\t\t".$f['quota']."\t\t\t".$f['used']."\n";
  }
} else {
  $mess .= "No mailboxes over quota\r\n";
}
if (!empty($mess)) {
  mail(RECIPIENT,'Mailbox Quota Summary', $mess);
  //echo $message;
}

if (isset($e)) {
  echo $e;
}

?>
