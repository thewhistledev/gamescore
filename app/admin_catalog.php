<?php
declare(strict_types=1);

function admin_catalog_real(): array {
    $check = static fn(string $label,string $key,bool $default=true): array => [$key=>['label'=>$label,'type'=>'checkbox','default'=>$default?'1':'0']];
    $num = static fn(string $label,string $key,int $default): array => [$key=>['label'=>$label,'type'=>'number','default'=>$default]];
    $text = static fn(string $label,string $key,string $default): array => [$key=>['label'=>$label,'type'=>'text','default'=>$default]];
    $select = static fn(string $label,string $key,array $options,string $default): array => [$key=>['label'=>$label,'type'=>'select','options'=>$options,'default'=>$default]];
    $colour = static fn(string $label,string $key,string $default): array => [$key=>['label'=>$label,'type'=>'color','default'=>$default]];
    $catalog=[];
    $catalog['Site & Brand']=array_merge(
        $text('Site name','site_name','GameScore'),
        $colour('Accent colour','site_accent',cfg()['site']['accent']??'#8b5cf6'),
        $text('Browser title suffix','seo_title_suffix',' · GameScore'),
        $text('Meta description','seo_description','Factual game information and verified updates'),
        $text('Default language','default_language','en'),
        $text('Brand mark','brand_mark','GS'),
        $check('Show footer','footer_enabled'),
        $check('Show verification labels','verification_labels'),
        $check('Show source badges','source_badges'),
        $check('Enable custom CSS','custom_css_enabled')
    );
    $catalog['Homepage']=array_merge(
        $num('Featured games','homepage_game_count',6),$num('Recent updates','homepage_update_count',8),
        $check('Show hero','homepage_show_hero'),$check('Show featured games','homepage_show_games'),$check('Show recent updates','homepage_show_updates'),$check('Show source chain','homepage_show_sources'),
        $text('Hero eyebrow','homepage_eyebrow','GAME INTELLIGENCE'),$text('Hero title','homepage_title','Know what changed.'),$text('Hero subtitle','homepage_subtitle','Before everyone else.'),$text('Featured heading','homepage_featured_heading','Games worth watching')
    );
    $catalog['Games']=array_merge(
        $num('Games per page','games_per_page',24),
        $select('Sort order','games_sort',['name'=>'Name','updated'=>'Recently updated','featured'=>'Featured first'],'name'),
        $check('Show descriptions','games_show_descriptions'),$check('Show fallback artwork','games_fallback_art'),$check('Enable game pages','games_pages_enabled'),
        $check('Allow featured flag','games_featured_enabled'),$check('Allow manual games','games_manual_enabled'),$check('Show Steam App ID','games_show_steam_ids'),$check('Show game metadata','games_show_metadata'),$check('Require published flag','games_require_published',true)
    );
    $catalog['Updates']=array_merge(
        $num('Updates per page','updates_per_page',50),$num('Maximum body length','updates_max_body',4000),
        $select('Sort order','updates_sort',['newest'=>'Newest first','oldest'=>'Oldest first'],'newest'),
        $check('Show source name','updates_show_source_name'),$check('Show source URL','updates_show_source_url'),$check('Show verification state','updates_show_verification'),$check('Show publication date','updates_show_date'),
        $check('Allow manual publishing','updates_manual_enabled'),$check('Allow imported updates','updates_import_enabled'),$check('Clean external formatting','updates_clean_formatting')
    );
    $catalog['Sources']=array_merge(
        $check('Automatic sync','sources_auto_sync'),$num('HTTP timeout seconds','sources_timeout',12),$num('Maximum items per source','sources_max_items',20),
        $text('HTTP user agent','sources_user_agent','GameScore/1.1'),$check('Verify TLS certificates','sources_verify_tls',true),$check('Follow redirects','sources_follow_redirects',true),
        $check('Require titles','sources_require_title',true),$check('Store source URLs','sources_store_url',true),$check('Store external IDs','sources_store_external_id',true),$check('Trust verified sources','sources_trust_verified')
    );
    $catalog['API Sources']=array_merge(
        $check('Allow JSON APIs','source_json_enabled'),$check('Allow RSS feeds','source_rss_enabled'),$check('Allow Atom feeds','source_atom_enabled'),$select('Default HTTP method','source_default_method',['GET'=>'GET','POST'=>'POST'],'GET'),
        $num('Default sync interval minutes','source_default_interval',60),$num('Maximum configured sources','source_max_count',50),$check('Require HTTPS','source_require_https',true),$check('Allow custom headers','source_custom_headers',true),$check('Allow field mapping','source_field_mapping',true),$check('Show source diagnostics','source_diagnostics',true)
    );
    $catalog['Steam']=array_merge(
        $check('Steam News integration','steam_enabled'),$num('Items per game','steam_count',10),$num('Maximum source length','steam_max_length',8000),$check('Mark Steam as verified','steam_verify',true),
        $check('Import announcements','steam_import_announcements',true),$check('Import patch notes','steam_import_patchnotes',true),$check('Ignore duplicate posts','steam_ignore_duplicates',true),$check('Clean Steam BBCode','steam_clean_bbcode',true),$check('Use Steam publication date','steam_use_date',true),$check('Run Steam from cron','automation_sync_steam',true)
    );
    $catalog['RSS & Feeds']=array_merge(
        $check('Enable RSS','rss_enabled'),$check('Enable Atom','atom_enabled'),$num('Maximum feed items','feed_max_items',20),$num('Feed timeout seconds','feed_timeout',12),$check('Follow feed redirects','feed_follow_redirects',true),
        $check('Validate XML','feed_validate_xml',true),$check('Import descriptions','feed_import_descriptions',true),$check('Import dates','feed_import_dates',true),$check('Deduplicate IDs','feed_dedupe',true),$check('Trust verified feeds','feed_trust_verified')
    );
    $catalog['Users & Auth']=array_merge(
        $check('Allow registration','users_registration',true),$num('Minimum password length','auth_password_min',8),$check('Require strong passwords','auth_strong_passwords'),$check('Allow profile editing','users_profile_edit',true),
        $check('Allow account deletion','users_delete',true),$check('Regenerate session on login','auth_regenerate_session',true),$num('Session lifetime minutes','auth_session_minutes',120),$check('Allow password reset','auth_password_reset'),
        $check('Require CSRF protection','auth_csrf',true),$check('Log authentication events','auth_log_events',true)
    );
    $catalog['Public API']=array_merge(
        $check('Public API enabled','public_api_enabled',true),$num('Maximum game records','public_api_games_limit',100),$num('Maximum update records','public_api_updates_limit',100),$text('API version label','public_api_version','v1'),
        $check('Allow CORS','api_cors'),$text('CORS origin','api_cors_origin','*'),$check('Expose source URLs','api_expose_source_urls',true),$check('Expose verification state','api_expose_verification',true),$check('Expose unpublished data','api_expose_unpublished'),$check('Log API requests','api_log_requests')
    );
    $catalog['Automation']=array_merge(
        $check('Configured sources from cron','automation_sources',true),$check('Steam sync from cron','automation_sync_steam',true),$num('Maximum jobs per run','automation_max_jobs',50),$num('Minimum source interval','automation_min_interval',5),
        $check('Skip disabled sources','automation_skip_disabled',true),$check('Log cron runs','automation_log_runs',true),$check('Retry failed sources','automation_retry'),$num('Retry count','automation_retry_count',2),$check('Stop repeated failures','automation_stop_failures'),$check('Update source status','automation_status',true)
    );
    $catalog['Website Updater']=array_merge(
        $check('Updater enabled','updater_enabled',true),$text('GitHub repository','updater_repo','thewhistledev/gamescore'),$text('Branch','updater_branch','main'),$text('Manifest path','updater_manifest','update.json'),
        $check('Create backups','updater_backups',true),$check('Verify SHA-256','updater_verify_hash',true),$check('Allow automatic updates','updater_auto'),$check('Show update notices','updater_notices',true),$check('Protect config','updater_protect_config',true),$check('Protect storage','updater_protect_storage',true)
    );
    $catalog['Payments']=array_merge(
        $check('Enable payments','payments_enabled',false),$select('Provider','payments_provider',['stripe'=>'Stripe'],'stripe'),$text('Currency','payments_currency','gbp'),$check('Require login','payments_require_login',true),
        $check('Hosted checkout','payments_hosted_checkout',true),$check('Payment logging','payments_log',true),$check('Webhook processing','payments_webhooks',true),$check('Refund support','payments_refunds'),$check('Payment history','payments_history'),$check('Show checkout controls','payments_show_button',true)
    );
    $catalog['Security & SEO']=array_merge(
        $check('Force HTTPS','security_https'),$check('Secure cookies','security_secure_cookies'),$check('HTTP-only cookies','security_httponly',true),$check('Security headers','security_headers',true),$check('Clickjacking protection','security_frame',true),
        $check('Content type protection','security_content_type',true),$check('Canonical URLs','seo_canonical',true),$check('Open Graph tags','seo_og',true),$check('Twitter cards','seo_twitter',true),$check('Game structured data','seo_game_schema',true)
    );
    return $catalog;
}
