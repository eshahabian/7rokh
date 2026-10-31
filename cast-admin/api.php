<?php
declare(strict_types=1);
namespace CastingCms;
use Throwable, InvalidArgumentException, JsonException;
require __DIR__.'/core.php';
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');
try {
 session_boot();$route=$_GET['r']??'';$method=$_SERVER['REQUEST_METHOD'];$data=[];
 if($method==='POST'){
  if(!str_starts_with($_SERVER['CONTENT_TYPE']??'','application/json'))api_json(415,['error'=>'قالب درخواست معتبر نیست.']);
  if(!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_EDITOR_TOKEN']??''))api_json(403,['error'=>'نشست معتبر نیست؛ پنل را دوباره باز کنید.']);
  if(($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site')api_json(403,['error'=>'درخواست خارج از پنل مجاز نیست.']);
  $origin=$_SERVER['HTTP_ORIGIN']??'';if($origin!==''&&$origin!==(https()?'https://':'http://').($_SERVER['HTTP_HOST']??''))api_json(403,['error'=>'مبدأ درخواست معتبر نیست.']);
  $raw=file_get_contents('php://input',false,null,0,16*1024*1024+1);if(strlen($raw)>16*1024*1024)api_json(413,['error'=>'حجم درخواست بیش از حد مجاز است.']);$data=json_decode($raw,true,32,JSON_THROW_ON_ERROR);if(!is_array($data))api_json(400,['error'=>'درخواست معتبر نیست.']);
 }elseif($method!=='GET')api_json(405,['error'=>'روش درخواست معتبر نیست.']);
 if($route==='session'&&$method==='GET'){$u=current_user();api_json(200,['user'=>$u?public_user($u):null,'csrf'=>$_SESSION['csrf'],'needsActivation'=>!is_file(data_dir().'/users.json')]);}
 if($route==='login'&&$method==='POST'){
  foreach(['username'=>40,'password'=>128,'activation'=>100] as $key=>$limit)if(isset($data[$key])&&(!is_string($data[$key])||strlen($data[$key])>$limit))api_json(400,['error'=>'اطلاعات ورود معتبر نیست.']);
  $result=locked(function()use($data){
   $key=hash('sha256',$_SERVER['REMOTE_ADDR']??'unknown');$attempts=read_json('attempts');$attempts=array_filter($attempts,fn($a)=>($a['since']??0)>time()-900);$a=$attempts[$key]??['count'=>0,'since'=>time()];
   if($a['count']>=8)return [429,['error'=>'تعداد تلاش زیاد است؛ ۱۵ دقیقه بعد دوباره امتحان کنید.']];
   $users=read_json('users');$user=null;$uid=null;
   if(!$users){
    $valid=is_string($data['activation']??null)&&hash_equals(config()['activationHash'],hash('sha256',$data['activation']));
    if($valid&&($data['username']??'')==='eshahabian'&&password_verify((string)($data['password']??''),config()['bootstrapHash'])){
     $uid=bin2hex(random_bytes(12));$user=['username'=>'eshahabian','hash'=>config()['bootstrapHash'],'role'=>'admin','active'=>true,'mustChange'=>true,'version'=>1];$users[$uid]=$user;write_json('users',$users);
    }
   }else{foreach($users as $id=>$u)if(strcasecmp($u['username'],(string)($data['username']??''))===0){$uid=$id;$user=$u;break;}if(!$user||!$user['active']||!password_verify((string)($data['password']??''),$user['hash'])){$user=null;password_verify('dummy',config()['bootstrapHash']);}}
   if(!$user){$a['count']++;$attempts[$key]=$a;write_json('attempts',$attempts);return [401,['error'=>'اطلاعات ورود یا کد فعال‌سازی درست نیست.']];}
   unset($attempts[$key]);write_json('attempts',$attempts);session_regenerate_id(true);$_SESSION=['uid'=>$uid,'uv'=>$user['version'],'csrf'=>bin2hex(random_bytes(32)),'last'=>time(),'created'=>time()];audit('login');return [200,['ok'=>true,'user'=>public_user($user+['id'=>$uid])]];
  },false);api_json(...$result);
 }
 $user=current_user();if(!$user)api_json(401,['error'=>'برای ادامه وارد پنل شوید.']);
 if($route==='logout'&&$method==='POST'){$_SESSION=[];session_regenerate_id(true);api_json(200,['ok'=>true]);}
 if($route==='password'&&$method==='POST'){
  $result=locked(function()use($data,$user){$users=read_json('users');$u=$users[$user['id']];$new=$data['password']??'';
   if(!is_string($new)||strlen($new)<12||strlen($new)>128||$new!==($data['confirm']??'')||!password_verify((string)($data['current']??''),$u['hash'])||password_verify($new,$u['hash']))return [400,['error'=>'رمز فعلی باید درست باشد؛ رمز جدید باید متفاوت و حداقل ۱۲ نویسه و با تکرارش یکسان باشد.']];
   $u['hash']=password_hash($new,PASSWORD_DEFAULT);$u['mustChange']=false;$u['version']++;$users[$user['id']]=$u;write_json('users',$users);session_regenerate_id(true);$_SESSION['uv']=$u['version'];audit('password-change');return [200,['ok'=>true]];
  });api_json(...$result);
 }
 if($user['mustChange'])api_json(403,['error'=>'پیش از استفاده از پنل باید رمز موقت را تغییر دهید.','mustChange'=>true]);
 if($route==='users'){
  if($user['role']!=='admin')api_json(403,['error'=>'مدیریت کاربران فقط برای مدیر مجاز است.']);
  if($method==='GET'){api_json(200,['users'=>array_map(fn($id,$u)=>public_user($u+['id'=>$id]),array_keys(read_json('users')),array_values(read_json('users'))),'permissionOptions'=>design_permission_options()]);}
  $result=locked(function()use($data,$user){$users=read_json('users');$action=$data['action']??'';$id=$data['id']??'';
   if(!password_verify((string)($data['adminPassword']??''),$users[$user['id']]['hash']))return [403,['error'=>'رمز فعلی مدیر درست نیست.']];
   if($action==='add'){
    $name=$data['username']??'';$pass=$data['password']??'';$role=$data['role']??'';
    if(!is_string($name)||!preg_match('~^[A-Za-z0-9_.-]{3,40}$~D',$name)||!is_string($pass)||strlen($pass)<12||strlen($pass)>128||!in_array($role,['admin','editor'],true)||count($users)>=50)return [400,['error'=>'نام کاربری ۳ تا ۴۰ حرف انگلیسی و رمز موقت حداقل ۱۲ نویسه باشد.']];
    foreach($users as $u)if(strcasecmp($u['username'],$name)===0)return [409,['error'=>'این نام کاربری قبلاً ثبت شده است.']];
    $id=bin2hex(random_bytes(12));$users[$id]=['username'=>$name,'hash'=>password_hash($pass,PASSWORD_DEFAULT),'role'=>$role,'active'=>true,'mustChange'=>true,'version'=>1,'permissions'=>validate_design_permissions($data['permissions']??[])];
   }else{
    if(!isset($users[$id]))return [404,['error'=>'کاربر پیدا نشد.']];
    if($action==='reset'){$pass=$data['password']??'';if(!is_string($pass)||strlen($pass)<12||strlen($pass)>128)return [400,['error'=>'رمز موقت حداقل ۱۲ نویسه باشد.']];$users[$id]['hash']=password_hash($pass,PASSWORD_DEFAULT);$users[$id]['mustChange']=true;$users[$id]['version']++;}
    elseif($action==='update'){$role=$data['role']??'';if(!in_array($role,['admin','editor'],true)||!is_bool($data['active']??null))return [400,['error'=>'نقش یا وضعیت معتبر نیست.']];$users[$id]['role']=$role;$users[$id]['active']=$data['active'];$users[$id]['version']++;}
    elseif($action==='permissions'){if($users[$id]['role']==='admin')return [400,['error'=>'مدیر طراحی به تمام بخش‌های طراحی دسترسی دارد.']];$users[$id]['permissions']=validate_design_permissions($data['permissions']??null);$users[$id]['version']++;}
    else return [400,['error'=>'عملیات معتبر نیست.']];
    if($id===$user['id']&&(!$users[$id]['active']||$users[$id]['role']!=='admin'))return [400,['error'=>'نمی‌توانی حساب مدیر خودت را غیرفعال کنی یا نقش آن را کاهش بدهی.']];
   }
   if(!array_filter($users,fn($u)=>$u['active']&&$u['role']==='admin'&&!$u['mustChange']))return [400,['error'=>'حداقل یک مدیر فعال با رمز قطعی باید باقی بماند.']];
   write_json('users',$users);if($id===$user['id'])$_SESSION['uv']=$users[$id]['version'];audit('users-'.$action);return [200,['ok'=>true]];
  });api_json(...$result);
 }
 require_once __DIR__.'/content-api.php';
 if($method==='GET')api_json(...content_get($route));
 api_json(...locked(fn()=>content_post($route,$data)));
}catch(InvalidArgumentException|JsonException $e){api_json(400,['error'=>'اطلاعات درخواست معتبر نیست.']);}catch(Throwable $e){error_log('Casting CMS operation failed');api_json(500,['error'=>'عملیات انجام نشد؛ مسیر خصوصی و مجوزهای PHP را بررسی کنید.']);}
