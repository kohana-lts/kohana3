<?php defined('SYSPATH') OR die('No direct script access.') ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo (int) $status, ' ', HTML::chars($message) ?></title>
</head>
<body>
<h1><?php echo (int) $status, ' ', HTML::chars($message) ?></h1>
</body>
</html>
