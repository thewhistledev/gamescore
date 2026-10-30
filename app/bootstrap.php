<?php
declare(strict_types=1);
session_start();
const APP_ROOT=__DIR__.'/..';const CONFIG_FILE=APP_ROOT.'/config/config.php';
function cfg():array{static $c;if($c!==null)return $c;$c=is_file(CONFIG_FILE)?require CONFIG_FILE:[];return $c;}
require_once APP_ROOT.'/app/i18n.php';
function installed():bool{return is_file(CONFIG_FILE)&&is_file(APP_ROOT.'/storage/installed.lock');}
function db():PDO{static $pdo;if($pdo)return $pdo;$c=cfg();$pdo=new PDO($c['db']['dsn'],$c['db']['user']??null,$c['db']['pass']??null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);return $pdo;}
function e(?string $v):string{return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8');}
function url(string $p='/'):string{$base=rtrim(dirname($_SERVER['SCRIPT_NAME']??''),'/');return($base==='/'?'':$base).'/'.ltrim($p,'/');}
function redirect(string $p):never{header('Location: '.url($p));exit;}
function csrf():string{if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));return $_SESSION['csrf'];}
function check_csrf():void{if(!hash_equals($_SESSION['csrf']??'',$_POST['_csrf']??'')){http_response_code(419);exit('Invalid request token.');}}
function user():?array{static $u;if($u!==null)return $u;if(empty($_SESSION['uid']))return null;$s=db()->prepare('SELECT * FROM users WHERE id=?');$s->execute([$_SESSION['uid']]);return $u=$s->fetch()?:null;}
function require_login():void{if(!user())redirect('/login');}
function require_admin():void{if(!user()||user()['role']!=='admin'){http_response_code(403);exit('Forbidden');}}
function login_user(int $id):void{session_regenerate_id(true);$_SESSION['uid']=$id;}
function logout_user():void{$_SESSION=[];if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);}session_destroy();}
function flash(?string $set=null):?string{if($set!==null){$_SESSION['flash']=$set;return null;}$x=$_SESSION['flash']??null;unset($_SESSION['flash']);return $x;}
function render(string $title,string $content):void{$site=cfg()['site']??['name'=>'GameScore','accent'=>'#8b5cf6'];$u=user();$f=flash();require APP_ROOT.'/views/layout.php';}
function page_home():void{
    $gameCount=max(1,(int)setting_value('homepage_game_count',6));$updateCount=max(1,(int)setting_value('homepage_update_count',8));
    $games=db()->query('SELECT * FROM games WHERE published=1 ORDER BY featured DESC,updated_at DESC LIMIT '.(int)$gameCount)->fetchAll();
    $updates=db()->query('SELECT u.*,g.name game_name,g.slug FROM updates u JOIN games g ON g.id=u.game_id ORDER BY u.published_at DESC LIMIT '.(int)$updateCount)->fetchAll();
    ob_start();require APP_ROOT.'/views/home.php';render('Discover games',ob_get_clean());
}
function page_games():void{
    $limit=max(1,(int)setting_value('games_per_page',24));$sort=setting_value('games_sort','name');$order=$sort==='updated'?'updated_at DESC':($sort==='featured'?'featured DESC,name ASC':'name ASC');
    $games=db()->query('SELECT * FROM games WHERE published=1 ORDER BY '.$order.' LIMIT '.(int)$limit)->fetchAll();ob_start();require APP_ROOT.'/views/games.php';render('Games',ob_get_clean());
}
function page_updates():void{
    $limit=max(1,(int)setting_value('updates_per_page',50));$order=setting_value('updates_sort','newest')==='oldest'?'ASC':'DESC';
    $updates=db()->query('SELECT u.*,g.name game_name,g.slug FROM updates u JOIN games g ON g.id=u.game_id ORDER BY u.published_at '.$order.' LIMIT '.(int)$limit)->fetchAll();ob_start();require APP_ROOT.'/views/updates.php';render('Latest updates',ob_get_clean());
}
function page_game(string $slug):void{$s=db()->prepare('SELECT * FROM games WHERE slug=? AND published=1');$s->execute([$slug]);$game=$s->fetch();if(!$game){http_response_code(404);exit('Game not found');}$s=db()->prepare('SELECT * FROM updates WHERE game_id=? ORDER BY published_at DESC LIMIT 30');$s->execute([$game['id']]);$updates=$s->fetchAll();ob_start();require APP_ROOT.'/views/game.php';render($game['name'],ob_get_clean());}
function page_auth(bool $register):void{if(user())redirect('/');$error=null;if($_SERVER['REQUEST_METHOD']==='POST'){check_csrf();$email=strtolower(trim($_POST['email']??''));$pass=$_POST['password']??'';if($register){$name=trim($_POST['name']??'');$min=max(8,(int)setting_value('auth_password_min',8));if(!setting_value('users_registration',true))$error='Registration is currently disabled.';elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pass)<$min||strlen($name)<2)$error='Use a valid name, email and password of at least '.$min.' characters.';else{try{$s=db()->prepare('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)');$s->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT)]);login_user((int)db()->lastInsertId());redirect('/dashboard');}catch(Throwable $x){$error='That email is already registered.';}}}else{$s=db()->prepare('SELECT * FROM users WHERE email=?');$s->execute([$email]);$u=$s->fetch();if($u&&password_verify($pass,$u['password_hash'])){login_user((int)$u['id']);redirect('/dashboard');}$error='Email or password was incorrect.';}}ob_start();require APP_ROOT.'/views/auth.php';render($register?'Create account':'Sign in',ob_get_clean());}
