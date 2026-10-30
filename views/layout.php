<!doctype html>
<html lang="<?=e(function_exists('app_locale')?app_locale():'en_GB')?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="<?=e(function_exists('setting_value')?(string)setting_value('seo_description',t('site.meta_description')):t('site.meta_description'))?>">
<title><?=e($title)?><?=function_exists('setting_value')?e((string)setting_value('seo_title_suffix',' · GameScore')):' · '.e($site['name'])?></title>
<link rel="stylesheet" href="<?=url('/assets/app.css?v=1.3.0')?>">
<link rel="stylesheet" href="<?=url('/assets/steam.css?v=1.3.0')?>">
<?php if(strtolower($title)==='admin'): ?><link rel="stylesheet" href="<?=url('/assets/admin.css?v=1.3.0')?>"><?php endif; ?>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{background:'hsl(var(--background))',foreground:'hsl(var(--foreground))',card:'hsl(var(--card))',muted:'hsl(var(--muted))',border:'hsl(var(--border))',primary:'hsl(var(--primary))',ring:'hsl(var(--ring))'}}}};</script>
<style>:root{--background:240 10% 3%;--foreground:220 14% 96%;--card:224 20% 7%;--muted:224 18% 10%;--muted-foreground:220 9% 62%;--border:223 16% 17%;--input:223 16% 14%;--primary:262 83% 66%;--primary-foreground:0 0% 100%;--ring:262 83% 66%;--radius:.75rem}</style>
</head>
<body class="<?=strtolower($title)==='admin'?'admin-page':''?> bg-background text-foreground" style="--accent:<?=e(function_exists('setting_value')?(string)setting_value('site_accent',$site['accent']??'#8b5cf6'):($site['accent']??'#8b5cf6'))?>">
<?php $liveName=function_exists('setting_value')?(string)setting_value('site_name',$site['name']??'GameScore'):($site['name']??'GameScore'); ?>
<header class="nav">
  <a class="brand" href="<?=url('/')?>"><span class="brand-mark">G</span><span><?=e($liveName)?></span></a>
  <nav>
    <a href="<?=url('/games')?>"><?=e(t('nav.games'))?></a>
    <a href="<?=url('/updates')?>"><?=e(t('nav.updates'))?></a>
    <?php if($u): ?>
      <a href="<?=url('/dashboard')?>"><?=e(t('nav.dashboard'))?></a>
      <?php if($u['role']==='admin'): ?><a href="<?=url('/admin')?>"><?=e(t('nav.admin'))?></a><?php endif; ?>
      <a class="nav-user" href="<?=url('/logout')?>"><?=e(t('nav.sign_out'))?></a>
    <?php else: ?>
      <a href="<?=url('/login')?>"><?=e(t('nav.sign_in'))?></a>
      <a class="button small" href="<?=url('/register')?>"><?=e(t('nav.join'))?></a>
    <?php endif; ?>
  </nav>
</header>
<?php if($f): ?><div class="toast"><?=e($f)?></div><?php endif; ?>
<main><?=$content?></main>
<footer><span>© <?=date('Y')?> <?=e($liveName)?></span><span><?=e(t('footer.verified_sources'))?> · <?=e(t('footer.editorial_control'))?> · <?=e(t('footer.open_api'))?></span></footer>
<?php if(function_exists('setting_value')&&setting_value('custom_css_enabled',true)&&setting_value('custom_css','')): ?><style><?=setting_value('custom_css','')?></style><?php endif; ?>
</body>
</html>
