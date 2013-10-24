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

$sql="SELECT domains.name, mail.id, mail.mail_name, mail.mbox_quota, Limits.value as domain_mbox_quota FROM mail 
JOIN domains ON mail.dom_id = domains.id 
JOIN Limits ON domains.limits_id = Limits.id 
WHERE Limits.limit_name = 'mbox_quota'
ORDER BY domains.name ASC, mail.mail_name ASC";

$result = mysql_query($sql);
$o = ''; $num_over = 0; $num_full = 0; $totalmailboxes = 0; $unlimited = array();
if (mysql_num_rows($result) > 0) {
  while ($mailbox = mysql_fetch_assoc($result)) {
    // Catch unlimited mailboxes
    if (($mailbox['mbox_quota'] == '-1') && ($mailbox['domain_mbox_quota'] == '-1')) $unlimited[] = array('mailbox' => $mailbox['mail_name'].'@'.$mailbox['name']);
    $mailbox['maildir_path'] = MAILNAMES.$mailbox['name']."/".$mailbox['mail_name']."/Maildir";
    $mailbox['maildir_size'] = dirsize($mailbox['maildir_path']);
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
          $message = compose_message($mailbox, $percentuse, $quota, true);

          if (defined(DELIVER_TO_ADMIN) && detect_environment()!='HTTP') {
            $admin_dir = MAILNAMES.DOMAIN."/".ACCOUNT."/Maildir/new/".$msgid;
            add_message_to_maildir($admin_dir, $message);
          }
          if (defined(DELIVER_TO_USER) && detect_environment()!='HTTP') {
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
          $message = compose_message($mailbox, $percentuse, $quota, false);

          if (defined(DELIVER_TO_ADMIN) && detect_environment()!='HTTP') {
            $admin_dir=MAILNAMES.DOMAIN."/".ACCOUNT."/Maildir/new/".$msgid;
            add_message_to_maildir($admin_dir, $message);
          }
          if (defined(DELIVER_TO_USER) && detect_environment()!='HTTP') {
            $user_dir = MAILNAMES.$mailbox['name']."/".$mailbox['mail_name']."/Maildir/new/".$msgid;
            add_message_to_maildir($user_dir, $message);
          }
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
    //echo $message;
  } else {
    // print report to screen
    include_once(PRODUCT_ROOT."/admin/plib/modules/pm.php");
    if (!$session->chkLevel(IS_ADMIN)) {
      pm_alert(pm_lmsg('__perm_denied').'You must be admin to use this page');
      go_to_uplevel();
    }
    print nl2br($mess);
    print "<table><tr><th>Mailbox</th><th>Size (MB)</th><th>Quota (MB)</th><th>Percent Usage</th></tr>";

    $sort = $_GET['sort'];
    $order = $_GET['order'];
    if (empty($order)) $order = 'desc';
    if (!empty($sort)) {
      // Obtain a list of columns
      foreach ($report as $key => $row) {
        $email[] = $row['email'];
        $size[] = $row['size'];
        $percentuse[] = $row['percentuse'];
        $quota[] = $row['quota'];
      }
      $sort = $$sort;
      switch($order) {
        case 'asc':
          $sort_order = 'SORT_ASC';
          break;
        case 'desc':
          $sort_order = 'SORT_DESC';
          break;
      }
      array_multisort($sort, $sort_order, $report);
    }
    foreach ($report as $rep_row) {
      $warning_class = ($rep_row['percentuse'] > LOWER_LIMIT ? 'red' : '');
      if ($rep_row['domain'] != $previous_domain) {
        print "<tr><td colspan='3'>".$rep_row['domain']."</td></tr>";
      }
      print "<tr class='".$warning_class."'><td>".$rep_row['email']."</td><td>".$rep_row['size']."</td><td>".$rep_row['quota']."</td><td>".$rep_row['percentuse']."%</td></tr>";
      $previous_domain = $rep_row['domain'];
    }
    print "</table>";
  }

}

if (isset($e)) {
  echo $e;
}

?>
