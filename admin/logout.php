<?php
/**
 * admin/logout.php — end the admin session and return to the login page.
 */
require_once __DIR__ . '/auth.php';

admin_logout();
redirect('/menu/admin/login.php');
