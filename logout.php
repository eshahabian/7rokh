<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

wp_logout();
casting_set_flash('success', 'با موفقیت خارج شدید.');
casting_redirect('index.php');
