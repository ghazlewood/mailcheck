<?php
// Plesk Mailbox checkscript door Ramon Verhagen (mooiesite.nl)
// (c) 2006

// English version and modifications by George Hazlewood (www.layer1.co.uk)
// With permission April 2007
// Added some extra stuff to show actual usage in the emails.
// Defaults to only sending notification to the postmaster account but switch the config to deliver to the end user too.
// Control panel reporting inspired by http://www4.atomicorp.com/channels/source/atomic-yum/

include(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'mailcheck_lib.inc.php');
include(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'mailcheck_config.inc.php');

$link = mysql_connect("localhost", $db_user, $db_pass);
mysql_select_db("psa", $link);

chdir(MAILNAMES);
ini_set('open_basedir', MAILNAMES);
ini_set('safe_mode',0);
chdir(MAILNAMES);

echo mysql_error($link);

$sql="SELECT domains.name, mail.id, mail.mail_name FROM mail 
JOIN domains ON mail.dom_id = domains.id 
ORDER BY domains.name ASC, mail.mail_name ASC";

$result = mysql_query($sql);
$o = ''; $num_over = 0; $num_full = 0; $totalmailboxes = 0; $unlimited = array();
if (mysql_num_rows($result) > 0) {
  while ($mailbox = mysql_fetch_assoc($result)) {
    $mailbox['maildir_path'] = MAILNAMES.$mailbox['name']."/".$mailbox['mail_name']."/Maildir";
    $mailbox['maildir_size'] = dirsize($mailbox['maildir_path']);
    $mailbox['mbox_quota'] = read_maildirsize_quota($mailbox['maildir_path']);
    // Catch unlimited mailboxes
    if (($mailbox['mbox_quota'] == '-1') && ($mailbox['domain_mbox_quota'] == '-1')) $unlimited[] = array('mailbox' => $mailbox['mail_name'].'@'.$mailbox['name']);
    $totalmailboxes++;
    if ($mailbox['mbox_quota']=='-1') $mailbox['mbox_quota'] = $mailbox['domain_mbox_quota'];
    $quota = array(
      'sizemb' => round(bytes_to($mailbox['maildir_size'],'MB'),2),
      'quotamb' => round(bytes_to($mailbox['mbox_quota'],'MB'),2)
    );
    if ($mailbox['mbox_quota']>0) {
      $percentage = $mailbox['maildir_size'] / $mailbox['mbox_quota'];
      $percentuse = round($percentage*100,2);
    } else {
      $percentage = 0;
      $percentuse = 0;
    }

    $report[] = array(
      'email' => $mailbox['mail_name'].'@'.$mailbox['name'],
      'size' => $quota['sizemb'],
      'quota' =>$quota['quotamb'],
      'percentuse' => $percentuse,
      'domain' => $mailbox['name']
    );

    if ($percentuse > LOWER_LIMIT) {

        if ($percentuse > UPPER_LIMIT) {
          $full[] = array(
            'mailbox' => $mailbox['mail_name'].'@'.$mailbox['name'], 
            'quota'=>$quota['quotamb'], 
            'used'=>$quota['sizemb']
          );
          $num_full++;
        } else {
          // Find out if this is a mailbox which also has an active redirect and therefore might also
          // fill up without every being emptied
          $redirect_sql = "SELECT `address` FROM `mail_redir` WHERE `mn_id` = ".$mailbox['id']." LIMIT 1";
          $redirect_result = mysql_query($redirect_sql);
          if (mysql_num_rows($redirect_result) > 0) {
            $redirect_row = mysql_fetch_assoc($redirect_result);
          } else {
            $redirect_row['address'] = '';
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
  $mess .= "There are ".$num_over." mailboxes near quota limit (Over " . LOWER_LIMIT . "% full).\n\n";
  foreach($over as $ov) {
    $mess .= $ov['mailbox']."\t\t\t".$ov['quota']."\t\t\t".$ov['used']."\n";
  }
  $mess .= "\n\n";
} else {
  $mess .= "No mailboxes near quota (" . LOWER_LIMIT . "%)\n\n";
}

if (!empty($num_full)) {
  $mess .= "There are ".$num_full." mailboxes which are full.\n\n";
  foreach($full as $f) {
    $mess .= $f['mailbox']."\t\t\t".$f['quota']."\t\t\t".$f['used']."\n";
  }
} else {
  $mess .= "No mailboxes over quota\n\n";
}

if (!empty($unlimited)) {
  $mess .= "The are " . count($unlimited) . " mailbox(es) without domain or mailbox quota limits: \n";
  foreach($unlimited as $u) {
    $mess .= $u['mailbox']."\n";
  }
}
if (!empty($mess)) {
  if (detect_environment()!='HTTP') {
    // Only send an email if there are any mailboxes over the limit or quota
    if ( ($num_over>0) || ($num_full>0) ) {
      mail(RECIPIENT, 'Mailbox Quota Summary: '.$num_over.' over '.LOWER_LIMIT.'% and '.$num_full.' full mailboxes', $mess);
    }
    //echo $mess;
  } 

}

if (isset($e)) {
  echo $e;
}
