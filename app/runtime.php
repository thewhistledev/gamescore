<?php
declare(strict_types=1);
if (!defined('APP_VERSION')) define('APP_VERSION', '1.3.0');
require_once APP_ROOT.'/app/services.php';
require_once APP_ROOT.'/app/steam_formatter.php';
function sync_steam_v2(): int {
    $count=0;$fullContent=(bool)setting_value('steam_full_content',true);
    foreach(db()->query('SELECT * FROM games WHERE steam_appid IS NOT NULL AND steam_appid<>0')->fetchAll() as $game){
        $maxLength=$fullContent?0:max(100,(int)setting_value('steam_max_length',8000));
        $url='https://api.steampowered.com/ISteamNews/GetNewsForApp/v2/?appid='.(int)$game['steam_appid'].'&count='.(int)setting_value('steam_count',10).'&maxlength='.$maxLength;
        $raw=http_fetch($url);if(!$raw)continue;$json=json_decode($raw,true);
        foreach($json['appnews']['newsitems']??[] as $item){
            $external='steam:'.($item['gid']??sha1(($item['title']??'').($item['url']??'')));
            $q=db()->prepare('SELECT id FROM updates WHERE external_id=?');$q->execute([$external]);if($q->fetch())continue;
            $contents=(string)($item['contents']??'');
            $q=db()->prepare('INSERT INTO updates(game_id,title,body,body_format,source_name,source_url,external_id,published_at,verified) VALUES(?,?,?,?,?,?,?,?,?)');
            $q->execute([$game['id'],mb_substr((string)($item['title']??''),0,255),$contents,'steam_bbcode','Steam News',(string)($item['url']??''),$external,date('Y-m-d H:i:s',(int)($item['date']??time())),setting_value('steam_verify',true)?1:0]);$count++;
        }
    }
    return $count;
}
function api_cron_v2(): void {
    $token=(string)($_GET['token']??'');if($token===''||!hash_equals((string)(cfg()['cron_token']??''),$token)){http_response_code(403);exit('Forbidden');}
    $count=sync_all_sources();if(setting_value('automation_sync_steam',true)&&setting_value('steam_enabled',true))$count+=sync_steam_v2();
    header('Content-Type: application/json');echo json_encode(['imported'=>$count,'version'=>APP_VERSION,'time'=>date(DATE_ATOM)]);exit;
}
function display_update_body(string $body,string $format='text'): string { return format_update_body($body,$format); }
