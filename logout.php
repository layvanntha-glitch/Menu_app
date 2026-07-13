<?php
/**
 * logout.php — sign the customer out and return to the menu.
 */
require_once __DIR__ . '/includes/auth_user.php';

user_logout();
redirect('/menu/index.php');
