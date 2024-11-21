<?php

function theme_info_directory($data)
{
    $authorUrl = $data['author']['author_url']; // remove the utm parameters
    $parsedUrl = parse_url($authorUrl);
    $authorUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] ?? null;

    $creationTime = substr($data['creation_time'], 0, 10); // remove the hour

    // $homepage = $data['homepage']; // look for the correct one, remove the utm parameters.
    // in themes returns the wp directory url

    $description = $data['sections']['description'];
    $description = substr($description, 0, 1000); // limit size

    $theme = [
        'name' => $data['name'],
        'slug' => $data['slug'],
        'version' => $data['version'],
        'author' => $data['author']['author'],
        'author_url' => $authorUrl,
        'website_url' => $authorUrl,
        'sanatized_website' => $authorUrl,
        'screenshot_url' => "https:" . $data['screenshot_url'],
        'rating' => $data['rating'],
        'num_ratings' => $data['num_ratings'],
        'downloaded' => $data['downloaded'],
        'last_updated' => $data['last_updated'],
        'creation_time' => $creationTime,
        'requires' => $data['requires'],
        'tested' => null,
        'requires_php' => $data['requires_php'],
        'description' => $description,
        'link' => "https://wordpress.org/themes/" . $data['slug'] . "/",
    ];

    return $theme;
}

function plugin_info_directory($data)
{
    // $author = $data['author']; // clean
    // $authorUrl = $data['author']; // remove the utm parameters

    $contributorNames = [];
    foreach ($data['contributors'] as $contributor) {
        $contributorNames[] = $contributor['display_name'];
    }
    $contributors = implode(', ', $contributorNames);

    $lastUpdated = substr($data['last_updated'], 0, 10); // remove the hour
    $creationTime = substr($data['added'], 0, 10); // Remove the hour

    $homepage = $data['homepage']; // look for the correct one, remove the utm parameters
    $parsedHomepage = parse_url($homepage);
    $homepage = $parsedHomepage['scheme'] . '://' . $parsedHomepage['host'] ?? null;

    $banner = $data['banners']['low'];

    $plugin = [
        'name' => $data['name'],
        'slug' => $data['slug'],
        'version' => $data['version'],
        'contributors' => $contributors,
        'website_url' => $homepage,
        'sanatized_website' => $homepage,
        'requires' => $data['requires'],
        'tested' => $data['tested'],
        'requires_php' => $data['requires_php'],
        'rating' => $data['rating'],
        'num_ratings' => $data['num_ratings'],
        'active_installs' => $data['active_installs'],
        'last_updated' => $lastUpdated,
        'creation_time' => $creationTime,
        'description' => null,
        'banner_url' => $banner,
        'icon_url' => null,
        'link' => "https://wordpress.org/plugins/" . $data['slug'] . "/",
    ];

    return $plugin;
}

function theme_info_styles($styleCssContent, $slug, $wpContentPath)
{
    preg_match('/Theme Name: (.*)/', $styleCssContent, $matches);
    if (isset($matches[1])) {
        $themeName = trim($matches[1]);
    } else {
        return null; // The title should exist
    }

    preg_match('/Theme URI: (.*)/', $styleCssContent, $matches);
    if (isset($matches[1])) {
        $parsedUrl = parse_url($matches[1]);
        $homepage = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] ?? null;
    } else {
        $homepage = null;
    }

    preg_match('/Author: (.*)/', $styleCssContent, $matches);
    $author = isset($matches[1]) ? trim($matches[1]) : null;

    /*
    preg_match('/Author URI: (.*)/', $styleCssContent, $matches);
    if (isset($matches[1])) {
        $parsedUrl = parse_url($matches[1]);
        $authorUri = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] ?? null;
    } else {
        $authorUri = null;
    }
    */

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
    if (!empty($description)) {
        $description = substr($description, 0, 1000); // Limit the description to 1000 characters
        preg_replace('/[^\p{L}\p{N}\p{P}\p{Z}\s]/u', '', $description); // remove emojis, links, etc
    }

    $theme = [
        'name' => $themeName,
        'slug' => $slug,
        'version' => $version,
        'author' => $author,
        'screenshot_url' => $wpContentPath . "themes/" . $slug . "/screenshot.png",
        'website_url' => $homepage,
        'sanatized_website' => $homepage,
        'rating' => null,
        'num_ratings' => null,
        'downloaded' => null,
        'last_updated' => null,
        'creation_time' => null,
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
        $name = ucwords(str_replace(['-', '_'], ' ', $slug));
    }

    preg_match('/Contributors: (.*)/', $readmeTxtContent, $matches);
    $contributors = isset($matches[1]) ? trim($matches[1]) : null;

    preg_match('/Stable tag: (.*)/', $readmeTxtContent, $matches);
    $version = isset($matches[1]) ? trim($matches[1]) : null;

    preg_match('/Donate link: (.*)/', $readmeTxtContent, $matches);

    $homepage = null;
    if (!empty($matches[1])) {
        // The donate link exists and is not PayPal
        if (strpos($matches[1], 'paypal') === false) {
            $parsedUrl = parse_url($matches[1]);
            $homepage = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] ?? null;
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
    if (!empty($description)) {
        $description = substr($description, 0, 1000); // Limit the description to 1000 characters
        preg_replace('/[^\p{L}\p{N}\p{P}\p{Z}\s]/u', '', $description); // remove emojis, links, etc
    }

    $plugin = [
        'name' => $name,
        'slug' => $slug,
        'version' => $version,
        'contributors' => $contributors,
        'website_url' => null,
        'sanatized_website' => $homepage,
        'requires' => $reqWpVersion,
        'tested' => $testedWpVersion,
        'requires_php' => $reqPhpVersion,
        'rating' => null,
        'num_ratings' => null,
        'active_installs' => null,
        'last_updated' => null,
        'creation_time' => null,
        'description' => $description,
        'banner_url' => null,
        'icon_url' => null,
        'link' => null,
    ];

    return $plugin;
}