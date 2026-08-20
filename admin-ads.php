<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$status = sanitize_key((string) ($_GET['status'] ?? 'pending'));
if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
    $status = 'pending';
}
casting_redirect('my-ads.php?tab=inbox&status=' . rawurlencode($status));
