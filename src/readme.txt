=== Simple Post Expiry ===
Tags: post, expiry, expiration, draft, cron
Requires at least: 5.3
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/license/mit/

指定した公開期限を過ぎた投稿を、自動的に下書きへ戻すシンプルなプラグインです。

== Description ==

投稿編集画面で公開期限の有無と終了日時を設定できます。公開期限はデフォルトで無効です。

有効にした場合、終了日時をサイトのタイムゾーンで受け取り、UTCのUnixタイムスタンプとして投稿メタ `_simple_post_expiry_at` に保存します。期限になるとWP-Cronが投稿ステータスを「公開済み」から「下書き」に変更します。

== Installation ==

1. `simple-post-expiry` ディレクトリを `/wp-content/plugins/` に配置します。
2. WordPress管理画面の「プラグイン」から「Simple Post Expiry」を有効化します。
3. 投稿編集画面の「公開期限」で終了日時を設定します。

== Frequently Asked Questions ==

= 指定時刻に必ず即時実行されますか？ =

期限処理にはWordPress標準のWP-Cronを使用します。WP-Cronはサイトへのアクセスを契機に動作するため、アクセスが少ないサイトでは実行が遅れることがあります。正確な実行時刻が必要な場合は、サーバーのcronから `wp-cron.php` を定期実行してください。

= 公開期限を解除するとどうなりますか？ =

予約済みの期限処理を取り消し、プラグインが使用する公開期限の投稿メタを削除します。

== Changelog ==

= 1.0.0 =

* 初回リリース。
* 公開期限の設定、投稿メタ保存、WP-Cronによる下書き化に対応。
