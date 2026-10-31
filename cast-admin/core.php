<?php
declare(strict_types=1);
namespace CastingCms;
use RuntimeException, InvalidArgumentException, JsonException, Throwable;
require_once __DIR__.'/compat.php';
// Runtime data is deliberately outside the public document root and Git checkout.
const SITE_ROOT = __DIR__ . '/..';
function config(): array { static $c; return $c ??= require __DIR__.'/config.php'; }
function data_dir(): string {
    static $dir; if ($dir) return $dir;
    $root=realpath(SITE_ROOT); $public=realpath(($_SERVER['DOCUMENT_ROOT'] ?? '') ?: SITE_ROOT) ?: $root;
    $candidate=getenv('CASTING_CMS_DATA_DIR') ?: dirname($public).'/.fara-cms-'.config()['installation'];
    if (!@is_dir($candidate) && !@mkdir($candidate,0700,true)) throw new RuntimeException('Private storage is not writable');
    $resolved=@realpath($candidate);
    if (!$resolved || $resolved===$root || str_starts_with(strtolower($resolved).DIRECTORY_SEPARATOR,strtolower($root).DIRECTORY_SEPARATOR) || $resolved===$public || str_starts_with(strtolower($resolved).DIRECTORY_SEPARATOR,strtolower($public).DIRECTORY_SEPARATOR)) throw new RuntimeException('Storage must be outside document root');
    if(!@is_writable($resolved))throw new RuntimeException('Private storage is not writable');
    return $dir=$resolved;
}
function read_json(string $name,array $default=[]): array { $f=data_dir().'/'.$name.'.json';return is_file($f)?json_decode((string)@file_get_contents($f),true,512,JSON_THROW_ON_ERROR):$default; }
function write_json(string $name,array $value): void {
    $f=data_dir().'/'.$name.'.json'; $tmp=$f.'.'.bin2hex(random_bytes(6)).'.tmp';
    $bytes=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);if(@file_put_contents($tmp,$bytes)!==strlen($bytes)){@unlink($tmp);throw new RuntimeException('Write failed');}
    @chmod($tmp,0600);if(!@rename($tmp,$f)){@unlink($tmp);throw new RuntimeException('Atomic write failed');}
}
function locked(callable $fn,bool $checkSession=true) { $lock=@fopen(data_dir().'/write.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Lock failed');try{if($checkSession&&isset($_SESSION['uid'])&&!current_user())throw new RuntimeException('Session revoked');return $fn();}finally{flock($lock,LOCK_UN);fclose($lock);} }
function initial_state(): array {return ['edits'=>[],'menu'=>null,'menus'=>[],'images'=>[],'version'=>0];}

function state(): array {return read_json('state',initial_state());}
function commit_state(array $next,array $change=[]): void {
    $before=state();$next['version']=$before['version']+1;
    // State and undo are one atomic transaction, including across a failed disk write.
    unset($before['_undo']);$next['_undo']=$before;$next['_lastChange']=$change;write_json('state',$next);
}

function site_config(): array { static $s;return $s??=json_decode(file_get_contents(__DIR__.'/catalog.json'),true,512,JSON_THROW_ON_ERROR); }

function page_config(string $id): array {foreach(site_config()['pages'] as $p)if($p['id']===$id)return $p;throw new InvalidArgumentException('صفحه پیدا نشد.');}
function esc(string $s): string {return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function revision(string $s): string {return hash('sha256',$s);}
function text_scan(string $html,string $page): array {
    $editState=state()['edits'];$nodes=[['tag'=>'root','path'=>'','counts'=>[],'textCount'=>0,'raw'=>'']];$stack=[0];$fields=[];
    preg_match_all('~<!--[\s\S]*?-->|<![^>]*>|</?[A-Za-z][A-Za-z0-9:-]*(?:"[^"]*"|\x27[^\x27]*\x27|[^\x27">])*>|[^<]+|<~',$html,$matches,PREG_OFFSET_CAPTURE);
    foreach($matches[0] as [$raw,$offset]){
        $parentId=end($stack);$parent=&$nodes[$parentId];
        if(in_array($parent['tag'],['script','style'],true)&&!str_starts_with(strtolower($raw),'</'.$parent['tag']))continue;
        if(str_starts_with($raw,'<!--')||str_starts_with($raw,'<!'))continue;
        if($raw[0]==='<'){
            if(!preg_match('~^<(/?)([\w:-]+)~',$raw,$m))continue;$tag=strtolower($m[2]);
            if($m[1]){for($j=count($stack)-1;$j>0;$j--)if($nodes[$stack[$j]]['tag']===$tag){$nodes[$stack[$j]]['closeStart']=$offset;$stack=array_slice($stack,0,$j);break;}continue;}
            $nth=$parent['counts'][$tag]??0;$parent['counts'][$tag]=$nth+1;
            $node=['tag'=>$tag,'path'=>$parent['path'].'/'.$tag.'['.$nth.']','counts'=>[],'textCount'=>0,'start'=>$offset,'end'=>$offset+strlen($raw),'raw'=>$raw];$nodes[]=$node;$nodeId=count($nodes)-1;
            if(!preg_match('~^(area|base|br|col|embed|hr|img|input|link|meta|param|source|track|wbr)$~',$tag)&&!str_ends_with($raw,'/>'))$stack[]=$nodeId;continue;
        }
        $nth=$parent['textCount']++;$skip=false;$shared=null;$menu=false;$toc=false;
        foreach($stack as $i){$n=$nodes[$i];if(in_array($n['tag'],['head','script','style','noscript','svg','button'],true)||preg_match('~aria-hidden\s*=\s*["\x27]true~',$n['raw']))$skip=true;if(($n['tag']==='footer'||($n['tag']==='header'&&preg_match('~class=["\x27][^"\x27]*site-header~',$n['raw'])))&&$shared===null)$shared=$n;if($n['tag']==='nav')$menu=true;if(preg_match('~class=["\x27][^"\x27]*fara-sector-strip~',$n['raw']))$menu=true;if(preg_match('~class=["\x27][^"\x27]*profile-contents~',$n['raw']))$toc=true;}
        $value=trim(html_entity_decode($raw,ENT_QUOTES|ENT_HTML5,'UTF-8'));if($skip||!preg_match('~[\p{L}\p{N}]~u',$value))continue;
        if(!eligible_text($value)&&!isset($editState[($shared?'shared:'.$shared['tag']:$page).($shared?substr($parent['path'],strlen($shared['path'])):$parent['path']).'/text['.$nth.']@'.substr(template_revision(),0,16)]))continue;
        $scope=$shared?$shared['tag']:'page';$relative=$shared?substr($parent['path'],strlen($shared['path'])):$parent['path'];
        $key=($shared?'shared:'.$scope:$page).$relative.'/text['.$nth.']';
        $key.='@'.substr(template_revision(),0,16);
        $labels=['h1'=>'عنوان اصلی','h2'=>'عنوان بخش','h3'=>'عنوان فرعی','p'=>'متن','li'=>'گزینهٔ فهرست','a'=>'متن لینک','small'=>'متن کوتاه','strong'=>'متن برجسته'];
        $fields[]=['id'=>$key,'scope'=>$scope,'label'=>$labels[$parent['tag']]??'متن','value'=>$value,'derived'=>$menu||($toc&&$parent['tag']==='a'&&preg_match('~href=["\x27]#profile-~',$parent['raw'])),'menu'=>$menu,'start'=>$offset,'end'=>$offset+strlen($raw),'raw'=>$raw,'parentStart'=>$parent['start'],'parentEnd'=>$parent['end'],'parentPath'=>$relative];
    }
    unset($parent);return ['fields'=>$fields,'nodes'=>$nodes];
}
function patch(string $html,array $replacements): string {usort($replacements,fn($a,$b)=>$b['start']<=>$a['start']);foreach($replacements as $r)$html=substr($html,0,$r['start']).$r['value'].substr($html,$r['end']);return $html;}
function apply_edits(string $html,string $page,array $edits): string {
    $scan=text_scan($html,$page);$replacements=[];$derived=[];$known=[];
    foreach($scan['fields'] as $f){$known[$f['id']]=true;if($f['derived']||!isset($edits[$f['id']]))continue;$edit=$edits[$f['id']];if($f['value']!==$edit['original']&&$f['value']!==$edit['value'])continue;
        preg_match('~^\s*~u',$f['raw'],$pre);preg_match('~\s*$~u',$f['raw'],$post);$replacements[]=['start'=>$f['start'],'end'=>$f['end'],'value'=>$pre[0].esc($edit['value']).$post[0]];
        if($f['scope']==='page')$derived[$f['value']]=$edit['value'];
    }
    foreach($scan['fields'] as $f)if($f['derived']&&!$f['menu']&&isset($derived[$f['value']]))$replacements[]=['start'=>$f['start'],'end'=>$f['end'],'value'=>esc($derived[$f['value']])];
    return patch($html,$replacements);
}
function menu_items(array $s): array { $id=menu_id();$m=read_json('menus');return $s['menus'][$id]['items']??$m[$id]['items']??[]; }

function render_page(string $id,?array $s=null): string {
    page_config($id);$s??=state();$snapshot=read_json('page-'.hash('sha256',$id));
    if(($snapshot['template']??'')!==template_revision())return '';
    return apply_edits($snapshot['html']??'', $id, $s['edits']);
}

function fields_for(string $html,string $page,array $s): array {
    $snapshot=read_json('page-'.hash('sha256',$page));if(($snapshot['template']??'')!==template_revision())return [];
    $fields=array_values(array_filter(text_scan($snapshot['html']??'',$page)['fields'],fn($f)=>!$f['derived']));
    foreach($fields as &$f)if(isset($s['edits'][$f['id']]))$f['value']=$s['edits'][$f['id']]['value'];unset($f);return $fields;
}

function image_catalog(bool $withRevision=true): array {
    $s=state();$out=[];foreach(site_config()['images'] as $item){$id=$item['id'];$file=isset($s['images'][$id])?data_dir().'/media/'.$s['images'][$id]['file']:SITE_ROOT.'/'.$id;
    if(!is_file($file))continue;if($withRevision)$item['revision']=hash_file('sha256',$file);$item['url']=site_base().'/cast-admin/media.php?asset='.rawurlencode($id);$out[]=$item;}return $out;
}

function site_base(): string { return config()['basePath']; }

function is_local(): bool {return in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)&&in_array(explode(':',$_SERVER['HTTP_HOST']??'')[0],['127.0.0.1','localhost'],true);}
function https(): bool {return (!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||($_SERVER['SERVER_PORT']??'')==='443';}
function session_boot(): void {
    if(PHP_SAPI==='cli')return;
    if(!https()&&!is_local()){http_response_code(403);exit('برای ورود به پنل، سایت را با HTTPS باز کنید.');}
    ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');ini_set('session.use_trans_sid','0');
    session_name('FARA_'.config()['installation']);session_set_cookie_params(['lifetime'=>0,'path'=>site_base().'/','secure'=>https(),'httponly'=>true,'samesite'=>'Strict']);session_save_path(data_dir());if(!@session_start())throw new RuntimeException('Session unavailable');
    if(isset($_SESSION['last'])&&(time()-$_SESSION['last']>1800||time()-($_SESSION['created']??0)>28800))$_SESSION=[];
    $_SESSION['last']=time();$_SESSION['created']??=time();$_SESSION['csrf']??=bin2hex(random_bytes(32));
}
function current_user(): ?array {$id=$_SESSION['uid']??null;if(!$id)return null;$users=read_json('users');$u=$users[$id]??null;return $u&&$u['active']&&($u['version']??0)===($_SESSION['uv']??-1)?$u+['id'=>$id]:null;}
function public_user(array $u): array {return array_intersect_key($u,array_flip(['id','username','role','active','mustChange']))+['permissions'=>design_permissions($u)];}
function api_json(int $status,array $data): void {http_response_code($status);header('Content-Type: application/json; charset=utf-8');try{$data['stateVersion']=state()['version'];}catch(Throwable $e){/* Error responses must also work when private storage is unavailable. */}echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;}

function audit(string $action): void { /* Account/content commits must not fail after an auxiliary log write. */ }

require_once __DIR__.'/permissions.php';
require_once __DIR__.'/runtime.php';
require_once __DIR__.'/design.php';
