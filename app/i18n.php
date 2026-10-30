<?php
declare(strict_types=1);

function app_locale(): string {
    $locale = 'en_GB';
    if (function_exists('setting_value')) {
        $locale = (string) setting_value('default_language', 'en_GB');
    } else {
        $config = cfg();
        $locale = (string) (($config['site']['language'] ?? null) ?: 'en_GB');
    }
    $locale = str_replace('-', '_', trim($locale));
    if ($locale === '' || strtolower($locale) === 'en') $locale = 'en_GB';
    return $locale;
}

function translations(string $locale = ''): array {
    static $cache = [];
    $locale = $locale !== '' ? $locale : app_locale();
    if (isset($cache[$locale])) return $cache[$locale];

    $file = APP_ROOT.'/translations/'.$locale.'.php';
    if (!is_file($file)) $file = APP_ROOT.'/translations/en_GB.php';
    $data = is_file($file) ? require $file : [];
    return $cache[$locale] = is_array($data) ? $data : [];
}

function t(string $key, array $replace = []): string {
    $text = translations()[$key] ?? null;
    if (!is_string($text) || $text === '') {
        $fallback = preg_replace('/^.*\./', '', $key) ?? $key;
        $fallback = str_replace(['_', '-'], ' ', $fallback);
        $text = ucwords($fallback);
    }
    foreach ($replace as $name => $value) {
        $text = str_replace('{'.$name.'}', (string)$value, $text);
    }
    return $text;
}

function admin_section_key(string $section): string {
    $map = [
        'overview' => 'admin.section.overview',
        'Site & Brand' => 'admin.section.site_brand',
        'Homepage' => 'admin.section.homepage',
        'Games' => 'admin.section.games',
        'Updates' => 'admin.section.updates',
        'Sources' => 'admin.section.sources',
        'API Sources' => 'admin.section.api_sources',
        'Steam' => 'admin.section.steam',
        'RSS & Feeds' => 'admin.section.rss_feeds',
        'Users & Auth' => 'admin.section.users_auth',
        'Public API' => 'admin.section.public_api',
        'Automation' => 'admin.section.automation',
        'Website Updater' => 'admin.section.website_updater',
        'Payments' => 'admin.section.payments',
        'Security & SEO' => 'admin.section.security_seo',
    ];
    return $map[$section] ?? $section;
}
