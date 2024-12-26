<?php
require 'vendor/autoload.php';
$config = require 'config.php';

function getNewTextFiles($config) {
    $files = glob($config['text_dir'] . '/*.txt');
    sort($files); // タイムスタンプ順にソート
    return $files;
}

function convertSpecialCharacters($text) {
    $replacements = [
        '！' => '!', '"' => '"', '＃' => '#', '＄' => '$', '％' => '%',
        '＆' => '&', ''' => "'", '（' => '(', '）' => ')', '＝' => '=',
        '＾' => '^', '〜' => '~', '｜' => '|', '￥' => '\\', 
        '１' => '1', '２' => '2', '３' => '3', '４' => '4', '５' => '5',
        '６' => '6', '７' => '7', '８' => '8', '９' => '9', '０' => '0',
        '＠' => '@', '｀' => '`', '「' => '[', '」' => ']', 
        '｛' => '{', '｝' => '}', '；' => ';', '：' => ':',
        '＋' => '+', '＊' => '*', '＜' => '<', '＞' => '>',
        '、' => ',', '。' => '.', '・' => '-', '？' => '?', '＿' => '_'
    ];
    
    return str_replace(array_keys($replacements), array_values($replacements), $text);
}

function processNewTexts($config) {
    try {
        $files = getNewTextFiles($config);
        $lastProcessed = @file_get_contents($config['mermaid_dir'] . '/last_processed.txt') ?: '';
        
        // 新規ファイルの特定
        $newFiles = array_filter($files, function($file) use ($lastProcessed) {
            return basename($file) > $lastProcessed;
        });

        if (empty($newFiles)) {
            return ['success' => true, 'message' => 'No new files'];
        }

        // 新規テキストの結合
        $newText = '';
        foreach ($newFiles as $file) {
            $newText .= file_get_contents($file) . "\n";
        }

        // GPT-4による処理
        $openai = OpenAI::client($config['openai_api_key']);
        $currentGraph = @file_get_contents($config['mermaid_dir'] . '/current.mmd') ?: "graph TD\n  root[メインテーマ]";
        
        $completion = $openai->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Mermaid記法で書かれた既存のグラフに、新しいテキストを統合してください。"
                ],
                [
                    'role' => 'user',
                    'content' => "現在のグラフ:\n$currentGraph\n\n新しいテキスト:\n$newText"
                ]
            ]
        ]);

        // 更新されたグラフの保存
        $updatedGraph = $completion->choices[0]->message->content;
        $updatedGraph = convertSpecialCharacters($updatedGraph);
        file_put_contents($config['mermaid_dir'] . '/current.mmd', $updatedGraph);
        
        // 最後に処理したファイルを記録
        file_put_contents($config['mermaid_dir'] . '/last_processed.txt', basename(end($newFiles)));

        return ['success' => true];

    } catch (Exception $e) {
        error_log($e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

// API呼び出し時の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = processNewTexts($config);
    header('Content-Type: application/json');
    echo json_encode($result);
} 