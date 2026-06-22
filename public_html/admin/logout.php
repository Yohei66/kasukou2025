<?php
require __DIR__ . '/auth.php';
admin_logout();
header('Location: login.php');
exit;
