<?php
require_once __DIR__ . '/../class/config.php';

$steamauth['apikey'] = STEAM_API_KEY;
$steamauth['domainname'] = STEAM_DOMAIN_NAME;
$steamauth['logoutpage'] = STEAM_LOGOUT_PAGE;
$steamauth['loginpage'] = STEAM_LOGIN_PAGE;

if (empty($steamauth['apikey'])) {
    die("<div style='color: white; background: red; padding: 10px;'>SteamAuth: Please supply an API-Key in class/config.php!</div>");
}
if (empty($steamauth['domainname'])) {$steamauth['domainname'] = $_SERVER['SERVER_NAME'];}
if (empty($steamauth['logoutpage'])) {$steamauth['logoutpage'] = $_SERVER['PHP_SELF'];}
if (empty($steamauth['loginpage'])) {$steamauth['loginpage'] = $_SERVER['PHP_SELF'];}
?>
