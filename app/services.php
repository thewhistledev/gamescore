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
        INDEX(game_id),
        FOREIGN KEY(game_id) REFERENCES games(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS admin_logs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NULL,
        action VARCHAR(120) NOT NULL,
        details TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try { $p->exec("ALTER TABLE updates ADD COLUMN body_format VARCHAR(32) NOT NULL DEFAULT 'text' AFTER body"); } catch (Throwable) {}
}

function admin_log(string $action, string $details = ''): void {
    try {
        $s = db()->prepare('INSERT INTO admin_logs(user_id,action,details) VALUES(?,?,?)');
        $s->execute([user()['id'] ?? null, $action, $details]);
    } catch (Throwable) {}
}

function clean_update_body(string $body): string {
    $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $body = preg_replace('~\[img(?:=[^\]]+)?\].*?\[/img\]~is', '', $body) ?? $body;
    $body = preg_replace('~\[url=([^\]]+)\](.*?)\[/url\]~is', '$2', $body) ?? $body;
    $body = preg_replace('~\[url\](.*?)\[/url\]~is', '$1', $body) ?? $body;
    $body = preg_replace('~\[/?(?:h[1-6]|b|i|u|strike|s|list|olist|table|tr|th|td|center|left|right|hr|code|spoiler|preview|noparse|expand|collapse|p)\]~i', "\n", $body) ?? $body;
    $body = preg_replace('~\[\*\]\s*~', "\n• ", $body) ?? $body;
    $body = preg_replace('~\[[^\]]+\]~', '', $body) ?? $body;
    $body = strip_tags($body);
    $body = preg_replace("/\r\n?/", "\n", $body) ?? $body;
    $body = preg_replace("/\n{3,}/", "\n\n", $body) ?? $body;
    return trim($body);
}

function http_fetch(string $url, string $method = 'GET', string $headers = ''): ?string {
    if (!filter_var($url, FILTER_VALIDATE_URL)) return null;
    $ch = curl_init($url);
    $headerList = [];
    foreach (preg_split('/\r?\n/', trim($headers)) ?: [] as $h) {
        if ($h !== '') $headerList[] = $h;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => (bool) setting_value('sources_follow_redirects', true),
        CURLOPT_TIMEOUT => max(3, (int) setting_value('sources_timeout', 12)),
        CURLOPT_USERAGENT => (string) setting_value('sources_user_agent', 'GameScore/1.3.1'),
        CURLOPT_HTTPHEADER => $headerList,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_SSL_VERIFYPEER => (bool) setting_value('sources_verify_tls', true),
        CURLOPT_SSL_VERIFYHOST => (bool) setting_value('sources_verify_tls', true) ? 2 : 0,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $raw !== false && $code >= 200 && $code < 400 ? $raw : null;
}

function setting_value(string $key, mixed $default = null): mixed {
    try {
        $s = db()->prepare('SELECT setting_value FROM settings WHERE setting_key=?');
        $s->execute([$key]);
        $v = $s->fetchColumn();
    } catch (Throwable) {
        return $default;
    }
    if ($v === false) return $default;
    $json = json_decode((string) $v, true);
    return ($json === null && $v !== 'null') ? $v : $json;
}

function save_setting_value(string $key, mixed $value): void {
    $encoded = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_SLASHES);
    $s = db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $s->execute([$key, $encoded]);
}

function json_path(mixed $data, string $path): mixed {
    foreach (explode('.', trim($path, '.')) as $part) {
        if ($part === '') continue;
        if (!is_array($data) || !array_key_exists($part, $data)) return null;
        $data = $data[$part];
    }
    return $data;
}

function sync_configured_source(array $source): int {
    $raw = http_fetch($source['endpoint'], $source['method'] ?: 'GET', $source['headers'] ?? '');
    if ($raw === null) {
        db()->prepare('UPDATE sources SET last_run_at=NOW(),last_status=? WHERE id=?')->execute(['Fetch failed', $source['id']]);
        return 0;
    }
    $map = json_decode($source['mapping_json'] ?: '{}', true) ?: [];
    $items = [];
    if (in_array($source['type'], ['rss', 'atom'], true)) {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($raw);
        if ($xml) {
            $nodes = isset($xml->channel->item) ? $xml->channel->item : ($xml->entry ?? []);
            foreach ($nodes as $item) {
                $items[] = [
                    'title' => (string) ($item->title ?? ''),
                    'body' => (string) ($item->description ?? $item->summary ?? $item->content ?? ''),
                    'url' => (string) ($item->link['href'] ?? $item->link ?? ''),
                    'id' => (string) ($item->guid ?? $item->id ?? ''),
                    'date' => (string) ($item->pubDate ?? $item->published ?? $item->updated ?? ''),
                ];
            }
        }
    } else {
        $json = json_decode($raw, true);
        if (!is_array($json)) return 0;
        $items = !empty($map['items']) ? json_path($json, $map['items']) : $json;
        if (is_array($items) && isset($items['items'])) $items = $items['items'];
        if (!is_array($items)) $items = [];
    }
    $count = 0;
    $max = max(1, (int) setting_value('sources_max_items', 20));
    foreach (array_slice($items, 0, $max) as $item) {
        if (!is_array($item)) continue;
        $title = (string) (!empty($map['title']) ? json_path($item, $map['title']) : ($item['title'] ?? ''));
        $body = (string) (!empty($map['body']) ? json_path($item, $map['body']) : ($item['body'] ?? $item['description'] ?? $item['content'] ?? ''));
        $url = (string) (!empty($map['url']) ? json_path($item, $map['url']) : ($item['url'] ?? $item['link'] ?? ''));
        $id = (string) (!empty($map['id']) ? json_path($item, $map['id']) : ($item['id'] ?? $item['guid'] ?? sha1($title . $url)));
        $date = (string) (!empty($map['date']) ? json_path($item, $map['date']) : ($item['published_at'] ?? $item['published'] ?? $item['date'] ?? ''));
        if ($title === '' || !$source['game_id']) continue;
        $external = 'source:' . $source['id'] . ':' . $id;
        $q = db()->prepare('SELECT id FROM updates WHERE external_id=?');
        $q->execute([$external]);
        if ($q->fetch()) continue;
        $clean = mb_substr(clean_update_body($body), 0, max(100, (int) setting_value('updates_max_body', 4000)));
        $ts = strtotime($date) ?: time();
        $q = db()->prepare('INSERT INTO updates(game_id,title,body,body_format,source_name,source_url,external_id,published_at,verified) VALUES(?,?,?,?,?,?,?,?,?)');
        $q->execute([$source['game_id'], mb_substr($title, 0, 255), $clean, 'text', $source['name'], $url, $external, date('Y-m-d H:i:s', $ts), !empty($source['verified']) ? 1 : 0]);
        $count++;
    }
    db()->prepare('UPDATE sources SET last_run_at=NOW(),last_status=? WHERE id=?')->execute(['OK: ' . $count, $source['id']]);
    return $count;
}

function sync_all_sources(): int {
    ensure_extended_schema();
    if (!setting_value('sources_auto_sync', true)) return 0;
    $count = 0;
    foreach (db()->query('SELECT * FROM sources WHERE enabled=1 ORDER BY id')->fetchAll() as $source) {
        $count += sync_configured_source($source);
    }
    return $count;
}
