<?php
defined('APP_ROOT') OR exit('No direct script access allowed');
return function () {
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'driver') {
        header('Location: ' . url('login'));
        exit;
    }
    return true;
};
