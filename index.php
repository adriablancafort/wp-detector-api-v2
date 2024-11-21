<?php
header('Content-Type: application/json');
// do not display errors
ini_set('display_errors', '0');

include 'functions.php';

$url = $_GET['url'];
$parsedUrl = parse_url($url);
$websiteHost = $parsedUrl['host'];

// query db

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_ENCODING, '');
$htmlContent = curl_exec($ch);
curl_close($ch);

// wp-content
$patternWpContent = '/http[^"]*' . $websiteHost . '[^"]*-content\//i';
if (preg_match($patternWpContent, $htmlContent, $matches)) {
    $wpContentPath = $matches[0];
} else {
    echo json_encode(['detected' => false]); // return top themes / plugins from database
    return;
}

// themes and plugins
$patternThemesPlugins = '/content\/(themes|plugins)\/([a-zA-Z0-9-_]+)\//';
preg_match_all($patternThemesPlugins, $htmlContent, $matches);

$themeSlugs = [];
$pluginSlugs = [];

foreach ($matches[1] as $index => $type) {
    $slug = $matches[2][$index];
    if ($type === 'themes') {
        if (!in_array($slug, $themeSlugs)) {
            $themeSlugs[] = $slug;
        }
    } elseif ($type === 'plugins') {
        if (!in_array($slug, $pluginSlugs)) {
            $pluginSlugs[] = $slug;
        }
    }
}

/*
// check manual plugin slugs (custom logic: if cf7 is found, add all cf7 related)
$pluginSlugsManual = ['wordpress-seo', 'seo-by-rank-math', 'wp-mail-smtp'];
$pluginSlugsManual = array_diff($pluginSlugsManual, $pluginSlugs); // remove already found plugins

// paralelize requests
$pluginHandles = [];
$multiHandle = curl_multi_init();

// plugins
foreach ($pluginSlugsManual as $pluginSlug) {
    $url = $wpContentPath . "plugins/" . $pluginSlug . "/readme.txt";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_multi_add_handle($multiHandle, $ch);
    $pluginHandles[$pluginSlug] = $ch;
}

do {
    curl_multi_exec($multiHandle, $running);
    curl_multi_select($multiHandle);
} while ($running > 0);

foreach ($pluginHandles as $pluginSlug => $ch) {
    $readmeTxtContent = curl_multi_getcontent($ch);
    if (!empty($readmeTxtContent)) {
        preg_match('/=== (.*) ===/', $readmeTxtContent, $matches);
        if (isset($matches[1])) {
            $pluginSlugs[] = $pluginSlug;
        }
    }
    curl_multi_remove_handle($multiHandle, $ch);
    curl_close($ch);
}

curl_multi_close($multiHandle);

// themes and plugins in db
*/

$themes = [];
$plugins = [];

$themeSlugsStyles = [];
$pluginSlugsReadme = [];

// paralelize requests
$themeHandles = [];
$pluginHandles = [];
$multiHandle = curl_multi_init();

foreach ($themeSlugs as $themeSlug) {
    $url = "https://api.wordpress.org/themes/info/1.2/?action=theme_information&request[slug]=" . $themeSlug;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_multi_add_handle($multiHandle, $ch);
    $themeHandles[$themeSlug] = $ch;
}

foreach ($pluginSlugs as $pluginSlug) {
    $url = "https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=" . $pluginSlug;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_multi_add_handle($multiHandle, $ch);
    $pluginHandles[$pluginSlug] = $ch;
}

do {
    curl_multi_exec($multiHandle, $running);
    curl_multi_select($multiHandle);
} while ($running > 0);

foreach ($themeHandles as $themeSlug => $ch) {
    $theme = curl_multi_getcontent($ch);
    $data = json_decode($theme, true);
    if (isset($data['error'])) {
        $themeSlugsStyles[] = $themeSlug;
    } else {
        $themes[] = theme_info_directory($data);
    }
    curl_multi_remove_handle($multiHandle, $ch);
    curl_close($ch);
}

foreach ($pluginHandles as $pluginSlug => $ch) {
    $plugin = curl_multi_getcontent($ch);
    $data = json_decode($plugin, true);
    if (isset($data['error'])) {
        $pluginSlugsReadme[] = $pluginSlug;
    } else {
        $plugins[] = plugin_info_directory($data);
    }
    curl_multi_remove_handle($multiHandle, $ch);
    curl_close($ch);
}

curl_multi_close($multiHandle);

// website host as theme slug
preg_match('/^(?:www\.)?([^\.]+)/', $websiteHost, $matches);
$websiteHostSlug = $matches[1];
if (isset($websiteHostSlug) && !in_array($websiteHostSlug, $themeSlugs)) {
    $themeSlugsStyles[] = $websiteHostSlug; // add website host as theme slug
}

// paralelize requests
$themeHandles = [];
$pluginHandles = [];
$multiHandle = curl_multi_init();

foreach ($themeSlugsStyles as $themeSlug) {
    $url = $wpContentPath . "themes/" . $themeSlug . "/style.css";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_multi_add_handle($multiHandle, $ch);
    $themeHandles[$themeSlug] = $ch;
}

foreach ($pluginSlugsReadme as $pluginSlug) {
    $url = $wpContentPath . "plugins/" . $pluginSlug . "/readme.txt";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_multi_add_handle($multiHandle, $ch);
    $pluginHandles[$pluginSlug] = $ch;
}

do {
    curl_multi_exec($multiHandle, $running);
    curl_multi_select($multiHandle);
} while ($running > 0);

foreach ($themeHandles as $themeSlug => $ch) {
    $styleCssContent = curl_multi_getcontent($ch);
    if (!empty($styleCssContent)) {
        $theme = theme_info_styles($styleCssContent, $themeSlug, $wpContentPath);
        if (!empty($theme)) {
            // add missing attributes
            if ($theme['banner_url'] === null) {
                $theme['banner_url'] = "/no-theme-screenshot.svg";
            }
            $themes[] = $theme;
        }
    }
    curl_multi_remove_handle($multiHandle, $ch);
    curl_close($ch);
}

foreach ($pluginHandles as $pluginSlug => $ch) {
    $readmeTxtContent = curl_multi_getcontent($ch);
    $plugin = plugin_info_readme($readmeTxtContent, $pluginSlug);
    if (!empty($plugin)) {
        // add missing attributes
        if ($plugin['banner_url'] === null) {
            $plugin['banner_url'] = "/no-plugin-banner.svg";
        }
        $plugins[] = $plugin;
    }
    curl_multi_remove_handle($multiHandle, $ch);
    curl_close($ch);
}

curl_multi_close($multiHandle);

echo json_encode([
    'detected' => true,
    'themes' => $themes,
    'plugins' => $plugins,
]);

// write themes and plugins to database