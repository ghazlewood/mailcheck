<?php


$k = 1024;
$m = 1048576;
$g = 1073741824;

function to_bytes($value, $unit) {
  global $k, $m, $g;
  switch (trim($unit)) {
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
  $cmd = '/usr/bin/du -bs "'.$dir.'/"';
  if (is_dir($dir)) {
     $res = `$cmd`;
     $res = explode(" ", $res);
     return $res[0];
  } else {
     echo "Couldn\'t run command: ".$cmd." Not a directory: ".$dir."\n";
     return 0;
  }
}

function compose_message($mailbox, $percentage, $quota, $over_full_quota) {

  if ($over_full_quota) {
    $subject = "WARNING! Mailbox: \"".$mailto."\" mail delivery failing - mailbox full\n";
    $warning = "Your mailbox has reached maximum capacity, no more email can be delivered to it until some of the existing email is deleted.\n";

  } else {
    $subject = "WARNING! Mailbox: \"".$mailto."\" approaching capacity\n";
    $warning = "Your mailbox has reached a capacity of ".$percentage."% full.\n";
  }

  $mailto = $mailbox['mail_name']."@".$mailbox['name'];
  $message = "Return-Path: <".FROM.">\n";
  $message .= "Delivered-To: ".$mailto."\n";
  $message .= "Date: ".date("j M Y G:i:s")." +0200\n";
  $message .= "X-Priority: 1\n";
  $msgid = date("YmdHis").".".rand(10000,99999).".qmail@".DOMAIN;
  $message .= "Message-ID: <".$msgid.">\n";
  $message .= "To: ".$mailto."\n";
  $message .= "Subject: ".$subject;
  $message .= "From: Mailserver <".FROM.">\n";
  $message .= "\n\n";
  $body = "Dear mail user,\n\n";
  $body .= $warning;
  $body .= "To ensure that you can continue to receive email please download or delete some messages immediately using either
  your regular email client (Outlook, Outlook Express, Entourage etc.) or the webmail client at http://webmail.".$mailbox['name']."\n\n";
  $body .= "Used:  ".$quota['sizemb']."MB\n";
  $body .= "Quota: ".$quota['quotamb']."MB\n";
  $body .= "(".$percentage."% full)\n\n";
  $body .= "This is an automatically generated email.";
  $message .= wordwrap($body,80);
  return $message;
}

function add_message_to_maildir($maildir_dir, $message) {
  file_put_contents($maildir_dir, $message);
  chown($maildir_dir, POPUSER);
  chgrp($maildir_dir, POPGROUP);
}

?>