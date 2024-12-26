<?php
require 'vendor/autoload.php';
$config = require 'config.php';

function initializeDirectories($config) {
    $dirs = ['uploads_dir', 'audio_dir', 'text_dir', 'mermaid_dir'];
    foreach ($dirs as $dir) {
        if (!file_exists($config[$dir])) {
            mkdir($config[$dir], 0777, true);
        }
    }
}

function processAudio($audioFile, $config) {
    try {
        // アップロードされたファイルの情報をログ
        error_log("Original file info: " . print_r($audioFile, true));
        
        // MIMEタイプの検証を緩和
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($audioFile['tmp_name']);
        error_log("Detected MIME type: " . $mimeType);
        
        // 許可するMIMEタイプのリスト
        $allowedMimeTypes = [
            'audio/webm',
            'video/webm',
            'application/octet-stream',
            'audio/ogg',
            'video/ogg'
        ];
        
        if (!in_array($mimeType, $allowedMimeTypes)) {
            throw new Exception("Invalid MIME type: " . $mimeType);
        }

        // WebMファイルのヘッダーチェックを緩和
        $handle = fopen($audioFile['tmp_name'], 'rb');
        if ($handle) {
            $header = fread($handle, 4);
            fclose($handle);
            
            $hexHeader = bin2hex($header);
            error_log("File header: " . $hexHeader);
            
            // WebMのマジックナンバーチェックを一時的に無効化
            // if ($hexHeader !== '1a45dfa3') {
            //     error_log("Invalid file header: " . $hexHeader);
            //     throw new Exception("File is not a valid WebM container");
            // }
        }

        // タイムスタンプベースのファイル名生成
        $timestamp = date('Ymd-His');
        $audioPath = $config['audio_dir'] . '/' . $timestamp . '.webm';
        $textPath = $config['text_dir'] . '/' . $timestamp . '.txt';

        // ファイルの一時的なバッファリングと保存
        $tempContent = file_get_contents($audioFile['tmp_name']);
        if ($tempContent === false) {
            throw new Exception("Failed to read uploaded file");
        }
        
        // WebMファイルとして保存
        if (file_put_contents($audioPath, $tempContent) === false) {
            throw new Exception("Failed to save audio file");
        }

        // 保存されたファイルの検証
        if (!file_exists($audioPath)) {
            throw new Exception("Saved audio file not found");
        }

        $savedFileSize = filesize($audioPath);
        error_log("Saved file size: " . $savedFileSize . " bytes");

        // OpenAI APIでテキスト化
        $openai = OpenAI::client($config['openai_api_key']);
        
        // ファイルを読み込みモードで開く
        $fileStream = fopen($audioPath, 'r');
        if ($fileStream === false) {
            throw new Exception("Failed to open audio file");
        }

        try {
            // ファイルポインタを先頭に移動
            fseek($fileStream, 0);
            
            $response = $openai->audio()->transcribe([
                'file' => $fileStream,
                'model' => 'whisper-1',
                'response_format' => 'json',
                'language' => 'ja'
            ]);

            // テキストファイルに保存
            if (!file_put_contents($textPath, $response->text)) {
                throw new Exception("Failed to save text file");
            }

            return ['success' => true];

        } finally {
            // 必ずファイルストリームを閉じる
            if (is_resource($fileStream)) {
                fclose($fileStream);
            }
        }

    } catch (Exception $e) {
        error_log($e->getMessage());
        return [
            'error' => $e->getMessage(),
            'type' => 'server_error'
        ];
    }
}

// メイン処理
initializeDirectories($config);

if (isset($_FILES['audio'])) {
    $result = processAudio($_FILES['audio'], $config);
    header('Content-Type: application/json');
    echo json_encode($result);
}