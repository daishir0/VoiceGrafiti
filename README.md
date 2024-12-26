[mindmap.mp4 を再生](mindmap.mp4)

## Overview
VoiceGrafiti is a real-time knowledge graph visualization system that converts voice input into an interactive graph structure. Using the OpenAI Whisper API for speech-to-text conversion and GPT-4 for knowledge graph generation, it creates a visual representation of spoken content in real-time.

## Installation

1. Clone the repository:
```bash
git clone https://github.com/daishir0/VoiceGrafiti.git
cd VoiceGrafiti
```

2. Install dependencies using Composer:
```bash
composer install
```

3. Create configuration file:
```bash
cp config.php.sample config.php
```

4. Edit config.php and set your OpenAI API key:
```php
'openai_api_key' => 'YOUR_OPENAI_API_KEY_HERE'
```

5. Configure your web server (Apache/Nginx) to serve the application directory and ensure proper permissions:
```bash
chmod 777 uploads/
chmod 777 data/
```

## Usage

1. Open the application in your Chrome browser
2. Click "Start Recording" to begin voice input
3. Speak clearly into your microphone
4. The system will automatically:
   - Convert speech to text using Whisper API
   - Process the text using GPT-4
   - Update the knowledge graph visualization
5. Click "Stop Recording" to end the session

## Notes

- Tested and verified to work with Google Chrome browser
- Requires a microphone and stable internet connection
- The application uses WebM audio format for voice recording
- OpenAI API key with access to both Whisper and GPT-4 is required
- Server requirements:
  - PHP 7.4 or higher
  - Web server (Apache/Nginx) with proper configuration
  - Write permissions for uploads/ and data/ directories

## License
This project is licensed under the MIT License - see the LICENSE file for details.

---

# VoiceGrafiti

## 概要
VoiceGrafitiは、音声入力をリアルタイムで知識グラフとして視覚化するシステムです。OpenAIのWhisper APIを使用して音声をテキストに変換し、GPT-4を使用して知識グラフを生成することで、話された内容をリアルタイムでビジュアル化します。

## インストール方法

1. レポジトリをクローン：
```bash
git clone https://github.com/daishir0/VoiceGrafiti.git
cd VoiceGrafiti
```

2. Composerで依存関係をインストール：
```bash
composer install
```

3. 設定ファイルを作成：
```bash
cp config.php.sample config.php
```

4. config.phpを編集してOpenAI APIキーを設定：
```php
'openai_api_key' => 'YOUR_OPENAI_API_KEY_HERE'
```

5. Webサーバーのドキュメントディレクトリにシステムを配置し、適切な権限を設定：
```bash
chmod 777 uploads/
chmod 777 data/
```

## 使い方

1. Chromeブラウザでアプリケーションにアクセスします
2. 「録音開始」をクリックして音声入力を開始します
3. マイクに向かって話します
4. システムは自動的に以下を実行します：
   - Whisper APIを使用して音声をテキストに変換
   - GPT-4を使用してテキストを処理
   - 知識グラフの視覚化を更新
5. 「録音停止」をクリックしてセッションを終了します

## 注意点

- Google Chromeブラウザでの動作を確認しています
- マイクと安定したインターネット接続が必要です
- 音声録音にはWebM形式を使用します
- WhisperとGPT-4の両方にアクセスできるOpenAI APIキーが必要です
- サーバー要件：
  - PHP 7.4以上
  - 適切に設定されたWebサーバー（Apache/Nginx）
  - uploads/とdata/ディレクトリの書き込み権限

## ライセンス
このプロジェクトはMITライセンスの下でライセンスされています。詳細はLICENSEファイルを参照してください。
