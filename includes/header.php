<?php
if (!isset($pageTitle)) {
    $pageTitle = SITE_NAME;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo ri_h($pageTitle); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- Home-screen pinning: manifest for modern browsers, apple meta
     tags for iOS-style "add to home screen". No service worker on
     purpose - old WebKit (webOS TouchPad and friends) doesn't support
     one, and this site has nothing worth caching offline anyway. -->
<link rel="manifest" href="<?php echo ri_h(ri_url('/manifest.json')); ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black">
<meta name="apple-mobile-web-app-title" content="<?php echo ri_h(SITE_NAME); ?>">
<meta name="theme-color" content="#00d497">
<link rel="apple-touch-icon" href="<?php echo ri_h(ri_url('/icons/icon-180.png')); ?>">
<link rel="apple-touch-icon" sizes="152x152" href="<?php echo ri_h(ri_url('/icons/icon-152.png')); ?>">
<link rel="apple-touch-icon" sizes="144x144" href="<?php echo ri_h(ri_url('/icons/icon-144.png')); ?>">
<link rel="apple-touch-icon" sizes="120x120" href="<?php echo ri_h(ri_url('/icons/icon-120.png')); ?>">
<link rel="apple-touch-icon" sizes="114x114" href="<?php echo ri_h(ri_url('/icons/icon-114.png')); ?>">
<link rel="apple-touch-icon" sizes="76x76" href="<?php echo ri_h(ri_url('/icons/icon-76.png')); ?>">
<link rel="apple-touch-icon" sizes="72x72" href="<?php echo ri_h(ri_url('/icons/icon-72.png')); ?>">
<link rel="apple-touch-icon" sizes="60x60" href="<?php echo ri_h(ri_url('/icons/icon-60.png')); ?>">
<link rel="apple-touch-icon" sizes="57x57" href="<?php echo ri_h(ri_url('/icons/icon-57.png')); ?>">
<link rel="icon" href="<?php echo ri_h(ri_url('/favicon.ico')); ?>">

<link rel="stylesheet" href="<?php echo ri_h(ri_url('/style.css')); ?>">
</head>
<body>

<div id="header">
  <div id="headerContent">
    <a id="logo" href="<?php echo ri_h(ri_url('/')); ?>"><img id="logoIcon" src="<?php echo ri_h(ri_url('/icons/icon-72.png')); ?>" alt=""><?php echo ri_h(SITE_NAME); ?></a>
    <span id="tagline"><?php echo ri_h(SITE_TAGLINE); ?></span>
  </div>
</div>

<div id="content">
