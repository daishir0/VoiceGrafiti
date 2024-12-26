<?php
require 'vendor/autoload.php';
$config = require 'config.php';

// 必要なディレクトリの作成
$directories = [
    $config['data_dir'],
    $config['uploads_dir'],
    $config['audio_dir'],
    $config['text_dir'],
    $config['mermaid_dir']
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        if (!mkdir($dir, 0777, true)) {
            error_log("Failed to create directory: " . $dir);
            throw new Exception("Failed to create directory: " . $dir);
        }
    }
}

// 初期ファイルの作成
$initialFiles = [
    $config['graph_file'] => "graph TD\n  root[メインテーマ]",
    $config['version_file'] => "1",
    $config['changes_file'] => json_encode(['changes' => []])
];

foreach ($initialFiles as $file => $content) {
    if (!file_exists($file)) {
        if (file_put_contents($file, $content) === false) {
            error_log("Failed to create file: " . $file);
            throw new Exception("Failed to create file: " . $file);
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>リアルタイム知識グラフ</title>
    <meta charset="UTF-8">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mermaid/8.11.0/mermaid.min.js"></script>
    <style>
        .controls { margin: 20px 0; }
        .node { cursor: pointer; }
        .node.selected { fill: red; }
        #status { margin: 10px 0; }
        #graphText {
            background-color: #f5f5f5;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: monospace;
            white-space: pre-wrap;
            margin: 20px 0;
        }
        #graph { 
            margin: 20px 0;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .error { color: red; }
    </style>
</head>
<body>
    <div class="controls">
        <button id="startRecording">録音開始</button>
        <button id="stopRecording" disabled>録音停止</button>
        <button id="resetSystem" style="margin-left: 20px; background-color: #ff4444; color: white;">システムリセット</button>
    </div>
    <div id="status"></div>
    <div id="graph"></div>

    <script>
        let currentVersion = 1;
        let mediaRecorder;
        let isRecording = false;

        // Mermaidの初期設定
        mermaid.initialize({
            startOnLoad: true,
            securityLevel: 'loose',
            theme: 'default'
        });

        // グラフの初期描画
        updateGraph();

        // 定期的にグラフの更新を確認（display_update_intervalを使用）
        setInterval(checkForUpdates, <?php echo $config['display_update_interval'] * 1000 ?>);

        // MediaRecorderの初期化前にサポートされているMIMEタイプェック
        function getPreferredMimeType() {
            const types = [
                'audio/webm;codecs=opus',
                'audio/webm',
                'video/webm;codecs=opus',
                'video/webm'
            ];
            
            for (const type of types) {
                if (MediaRecorder.isTypeSupported(type)) {
                    console.log('Supported type:', type);  // デバッグ用
                    return type;
                }
            }
            throw new Error('No supported MIME type found');
        }

        // 録音開始ボタンのイベントリスナー
        document.getElementById('startRecording').addEventListener('click', async () => {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ 
                    audio: true
                });

                mediaRecorder = new MediaRecorder(stream, {
                    mimeType: 'audio/webm'
                });
                
                let chunks = [];
                let startTime = Date.now();
                let recordingInterval;  // インターバルタイマーの参照を保持
                
                mediaRecorder.ondataavailable = (event) => {
                    if (event.data.size > 0) {
                        chunks.push(event.data);
                    }
                };

                // 各インターバルごとの処理
                mediaRecorder.onstop = async () => {
                    if (chunks.length > 0 && isRecording) {
                        const audioBlob = new Blob(chunks, { type: 'audio/webm' });
                        const formData = new FormData();
                        formData.append('audio', audioBlob, 'audio.webm');
                        
                        try {
                            const response = await fetch('process-audio.php', {
                                method: 'POST',
                                body: formData
                            });
                            
                            const result = await response.json();
                            if (result.success) {
                                const elapsed = Math.floor((Date.now() - startTime) / 1000);
                                document.getElementById('status').textContent = 
                                    `録音中... (${elapsed}秒経過) - 最新の認識テキスト: ${result.text}`;
                            }
                        } catch (error) {
                            console.error('Error processing audio chunk:', error);
                        }
                        
                        chunks = [];  // チャンクをリセット
                    }
                };
                
                // 録音の開始
                isRecording = true;
                document.getElementById('startRecording').disabled = true;
                document.getElementById('stopRecording').disabled = false;
                document.getElementById('status').textContent = '録音中...';

                // 定期的な録音の制御
                function startNewRecording() {
                    if (isRecording) {
                        if (mediaRecorder.state === 'recording') {
                            mediaRecorder.stop();
                        }
                        setTimeout(() => {
                            if (isRecording) {
                                mediaRecorder.start();
                            }
                        }, 100);  // 100ms後に次の録音を開始
                    }
                }

                // 初回の録音開始
                mediaRecorder.start();
                
                // 定期的な録音の制御を開始
                recordingInterval = setInterval(startNewRecording, <?php echo $config['recording_interval'] * 1000 ?>);

                // 録音停止時のクリアンアップ
                document.getElementById('stopRecording').onclick = () => {
                    isRecording = false;
                    clearInterval(recordingInterval);  // インターバルタイマーを停止
                    if (mediaRecorder.state !== 'inactive') {
                        mediaRecorder.stop();
                    }
                    stream.getTracks().forEach(track => track.stop());
                    document.getElementById('startRecording').disabled = false;
                    document.getElementById('stopRecording').disabled = true;
                    document.getElementById('status').textContent = '録音停止';
                };

            } catch (err) {
                console.error('Error accessing microphone:', err);
                document.getElementById('status').innerHTML = 
                    '<span class="error">マイクへのアクセスエラー</span>';
            }
        });

        // 録音停止ボタンのイベントリスナー
        document.getElementById('stopRecording').addEventListener('click', () => {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                isRecording = false;
                mediaRecorder.stop();
                mediaRecorder.stream.getTracks().forEach(track => track.stop());
                document.getElementById('startRecording').disabled = false;
                document.getElementById('stopRecording').disabled = true;
                document.getElementById('status').textContent = '録音停止';
            }
        });

        // グラフの更新をチェック
        async function checkForUpdates() {
            try {
                const response = await fetch(`check-updates.php?version=${currentVersion}`);
                const data = await response.json();
                
                if (data.changes.length > 0) {
                    // 変更を適用
                    for (const change of data.changes) {
                        applyGraphChange(change);
                    }
                    currentVersion = data.newVersion;
                }
            } catch (error) {
                console.error('Error checking updates:', error);
            }
        }

        // グラフの変更を適用
        function applyGraphChange(change) {
            const graphDiv = document.getElementById('graph');
            const svg = graphDiv.querySelector('svg');
            
            switch (change.type) {
                case 'addNode':
                    addNodeToSVG(svg, change.data);
                    break;
                case 'updateNode':
                    updateNodeInSVG(svg, change.data);
                    break;
                case 'addEdge':
                    addEdgeToSVG(svg, change.data);
                    break;
                case 'updateLayout':
                    updateGraph();
                    break;
            }
        }

        // グラフ表示用の要素を追加
        let graphText = document.createElement('pre');
        graphText.id = 'graphText';
        document.body.insertBefore(graphText, document.getElementById('graph'));

        // グラフの更新処理
        async function updateGraph() {
            try {
                const response = await fetch('display-graph.php');
                const data = await response.json();
                
                if (data.success) {
                    // Mermaidテキストを表示
                    document.getElementById('graphText').textContent = data.current_graph;
                    
                    // Mermaidグラフを描画
                    const graphDiv = document.getElementById('graph');
                    mermaid.render('graphDiv', data.current_graph, (svgGraph) => {
                        graphDiv.innerHTML = svgGraph;
                    });
                }
            } catch (error) {
                console.error('Error updating graph:', error);
            }
        }

        // 定期的なグラフ更新
        setInterval(updateGraph, <?php echo $config['display_update_interval'] * 1000 ?>);

        // ノードクリックの処理
        function handleNodeClick(event) {
            const nodeId = event.target.closest('.node').id;
            const label = event.target.closest('.node').querySelector('text').textContent;
            
            const newLabel = prompt('新しいラベルを入力してください:', label);
            if (newLabel && newLabel !== label) {
                updateNodeLabel(nodeId, newLabel);
            }
        }

        // ノードダブルクリックの処理
        function handleNodeDoubleClick(event) {
            const node = event.target.closest('.node');
            if (node.classList.contains('selected')) {
                node.classList.remove('selected');
            } else {
                node.classList.add('selected');
            }
        }

        // ノードラベルの更新
        async function updateNodeLabel(nodeId, newLabel) {
            try {
                const response = await fetch('update-node.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        nodeId: nodeId,
                        newLabel: newLabel
                    })
                });
                
                if (response.ok) {
                    updateGraph();
                }
            } catch (error) {
                console.error('Error updating node:', error);
            }
        }

        // リセットボタンのイベントリスナー
        document.getElementById('resetSystem').addEventListener('click', async () => {
            if (confirm('本当にシステムをリセットしますか\nすべての音声データとテキストデータが削除されます。')) {
                try {
                    // 録音中の場合は停止
                    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                        isRecording = false;
                        mediaRecorder.stop();
                        mediaRecorder.stream.getTracks().forEach(track => track.stop());
                        document.getElementById('startRecording').disabled = false;
                        document.getElementById('stopRecording').disabled = true;
                    }

                    const response = await fetch('reset-system.php', {
                        method: 'POST'
                    });
                    
                    const result = await response.json();
                    if (result.success) {
                        document.getElementById('status').textContent = 'システムを初期化しました';
                        // グラフを初期状態に更新
                        updateGraph();
                        currentVersion = 1;
                    } else {
                        document.getElementById('status').innerHTML = 
                            `<span class="error">初期化エラー: ${result.error}</span>`;
                    }
                } catch (error) {
                    console.error('Error resetting system:', error);
                    document.getElementById('status').innerHTML = 
                        '<span class="error">システムの初期化中にエラーが発生しました</span>';
                }
            }
        });
    </script>
</body>
</html>