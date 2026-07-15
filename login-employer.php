<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$qs = $_SERVER['QUERY_STRING'] ?? '';
casting_redirect('login.php' . ($qs !== '' ? '?' . $qs : ''));
