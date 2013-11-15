<?php

/* CHANGE ME !
Just change the settings below: 
*/

define("MAILNAMES", "/var/qmail/mailnames/");
define("DOMAIN", "example.com");
define("ACCOUNT", "postmaster");
define("FROM", ACCOUNT."@".DOMAIN);
define("RECIPIENT", ACCOUNT."@".DOMAIN);
define("POPUSER",'popuser');
define("POPGROUP",'popuser');

define("LOWER_LIMIT", 90.0); // 90% full - approaching capacity
define("UPPER_LIMIT", 99.9); // 99.9% full - mailbox full

// You must set these!
$db_user = 'mailcheck';
$db_pass = '1234567890';
/* End of config */
