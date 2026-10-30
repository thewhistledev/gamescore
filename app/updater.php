<?php
declare(strict_types=1);

function updater_manifest_url(): string {
    $repo = (string)setting_value('updater_repo', 'thewhistledev/gamescore');
    $branch = (string)setting_value('updater_branch', 'main');
    $manifest = ltrim((string)setting_value('updater_manifest', 'update.json'), '/');
    return 'https://raw.githubusercontent.com/'.rawurlencode(strtok($repo, '/')).'/'.rawurlencode(substr($repo, strpos($repo, '/') + 1)).'/'.rawurlencode($branch).'/'.$manifest;
}
function updater_manifest(): ?array {
    $raw = http_fetch(updater_manifest_url(), 'GET', 'Accept: application/json');
    $data = $raw ? json_decode($raw, true) : null;
    return is_array($data) ? $data : null;
}
function updater_status(): array {
    if (!setting_value('updater_enabled', true)) return ['available'=>false,'version'=>APP_VERSION,'message'=>'Updater disabled.'];
    $manifest = updater_manifest();
    if (!$manifest) return ['available'=>false,'version'=>APP_VERSION,'message'=>'Could not reach the GitHub update manifest.'];
    $remote = (string)($manifest['version'] ?? APP_VERSION);
    return ['available'=>version_compare($remote, APP_VERSION, '>'),'version'=>APP_VERSION,'remote'=>$remote,'message'=>version_compare($remote, APP_VERSION, '>') ? 'Update available.' : 'Site is up to date.','manifest'=>$manifest];
}
function site_update(bool $install = false): array {
    $status = updater_status();
    if (!$status['available']) return ['ok'=>true,'message'=>$status['message']];
    if (!$install) return ['ok'=>true,'message'=>'Update '.$status['remote'].' is available.'];
    $manifest = $status['manifest'] ?? null;
    if (!$manifest || empty($manifest['files']) || !is_array($manifest['files'])) return ['ok'=>false,'message'=>'The GitHub manifest contains no update file list.'];
    $root = APP_ROOT;
    $backupDir = $root.'/storage/update-backups/'.date('Ymd-His');
    if (setting_value('updater_backup', true)) @mkdir($backupDir, 0755, true);
    $repo = (string)setting_value('updater_repo', 'thewhistledev/gamescore');
    $branch = (string)setting_value('updater_branch', 'main');
    $owner = strtok($repo, '/');
    $name = substr($repo, strpos($repo, '/') + 1);
    $done = 0;
    foreach ($manifest['files'] as $file) {
        $path = ltrim((string)($file['path'] ?? ''), '/');
        if ($path === '' || str_contains($path, '..') || preg_match('#^(config/|storage/|\.git/)#i', $path)) continue;
        $rawUrl = 'https://raw.githubusercontent.com/'.$owner.'/'.$name.'/'.rawurlencode($branch).'/'.str_replace('%2F','/',rawurlencode($path));
        $raw = http_fetch($rawUrl);
        if ($raw === null) return ['ok'=>false,'message'=>'Download failed for '.$path.'. No partial install was committed.'];
        if (setting_value('updater_verify_hash', true) && !empty($file['sha256']) && !hash_equals(strtolower((string)$file['sha256']), strtolower(hash('sha256', $raw)))) return ['ok'=>false,'message'=>'Hash verification failed for '.$path.'.'];
        $target = $root.'/'.$path;
        if (setting_value('updater_backup', true) && is_file($target)) { @mkdir(dirname($backupDir.'/'.$path),0755,true); @copy($target,$backupDir.'/'.$path); }
        @mkdir(dirname($target),0755,true);
        $tmp = $target.'.update-'.bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $raw) === false || !@rename($tmp, $target)) { @unlink($tmp); return ['ok'=>false,'message'=>'Could not write '.$path.'.']; }
        $done++;
    }
    @file_put_contents($root.'/storage/update-last.json', json_encode(['version'=>$status['remote'],'files'=>$done,'at'=>date(DATE_ATOM)], JSON_PRETTY_PRINT));
    return ['ok'=>true,'message'=>'Updated to '.$status['remote'].' ('.$done.' files).'];
}
