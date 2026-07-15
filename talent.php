<?php
declare(strict_types=1);

$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    casting_redirect('member.php?id=' . $id);
}
casting_redirect('search-users.php');
