<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/services.php';
require_once __DIR__.'/app/admin_page.php';
$route = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/');
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
if ($base && str_starts_with($route, trim($base,'/'))) $route = ltrim(substr($route, strlen(trim($base,'/'))), '/');
if (!installed()) { require __DIR__.'/app/install.php'; exit; }
$path = '/' . trim($route,'/');
if ($path === '/') { page_home(); exit; }
if ($path === '/login') { page_auth(false); exit; }
if ($path === '/register') { page_auth(true); exit; }
if ($path === '/logout') { logout_user(); redirect('/'); }
if ($path === '/games') { page_games(); exit; }
if ($path === '/updates') { page_updates(); exit; }
if ($path === '/dashboard') { require_login(); page_dashboard(); exit; }
if ($path === '/admin') { require_admin(); page_admin_v2(); exit; }
if ($path === '/checkout') { require_login(); stripe_checkout(); exit; }
if ($path === '/stripe/webhook') { stripe_webhook(); exit; }
if (preg_match('#^/game/([a-z0-9-]+)$#i',$path,$m)) { page_game($m[1]); exit; }
if ($path === '/api/games') { api_games(); exit; }
if ($path === '/api/updates') { api_updates(); exit; }
if ($path === '/cron') { api_cron_v2(); exit; }
http_response_code(404); render('Not found','<section class="empty"><h1>404</h1><p>This page wandered off. Humans lose things constantly.</p></section>');
