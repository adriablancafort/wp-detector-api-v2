<?php
header('Content-Type: application/json');

$url = $_GET['url'];
$parsedUrl = parse_url($url);
$websiteHost = $parsedUrl['host'];

// query db

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$htmlContent = curl_exec($ch);
curl_close($ch);

// wp-content
$pattern = '/http[^"]*' . preg_quote($websiteHost, '/') . '[^"]*-content\//i';
if (preg_match($pattern, $htmlContent, $matches)) {
    $wpContentPath = $matches[0];
} else {
    echo json_encode(['wp' => 'false']); // return top themes / plugins from database
    return;
}

// themes and plugins
preg_match_all('/content\/(themes|plugins)\/([^\/]+)\//', $htmlContent, $matches);

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
            $themes[] = $theme;
        }
    }
    curl_multi_remove_handle($multiHandle, $ch);
    curl_close($ch);
}

foreach ($pluginHandles as $pluginSlug => $ch) {
    $readmeTxtContent = curl_multi_getcontent($ch);
    if (!empty($readmeTxtContent)) {
        $plugin = plugin_info_readme($readmeTxtContent, $pluginSlug);
        if (!empty($plugin)) {
            $plugins[] = $plugin;
        }
    }
    curl_multi_remove_handle($multiHandle, $ch);
    curl_close($ch);
}

curl_multi_close($multiHandle);

echo json_encode([
    'wp' => 'true',
    'themes' => $themes,
    'plugins' => $plugins,
]);

// write themes and plugins to database


// functions

function theme_info_directory($data)
{
    $creationTime = substr($data['creation_time'], 0, 10); // Remove the hour
    $homepage = $data['homepage']; // remove the utm parameters
    $description = $data['sections']['description']; // limit size
    $description = substr($description, 0, 1000);

    $theme = [
        'name' => $data['name'],
        'slug' => $data['slug'],
        'version' => $data['version'],
        'author' => $data['author']['author'],
        'author_url' => $data['author']['author_url'],
        'screenshot_url' => "https:" . $data['screenshot_url'],
        'rating' => $data['rating'],
        'num_ratings' => $data['num_ratings'],
        'downloaded' => $data['downloaded'],
        'last_updated' => $data['last_updated'],
        'creation_time' => $creationTime,
        'homepage' => $homepage,
        'requires' => $data['requires'],
        'tested' => null,
        'requires_php' => $data['requires_php'],
        'description' => $description,
        'link' => "https://wordpress.org/themes/" . $data['slug'],
    ];

    return $theme;
}

function plugin_info_directory($data)
{
    $contributors = $data['contributors'];
    $lastUpdated = substr($data['last_updated'], 0, 10); // Remove the hour
    $creationTime = substr($data['added'], 0, 10); // Remove the hour
    $homepage = $data['homepage']; // remove the utm parameters
    $description = $data['sections']['description']; // limit size
    $description = substr($description, 0, 1000);

    $plugin = [
        'name' => $data['name'],
        'slug' => $data['slug'],
        'version' => $data['version'],
        'contributors' => $contributors,
        'requires' => $data['requires'],
        'tested' => $data['tested'],
        'requires_php' => $data['requires_php'],
        'rating' => $data['rating'],
        'num_ratings' => $data['num_ratings'],
        'active_installs' => $data['active_installs'],
        'last_updated' => $lastUpdated,
        'creation_time' => $creationTime,
        'homepage' => $homepage,
        'description' => $description,
        'banner' => $data['banners']['low'],
        'link' => "https://wordpress.org/plugins/" . $data['slug'],
    ];

    return $plugin;
}

function theme_info_styles($styleCssContent, $slug, $wpContentPath)
{
    preg_match('/Theme Name: (.*)/', $styleCssContent, $matches);
    if (isset($matches[1])) {
        $name = trim($matches[1]);
    } else {
        return null; // The title should exist
    }

    preg_match('/Theme URI: (.*)/', $styleCssContent, $matches);
    if (isset($matches[1])) {
        $parsedUrl = parse_url($matches[1]);
        $sanatizedHomepage = $parsedUrl['host'] ?? null;
        $homepage = $sanatizedHomepage ? $parsedUrl['scheme'] . '://' . $sanatizedHomepage : null;
    } else {
        $homepage = null;
        $sanatizedHomepage = null;
    }

    preg_match('/Author: (.*)/', $styleCssContent, $matches);
    $author = isset($matches[1]) ? trim($matches[1]) : null;

    preg_match('/Version: (.*)/', $styleCssContent, $matches);
    $version = isset($matches[1]) ? trim($matches[1]) : null;

    preg_match('/Requires at least: (.*)/', $styleCssContent, $matches);
    $reqWpVersion = isset($matches[1]) ? trim($matches[1]) . ' or higher' : null;

    preg_match('/Tested up to: (.*)/', $styleCssContent, $matches);
    $testedWpVersion = isset($matches[1]) ? trim($matches[1]) : null;

    preg_match('/Requires PHP: (.*)/', $styleCssContent, $matches);
    $reqPhpVersion = isset($matches[1]) ? trim($matches[1]) . ' or higher' : null;

    preg_match('/Description: (.*)/', $styleCssContent, $matches);
    $description = isset($matches[1]) ? trim($matches[1]) : null;
    $description = substr($description, 0, 1000);

    $theme = [
        'name' => $name,
        'slug' => $slug,
        'version' => $version,
        'author' => $author,
        'author_url' => null,
        'screenshot_url' => $wpContentPath . "themes/" . $slug . "/screenshot.png",
        'rating' => null,
        'num_ratings' => null,
        'downloaded' => null,
        'last_updated' => null,
        'creation_time' => null,
        'homepage' => $homepage,
        'requires' => $reqWpVersion,
        'tested' => $testedWpVersion,
        'requires_php' => $reqPhpVersion,
        'description' => $description,
        'link' => null,
    ];

    return $theme;
}

function plugin_info_readme($readmeTxtContent, $slug)
{
    preg_match('/=== (.*) ===/', $readmeTxtContent, $matches);
    if (isset($matches[1])) {
        $name = trim($matches[1]);
    } else {
        return null; // The name should exist
    }

    preg_match('/Contributors: (.*)/', $readmeTxtContent, $matches);
    $contributors = isset($matches[1]) ? trim($matches[1]) : null;

    preg_match('/Stable tag: (.*)/', $readmeTxtContent, $matches);
    $version = isset($matches[1]) ? trim($matches[1]) : null;

    preg_match('/Donate link: (.*)/', $readmeTxtContent, $matches);

    $homepage = null;
    $sanatizedHomepage = null;

    if (!empty($matches[1])) {
        // The donate link exists and is not PayPal
        if (strpos($matches[1], 'paypal') === false) {
            $parsedUrl = parse_url($matches[1]);
            $sanatizedHomepage = $parsedUrl['host'] ?? null;
            $homepage = $sanatizedHomepage ? $parsedUrl['scheme'] . '://' . $sanatizedHomepage : null;
        }
    }

    preg_match('/Requires at least: (.*)/', $readmeTxtContent, $matches);
    $reqWpVersion = isset($matches[1]) ? $matches[1] . ' or higher' : null;

    preg_match('/Tested up to: (.*)/', $readmeTxtContent, $matches);
    $testedWpVersion = isset($matches[1]) ? trim($matches[1]) : null;

    preg_match('/Requires PHP: (.*)/', $readmeTxtContent, $matches);
    $reqPhpVersion = isset($matches[1]) ? $matches[1] . ' or higher' : null;

    preg_match('/== Description ==\n\n(.*?)\n==/s', $readmeTxtContent, $matches); // Description until the next "=="
    $description = $matches[1] ?? null;
    $description = substr($description, 0, 1000); // Limit the description to 1000 characters
    $description = str_replace(['"', "'"], ' ', $description); // Remove quotes

    $plugin = [
        'name' => $name,
        'slug' => $slug,
        'version' => $version,
        'contributors' => $contributors,
        'requires' => $reqWpVersion,
        'tested' => $testedWpVersion,
        'requires_php' => $reqPhpVersion,
        'rating' => null,
        'num_ratings' => null,
        'active_installs' => null,
        'last_updated' => null,
        'creation_time' => null,
        'homepage' => $homepage,
        'description' => $description,
        'banner' => null,
        'link' => null,
    ];

    return $plugin;
}