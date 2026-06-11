<?php
// Her korumalı sayfanın başına include edilir.
// Oturum açık değilse login'e yönlendirir.
require_once dirname(__DIR__) . '/config/auth.php';
requireLogin();
$currentUser = getCurrentUser();
