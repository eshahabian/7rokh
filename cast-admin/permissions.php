<?php
declare(strict_types=1);
namespace CastingCms;
use InvalidArgumentException;

function design_permissions(?array $user): array {
    $p=$user['permissions']??[];
    return ['pages'=>$p['pages']??[], 'shared'=>$p['shared']??[], 'fields'=>$p['fields']??[], 'menus'=>$p['menus']??[], 'images'=>$p['images']??[]];
}
function design_can(?array $user,string $type,string $id): bool {
    if(!$user||!$user['active']||$user['mustChange'])return false;
    return $user['role']==='admin'||in_array($id,design_permissions($user)[$type]??[],true);
}
function design_field_allowed(?array $user,string $page,string $scope,string $field=''): bool {
    return ($field!==''&&design_can($user,'fields',$field))||($scope==='page'?design_can($user,'pages',$page):design_can($user,'shared',$scope));
}
function design_preview_allowed(?array $user,string $page): bool {
    if(!$user||!$user['active']||$user['mustChange'])return false;
    $p=design_permissions($user);
    foreach($p['fields'] as $field)if(str_starts_with($field,$page.'/')||str_starts_with($field,'shared:'))return true;
    return $user['role']==='admin'||in_array($page,$p['pages'],true)||(bool)($p['shared']||$p['menus']||$p['images']);
}
function design_undo_allowed(array $s,?array $user): bool {
    if(!$user||$user['mustChange']||!$user['active'])return false;
    if($user['role']==='admin')return true;
    $change=$s['_lastChange']??[];
    if(($change['type']??'')==='text'){
        if(!empty($change['fields'])){foreach($change['fields'] as $f)if(!design_field_allowed($user,$change['page'],$f['scope'],$f['id']))return false;return true;}
        if(empty($change['scopes']))return false;
        foreach($change['scopes'] as $scope)if(!design_field_allowed($user,$change['page'],$scope))return false;
        return true;
    }
    if(in_array($change['type']??'',['menus','images'],true))return design_can($user,$change['type'],$change['id']);
    return false;
}
function design_permission_options(): array {
    $fields=[];$s=state();foreach(site_config()['pages'] as $p)foreach(fields_for('', $p['id'],$s) as $f){preg_match('~^.{0,70}~us',$f['value'],$snippet);$place=['header'=>'هدر مشترک','footer'=>'فوتر مشترک'][$f['scope']]??$p['title'];$fields[$f['id']]=['id'=>$f['id'],'title'=>$place.' · '.$f['label'].' · '.($snippet[0]??'متن خالی')];}
    return ['pages'=>array_map(fn($p)=>['id'=>$p['id'],'title'=>$p['title']],site_config()['pages']),
        'fields'=>array_values($fields),
        'shared'=>[['id'=>'header','title'=>'هدر مشترک در همهٔ صفحات'],['id'=>'footer','title'=>'فوتر مشترک در همهٔ صفحات']],
        'menus'=>array_values(array_map(fn($g)=>['id'=>$g['id'],'title'=>$g['title']],read_json('menus'))),
        'images'=>array_map(fn($i)=>['id'=>$i['id'],'title'=>$i['label']],site_config()['images'])];
}
function validate_design_permissions(mixed $input): array {
    if(!is_array($input)||array_diff(array_keys($input),['pages','shared','fields','menus','images']))throw new InvalidArgumentException('Invalid permissions');
    $options=design_permission_options();$clean=[];
    foreach($options as $type=>$choices){$list=$input[$type]??[];if(!is_array($list)||!array_is_list($list)||count($list)>1000)throw new InvalidArgumentException('Invalid permissions');$known=array_column($choices,'id');foreach($list as $id)if(!is_string($id)||!in_array($id,$known,true))throw new InvalidArgumentException('Unknown design section');$clean[$type]=array_values(array_unique($list));}
    return $clean;
}
