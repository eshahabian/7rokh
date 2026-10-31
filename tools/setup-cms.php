<?php
// This creates only a new installation. Never run it to reset online users.
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__);$private=$argv[1]??'';$base=$argv[2]??'/casting-portal';
if(is_file($root.'/cast-admin/config.php')){fwrite(STDERR,"Installation already exists; no changes made.\n");exit(1);}
if(!$private||!preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~',$private)||!preg_match('~^(?:/[a-zA-Z0-9_-]+)*$~D',$base))exit(2);
if(!is_dir($private)&&!mkdir($private,0700,true))exit(3);$private=realpath($private);$repo=realpath($root);if($private===$repo||str_starts_with(strtolower($private).DIRECTORY_SEPARATOR,strtolower($repo).DIRECTORY_SEPARATOR))exit(4);
$installation='7rokh-'.bin2hex(random_bytes(10));$activation=bin2hex(random_bytes(32));$temporary=bin2hex(random_bytes(12));
$config=['installation'=>$installation,'basePath'=>$base,'activationHash'=>hash('sha256',$activation),'bootstrapHash'=>password_hash($temporary,PASSWORD_DEFAULT)];
$guide="راهنمای خصوصی فعال‌سازی پنل ۷ رخ\nاین فایل را به گیت یا هاست عمومی نفرستید.\nنشانی: https://7rokh.com".$base."/cast-admin/\nنام کاربری: eshahabian\nرمز موقت: ".$temporary."\nکد فعال‌سازی: ".$activation."\nشناسه نصب: ".$installation."\nرمز دائمی را خودتان در فرم تعیین کنید. فعال‌سازی و تغییر رمز محلی از هاست مستقل است.\n";
$path=$private.'/activation.txt';if(file_put_contents($path,$guide)===false)exit(5);chmod($path,0600);
file_put_contents($root.'/cast-admin/config.php',"<?php\n// Installation-specific hashes only. Keep this ID stable across releases.\nreturn ".var_export($config,true).";\n");
echo "Installation initialized. Secrets written to private activation.txt; never printed.\n";
