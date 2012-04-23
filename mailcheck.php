<?php
// Plesk Mailbox checkscript door Ramon Verhagen (mooiesite.nl)
// (c) 2006

// English version and modifications by George Hazlewood (www.layer1.co.uk)
// With permission April 2007
// Added some extra stuff to show actual usage in the emails.
// Defaults to only sending notification to the postmaster account but switch the config to deliver to the end user too.

include(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'mailcheck_lib.inc.php');
include(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'mailcheck_config.inc.php');

$link = mysql_connect("localhost", $db_user, $db_pass);
mysql_select_db("psa", $link);

chdir(MAILNAMES);
ini_set('open_basedir', MAILNAMES);
ini_set('safe_mode',0);
chdir(MAILNAMES);

echo mysql_error($link);

$sql="SELECT domains.name, mail.id, mail.mail_name, mail.mbox_quota, Limits.value as domain_mbox_quota FROM mail 
JOIN domains ON mail.dom_id = domains.id 
JOIN Limits ON domains.limits_id = Limits.id 
WHERE Limits.limit_name = 'mbox_quota'
ORDER BY domains.name ASC, mail.mail_name ASC";

$result = mysql_query($sql);
$o = ''; $num_over = 0; $num_full = 0; $totalmailboxes = 0;
if (mysql_num_rows($result) > 0) {
  while ($mailbox = mysql_fetch_assoc($result)) {
    $mailbox['maildir_path'] = MAILNAMES.$mailbox['name']."/".$mailbox['mail_name']."/Maildir";
    $mailbox['maildir_size'] = dirsize($mailbox['maildir_path']);
    $totalmailboxes++;
    if ($mailbox['mbox_quota']=='-1') $mailbox['mbox_quota'] = $mailbox['domain_mbox_quota'];
    $quota = array('sizemb' => round(bytes_to($mailbox['maildir_size'],'MB'),2), 'quotamb' => round(bytes_to($mailbox['mbox_quota'],'MB'),2));
    if ($mailbox['mbox_quota']>0) {
      $percentage = $mailbox['maildir_size'] / $mailbox['mbox_quota'];
      $percentuse = round($percentage*100,2);
    } else {
      $percentage = 0;
      $percentuse = 0;
    }

    $o .= $mailbox['mail_name'].'@'.$mailbox['name'].": \t\t\t\tSize: ".$quota['sizemb']."MB \tQuota: ".$quota['quotamb']."MB \t".$percentuse."%\n";
    $afrondperc=($percentage*100);
    $afrondperc=round($afrondperc,2);
    if ($percentage > LOWER_LIMIT) {
      
        if ($afrondperc > UPPER_LIMIT) { 
          $message = compose_message($row, $afrondperc, $quota, true);

          if (defined(DELIVER_TO_ADMIN)) {
            $admin_dir = MAILNAMES.DOMAIN."/".ACCOUNT."/Maildir/new/".$msgid;
            add_message_to_maildir($admin_dir, $message);
          }
          if (defined(DELIVER_TO_USER)) {
            $user_dir = MAILNAMES.$mailbox['name']."/".$mailbox['mail_name']."/Maildir/new/".$msgid;
            add_message_to_maildir($user_dir, $message);
          }
          $full[] = array(
            'mailbox' => $mailbox['mail_name'].'@'.$mailbox['name'], 
            'quota'=>$quota['quotamb'], 
            'used'=>$quota['sizemb']
          );
          $num_full++;
        } else {
          $message = compose_message($row, $afrondperc, $quota, false);          

          if (defined(DELIVER_TO_ADMIN)) {
            $admin_dir=MAILNAMES.DOMAIN."/".ACCOUNT."/Maildir/new/".$msgid;
            add_message_to_maildir($admin_dir, $message);
          }
          if (defined(DELIVER_TO_USER)) {
            $user_dir = MAILNAMES.$mailbox['name']."/".$mailbox['mail_name']."/Maildir/new/".$msgid;
            add_message_to_maildir($user_dir, $message);
          }
          // Find out if this is a mailbox which also has an active redirect and therefore might also
          // 
          $redirect_sql = "SELECT `address` FROM `mail_redir` WHERE `mn_id` = ".$mailbox['id']." LIMIT 1";
          $redirect_result = mysql_query($redirect_sql);
          if (mysql_num_rows($redirect_result) > 0) {
            $redirect_row = mysql_fetch_assoc($redirect_result);
          }

          $over[] = array(
            'mailbox'=>$mailbox['mail_name'].'@'.$mailbox['name'], 
            'quota'=>$quota['quotamb'], 
            'used'=>$quota['sizemb'], 
            'redirect'=>$redirect_row['address']
          );
          $num_over++;
        }
      } 
    }
  }
$e = '';
$mess = '';
if ($totalmailboxes>0) {
  $mess .= "There are ".$totalmailboxes." mailboxes\n\n";
}

if (!empty($num_over)) {
  $mess .= "There are ".$num_over." mailboxes near quota limit\n";
  $mess .= "Mailboxes Over 90% Full\n\n";
  foreach($over as $ov) {
    $mess .= $ov['mailbox']."\t\t\t".$ov['quota']."\t\t\t".$ov['used']."\n";
  }
  $mess .= "\n\n";
} else {
  $o .= "No mailboxes near quota\n";
}

if (!empty($num_full)) {
  $mess .= "There are ".$num_full." mailboxes which are full\n";
  $mess .= "Full Mailboxes\n\n";
  foreach($full as $f) {
    $mess .= $f['mailbox']."\t\t\t".$f['quota']."\t\t\t".$f['used']."\n";
  }
} else {
  $mess .= "No mailboxes over quota\n";
}
if (!empty($mess)) {
  mail(RECIPIENT,'Mailbox Quota Summary', $mess);
  //echo $message;
}

if (isset($e)) {
  echo $e;
}

?>
