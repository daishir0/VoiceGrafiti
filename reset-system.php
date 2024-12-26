<?php
require 'vendor/autoload.php';
$config = require 'config.php';

function deleteDirectoryContents($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            deleteDirectoryContents($path);
            rmdir($path);
        } else {
            unlink($path);
        }
    }
}

try {
    // 音声ファイルの削除
    deleteDirectoryContents($config['audio_dir']);
    
    // テキストファイルの削除
    deleteDirectoryContents($config['text_dir']);
    
    // Mermaidフィレクトリの初期化
    deleteDirectoryContents($config['mermaid_dir']);
    
    // Mermaidファイルを初期状態で作成
    file_put_contents($config['mermaid_dir'] . '/current.mmd', "graph TD\n  root[メインテーマ]");
    
    // バージョン情報をリセット
    file_put_contents($config['version_file'], "1");
    
    // 変更履歴をリセット
    file_put_contents($config['changes_file'], json_encode(['changes' => []]));
    
    // last_processed.txtを初期化（空のファイルとして作成）
    file_put_contents($config['mermaid_dir'] . '/last_processed.txt', '');
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("Reset system error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 