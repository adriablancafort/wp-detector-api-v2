<?php
header('Content-Type: application/json');

$url = $_GET['url'];
$parsedUrl = parse_url($url);
$host = $parsedUrl['host'];

echo "Host: " . $host . "\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$htmlContent = curl_exec($ch);
curl_close($ch);

// wp-content
$pattern = '/http[^"]*' . preg_quote($host, '/') . '[^"]*-content\//i';
if (preg_match($pattern, $htmlContent, $matches)) {
    $wpContentPath = $matches[0];
    echo "wp-content: " . $wpContentPath . "\n";
} else {
    echo "wp-content not found.";
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

$multiHandle = curl_multi_init();
$themeHandles = [];
$pluginHandles = [];

foreach ($themeSlugs as $themeSlug) {
    $url = $wpContentPath . "themes/" . $themeSlug . "/style.css";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_multi_add_handle($multiHandle, $ch);
    $themeHandles[$themeSlug] = $ch;
}

foreach ($pluginSlugs as $pluginSlug) {
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
    curl_multi_remove_handle($multiHandle, $ch);
    curl_close($ch);

    if ($styleCssContent) {
        preg_match('/Theme Name: (.*)/', $styleCssContent, $matches);
        echo isset($matches[1]) ? "Found: " . $matches[1] . "\n" : "Not match: " . $themeSlug . "\n";
    } else {
        echo "404: " . $themeSlug . "\n";
    }
}

foreach ($pluginHandles as $pluginSlug => $ch) {
    $readmeTxtContent = curl_multi_getcontent($ch);
    curl_multi_remove_handle($multiHandle, $ch);
    curl_close($ch);

    if ($readmeTxtContent) {
        preg_match('/=== (.*) ===/', $readmeTxtContent, $matches);
        echo isset($matches[1]) ? "Found: " . $matches[1] . "\n" : "Not match: " . $pluginSlug . "\n";
    } else {
        echo "404: " . $pluginSlug . "\n";
    }
}

curl_multi_close($multiHandle);
