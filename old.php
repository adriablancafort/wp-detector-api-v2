<?php
header('Content-Type: application/json');

$wpContentPath = "https://atlantidaclinicadental.cat/wp-content/";

$pluginSlugs = [
    "akismet", "all-in-one-wp-migration", "complianz-gdpr", "elementor", "connect-polylang-elementor", "contact-form-7", "contact-form-cfdb7", "metricool", "ocean-extra", "polylang", "facebook-conversion-pixel", "sucuri-scanner", "contact-form-7-image-captcha", "wp-mail-smtp", "wps-hide-login", "wordpress-seo"
];

$multiHandle = curl_multi_init();

foreach ($pluginSlugs as $pluginSlug) {
    $url = $wpContentPath . "/plugins/" . $pluginSlug . "/readme.txt";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_multi_add_handle($multiHandle, $ch);
    $handles[$pluginSlug] = $ch;
}

do {
    curl_multi_exec($multiHandle, $running);
    curl_multi_select($multiHandle);
} while ($running > 0);

foreach ($handles as $pluginSlug => $ch) {
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
