<?php
$config = require 'config.php';
$currentVersion = intval($_GET['version'] ?? 0);

$changes = json_decode(file_get_contents($config['changes_file']), true);
$latestVersion = intval(file_get_contents($config['version_file']));

// 現在のバージョン以降の変更のみを抽出
$newChanges = array_filter($changes['changes'], function($change) use ($currentVersion) {
    return $change['version'] > $currentVersion;
});

header('Content-Type: application/json');
echo json_encode([
    'newVersion' => $latestVersion,
    'changes' => array_values($newChanges)
]);