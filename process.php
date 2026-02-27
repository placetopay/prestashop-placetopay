<?php
/**
 * Deprecated file, only for backward compatibility with old versions of the module.
 * The process.php controller should be used instead of this file.
 */

require_once 'helpers.php';

$pathCMS = getPathCMS('process.php');

require fixPath($pathCMS . '/config/config.inc.php');
require fixPath($pathCMS . '/init.php');

$_GET['fc'] = 'module';
$_GET['module'] = getModuleName();
$_GET['controller'] = 'process';

require_once dirname(__FILE__) . '/../../index.php';
