<?php
declare(strict_types=1);
require_once APP_ROOT.'/app/services.php';
require_once APP_ROOT.'/app/updater.php';

function page_admin_v2(): void {
    ensure_extended_schema();
    $games = db()->query('SELECT * FROM games ORDER BY name')->fetchAll();
    $sources = db()->query('SELECT s.*,g.name game_name FROM sources s LEFT JOIN games g ON g.id=s.game_id ORDER BY s.name')->fetchAll();
    $catalog = admin_catalog();
    $section = $_GET['section'] ?? 'overview';
    $message = null;
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        try {
            $action = $_POST['action'] ?? '';
            if ($action === 'settings') {
                foreach ($_POST['setting'] ?? [] as $key => $value) save_setting_value((string)$key, is_array($value) ? $value : (string)$value);
                admin_log('settings.update', implode(',', array_keys($_POST['setting'] ?? [])));
                $message = 'Configuration saved.';
            } elseif ($action === 'source') {
                $mapping = json_decode(trim($_POST['mapping_json'] ?? '{}'), true);
                if (!is_array($mapping)) throw new RuntimeException('Mapping JSON is invalid.');
                $endpoint = trim($_POST['endpoint'] ?? '');
                if (!filter_var($endpoint, FILTER_VALIDATE_URL)) throw new RuntimeException('A valid API/feed URL is required.');
                $s = db()->prepare('INSERT INTO sources(game_id,name,type,endpoint,method,headers,mapping_json,enabled,verified,interval_minutes) VALUES(?,?,?,?,?,?,?,?,?,?)');
                $s->execute([($_POST['game_id'] ?? '') !== '' ? (int)$_POST['game_id'] : null, trim($_POST['source_name']), $_POST['source_type'], $endpoint, $_POST['method'] ?? 'GET', trim($_POST['headers'] ?? ''), json_encode($mapping), !empty($_POST['enabled']), !empty($_POST['verified']), max(5, (int)($_POST['interval_minutes'] ?? 60))]);
                admin_log('source.create', trim($_POST['source_name']));
                $message = 'Source added.';
                $section = 'sources';
            } elseif ($action === 'sync') {
                $message = 'Source sync imported '.sync_all_sources().' new updates.';
                $section = 'sources';
            } elseif ($action === 'site_update') {
                $result = site_update(!empty($_POST['install']));
                $message = $result['message'];
                admin_log('site.update', $message);
                $section = 'updater';
            } elseif ($action === 'game') {
                $name = trim($_POST['name'] ?? '');
                $slug = trim($_POST['slug'] ?? '') ?: preg_replace('/[^a-z0-9]+/', '-', strtolower($name));
                $s = db()->prepare('INSERT INTO games(name,slug,description,image_url,steam_appid,featured,published) VALUES(?,?,?,?,?,?,?)');
                $s->execute([$name, $slug, trim($_POST['description'] ?? ''), trim($_POST['image_url'] ?? ''), ($_POST['steam_appid'] ?? '') !== '' ? (int)$_POST['steam_appid'] : null, !empty($_POST['featured']), !empty($_POST['published'])]);
                admin_log('game.create', $name);
                $message = 'Game added.';
                $section = 'games';
            } elseif ($action === 'update') {
                $body = clean_update_body(trim($_POST['body'] ?? ''));
                $s = db()->prepare('INSERT INTO updates(game_id,title,body,source_name,source_url,published_at,verified) VALUES(?,?,?,?,?,?,1)');
                $s->execute([(int)$_POST['game_id'], trim($_POST['title']), $body, 'Manual', trim($_POST['source_url'] ?? ''), date('Y-m-d H:i:s')]);
                admin_log('update.create', trim($_POST['title']));
                $message = 'Update published.';
                $section = 'updates';
            }
        } catch (Throwable $e) { $error = $e->getMessage(); }
    }
    $values = [];
    foreach ($catalog as $menu => $items) foreach ($items as $key => $meta) $values[$key] = setting_value($key, $meta['default']);
    $updater = updater_status();
    ob_start();
    require APP_ROOT.'/views/admin.php';
    render('Admin', ob_get_clean());
}
