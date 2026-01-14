# サンプルプロジェクト: PDFレポート自動生成システム（PHP 5.6版）

**PHP バージョン**: 5.6

---

## 1. プロジェクト概要

CSVデータを読み込み、データ分析・グラフ生成を行い、PDFレポートとして出力するシステム（PHP 5.6版）

---

## 2. PHP 5.6 の特徴と制約

### 言語機能の制約

| 機能 | PHP 5.6 | PHP 8.3 |
|------|---------|---------|
| **戻り値の型宣言** | ❌ なし | ✅ あり |
| **スカラー型ヒント** | ❌ なし | ✅ あり |
| **配列構文** | `array()` が主流 | `[]` が主流 |
| **null合体演算子** | ❌ なし | ✅ `??` |
| **匿名クラス** | ❌ なし | ✅ あり |

### PHP 5.6 で使える構文

```php
// ✅ PHP 5.6で使える
$data = array();
$user = array('name' => 'Taro', 'age' => 30);

// ⚠️ PHP 5.6でも使えるが、古いコードではあまり使われない
$data = [];
$user = ['name' => 'Taro', 'age' => 30];

// ❌ PHP 5.6では使えない（PHP 7.0以降）
function getUserName($user): string {  // 戻り値の型宣言
    return $user['name'];
}

// ✅ PHP 5.6での書き方
function getUserName($user) {
    return $user['name'];
}
```

---

## 3. システム構成

### 処理フロー

```
CSVデータ → データ解析 → グラフ生成 → PDFレポート生成 → PDF出力
              ↓
           統計計算
```

### 主な機能

1. **CSVデータの読み込み・解析**
2. **統計処理**（平均、最大/最小、トレンド、合計）
3. **グラフ生成**（棒グラフ、折れ線グラフ、円グラフ）
4. **PDF生成**（TCPDF）

---

## 4. 使用ライブラリ・フォント（PHP 5.6対応）

| ライブラリ/フォント | 用途 | バージョン | PHP 5.6対応 |
|-----------|------|-----------|-----------|
| **TCPDF** | PDF生成 | 6.2.x | ✅ 対応 |
| **league/csv** | CSV処理 | 8.x | ✅ 対応 |
| **Noto Serif CJK JP** | グラフの日本語表示 | Variable Font | ✅ 対応 |

### フォントについて

**ファイル**: `fonts/NotoSerifCJKjp-VF.ttf` (58MB)
- **用途**: グラフの日本語テキスト表示（棒グラフ、折れ線グラフ、円グラフ）
- **ライセンス**: SIL Open Font License（再配布可能）
- **特徴**: プロジェクトに内包されているため、システムフォントのインストール不要
- **対応環境**: Windows/macOS/Linux すべてで動作

**注意**: PHP 5.6対応のバージョンを使用する必要があります。

---

## 5. サンプルデータ

### 売上データ（data/sales_data.csv）

```csv
日付,商品名,カテゴリ,売上金額,数量
2024-01-01,商品A,電化製品,50000,5
2024-01-02,商品B,食品,3000,30
2024-01-03,商品C,衣類,12000,8
2024-01-04,商品A,電化製品,60000,6
2024-01-05,商品D,電化製品,45000,3
```

---

## 6. クラス設計

### クラス構成

```
Main.php
  ├── CSVReader.php       # CSV読み込み
  ├── DataAnalyzer.php    # データ分析
  └── PDFGenerator.php    # PDF生成
```

### 各クラスの責務

| クラス | 責務 |
|--------|------|
| **CSVReader** | CSV読み込み、バリデーション |
| **DataAnalyzer** | データ集計、統計計算 |
| **PDFGenerator** | PDFレポート生成 |

---

## 7. PHP 5.6 特有の書き方

### 型宣言なし

```php
// ❌ PHP 7.0以降
public function getTotalSales(array $data): float {
    // ...
}

// ✅ PHP 5.6
public function getTotalSales($data) {
    // ...
}
```

### 配列構文

```php
// PHP 5.6では array() が主流
$data = array();
$user = array('name' => 'Taro', 'age' => 30);

// [] も使えるが、古いコードではあまり使われない
$data = [];
```

### null チェック

```php
// ❌ PHP 7.0以降（null合体演算子）
$value = $data['key'] ?? 'default';

// ✅ PHP 5.6
$value = isset($data['key']) ? $data['key'] : 'default';
```

### エラーハンドリング

```php
// try-catch は PHP 5.6でも使える
try {
    $reader = new CSVReader($filePath);
    $data = $reader->read();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

---

## 8. プロジェクト構成

```
./
├── README.md
├── composer.json
├── data/
│   └── sales_data.csv
├── fonts/
│   └── NotoSerifCJKjp-VF.ttf    # 日本語フォント（58MB）
├── output/
│   └── report.pdf
├── src/
│   ├── CSVReader.php
│   ├── DataAnalyzer.php
│   ├── PDFGenerator.php
│   └── Main.php
└── .gitignore
```

---

## 9. composer.json（PHP 5.6対応）

```json
{
    "name": "sample/pdf-report-php56",
    "description": "PDF Report Generator for PHP 5.6",
    "require": {
        "php": ">=5.6.0",
        "tecnickcom/tcpdf": "^6.2",
        "league/csv": "^8.0"
    },
    "autoload": {
        "classmap": ["src/"]
    }
}
```

**注意**: PHP 5.6対応のバージョンを明示的に指定しています。

---

## 10. インストール手順

### 1. PHP 5.6 の確認

```bash
php5.6 -v
```

### 2. Composerでライブラリをインストール

```bash
php5.6 /usr/local/bin/composer install
```

または

```bash
composer install
```

**注意**: 日本語フォント（NotoSerifCJKjp-VF.ttf）はプロジェクトに内包されているため、システムフォントのインストールは不要です。

### 3. 実行

```bash
php5.6 src/Main.php
```

---

## 11. 注意点

### PHP 5.6のサポート状況

PHP 5.6は2018年末に公式サポートが終了しており、セキュリティ更新も提供されていません。本番環境での使用は推奨されません。

---

## 12. 参考資料

### PHP 5.6 ドキュメント

- [PHP 5.6 マニュアル](https://www.php.net/manual/ja/migration56.php)
- [PHP 5.6 → 7.0 移行ガイド](https://www.php.net/manual/ja/migration70.php)
- [PHP 7.0 → 8.0 移行ガイド](https://www.php.net/manual/ja/migration80.php)

---

## 13. 必須環境・拡張モジュール（共通）

本プロジェクトは **Windows / macOS / Linux（WSL含む）** すべてで動作します。

### 必須要件

| 項目 | 必須/推奨 | 説明 |
|------|----------|------|
| **PHP 5.6** | 必須 | PHP 5.6.x 系 |
| **mbstring 拡張** | 必須 | マルチバイト文字処理に必要。無効の場合はエラーで停止 |
| **GD 拡張** | 推奨 | グラフ生成に必要。無効でもPDFは生成されるがグラフは表示されない |
| **curl 拡張** | 推奨 | Composer での依存解決に必要 |
| **Composer** | 必須 | 依存ライブラリのインストールに必要 |

### 日本語フォントについて

**システムフォントのインストールは不要です。**

プロジェクトの `fonts/` ディレクトリに `NotoSerifCJKjp-VF.ttf` が含まれており、グラフの日本語テキスト表示に使用されます。このフォントはプロジェクトに内包されているため、環境に依存せず動作します。

### クロスプラットフォーム対応

本プロジェクトは以下の点でクロスプラットフォーム対応されています：
- パス区切り文字: `DIRECTORY_SEPARATOR` を使用
- 一時ディレクトリ: `sys_get_temp_dir()` を使用
- ファイルロック: `flock()` を使用（Windows/Linux 両対応）
- OS固有のコード: 使用していません

---

## 14. 実行環境構築（WSL / Ubuntu）

以下は WSL (Ubuntu) 上で PHP5.6 環境を整え、本プロジェクトを動かす手順です。

### 1. PHP5.6 と必須拡張をインストール

```bash
sudo apt-get update
sudo apt-get install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt-get update
sudo apt-get install -y php5.6 php5.6-cli php5.6-gd php5.6-mbstring php5.6-xml php5.6-curl unzip
```

### 2. インストール確認

```bash
php5.6 -v                    # バージョン確認
php5.6 -m | grep -E "gd|mbstring"  # 拡張モジュール確認
```

### 3. Composer をインストール（未インストール時）

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 4. 依存ライブラリをインストール

```bash
cd /path/to/sample-php-5.6
composer install --ignore-platform-reqs
```

### 5. 出力・ログディレクトリの作成

```bash
mkdir -p output logs
chmod -R 775 output logs
```

### 6. 実行

```bash
php5.6 src/Main.php
# デバッグモード
DEBUG=1 php5.6 src/Main.php
```

### 注意事項（WSL/Ubuntu）

- 実行時に使用する PHP が php5.6 であることを確認: `php5.6 -v`
- CLI と Apache/php-fpm で php.ini が異なる場合あり: `php5.6 --ini` で確認

---

## 15. 実行環境構築（Windows）

Windows 上で PHP5.6 環境を整え、本プロジェクトを動かす手順です。

### 1. PHP 5.6 をインストール

以下のいずれかの方法でインストール：
- PHP公式サイトから zip をダウンロード
- XAMPP/WAMP の PHP5.6 を使用

### 2. php.ini で拡張を有効化

php.ini ファイルを編集し、以下の行のコメント（`;`）を外す：

```ini
; 必須
extension=php_mbstring.dll

; 推奨（グラフ生成に必要）
extension=php_gd2.dll

; 推奨（Composer用）
extension=php_curl.dll

; extension_dir の設定（必要に応じて）
extension_dir = "ext"
```

### 3. インストール確認

```cmd
php -v
php -m
```

`mbstring` と `gd` が表示されることを確認。

### 4. Composer for Windows をインストール

https://getcomposer.org/ からインストーラーをダウンロードして実行。

### 5. 依存ライブラリをインストール

```cmd
cd C:\path\to\sample-php-5.6
composer install
```

### 6. 出力・ログディレクトリの作成

Explorer で `output` と `logs` フォルダが存在し、書き込み可能であることを確認。

### 7. 実行

```cmd
php src\Main.php

REM デバッグモード
set DEBUG=1 & php src\Main.php
```

### 注意事項（Windows）

- php.ini の場所: `php --ini` で確認
- 有効な拡張の確認: `php -m` または `php -i | findstr mbstring`
- PATH 環境変数に PHP のパスが含まれていることを確認

---

## 16. トラブルシューティング

### mbstring が無効というエラーが出る

**WSL/Ubuntu:**
```bash
sudo apt-get install php5.6-mbstring
```

**Windows:**
php.ini で `extension=php_mbstring.dll` のコメントを外す。

### グラフが表示されない

GD 拡張が無効です。

**WSL/Ubuntu:**
```bash
sudo apt-get install php5.6-gd
```

**Windows:**
php.ini で `extension=php_gd2.dll` のコメントを外す。

### フォントが見つからないエラー

`fonts/NotoSerifCJKjp-VF.ttf` ファイルが存在することを確認してください。
Git clone 時に LFS ファイルが取得できていない可能性があります。

### 出力ディレクトリに書き込めない

**WSL/Ubuntu:**
```bash
chmod -R 775 output logs
```

**Windows:**
Explorer でフォルダのプロパティを確認し、読み取り専用を解除。

---

**作成日**: 2026年1月14日
