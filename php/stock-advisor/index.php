<?php
require_once __DIR__ . '/bootstrap.php';

if (!empty($_SESSION['loggedIn'])) {
    redirect('/stock-advisor/stocks/index.php');
} else {
    redirect('/stock-advisor/login.php');
}
