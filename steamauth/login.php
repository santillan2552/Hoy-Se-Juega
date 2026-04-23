<?php
require 'steamauth.php';
if (isset($_GET['logout'])) {
    logout();
} else {
    login();
}
?>
