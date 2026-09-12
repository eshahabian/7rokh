<?php
/**
 * این مسیر دیگر endpoint وب نیست — فقط جلوگیری از افشای سلامت از /includes/
 */
declare(strict_types=1);

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
echo "Not Found\n";
exit;
