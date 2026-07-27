<?php
session_name('sess_dashboards');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>