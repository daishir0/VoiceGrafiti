<?php
$config = require 'config.php';

function getMermaidContent($config) {
    try {
        $mermaidPath = $config['mermaid_dir'] . '/current.mmd';
        if (!file_exists($mermaidPath)) {
            return ['error' => 'No mermaid file found'];
        }

        $content = file_get_contents($mermaidPath);
        return [
            'success' => true,
            'text' => $content,
            'version' => count(glob($config['text_dir'] . '/*.txt'))
        ];

    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = getMermaidContent($config);
    header('Content-Type: application/json');
    echo json_encode($result);
} 