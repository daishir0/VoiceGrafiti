<?php
$config = require 'config.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['nodeId']) || !isset($data['newLabel'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$graphContent = file_get_contents($config['graph_file']);
$pattern = '/(' . preg_quote($data['nodeId']) . ')\[([^\]]+)\]/';
$updatedContent = preg_replace($pattern, '$1[' . $data['newLabel'] . ']', $graphContent);

if ($updatedContent !== $graphContent) {
    file_put_contents($config['graph_file'], $updatedContent);
    
    // バージョン更新と変更記録
    $version = intval(file_get_contents($config['version_file'])) + 1;
    file_put_contents($config['version_file'], $version);
    
    $changes = json_decode(file_get_contents($config['changes_file']), true);
    $changes['changes'][] = [
        'version' => $version,
        'type' => 'updateNode',
        'data' => [
            'nodeId' => $data['nodeId'],
            'newLabel' => $data['newLabel']
        ],
        'timestamp' => time()
    ];
    
    file_put_contents($config['changes_file'], json_encode($changes));
    
    echo json_encode(['success' => true]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Node not found']);
}