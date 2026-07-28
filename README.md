# OD Update History

WordPress の更新履歴を記録するプラグインです。

## 開発環境

Docker、Node.js、npm、Composer、PHP が必要です。

```sh
npm install
composer install
npm run env:start
```

起動後に表示される URL、または次のコマンドで割り当てられたポートを確認できます。

```sh
npm run env:status
npm run env:logs
npm run env:cli -- plugin list
npm run env:stop
composer lint
```
