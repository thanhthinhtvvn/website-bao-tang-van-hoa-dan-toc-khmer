<?php
// SMTP credentials are provided by the server environment.
$mailUsername = getenv('MUSEUM_MAIL_USERNAME');
$mailPassword = getenv('MUSEUM_MAIL_PASSWORD');
$mailAdmin = getenv('MUSEUM_MAIL_ADMIN');

define('MAIL_USERNAME', $mailUsername === false ? '' : $mailUsername);
define('MAIL_PASSWORD', $mailPassword === false ? '' : $mailPassword);
define('MAIL_ADMIN', $mailAdmin === false ? '' : $mailAdmin);
define('MAIL_FROM_NAME', 'Bảo tàng Văn hóa Dân tộc Khmer');
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
