<?php
require_once __DIR__ . '/bootstrap.php';

if (empty($_SESSION['loggedIn'])) {
    redirect('/auth/login.php');
}

redirect('/dashboard.php');
