<?php
declare(strict_types=1);
require_once APP_ROOT.'/app/services.php';
require_once APP_ROOT.'/app/admin_catalog.php';
require_once APP_ROOT.'/app/updater.php';

function page_admin_v2(): void {
    ensure_extended_schema();
    $games=db()->query('SELECT * FROM games ORDER BY name')->fetchAll();
    $sources=db()->query('SELECT s.*,g.name game_name FROM sources s LEFT JOIN games g ON g.id=s.game_id ORDER BY s.name')->fetchAll();
    $catalog=admin_catalog_real();$section=$_GET['section']??'overview';$message=null;$error=null;
    if($_SERVER['REQUEST_METHOD']==='POST'){
        check_csrf();
        try{
            $action=$_POST['action']??'';
            if($action==='settings'){
                $allowed=[];foreach($catalog as $items)foreach($items as $key=>$meta)$allowed[$key]=$meta;
                foreach($_POST['setting']??[] as $key=>$value)if(isset($allowed[$key]))save_setting_value((string)$key,is_array($value)?$value:(string)$value);
                admin_log('settings.update',implode(',',array_keys($_POST['setting']??[])));$message='Configuration saved.';
            }elseif($action==='source'){
                if(!setting_value('source_json_enabled',true)&&($_POST['source_type']??'')==='json')throw new RuntimeException('JSON API sources are disabled in API Sources settings.');
                if(!setting_value('source_rss_enabled',true)&&($_POST['source_type']??'')==='rss')throw new RuntimeException('RSS sources are disabled in API Sources settings.');
                if(!setting_value('source_atom_enabled',true)&&($_POST['source_type']??'')==='atom')throw new RuntimeException('Atom sources are disabled in API Sources settings.');
                $endpoint=trim($_POST['endpoint']??'');if(!filter_var($endpoint,FILTER_VALIDATE_URL))throw new RuntimeException('A valid API/feed URL is required.');
                if(setting_value('source_require_https',true)&&parse_url($endpoint,PHP_URL_SCHEME)!=='https')throw new RuntimeException('HTTPS is required for configured sources.');
                $mapping=json_decode(trim($_POST['mapping_json']??'{}'),true);if(!is_array($mapping))throw new RuntimeException('Mapping JSON is invalid.');
                $count=(int)db()->query('SELECT COUNT(*) FROM sources')->fetchColumn();if($count>=(int)setting_value('source_max_count',50))throw new RuntimeException('The configured source limit has been reached.');
                $s=db()->prepare('INSERT INTO sources(game_id,name,type,endpoint,method,headers,mapping_json,enabled,verified,interval_minutes) VALUES(?,?,?,?,?,?,?,?,?,?)');
                $s->execute([($_POST['game_id']??'')!==''?(int)$_POST['game_id']:null,trim($_POST['source_name']),$_POST['source_type'],$endpoint,$_POST['method']??'GET',trim($_POST['headers']??''),json_encode($mapping),!empty($_POST['enabled']),!empty($_POST['verified']),max(5,(int)($_POST['interval_minutes']??setting_value('source_default_interval',60)))]);
                admin_log('source.create',trim($_POST['source_name']));$message='Source added.';$section='sources';
            }elseif($action==='sync'){
                $count=sync_all_sources();if(setting_value('steam_enabled',true)&&setting_value('automation_sync_steam',true))$count+=sync_steam_v2();$message='Source sync imported '.$count.' new updates.';$section='sources';
            }elseif($action==='site_update'){$result=site_update(!empty($_POST['install']));$message=$result['message'];admin_log('site.update',$message);$section='updater';
            }elseif($action==='game'){
                if(!setting_value('games_manual_enabled',true))throw new RuntimeException('Manual game creation is disabled.');
                $name=trim($_POST['name']??'');$slug=trim($_POST['slug']??'')?:preg_replace('/[^a-z0-9]+/','-',strtolower($name));
                if(!setting_value('games_allow_duplicate_slugs',false)){$q=db()->prepare('SELECT id FROM games WHERE slug=?');$q->execute([$slug]);if($q->fetch())throw new RuntimeException('That game slug already exists.');}
                $s=db()->prepare('INSERT INTO games(name,slug,description,image_url,steam_appid,featured,published) VALUES(?,?,?,?,?,?,?)');$s->execute([$name,$slug,trim($_POST['description']??''),trim($_POST['image_url']??''),($_POST['steam_appid']??'')!==''?(int)$_POST['steam_appid']:null,!empty($_POST['featured'])&&setting_value('games_featured_enabled',true),!empty($_POST['published'])]);admin_log('game.create',$name);$message='Game added.';$section='games';
            }elseif($action==='update'){
                if(!setting_value('updates_manual_enabled',true))throw new RuntimeException('Manual updates are disabled.');
                $body=setting_value('updates_clean_formatting',true)?clean_update_body(trim($_POST['body']??'')):trim($_POST['body']??'');if($body==='')throw new RuntimeException('Update body cannot be empty.');$body=mb_substr($body,0,max(100,(int)setting_value('updates_max_body',4000)));
                $s=db()->prepare('INSERT INTO updates(game_id,title,body,body_format,source_name,source_url,published_at,verified) VALUES(?,?,?,?,?,?,?,1)');$s->execute([(int)$_POST['game_id'],trim($_POST['title']),$body,'text','Manual',trim($_POST['source_url']??''),date('Y-m-d H:i:s')]);admin_log('update.create',trim($_POST['title']));$message='Update published.';$section='updates';
            }
        }catch(Throwable $e){$error=$e->getMessage();}
    }
    $values=[];foreach($catalog as $menu=>$items)foreach($items as $key=>$meta)$values[$key]=setting_value($key,$meta['default']);$updater=updater_status();ob_start();require APP_ROOT.'/views/admin.php';render('Admin',ob_get_clean());
}
