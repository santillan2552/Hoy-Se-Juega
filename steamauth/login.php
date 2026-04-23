php<?php
require 'steamauth.php';
if (isset($_GET['login']) || !isset($_GET['logout'])) {
    login();
}
?>
