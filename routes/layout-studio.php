<?php
declare(strict_types=1);

use Erased\LayoutStudio\LayoutStudioAdminRoute;
use Erased\LayoutStudio\LayoutStudioAdminScreen;
use Erased\LayoutStudio\LivePreviewRoute;
use Erased\LayoutStudio\LayoutSerializer;
use Erased\LayoutStudio\LivePreviewRenderer;

if($path==='/admin/layout-studio' || $path==='/admin/layout-studio/preview'){
    require_login();
    require_permission('packages.manage');
    $profileId=strtolower(trim((string)($_GET['profile']??'default')));
    if(preg_match('/^[a-z0-9][a-z0-9._-]*$/',$profileId)!==1)$profileId='default';

    if($path==='/admin/layout-studio/preview'){
        if(!defined('ERASED_LIVE_PREVIEW_MODE')) define('ERASED_LIVE_PREVIEW_MODE', true);
        $device=strtolower(trim((string)($_GET['device']??'desktop')));
        $route=new LivePreviewRoute(db(),new LayoutSerializer(),new LivePreviewRenderer());
        header('Content-Type: text/html; charset=UTF-8');
        echo $route->render($profileId,$device);
        exit;
    }

    $route=new LayoutStudioAdminRoute(new LayoutStudioAdminScreen(),db());
    if($_SERVER['REQUEST_METHOD']==='POST'){
        header('Content-Type: application/json; charset=UTF-8');
        try{
            $input=json_decode((string)file_get_contents('php://input'),true);
            if(!is_array($input))throw new RuntimeException('Invalid Layout Studio request.');
            $token=(string)($input['csrf']??'');
            if($token===''||!hash_equals((string)csrf(),$token)){
                security_log('csrf.validation_failed',['path'=>(string)($_SERVER['REQUEST_URI']??'')],'warning');
                throw new RuntimeException('Security token is invalid.');
            }
            $action=(string)($input['action']??'save');
            if($action==='save_widget_options'){
                $richBlockTypes=['features'];
                $blockType=(string)($input['block_type']??'');
                if(!in_array($blockType,$richBlockTypes,true))throw new RuntimeException('Unknown widget block type.');
                $fields=is_array($input['fields']??null)?$input['fields']:[];
                $allowed=['title','subtitle','button_text','button_url','primary_color','bg_style','align','min_height','padding','css_class','html_id'];
                $itemBlockTypes=['features'];
                if(in_array($blockType,$itemBlockTypes,true))$allowed[]='items';
                $update=[];
                foreach($allowed as $field){
                    if(array_key_exists($field,$fields))$update[$field]=(string)$fields[$field];
                }
                if($update===[])throw new RuntimeException('No widget fields to save.');
                if(isset($update['items'])){
                    $lines=preg_split('/\R/',$update['items'])?:[];
                    if(count($lines)>100)throw new RuntimeException('List items: 100 lines maximum.');
                    if(strlen($update['items'])>20000)throw new RuntimeException('List items: content is too long.');
                }
                if(isset($update['primary_color'])&&function_exists('homepage_studio_safe_color')){
                    $update['primary_color']=homepage_studio_safe_color($update['primary_color'],'#16a34a');
                }
                if(isset($update['button_url'])&&$update['button_url']!==''&&!preg_match('#^(/|https?://)#i',$update['button_url'])){
                    throw new RuntimeException('Button URL must be a relative path or start with http(s)://.');
                }
                if(isset($update['bg_style'])&&!in_array($update['bg_style'],['solid','gradient','image'],true)){
                    throw new RuntimeException('Unknown background style.');
                }
                if(isset($update['align'])&&!in_array($update['align'],['left','center','right','justify'],true)){
                    throw new RuntimeException('Unknown text alignment.');
                }
                if(isset($update['min_height'])&&$update['min_height']!==''&&(!ctype_digit($update['min_height'])||(int)$update['min_height']>4000)){
                    throw new RuntimeException('Minimum height must be a number up to 4000.');
                }
                if(isset($update['padding'])&&$update['padding']!==''&&!preg_match('/^[0-9]{1,4}(?:px|%|rem|em)?(?:\s+[0-9]{1,4}(?:px|%|rem|em)?){0,3}$/',$update['padding'])){
                    throw new RuntimeException('Padding must be simple CSS lengths, e.g. "60px 20px".');
                }
                if(isset($update['css_class'])&&$update['css_class']!==''&&!preg_match('/^[a-zA-Z0-9_-]{1,60}$/',$update['css_class'])){
                    throw new RuntimeException('CSS class must be a single class name (letters, numbers, - and _ only).');
                }
                if(isset($update['html_id'])&&$update['html_id']!==''&&!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,60}$/',$update['html_id'])){
                    throw new RuntimeException('HTML ID must start with a letter and contain only letters, numbers, - and _.');
                }
                $allOptions=json_decode((string)setting('homepage_widget_options','{}'),true);
                if(!is_array($allOptions))$allOptions=[];
                $allOptions[$blockType]=array_merge(is_array($allOptions[$blockType]??null)?$allOptions[$blockType]:[],$update);
                set_setting('homepage_widget_options',json_encode($allOptions,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
                if(function_exists('audit'))audit('layout.save_widget_options',['block_type'=>$blockType,'fields'=>array_keys($update)]);
                $merged=function_exists('homepage_studio_block_options')?homepage_studio_block_options($blockType,$allOptions):$allOptions[$blockType];
                echo json_encode(['ok'=>true,'block_type'=>$blockType,'options'=>$merged],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
                exit;
            }
            if($action==='publish'){
                $result=$route->publish($profileId);
                if(function_exists('audit'))audit('layout.publish',['profile_id'=>$profileId,'revision'=>$result['revision'],'published'=>$result['published']]);
                if(function_exists('setting')&&function_exists('erased_cloudflare_purge_cache')&&setting('cloudflare_auto_purge','0')==='1')erased_cloudflare_purge_cache(['/']);
                echo json_encode(['ok'=>true]+$result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
                exit;
            }
            $draft=$route->save($profileId,$input,(int)(current_user()['id']??0));
            echo json_encode(['ok'=>true,'revision'=>$draft->revision()],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            exit;
        }catch(Throwable $error){
            http_response_code(422);
            echo json_encode(['ok'=>false,'error'=>$error->getMessage()],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // GET rendering here is retired: it used to inject a second, competing
    // save/publish/undo/redo script (LayoutStudioAdminRoute::draftScript())
    // bound to the same [data-layout-save] etc. buttons that the live
    // /admin/appearance/homepage screen already wires up itself, causing
    // both handlers to fire on a single click. That screen is the only
    // linked entry point for Layout Studio now, so send stray requests
    // to this legacy URL there instead of rendering a doubly-wired page.
    erased_redirect_preserving_studio_embed('/admin/appearance/homepage');
}
