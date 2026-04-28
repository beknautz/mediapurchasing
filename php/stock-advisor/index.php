<?php
require_once __DIR__ . '/bootstrap.php';

if (!empty($_SESSION['loggedIn'])) {
    redirect('/stocks/index.php');
} else {
    redirect('/login.php');
}
