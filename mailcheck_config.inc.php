<?php

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
