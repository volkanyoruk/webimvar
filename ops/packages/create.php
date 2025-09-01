<?php
define('WEBIMVAR_ENTERPRISE', true);

require __DIR__ . '/../../core/config.php';
require __DIR__ . '/../../core/classes/Session.php';
require __DIR__ . '/../../core/classes/Database.php';
require __DIR__ . '/../models/Package.php';
require __DIR__ . '/../controllers/PackageController.php';

Session::start();

$controller = new PackageController();
$controller->create();
?>