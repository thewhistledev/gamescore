<?php
declare(strict_types=1);
if (!defined('APP_VERSION')) define('APP_VERSION', '1.1.0');
require_once APP_ROOT.'/app/services.php';
function sync_steam_v2(): int {
    $count=0;
    foreach(db()->query('SELECT * FROM games WHERE steam_appid IS NOT NULL AND steam_appid<>0')->fetchAll() as $game){
        $url='https://api.steampowered.com/ISteamNews/GetNewsForApp/v2/?appid='.(int)$game['steam_appid'].'&count='.(int)setting_value('steam_count',10).'&maxlength='.(int)setting_value('steam_max_length',8000);
        $raw=http_fetch($url);
        if(!$raw) continue;
        $json=json_decode($raw,true);
        foreach($json['appnews']['newsitems']??[] as $item){
            $external='steam:'.($item['gid']??sha1(($item['title']??'').($item['url']??'')));
            $q=db()->prepare('SELECT id FROM updates WHERE external_id=?');$q->execute([$external]);if($q->fetch())continue;
            $body=mb_substr(clean_update_body((string)($item['contents']??'')),0,max(100,(int)setting_value('updates_max_body',4000)));
            $q=db()->prepare('INSERT INTO updates(game_id,title,body,source_name,source_url,external_id,published_at,verified) VALUES(?,?,?,?,?,?,?,?)');
            $q->execute([$game['id'],mb_substr((string)$item['title'],0,255),$body,'Steam News',(string)($item['url']??''),$external,date('Y-m-d H:i:s',(int)($item['date']??time())),setting_value('steam_verify',true)?1:0]);$count++;
        }
    }
    return $count;
}
function sync_all_sources_v2(): int {
    ensure_extended_schema();
    if(!setting_value('sources_auto_sync',true)) return 0;
    $count=0;
    foreach(db()->query('SELECT * FROM sources WHERE enabled=1 ORDER BY id')->fetchAll() as $source) $count+=sync_configured_source($source);
    if(setting_value('steam_enabled',true)&&setting_value('automation_sync_steam',true)) $count+=sync_steam_v2();
    return $count;
}
function api_cron_v2(): never {
    ensure_extended_schema();
    $token=$_GET['token']??'';$config=cfg();
    if(!$token||!hash_equals($config['cron_token']??'',$token)){http_response_code(403);exit('Forbidden');}
    $count=sync_all_sources_v2();
    header('Content-Type: application/json');echo json_encode(['imported'=>$count,'version'=>APP_VERSION,'time'=>date(DATE_ATOM)]);exit;
}
function display_update_body(string $body): string { return clean_update_body($body); }
