<?php
define('MAIL_USERNAME', getenv('MUSEUM_MAIL_USERNAME') ?: '');
define('MAIL_PASSWORD', getenv('MUSEUM_MAIL_PASSWORD') ?: '');
define('MAIL_ADMIN', getenv('MUSEUM_MAIL_ADMIN') ?: '');
define('MAIL_FROM_NAME', 'Bảo tàng Văn hóa Dân tộc Khmer');
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
