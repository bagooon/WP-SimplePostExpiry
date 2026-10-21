# Project Instructions

## Project Information

- Client: 自社
- Project: 公開期限 WordPressプラグイン
- Project ID: wp-plugin_simple-post-expiry

## Overview

「Simple Post Expiry」は、WordPressの投稿に公開期限を設定するプラグインです。

- 公開期限はデフォルトで無効です。
- 投稿編集画面で終了日時を設定できます。
- 終了日時は投稿メタ `_simple_post_expiry_at` にUTCのUnixタイムスタンプとして保存します。
- 期限に到達した公開済み投稿を、WP-Cronによって下書きへ戻します。

## Repository Layout

- `.github/workflows/`: プラグインZIPの作成とGitHub Release公開
- `src/`: WordPressへ配置するプラグイン本体と配布用ファイル
- `tests/`: WordPressを起動せずに実行できる軽量な動作チェック
- `README.md`: 利用方法、開発方法、リリース手順

## Development Notes

- 対応WordPressバージョンは5.3以上です。
- 対応PHPバージョンは7.4以上です。
- PHPファイルの構文確認は `php -l src/simple-post-expiry.php` で行います。
- 動作チェックは `php tests/run.php` で行います。
- WordPressへ導入する際は、`src/` の内容をプラグインディレクトリへ配置します。
