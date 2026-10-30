<?php
declare(strict_types=1);

function ensure_extended_schema(): void {
    static $done = false;
    if ($done || !installed()) return;
    $done = true;
    $p = db();
    $p->exec("CREATE TABLE IF NOT EXISTS sources (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        game_id INT UNSIGNED NULL,
        name VARCHAR(160) NOT NULL,
        type VARCHAR(40) NOT NULL DEFAULT 'json',
        endpoint VARCHAR(1000) NOT NULL,
        method VARCHAR(10) NOT NULL DEFAULT 'GET',
        headers TEXT NULL,
        mapping_json TEXT NULL,
        enabled TINYINT(1) NOT NULL DEFAULT 1,
        verified TINYINT(1) NOT NULL DEFAULT 0,
        interval_minutes INT UNSIGNED NOT NULL DEFAULT 60,
        last_run_at DATETIME NULL,
        last_status VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX(game_id), FOREIGN KEY(game_id) REFERENCES games(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS admin_logs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NULL,
        action VARCHAR(120) NOT NULL,
        details TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function admin_log(string $action, string $details = ''): void {
    try { $s=db()->prepare('INSERT INTO admin_logs(user_id,action,details) VALUES(?,?,?)'); $s->execute([user()['id']??null,$action,$details]); } catch(Throwable) {}
}

function clean_update_body(string $body): string {
    $body=html_entity_decode($body,ENT_QUOTES|ENT_HTML5,'UTF-8');
    $body=preg_replace('/\[img(?:=[^\]]+)?\].*?\[\/img\]/is','',$body)??$body;
    $body=preg_replace('/\[url=([^\]]+)\](.*?)\[\/url\]/is','$2',$body)??$body;
    $body=preg_replace('/\[url\](.*?)\[\/url\]/is','$1',$body)??$body;
    $body=preg_replace('/\[\/?(?:h[1-6]|b|i|u|strike|quote|list|olist|table|tr|td|center|left|right|hr|code|spoiler|preview|noparse|expand|collapse|p)\]/i',"\n",$body)??$body;
    $body=preg_replace('/\[\*\]/',"\n• ",$body)??$body;
    $body=preg_replace('/\[[^\]]+\]/','',$body)??$body;
    $body=strip_tags($body);
    $body=preg_replace("/\r\n?/","\n",$body)??$body;
    $body=preg_replace("/\n{3,}/","\n\n",$body)??$body;
    return trim($body);
}

function http_fetch(string $url,string $method='GET',string $headers=''): ?string {
    if(!filter_var($url,FILTER_VALIDATE_URL)) return null;
    $ch=curl_init($url); $headerList=[];
    foreach(preg_split('/\r?\n/',trim($headers))?:[] as $h) if($h!=='') $headerList[]=$h;
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>max(3,(int)setting_value('source_timeout',12)),CURLOPT_USERAGENT=>(string)setting_value('source_user_agent','GameScore/1.1'),CURLOPT_HTTPHEADER=>$headerList,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_SSL_VERIFYPEER=(bool)setting_value('source_verify_tls',true)]);
    $raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    return $raw!==false&&$code>=200&&$code<400?$raw:null;
}

function setting_value(string $key,mixed $default=null): mixed {
    static $cache=[];
    if(array_key_exists($key,$cache)) return $cache[$key];
    try{$s=db()->prepare('SELECT setting_value FROM settings WHERE setting_key=?');$s->execute([$key]);$v=$s->fetchColumn();}catch(Throwable){return $cache[$key]=$default;}
    if($v===false)return $cache[$key]=$default;
    $json=json_decode((string)$v,true);
    return $cache[$key]=($json===null&&$v!=='null'?$v:$json);
}
function save_setting_value(string $key,mixed $value): void {
    $encoded=is_string($value)?$value:json_encode($value,JSON_UNESCAPED_SLASHES);
    $s=db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');$s->execute([$key,$encoded]);
}
function json_path(mixed $data,string $path): mixed { foreach(explode('.',trim($path,'.')) as $part){if($part==='' )continue;if(!is_array($data)||!array_key_exists($part,$data))return null;$data=$data[$part];}return $data; }

function sync_configured_source(array $source): int {
    $raw=http_fetch($source['endpoint'],$source['method']?:'GET',$source['headers']??'');
    if($raw===null){db()->prepare('UPDATE sources SET last_run_at=NOW(),last_status=? WHERE id=?')->execute(['Fetch failed',$source['id']]);return 0;}
    $map=json_decode($source['mapping_json']?:'{}',true)?:[];$items=[];
    if(in_array($source['type'],['rss','atom'],true)){
        libxml_use_internal_errors(true);$xml=simplexml_load_string($raw);
        if($xml){$nodes=isset($xml->channel->item)?$xml->channel->item:($xml->entry??[]);foreach($nodes as $item)$items[]=['title'=>(string)($item->title??''),'body'=>(string)($item->description??$item->summary??$item->content??''),'url'=>(string)($item->link['href']??$item->link??''),'id'=>(string)($item->guid??$item->id??''),'date'=>(string)($item->pubDate??$item->published??$item->updated??'')];}
    }else{$json=json_decode($raw,true);if(!is_array($json))return 0;$items=!empty($map['items'])?json_path($json,$map['items']):$json;if(is_array($items)&&isset($items['items']))$items=$items['items'];if(!is_array($items))$items=[];}
    $count=0;$max=max(1,(int)setting_value('source_max_items',20));
    foreach(array_slice($items,0,$max) as $item){if(!is_array($item))continue;$title=(string)(!empty($map['title'])?json_path($item,$map['title']):($item['title']??''));$body=(string)(!empty($map['body'])?json_path($item,$map['body']):($item['body']??$item['description']??$item['content']??''));$url=(string)(!empty($map['url'])?json_path($item,$map['url']):($item['url']??$item['link']??''));$id=(string)(!empty($map['id'])?json_path($item,$map['id']):($item['id']??$item['guid']??sha1($title.$url)));$date=(string)(!empty($map['date'])?json_path($item,$map['date']):($item['published_at']??$item['published']??$item['date']??''));if($title===''||!$source['game_id'])continue;$external='source:'.$source['id'].':'.$id;$q=db()->prepare('SELECT id FROM updates WHERE external_id=?');$q->execute([$external]);if($q->fetch())continue;$clean=mb_substr(clean_update_body($body),0,max(100,(int)setting_value('update_max_body',4000)));$ts=strtotime($date)?:time();$q=db()->prepare('INSERT INTO updates(game_id,title,body,source_name,source_url,external_id,published_at,verified) VALUES(?,?,?,?,?,?,?,?)');$q->execute([$source['game_id'],mb_substr($title,0,255),$clean,$source['name'],$url,$external,date('Y-m-d H:i:s',$ts),!empty($source['verified'])?1:0]);$count++;}
    db()->prepare('UPDATE sources SET last_run_at=NOW(),last_status=? WHERE id=?')->execute(['OK: '.$count,$source['id']]);return $count;
}
function sync_all_sources(): int { ensure_extended_schema();if(!setting_value('sources_auto_sync',true))return 0;$count=0;foreach(db()->query('SELECT * FROM sources WHERE enabled=1 ORDER BY id')->fetchAll() as $source)$count+=sync_configured_source($source);if(setting_value('steam_enabled',true)&&setting_value('automation_sync_steam',true))$count+=sync_steam();return $count; }

function admin_catalog(): array {
    $c=[];
    $add=function(string $menu,array $items)use(&$c){$c[$menu]=$items;};
    $b=fn(string $label,string $key,bool $default=true)=>[$key=>['label'=>$label,'type'=>'checkbox','default'=>$default?'1':'0']];
    $n=fn(string $label,string $key,int $default)=>[$key=>['label'=>$label,'type'=>'number','default'=>$default]];
    $t=fn(string $label,string $key,string $default)=>[$key=>['label'=>$label,'type'=>'text','default'=>$default]];
    $s=fn(string $label,string $key,array $options,string $default)=>[$key=>['label'=>$label,'type'=>'select','options'=>$options,'default'=>$default]];

    $add('Site & Brand',array_merge(
        $t('Site name','site_name','GameScore'),$t('Browser title suffix','seo_title_suffix',' · GameScore'),$t('Site description','seo_description','Factual game information and verified updates'),
        [['site_accent'=>['label'=>'Accent colour','type'=>'color','default'=>cfg()['site']['accent']??'#8b5cf6']]],$t('Default language','default_language','en'),$t('Brand mark text','brand_mark','GS'),$b('Show footer','footer_enabled'),$b('Show source badges','source_badges'),$b('Show verification labels','verification_labels'),$b('Enable custom CSS','custom_css_enabled')
    ));
    $add('Homepage',array_merge($n('Featured game count','homepage_game_count',6),$n('Recent update count','homepage_update_count',8),$b('Show hero','homepage_show_hero'),$b('Show featured games','homepage_show_games'),$b('Show recent updates','homepage_show_updates'),$b('Show source chain','homepage_show_sources'),$b('Show verification badges','homepage_show_verification'),$t('Hero eyebrow','homepage_eyebrow','GAME INTELLIGENCE'),$t('Hero title','homepage_title','Know what changed.'),$t('Hero subtitle','homepage_subtitle','Before everyone else.')));
    $add('Games',array_merge($n('Games per page','games_per_page',24),$s('Default sort','games_sort',['name'=>'Name','updated'=>'Recently updated','featured'=>'Featured first'],'name'),$b('Show unpublished to admins','games_admin_unpublished'),$b('Allow featured games','games_featured_enabled'),$b('Show descriptions','games_show_descriptions'),$b('Show Steam IDs','games_show_steam_ids'),$b('Use fallback artwork','games_fallback_art'),$b('Show game metadata','games_show_metadata'),$b('Enable game pages','games_pages_enabled'),$b('Allow duplicate slugs','games_allow_duplicate_slugs')));
    $add('Updates',array_merge($n('Updates per page','updates_per_page',50),$n('Maximum update body length','update_max_body',4000),$s('Default sort','updates_sort',['newest'=>'Newest first','oldest'=>'Oldest first'],'newest'),$b('Show source URL','updates_show_source_url'),$b('Show source name','updates_show_source_name'),$b('Show verification state','updates_show_verification'),$b('Show publication date','updates_show_date'),$b('Allow manual updates','updates_manual_enabled'),$b('Allow imported updates','updates_import_enabled'),$b('Render update formatting','updates_render_formatting')));
    $add('Sources',array_merge($b('Automatic source sync','sources_auto_sync'),$n('HTTP timeout seconds','source_timeout',12),$n('Maximum items per source','source_max_items',20),$t('HTTP user agent','source_user_agent','GameScore/1.1'),$b('Verify TLS certificates','source_verify_tls'),$b('Follow redirects','source_follow_redirects'),$b('Reject empty titles','source_require_title'),$b('Store source URL','source_store_url'),$b('Store external IDs','source_store_external_id'),$b('Mark verified sources as verified','source_trust_verified')));
    $add('Steam',array_merge($b('Steam News integration','steam_enabled'),$n('Items per game','steam_count',10),$n('Maximum source length','steam_max_length',8000),$b('Trust Steam as verified','steam_verify'),$b('Import announcements','steam_import_announcements'),$b('Import patch notes','steam_import_patchnotes'),$b('Import developer posts','steam_import_developer'),$b('Ignore duplicates','steam_ignore_duplicates'),$b('Clean Steam BBCode','steam_clean_bbcode'),$b('Use Steam publication date','steam_use_date')));
    $add('RSS & Feeds',array_merge($b('RSS enabled','rss_enabled'),$b('Atom enabled','atom_enabled'),$n('Maximum feed items','feed_max_items',20),$n('Feed timeout seconds','feed_timeout',12),$b('Follow feed redirects','feed_follow_redirects'),$b('Validate feed XML','feed_validate_xml'),$b('Import descriptions','feed_import_descriptions'),$b('Import publication dates','feed_import_dates'),$b('Deduplicate feed IDs','feed_dedupe'),$b('Mark verified feeds','feed_trust_verified')));
    $add('Content Rules',array_merge($b('Require title','content_require_title'),$n('Maximum title length','content_title_max',255),$n('Maximum body length','content_body_max',4000),$b('Strip HTML','content_strip_html'),$b('Strip images','content_strip_images'),$b('Strip tracking parameters','content_strip_tracking'),$b('Collapse blank lines','content_collapse_blank'),$b('Reject empty bodies','content_require_body'),$b('Allow source links','content_allow_links'),$b('Log rejected content','content_log_rejections')));
    $add('Games Metadata',array_merge($b('Use Steam metadata','metadata_steam'),$b('Use manually entered metadata','metadata_manual'),$b('Update artwork automatically','metadata_auto_art'),$b('Update descriptions automatically','metadata_auto_description'),$b('Update release dates','metadata_release_dates'),$b('Update developer names','metadata_developers'),$b('Update publisher names','metadata_publishers'),$b('Update genres','metadata_genres'),$b('Update platforms','metadata_platforms'),$b('Preserve manual overrides','metadata_preserve_manual')));
    $add('Users',array_merge($b('Allow registration','users_registration'),$b('Require email verification','users_email_verify'),$b('Allow profile editing','users_profile_edit'),$b('Allow account deletion','users_delete'),$b('Allow display names','users_display_names'),$b('Show user activity','users_activity'),$n('Maximum users per page','users_per_page',50),$b('Log account changes','users_log_changes'),$b('Allow admin promotion','users_admin_promotion'),$b('Block disposable email','users_block_disposable')));
    $add('Authentication',array_merge($n('Minimum password length','auth_password_min',8),$b('Require strong passwords','auth_strong_passwords'),$b('Regenerate session on login','auth_regenerate_session'),$b('Remember login sessions','auth_remember'),$n('Session lifetime minutes','auth_session_minutes',120),$n('Maximum login attempts','auth_login_attempts',5),$n('Lockout minutes','auth_lockout_minutes',15),$b('Require CSRF tokens','auth_csrf'),$b('Allow password reset','auth_password_reset'),$b('Log authentication events','auth_log_events')));
    $add('API Access',array_merge($b('Public API enabled','public_api_enabled'),$n('Maximum records','public_api_limit',100),$t('API version','public_api_version','v1'),$b('Allow CORS','api_cors'),$t('CORS origin','api_cors_origin','*'),$b('Expose source URLs','api_expose_source_urls'),$b('Expose verification state','api_expose_verification'),$b('Expose unpublished data','api_expose_unpublished'),$b('Log API requests','api_log_requests'),$b('Require API key','api_require_key')));
    $add('Automation',array_merge($b('Run Steam sync from cron','automation_sync_steam'),$b('Run configured sources','automation_sources'),$n('Minimum source interval','automation_min_interval',5),$n('Maximum jobs per run','automation_max_jobs',50),$b('Skip disabled sources','automation_skip_disabled'),$b('Log cron runs','automation_log_runs'),$b('Retry failed sources','automation_retry'),$n('Retry count','automation_retry_count',2),$b('Stop on repeated failures','automation_stop_failures'),$b('Update source status','automation_status')));
    $add('Updater',array_merge($b('GitHub updater enabled','updater_enabled'),$t('GitHub repository','updater_repo','thewhistledev/gamescore'),$t('GitHub branch','updater_branch','main'),$t('Manifest path','updater_manifest','update.json'),$b('Create backups','updater_backups'),$b('Verify SHA-256','updater_verify_hash'),$b('Allow automatic update','updater_auto'),$b('Show update notices','updater_notices'),$b('Protect config','updater_protect_config',true),$b('Protect storage','updater_protect_storage',true)));
    $add('Payments',array_merge($b('Payments enabled','payments_enabled',false),$s('Provider','payments_provider',['stripe'=>'Stripe'],'stripe'),$t('Currency','payments_currency','gbp'),$b('Require login','payments_require_login'),$b('Use hosted checkout','payments_hosted_checkout'),$b('Log payments','payments_log'),$b('Enable webhooks','payments_webhooks'),$b('Allow refunds','payments_refunds'),$b('Show payment history','payments_history'),$b('Show checkout button','payments_show_button')));
    $add('Email',array_merge($b('Email enabled','email_enabled'),$t('From name','email_from_name','GameScore'),$t('From address','email_from_address',''),$s('Transport','email_transport',['mail'=>'PHP mail','smtp'=>'SMTP'],'mail'),$t('SMTP host','email_smtp_host',''),$n('SMTP port','email_smtp_port',587),$b('SMTP encryption','email_smtp_tls'),$b('Send registration email','email_registration'),$b('Send password reset email','email_password_reset'),$b('Log email attempts','email_log')));
    $add('Notifications',array_merge($b('Notifications enabled','notifications_enabled'),$b('Admin update alerts','notify_admin_updates'),$b('Source failure alerts','notify_source_failures'),$b('New user alerts','notify_new_users'),$b('Payment alerts','notify_payments'),$b('Updater alerts','notify_updater'),$b('Cron failure alerts','notify_cron_failures'),$b('Email notifications','notify_email'),$b('Dashboard notices','notify_dashboard'),$b('Browser notices','notify_browser')));
    $add('Security',array_merge($b('Force HTTPS','security_https'),$b('Secure cookies','security_secure_cookies'),$b('HTTP-only cookies','security_httponly'),$b('Content security headers','security_csp'),$b('X-Frame protection','security_frame'),$b('X-Content-Type protection','security_content_type'),$b('Referrer policy','security_referrer'),$b('Log admin actions','security_admin_logs'),$b('Block unsafe URLs','security_safe_urls'),$b('Disable debug output','security_no_debug')));
    $add('Performance',array_merge($b('Enable page caching','performance_cache'),$n('Cache lifetime seconds','performance_cache_seconds',60),$b('Cache public API','performance_api_cache'),$b('Compress HTML','performance_compress_html'),$b('Lazy-load artwork','performance_lazy_images'),$b('Limit database queries','performance_query_limits'),$b('Use persistent PDO','performance_persistent_db'),$b('Disable unused widgets','performance_minimal_widgets'),$b('Log slow requests','performance_slow_logs'),$n('Slow request threshold ms','performance_slow_ms',1000)));
    $add('SEO & Social',array_merge($t('Meta description','seo_description','Factual game information and verified updates'),$t('Title suffix','seo_title_suffix',' · GameScore'),$b('Canonical URLs','seo_canonical'),$b('Open Graph tags','seo_og'),$b('Twitter cards','seo_twitter'),$b('Sitemap enabled','seo_sitemap'),$b('Robots index','seo_index'),$b('Robots follow','seo_follow'),$b('Game structured data','seo_game_schema'),$b('Update structured data','seo_update_schema')));
    $add('Moderation',array_merge($b('Manual approval required','moderation_approval'),$b('Allow admin editing','moderation_edit'),$b('Allow admin deletion','moderation_delete'),$b('Flag duplicate updates','moderation_duplicates'),$b('Flag empty content','moderation_empty'),$b('Flag unknown sources','moderation_unknown_sources'),$b('Keep moderation logs','moderation_logs'),$b('Allow source disabling','moderation_disable_sources'),$b('Allow game hiding','moderation_hide_games'),$b('Show moderation warnings','moderation_warnings')));
    return $c;
}
