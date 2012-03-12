<?php
// Plesk Mailbox checkscript door Ramon Verhagen (mooiesite.nl)
// (c) 2006

// English version and modifications by George Hazlewood (www.layer1.co.uk)
// With permission April 2007
// Added some extra stuff to show actual usage in the emails.
// Defaults to only sending notification to the postmaster account but switch the config to deliver to the end user too.

include('mailcheck_lib.inc.php');
include('mailcheck_config.inc.php');

$link = mysql_connect("localhost", $db_user, $db_pass);
mysql_select_db("psa", $link);

chdir(MAILNAMES);
ini_set('open_basedir', MAILNAMES);
ini_set('safe_mode',0);
chdir(MAILNAMES);

#echo 'mailnames accessible?:';
#var_dump(is_dir(MAILNAMES)) . "\n";
echo mysql_error($link);

$sql="SELECT domains.name, mail.mail_name, mail.mbox_quota, Limits.value as domain_mbox_quota FROM mail 
JOIN domains ON mail.dom_id = domains.id 
JOIN Limits ON domains.limits_id = Limits.id 
WHERE Limits.limit_name = 'mbox_quota'
ORDER BY domains.name ASC, mail.mail_name ASC";

$result = mysql_query($sql);
$o = ''; $num_over = 0; $num_full = 0;
if (mysql_num_rows($result) > 0) {
  while ($row = mysql_fetch_assoc($result)) {
    $mailsdir = MAILNAMES.$row['name']."/".$row['mail_name']."/Maildir";
    $maildirsize= dirsize($mailsdir);
    if ($row['mbox_quota']=='-1') $row['mbox_quota'] = $row['domain_mbox_quota'];
    $sizemb = round(bytes_to($maildirsize,'MB'),2);
    $quotamb = round(bytes_to($row['mbox_quota'],'MB'),2);
    if ($row['mbox_quota']>0) {
      $percentage = $maildirsize / $row['mbox_quota'];
      $percentuse = round($percentage*100,2);
    } else {
      $percentage = 0;
      $percentuse = 0;
    }

    $o .= $row['mail_name'].'@'.$row['name'].": \t\t\t\tSize: ".$sizemb."MB \tQuota: ".$quotamb."MB \t".$percentuse."%\n";
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
