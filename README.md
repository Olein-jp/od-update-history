# OD Update History

OD Update History は、WordPress 本体・テーマ・プラグインの更新履歴を記録する WordPress プラグインです。

「いつ」「何が」「どのバージョンからどのバージョンへ」更新されたかを、WordPress 管理画面から確認できます。履歴は CSV または TXT で書き出せるため、保守報告や不具合調査の資料としても利用できます。

## 主な機能

- WordPress 本体の更新履歴を記録
- 有効・無効を問わず、インストール済みプラグインの更新履歴を記録
- 使用中・未使用を問わず、インストール済みテーマの更新履歴を記録
- 更新前後の実ファイルからバージョンを取得し、実際にバージョンが変わった更新だけを保存
- 更新時点のプラグイン・テーマの有効状態を保存
- 手動更新、自動更新、WP-CLI を区別
- 更新を実行した WordPress ユーザーを保存
- WordPress 管理画面の専用「更新履歴」メニューで一覧表示
- 種別・期間・更新方法による履歴の絞り込み
- CSV・TXTエクスポート
- 管理者による期間指定削除・全履歴削除
- 30日・90日・180日・365日から選べる自動保持期間
- プラグインの無効化・削除後も履歴データを保持
- GitHub Releasesを利用したOD Update History自身の管理画面更新

## 動作要件

- WordPress 6.9 以上
- PHP 7.4 以上
- MySQL または MariaDB

## インストール

1. このリポジトリを `wp-content/plugins/od-update-history` に配置します。
2. WordPress 管理画面の「プラグイン」から「OD Update History」を有効化します。
3. 管理画面の「更新履歴」を開きます。

プラグイン有効化時に、サイトのテーブル接頭辞を使った専用テーブルが作成されます。

```text
{prefix}od_update_history
```

通常の環境では `wp_od_update_history` です。

## 記録の仕組み

OD Update History は、WordPress 標準の更新処理である `WP_Upgrader` のフックを監視します。

1. `upgrader_pre_install` で更新前のバージョンと有効状態を取得
2. WordPress が更新ファイルを置き換え
3. `upgrader_process_complete` で更新後の実バージョンを取得
4. 更新前後のバージョンが異なる場合だけ、履歴を1件保存

更新候補として提示されたバージョンをそのまま信用せず、インストール済みファイルのバージョンを更新前後で比較します。そのため、バージョンが変わらなかった処理は成功履歴として保存されません。

### 外部アップデーターへの対応

WordPress.org 以外を配布元にしていても、最終的に WordPress 標準の `WP_Upgrader` を使うアップデーターは基本的に記録対象です。

たとえば、GitHub Releases の更新情報を WordPress の更新候補へ追加し、管理画面から標準の更新処理を実行するライブラリは同じフックを通るため、通常のプラグイン更新と同様に記録できます。

配布元の例：

- WordPress.org
- GitHub Releases
- 独自アップデートサーバー
- 商用プラグインのライセンス更新API

### OD Update History自身の更新

OD Update History は [`inc2734/wp-github-plugin-updater`](https://github.com/inc2734/wp-github-plugin-updater) を利用し、このリポジトリの最新GitHub ReleaseをWordPressの更新候補として表示します。

更新の確認とインストールは、WordPress管理画面の「プラグイン」または「更新」から通常のプラグインと同じように行えます。WordPressの自動更新を有効にした場合も、標準の自動更新処理が使用されます。

更新確認には次のURLへの外部通信が必要です。

- `https://api.github.com/repos/Olein-jp/od-update-history/`
- `https://github.com/Olein-jp/od-update-history/`
- `https://raw.githubusercontent.com/Olein-jp/od-update-history/`

GitHub Releaseには、Composerの本番依存を含んだ `od-update-history.zip` が必要です。GitHubが自動生成する「Source code」ZIPだけでは、更新機能に必要な `vendor` ディレクトリが含まれません。

## 記録する情報

履歴には次の情報を保存します。

| 項目 | 内容 |
| --- | --- |
| 日時 | サイトのタイムゾーンにおける更新完了日時 |
| 種別 | WordPress 本体、プラグイン、テーマ |
| 識別子 | plugin basename、theme stylesheet、または `wordpress` |
| 名前 | 更新時点の表示名 |
| 更新前バージョン | 更新直前に実ファイルから取得したバージョン |
| 更新後バージョン | 更新完了後に実ファイルから取得したバージョン |
| 有効状態 | 更新前後のプラグイン・テーマの有効状態 |
| 更新方法 | 手動更新、自動更新、WP-CLI、不明 |
| 実行者 | WordPress ユーザーID。自動処理はシステムとして記録 |
| メタデータ | PHPバージョン、WordPressバージョン、マルチサイト状態 |

## 管理画面

プラグインを有効化すると、WordPress 管理画面に「更新履歴」メニューが追加されます。表示には `manage_options` 権限が必要です。

一覧には次の情報が表示されます。

- 日時
- 種別
- 対象名と識別子
- 更新前後のバージョン
- 更新時点の有効状態
- 更新方法
- 実行者

履歴は新しい順に20件ずつ表示されます。種別、開始日、終了日、更新方法で絞り込めます。絞り込み条件はページ移動とエクスポートにも引き継がれます。

## 基本的な使い方

1. WordPress 本体、プラグイン、またはテーマを通常どおり更新します。
2. 管理画面の「更新履歴」を開き、記録された更新内容を確認します。
3. 必要に応じて、種別・期間・更新方法で履歴を絞り込みます。
4. 保守報告やバックアップが必要な場合は、CSVまたはTXTでエクスポートします。
5. 履歴を整理する場合は、画面下部の「データ管理」で保持期間の設定または手動削除を行います。

履歴の閲覧、エクスポート、保持期間の変更、削除には `manage_options` 権限が必要です。通常は管理者だけが操作できます。

## エクスポート

管理画面の「エクスポート」から、現在の絞り込み条件に一致する全履歴をダウンロードできます。エクスポートは `manage_options` 権限とnonceで保護されています。

### CSV

表計算ソフトや他のシステムへ取り込みやすい形式です。Excel で開いた際の文字化けを抑えるため、UTF-8 BOM を付けています。

```csv
date,type,name,slug,version_from,version_to,status,method,user
2026-07-28 08:32:14,plugin,WooCommerce,woocommerce/woocommerce.php,10.1.0,10.1.1,active,manual,admin
```

### TXT

人が読みやすいプレーンテキスト形式です。

```text
OD Update History
Site: https://example.com/
Exported: 2026-07-28 09:00:00

2026-07-28 08:32:14
プラグイン: WooCommerce
Version: 10.1.0 -> 10.1.1
Status: active
Method: manual
User: admin
```

## データの保持と削除

更新履歴は、プラグイン本体とは独立した保守記録として扱います。

### 保存場所

更新履歴は、サイトのテーブル接頭辞を付けた次の専用テーブルに保存します。

```text
{prefix}od_update_history
```

通常の接頭辞が `wp_` のサイトでは `wp_od_update_history` です。更新実行者のユーザーID、対象名、バージョン、更新時点の状態などが保存されます。保持期間の設定とデータベースのバージョンは、WordPressのoptionsテーブルに次の名前で保存されます。

- `od_update_history_retention_days`
- `od_update_history_db_version`

### 自動保持期間

初期値は「無期限」で、自動削除は行いません。「更新履歴」画面の「データ管理」で、30日・90日・180日・365日のいずれかを選択できます。

有限の保持期間を保存すると、WP-Cronが1日1回、サイトのタイムゾーンを基準に期限を過ぎた履歴を削除します。たとえば「30日」の場合、処理実行時点から30日前と同じ日時の履歴は残し、それより古い履歴だけを削除します。

保持期間を保存した直後に削除されるわけではありません。最初の自動処理は通常約24時間後です。また、WP-Cronはアクセスを契機に動作するため、アクセスの少ないサイトやWP-Cronを無効化している環境では実行が遅れることがあります。確実な定期実行が必要な場合は、サーバーのcronなどからWordPress cronを実行してください。

「無期限」へ戻すと自動削除の予定は解除されますが、すでに削除された履歴は復元されません。

### 手動削除

プラグインが有効な状態で、「更新履歴」画面の「データ管理」から実行します。

- 「期間指定削除」は、30日・90日・180日・365日より古い履歴を直ちに削除します。境界日時とそれ以降の履歴は残ります。
- 「更新履歴をすべて削除」は、専用テーブルを残したまま、保存済みの履歴レコードだけをすべて削除します。

手動削除と自動削除はいずれも取り消せません。必要な履歴は、削除前にCSVまたはTXTでエクスポートしてください。管理画面からの削除操作は `manage_options` 権限とnonceで保護されています。

### 無効化・アンインストール時の動作

| 操作 | 履歴テーブルと履歴 | 設定 | 自動削除の予定 |
| --- | --- | --- | --- |
| プラグインを無効化 | 残る | 残る | 解除される |
| WordPress管理画面からプラグインを削除（アンインストール） | 残る | 残る | 無効化時に解除される |
| 再インストールして有効化 | 既存の履歴を再利用 | 既存の設定を再利用 | 保持期間に応じて再登録 |

アンインストール時に専用テーブルやoptionsを削除する処理は、意図的に実装していません。そのため、WordPress管理画面でプラグインを削除しても履歴データはデータベースに残り、同じサイトへ再インストールすると再び参照できます。プラグインのファイルだけをFTPなどで直接削除した場合も、データベース上の履歴と設定は残ります。

### データを完全に消去する場合

このプラグインを今後使用せず、データベースからも完全に削除したい場合は、先に必要な履歴をエクスポートし、プラグインを無効化・削除したあとでデータベース管理ツールまたはWP-CLIを使って次のデータを手動で削除してください。

1. `{prefix}od_update_history` テーブル
2. optionsテーブル内の `od_update_history_retention_days`
3. optionsテーブル内の `od_update_history_db_version`

SQLの例を示します。`{prefix}` は実際のテーブル接頭辞へ置き換えてください。

```sql
DROP TABLE `{prefix}od_update_history`;
DELETE FROM `{prefix}options`
WHERE `option_name` IN (
  'od_update_history_retention_days',
  'od_update_history_db_version'
);
```

この操作は復元できません。実行前にデータベース全体のバックアップを取得し、対象サイトのテーブル接頭辞が正しいことを必ず確認してください。マルチサイトではサイトごとに接頭辞が異なる場合があります。

## 現在の制約

現時点では、次の変更は記録できません。

- FTP、SSH、rsync などによる直接のファイル置換
- Composer が WordPress を介さずに行うファイル更新
- 手動でのZIP展開やディレクトリ差し替え
- `WP_Upgrader` を使わない独自更新処理
- 更新開始、更新失敗、ロールバック
- プラグインやテーマの新規インストール、削除、有効化、無効化

また、現時点では単一サイトを主な対象としており、マルチサイトのサイト別・ネットワーク別の管理画面やデータ集約は未整備です。

## セキュリティとプライバシー

- 履歴画面、エクスポート、削除は `manage_options` 権限を持つユーザーに限定しています。
- エクスポートと削除ではnonceを検証します。
- 管理画面出力は文脈に応じてエスケープします。
- DBクエリの入力値は許可リストで検証し、`$wpdb->prepare()` を使用します。
- 外部サービスへ履歴を送信しません。
- 更新実行者としてWordPressユーザーIDと表示名を扱います。エクスポートファイルの共有範囲に注意してください。

## 開発環境

開発には Docker、Node.js、npm、Composer、PHP が必要です。

依存パッケージをインストールします。

```sh
npm install
composer install
```

`wp-env` を起動します。

```sh
npm run env:start
```

`.wp-env.json` ではリポジトリのルートをプラグインとしてマウントします。起動時に自動で割り当てられたURLとポートが表示されます。

よく使うコマンド：

```sh
# wp-envを起動し、WordPress統合テストをすべて実行
npm test

# 環境の状態を確認
npm run env:status

# WordPressのログを確認
npm run env:logs

# WP-CLIを実行
npm run env:cli -- plugin list

# PHP Coding Standardsを検証
composer lint

# 自動修正可能なコーディング規約違反を修正
composer format

# 配布ZIPを作成
bash bin/build-release.sh

# 開発環境を停止
npm run env:stop
```

### 自動テスト

`npm test` の1コマンドで wp-env を起動し、テスト用WordPressとデータベース上でPHPUnitを実行します。テスト環境を事前に起動しておく必要はありません。

現在は次の動作を検証しています。

- 専用テーブルの作成、履歴の保存・取得・絞り込み・全削除
- 指定期間より古い履歴の手動削除と境界日時の扱い
- 自動保持期間の初期値、定期処理、重複スケジュールの防止
- プラグイン、テーマ、WordPress本体の更新対象情報の取得
- 更新前後でバージョンが変わった場合だけ履歴を保存
- 履歴画面、保持期間設定、各削除処理の権限・nonceチェック

各テストは作成した履歴データ、ユーザー、リクエスト値を後処理で片付けます。テスト結果は実運用中のWordPressや履歴テーブルへ影響しません。テストを終えて開発環境も停止する場合は `npm run env:stop` を実行してください。

配布ZIPは `build/od-update-history.zip` に作成されます。ZIP内の最上位ディレクトリは必ず `od-update-history` になります。

## ディレクトリ構成

```text
.
├── includes/
│   ├── class-od-update-history-admin.php
│   ├── class-od-update-history-database.php
│   ├── class-od-update-history-recorder.php
│   ├── class-od-update-history-retention.php
│   └── class-od-update-history-updater.php
├── .github/workflows/release.yml
├── .wp-env.json
├── bin/build-release.sh
├── composer.json
├── od-update-history.php
├── package.json
├── phpunit.xml.dist
├── tests/
│   ├── bootstrap.php
│   ├── OD_Update_History_Admin_Test.php
│   ├── OD_Update_History_Database_Test.php
│   ├── OD_Update_History_Recorder_Test.php
│   └── OD_Update_History_Retention_Test.php
└── phpcs.xml.dist
```

- `od-update-history.php`: プラグインヘッダー、定数、クラス読込、初期化
- `OD_Update_History_Database`: テーブル作成、スキーマ更新、保存・取得・削除
- `OD_Update_History_Recorder`: 更新前後の状態取得と履歴確定
- `OD_Update_History_Admin`: 履歴一覧、フィルター、エクスポート、保持期間設定、削除
- `OD_Update_History_Retention`: 自動保持期間とWP-Cronによる定期削除
- `OD_Update_History_Updater`: GitHub Releaseの確認と配布ZIP URLの指定
- `bin/build-release.sh`: 本番依存だけを含む配布ZIPの生成
- `.github/workflows/release.yml`: タグからの検証、ZIP生成、GitHub Release作成
- `phpunit.xml.dist`: WordPress統合テストのPHPUnit設定
- `tests/`: テーブル操作、更新記録、管理画面の権限・nonceを検証するテスト

## リリース手順

GitHub Actionsは `v` で始まるタグがpushされたときに動作します。

1. `od-update-history.php` の `Version` と `OD_UPDATE_HISTORY_VERSION` を同じバージョンへ更新します。
2. 必要に応じて `package.json` の `version` とREADMEを更新します。
3. `composer lint`、`npm test`、`bash bin/build-release.sh` を実行します。
4. 変更をコミットして `main` へpushします。
5. プラグインバージョンと一致するタグを作成してpushします。

例：

```sh
git tag v0.2.0
git push origin main
git push origin v0.2.0
```

ワークフローはタグの `v` を除いた値とプラグインヘッダーのバージョンが一致することを確認します。検証が成功すると、リリースノートと `od-update-history.zip` を含むGitHub Releaseが作成されます。

最初のリリースは手動でサイトへインストールしてください。その後、より新しいバージョンのReleaseが公開されると、WordPress管理画面へ更新候補が表示されます。

## 開発ロードマップ

追加機能と改修はGitHub Issueで管理しています。

- [#1 更新履歴の自動テスト基盤を追加する（完了）](https://github.com/Olein-jp/od-update-history/issues/1)
- [#2 WP_Upgraderを通らないバージョン変更の検知方式を設計する（完了）](https://github.com/Olein-jp/od-update-history/issues/2)
- [#3 更新失敗・ロールバック履歴の状態モデルを設計する（完了）](https://github.com/Olein-jp/od-update-history/issues/3)
- [#4 履歴一覧に期間・更新方法フィルターを追加する（完了）](https://github.com/Olein-jp/od-update-history/issues/4)
- [#5 履歴の期間指定削除と保持期間設定を追加する（完了）](https://github.com/Olein-jp/od-update-history/issues/5)
- [#6 管理画面からの更新が履歴に記録されない不具合を修正する（完了）](https://github.com/Olein-jp/od-update-history/issues/6)

## ライセンス

GPL-2.0-or-later
