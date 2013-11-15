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

/*
 * When run through the control panel this script will be executed as psaadm,
 * a user without privs, and therefore unable to do anything on the filesystem
 * however an entry in the /etc/sudoers file solves that:
 *
 * psaadm  ALL = NOPASSWD: /usr/bin/du
 * "Defaults    requiretty" must also be commented out
 *
 */

function dirsize($dir) {
  if (detect_environment()==='HTTP') {
    $exec = '/usr/bin/sudo /usr/bin/du -bs '.escapeshellcmd($dir).'/';
    $out = exec($exec);
    $dirsize = explode(" ", $out);
    return $dirsize[0];
  } else {
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
}

function detect_environment() {
  if (php_sapi_name() == 'cli') {
    if (isset($_SERVER['TERM'])) {
      return 'SHELL';
    } else {
      return 'CRONTAB';
    }
  } else {
    return 'HTTP';
  }
}

?>
