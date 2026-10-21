# Simple Post Expiry

WordPressの投稿に公開期限を設定し、期限を過ぎた公開済み投稿を自動的に下書きへ戻すプラグインです。

## 機能

- 公開期限はデフォルトで無効
- 投稿編集画面で終了日時を指定
- サイトのタイムゾーンを考慮して日時を保存
- WP-Cronによる公開済み投稿の下書き化
- 公開期限の変更・解除時に予約イベントを同期
- プラグイン再有効化時に予約イベントを再作成

終了日時は、投稿メタ `_simple_post_expiry_at` にUTCのUnixタイムスタンプとして保存します。

## 動作要件

- WordPress 5.3以上
- PHP 7.4以上

## ディレクトリ構成

```text
.
├── .github/workflows/   # ZIP作成とGitHub Release公開
├── LICENSE              # MITライセンス
├── src/                 # WordPressプラグインの配布物
│   ├── assets/
│   ├── readme.txt
│   └── simple-post-expiry.php
└── tests/               # WordPress不要の軽量動作チェック
```

## インストール

GitHub ReleaseまたはGitHub Actionsの成果物から `simple-post-expiry.zip` を取得し、WordPress管理画面の「プラグイン」→「新規プラグインを追加」→「プラグインのアップロード」からインストールします。

開発中のソースを直接使用する場合は、`src/` の内容を次のディレクトリへ配置します。

```text
wp-content/plugins/simple-post-expiry/
```

## 使用方法

1. WordPress管理画面でプラグインを有効化します。
2. 投稿の編集画面を開きます。
3. 「公開期限」メタボックスで「公開期限を設定する」を有効にします。
4. 終了日時を指定して投稿を保存します。

期限に到達すると、対象の投稿は「公開済み」から「下書き」へ変更されます。

> [!NOTE]
> 期限処理にはWP-Cronを使用します。サイトへのアクセスが少ない場合、実行が指定時刻より遅れることがあります。正確な実行が必要な環境では、サーバーのcronから `wp-cron.php` を定期実行してください。

## 開発と検証

```bash
php -l src/simple-post-expiry.php
php -l tests/run.php
php tests/run.php
node --check src/assets/admin.js
```

## ZIPの作成と公開

`.github/workflows/build-plugin.yml` が、GitHubへのすべてのpushと手動実行で次の処理を行います。

1. PHP、JavaScript、期限処理の動作を検証
2. プラグインヘッダー、`VERSION` 定数、`Stable tag` の一致を検証
3. `src/` の内容とライセンスからインストール可能な `simple-post-expiry.zip` を作成
4. GitHub Actionsの成果物として30日間保存

`v` で始まるタグをpushすると、ZIPを添付したGitHub Releaseも自動作成します。
タグの先頭から `v` を除いた値とソースのバージョンが一致しない場合、ワークフローはZIPを公開せず失敗します。

```bash
git tag v1.0.0
git push origin v1.0.0
```

同じタグのワークフローを再実行した場合は、既存ReleaseのZIPを置き換えます。

## ライセンス

[MIT License](LICENSE)
