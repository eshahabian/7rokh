<?php
declare(strict_types=1);
namespace CastingCms;
use RuntimeException, Throwable;

function template_revision(): string {
    static $digest;if($digest)return $digest;$parts=[];
    foreach(site_config()['sources'] as $file=>$expected){$actual=is_file(SITE_ROOT.'/'.$file)?hash_file('sha256',SITE_ROOT.'/'.$file):'';$parts[]=$file.':'.$actual;}
    return $digest=hash('sha256',implode('|',$parts));
}
function eligible_text(string $text): bool {static $allowed;$allowed??=array_fill_keys(site_config()['texts'],true);return isset($allowed[$text]);}
function menu_id(): string {return (string)($_GET['group']??$_POST['group']??($GLOBALS['cms_menu_id']??array_key_first(read_json('menus'))??''));}
function nav_groups(string $html): array {
    preg_match_all('~<nav\b[^>]*>[\s\S]*?</nav>~i',$html,$matches,PREG_OFFSET_CAPTURE);$groups=[];
    foreach($matches[0] as [$nav,$start]){
        preg_match_all('~<a\b[^>]*href=["\x27]([^"\x27]*)["\x27][^>]*>([\s\S]*?)</a>~i',$nav,$anchors,PREG_SET_ORDER|PREG_OFFSET_CAPTURE);$items=[];
        foreach($anchors as $i=>$a){$label=trim(html_entity_decode(strip_tags($a[2][0]),ENT_QUOTES|ENT_HTML5,'UTF-8'));$href=html_entity_decode($a[1][0],ENT_QUOTES|ENT_HTML5,'UTF-8');if(!eligible_text($label)||!$label)continue;$items[]=['id'=>'link-'.$i,'label'=>$label,'href'=>$href,'raw'=>$a[0][0],'start'=>$start+$a[0][1],'end'=>$start+$a[0][1]+strlen($a[0][0])];}
        if(!$items)continue;
        $signature=array_map(fn($i)=>[$i['label'],$i['href']],$items);
        $id=substr(hash('sha256',template_revision().json_encode($signature)),0,24);
        preg_match('~aria-label=["\x27]([^"\x27]+)~',$nav,$label);
        $groups[$id]=['id'=>$id,'title'=>html_entity_decode($label[1]??'منوی سایت',ENT_QUOTES|ENT_HTML5,'UTF-8').' · '.count($items).' گزینه','items'=>$items];
    }return $groups;
}
function apply_menus(string $html,array $s): string {
    $patches=[];foreach(nav_groups($html) as $id=>$group){$saved=$s['menus'][$id]??null;if(!$saved)continue;$old=$group['items'];$last=end($old);$links='';
        foreach($saved['items'] as $item){$href=$item['href'];if(!preg_match('~^(?:https?:|mailto:|tel:|/|#)~i',$href))$href=site_base().'/'.$href;
            $original=null;foreach($old as $o)if($o['id']===$item['id'])$original=$o;
            // Retain the original link's layout classes, but never its script handlers or notifications.
            $class='';if($original&&preg_match('~class=["\x27]([^"\x27]*)~',$original['raw'],$m))$class=' class="'.esc(trim(preg_replace('~\bis-active\b|\bhas-notify\b~','',$m[1]))).'"';
            $links.='<a'.$class.' href="'.esc($href).'">'.esc($item['label']).'</a>';
        }
        $patches[]=['start'=>$old[0]['start'],'end'=>$old[0]['end'],'value'=>$links];foreach(array_slice($old,1) as $o)$patches[]=['start'=>$o['start'],'end'=>$o['end'],'value'=>''];
    }return patch($html,$patches);
}
function replace_media(string $html,array $s): string {
    foreach($s['images'] as $id=>$image){$url=site_base().'/cast-admin/media.php?asset='.rawurlencode($id).'&v='.rawurlencode($image['file']);
        $html=preg_replace_callback('~(?<=["\x27(])[^"\x27\s()<>]+~',function($m)use($id,$url){$path=parse_url(html_entity_decode($m[0],ENT_QUOTES|ENT_HTML5,'UTF-8'),PHP_URL_PATH);if(!$path)return $m[0];$normalized=preg_replace('~^(?:\.\./|\./)+~','',$path);if($normalized===$id||$path===site_base().'/'.$id)return esc($url);return $m[0];},$html);
    }return $html;
}
function runtime_render(string $html,string $page,bool $preview): string {
    if(stripos($html,'<html')===false||stripos($html,'</body>')===false)return $html;
    $s=state();
    if(!$preview&&!$s['edits']&&!$s['menus']&&!$s['images']&&empty($s['design']))return $html;
    if($preview){
        // Snapshots stay private; API fields are restricted to literal template text.
        locked(function()use($html,$page){$name='page-'.hash('sha256',$page);$prior=read_json($name);$fields=text_scan($html,$page)['fields'];$signature=hash('sha256',json_encode(array_map(fn($f)=>[$f['id'],$f['value']],$fields)));
            if(($prior['signature']??'')!==$signature||($prior['template']??'')!==template_revision())write_json($name,['html'=>$html,'template'=>template_revision(),'signature'=>$signature]);
            $groups=read_json('menus');foreach(nav_groups($html) as $id=>$g){foreach($g['items'] as &$i)unset($i['raw'],$i['start'],$i['end']);unset($i);$groups[$id]=$g;}write_json('menus',$groups);
        });
    }
    $html=apply_edits($html,$page,$s['edits']);$html=apply_menus($html,$s);$html=replace_media($html,$s);
    // CSS is served through a path-preserving proxy only when image replacements exist.
    if($s['images'])$html=preg_replace_callback('~(<link\b[^>]*href=["\x27])([^"\x27]+\.css(?:\?[^"\x27]*)?)(["\x27])~i',function($m){$p=parse_url(html_entity_decode($m[2]),PHP_URL_PATH);$prefix=site_base().'/';if(str_starts_with($p,$prefix))$p=substr($p,strlen($prefix));if(!str_starts_with($p,'assets/')||!is_file(SITE_ROOT.'/'.$p))return $m[0];return $m[1].esc(site_base().'/cast-admin/css.php?asset='.rawurlencode($p)).$m[3];},$html);
    if(!$preview)return design_render($html,$page,$s,false);
    $scan=text_scan($html,$page);$byParent=[];foreach($scan['fields'] as $f)if(!$f['derived']&&design_field_allowed($GLOBALS['cms_preview_user']??null,$page,$f['scope'],$f['id']))$byParent[$f['parentStart']][]=$f['id'];$patches=[];
    foreach($scan['nodes'] as $n)if(isset($n['start'],$byParent[$n['start']]))$patches[]=['start'=>$n['start'],'end'=>$n['end'],'value'=>substr($n['raw'],0,-1).' data-editor-fields="'.esc(json_encode($byParent[$n['start']],JSON_UNESCAPED_UNICODE)).'">'];
    $html=patch($html,$patches);$html=design_render($html,$page,$s,true);$html=str_replace('</body>','<script src="'.esc(site_base()).'/cast-admin/preview.js"></script><script src="'.esc(site_base()).'/cast-admin/preview-ready.js"></script></body>',$html);return $html;
}

function start_runtime(): void {
    if(PHP_SAPI==='cli')return;
    $script=realpath($_SERVER['SCRIPT_FILENAME']??'');$page=null;foreach(site_config()['pages'] as $p)if(realpath(SITE_ROOT.'/'.$p['href'])===$script){$page=$p['id'];break;}if($page===null)return;
    $preview=false;
    if(isset($_GET['cms-preview'])){
        $originalSavePath=session_save_path();try{session_boot();$u=current_user();}catch(Throwable $e){http_response_code(503);exit('فضای خصوصی پنل در دسترس نیست.');}$valid=design_preview_allowed($u,$page)&&(time()-($_SESSION['previews'][$page]??0)<600);session_write_close();$_SESSION=[];session_id('');session_name('PHPSESSID');session_save_path($originalSavePath);
        if(!$valid){http_response_code(403);exit('ابتدا وارد پنل شوید و دسترسی طراحی این صفحه را دریافت کنید.');}$GLOBALS['cms_preview_user']=$u;$preview=true;header('X-Casting-Preview-Page: '.$page);header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');
    }
    ob_start(function($html)use($page,$preview){try{return runtime_render($html,$page,$preview);}catch(Throwable $e){error_log('Casting CMS render failed');return $html;}});
}
