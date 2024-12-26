<?php
require 'vendor/autoload.php';
$config = require 'config.php';

function extractMermaidCode($content) {
    // Mermaidコードブロックを抽出
    if (preg_match('/```mermaid\s*(.*?)\s*```/s', $content, $matches)) {
        return $matches[1];
    }
    
    // Mermaidコードブロックが見つからない場合は、graph LRで始まる部分を探す
    if (preg_match('/graph LR.*$/s', $content, $matches)) {
        return $matches[0];
    }
    
    // どちらも見つからない場合は初期値を返す
    return "graph LR\n  root[メインテーマ]";
}

function processNewTexts($config) {
    try {
        // テキストファイルを取得し、タイムスタンプでソート
        $files = glob($config['text_dir'] . '/*.txt');
        sort($files);
        
        // 最後に処理したファイルを読み込む
        $lastProcessed = @file_get_contents($config['mermaid_dir'] . '/last_processed.txt') ?: '';
        
        // 新規ファイルの特定
        $newFiles = array_filter($files, function($file) use ($lastProcessed) {
            return basename($file) > $lastProcessed;
        });

        if (empty($newFiles)) {
            // 現在のグラフを読み込んでMermaid部分のみを抽出
            $currentContent = @file_get_contents($config['mermaid_dir'] . '/current.mmd') ?: "graph LR\n  root[メインテーマ]";
            $mermaidGraph = extractMermaidCode($currentContent);
            
            return [
                'success' => true,
                'message' => 'No new files',
                'current_graph' => $mermaidGraph
            ];
        }

        // 新規テキストの結合
        $newText = '';
        foreach ($newFiles as $file) {
            $newText .= file_get_contents($file) . "\n";
        }

        // GPT-4による処理
        $openai = OpenAI::client($config['openai_api_key']);
        $currentGraph = @file_get_contents($config['mermaid_dir'] . '/current.mmd') ?: "graph LR\n  root[メインテーマ]";
        $currentGraph = extractMermaidCode($currentGraph);
        
        $completion = $openai->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Mermaid記法のグラフを作成してください。必ず'graph LR'で始まるコードを生成し、既存のグラフの特定のノードに新しいテキストの情報をノードにして統合してください。トピックの関連から深さを持ったグラフを作成してください。Mermaid記法のコードのみを返してください。"
                ],
                [
                    'role' => 'user',
                    'content' => "現在のグラフ:\n$currentGraph\n\n新しいテキスト:\n$newText"
                ]
            ]
        ]);

        // 更新されたグラフの保存（Mermaid部分のみを抽出）
        $updatedGraph = $completion->choices[0]->message->content;
        $updatedGraph = extractMermaidCode($updatedGraph);
        file_put_contents($config['mermaid_dir'] . '/current.mmd', $updatedGraph);
        
        // 最後に処理したファイルを記録
        file_put_contents($config['mermaid_dir'] . '/last_processed.txt', basename(end($newFiles)));

        return [
            'success' => true,
            'current_graph' => $updatedGraph
        ];

    } catch (Exception $e) {
        error_log($e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// API呼び出し時の処理
header('Content-Type: application/json');
echo json_encode(processNewTexts($config)); 