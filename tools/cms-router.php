<?php
// Development router: Apache's .htaccess restrictions are reproduced here.
declare(strict_types=1);
require_once dirname(__DIR__).'/cast-admin/compat.php';
use function CastingCms\str_starts_with, CastingCms\str_ends_with, CastingCms\str_contains;
$root=dirname(__DIR__);$config=require $root.'/cast-admin/config.php';$base=$config['basePath'];$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/');
if(!str_starts_with($path,$base.'/')||str_contains($path,'..')||str_contains($path,'\\')||str_contains($path,"\0")){http_response_code(404);exit;}
$rel=substr($path,strlen($base)+1);if($rel===''||str_ends_with($rel,'/'))$rel.='index.php';
if(preg_match('~^(?:includes|mu-plugin|tools|\.cms-tests|\.)/|(?:^|/)\.|^config(?:\.local)?\.php$|^cast-admin/(?:core|compat|design|runtime|content-api|permissions|setup-error|config)\.php$|^cast-admin/.*\.(json|html)$~i',$rel)){http_response_code(403);exit;}
$file=realpath($root.'/'.$rel);if(!$file||!is_file($file)||!str_starts_with($file,realpath($root).DIRECTORY_SEPARATOR)){http_response_code(404);exit;}
if(strtolower(pathinfo($file,PATHINFO_EXTENSION))==='php'){$_SERVER['SCRIPT_FILENAME']=$file;$_SERVER['SCRIPT_NAME']=$base.'/'.$rel;require $file;return;}
$mime=['css'=>'text/css','js'=>'text/javascript','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','woff2'=>'font/woff2','svg'=>'image/svg+xml'];header('Content-Type: '.($mime[strtolower(pathinfo($file,PATHINFO_EXTENSION))]??'application/octet-stream'));header('X-Content-Type-Options: nosniff');readfile($file);
