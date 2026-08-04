<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

// مسیر قدیمی — کشف استعداد یکپارچه در جستجوی کاربران
casting_require_casting_user();
casting_redirect('search-users.php');
