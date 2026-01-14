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

## 4. 使用ライブラリ（PHP 5.6対応）

| ライブラリ | 用途 | バージョン | PHP 5.6対応 |
|-----------|------|-----------|-----------|
| **TCPDF** | PDF生成 | 6.2.x | ✅ 対応 |
| **league/csv** | CSV処理 | 8.x | ✅ 対応 |

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

## 7. 実装例（PHP 5.6スタイル）

### CSVReader.php

```php
<?php
/**
 * CSV読み込みクラス
 */
class CSVReader {
    private $filePath;

    /**
     * コンストラクタ
     *
     * @param string $filePath CSVファイルパス
     */
    public function __construct($filePath) {
        $this->filePath = $filePath;
    }

    /**
     * CSVデータを読み込む
     *
     * @return array データ配列
     */
    public function read() {
        $data = array();

        if (!file_exists($this->filePath)) {
            throw new Exception("File not found: {$this->filePath}");
        }

        $handle = fopen($this->filePath, 'r');
        if ($handle === false) {
            throw new Exception("Cannot open file: {$this->filePath}");
        }

        // ヘッダー行を読み込み
        $headers = fgetcsv($handle);

        // データ行を読み込み
        while (($row = fgetcsv($handle)) !== false) {
            $data[] = array_combine($headers, $row);
        }

        fclose($handle);

        return $data;
    }
}
```

### DataAnalyzer.php

```php
<?php
/**
 * データ分析クラス
 */
class DataAnalyzer {
    private $data;

    /**
     * コンストラクタ
     *
     * @param array $data 売上データ配列
     */
    public function __construct($data) {
        $this->data = $data;
    }

    /**
     * 総売上金額を計算する
     *
     * @return float 総売上金額
     */
    public function getTotalSales() {
        $total = 0;
        foreach ($this->data as $row) {
            $total += (float)$row['売上金額'];
        }
        return $total;
    }

    /**
     * 平均売上金額を計算する
     *
     * @return float 平均売上金額
     */
    public function getAverageSales() {
        $count = count($this->data);
        if ($count === 0) {
            return 0;
        }
        return $this->getTotalSales() / $count;
    }

    /**
     * 最高売上商品を取得する
     *
     * @return string 商品名
     */
    public function getTopProduct() {
        $sales = array();

        foreach ($this->data as $row) {
            $product = $row['商品名'];
            if (!isset($sales[$product])) {
                $sales[$product] = 0;
            }
            $sales[$product] += (float)$row['売上金額'];
        }

        arsort($sales);
        reset($sales);

        return key($sales);
    }

    /**
     * カテゴリ別売上を集計する
     *
     * @return array カテゴリ別売上配列
     */
    public function getSalesByCategory() {
        $sales = array();

        foreach ($this->data as $row) {
            $category = $row['カテゴリ'];
            if (!isset($sales[$category])) {
                $sales[$category] = 0;
            }
            $sales[$category] += (float)$row['売上金額'];
        }

        return $sales;
    }
}
```

---

## 8. PHP 5.6 特有の書き方

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

## 9. プロジェクト構成

```
./
├── README.md
├── composer.json
├── data/
│   └── sales_data.csv
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

## 10. composer.json（PHP 5.6対応）

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

## 11. インストール手順

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

### 3. 実行

```bash
php5.6 src/Main.php
```

---

## 12. チームA での学習フロー

### ステップ1: PHP 5.6版を理解する

- PHP 5.6の制約を理解
- 古い書き方（array(), 型宣言なし）を体験

### ステップ2: PHP 8.3版を作成する

- 型宣言を追加
- 配列を短縮構文に変更
- null合体演算子を活用

### ステップ3: 比較・検証

- 動作が同じことを確認
- コードの可読性を比較
- パフォーマンスを比較（オプション）

---

## 13. PHP 5.6 → PHP 8.3 移行のポイント

### 変更が必要な箇所

| 項目 | PHP 5.6 | PHP 8.3 |
|------|---------|---------|
| 配列 | `array()` | `[]` |
| 型宣言 | なし | 引数・戻り値に型を追加 |
| null チェック | `isset() ? :` | `??` |
| クラスプロパティ | 型なし | 型付き |

### 移行例

**PHP 5.6**:
```php
class DataAnalyzer {
    private $data;

    public function __construct($data) {
        $this->data = $data;
    }

    public function getTotalSales() {
        $total = 0;
        foreach ($this->data as $row) {
            $total += $row['売上金額'];
        }
        return $total;
    }
}
```

**PHP 8.3**:
```php
class DataAnalyzer {
    private array $data;

    public function __construct(array $data) {
        $this->data = $data;
    }

    public function getTotalSales(): float {
        $total = 0.0;
        foreach ($this->data as $row) {
            $total += (float)$row['売上金額'];
        }
        return $total;
    }
}
```

---

## 14. 学習目標

| 目標 | 内容 |
|------|------|
| **PHP 5.6の理解** | 古い書き方、制約を理解する |
| **移行スキル** | PHP 5.6 → 8.3 の移行手順を習得 |
| **比較分析** | 新旧コードの違いを説明できる |
| **動作保証** | 移行後も同じ動作をすることを確認 |

---

## 15. 注意点

### PHP 5.6のサポート状況

| 項目 | 状況 |
|------|------|
| **公式サポート** | 2018年末に終了 |
| **セキュリティ更新** | なし |
| **本番環境** | 使用非推奨 |
| **学習目的** | ✅ OK |

### セキュリティリスク

PHP 5.6は公式サポートが終了しているため、本番環境では使用しないでください。このプロジェクトは**学習目的のみ**です。

---

## 16. 参考資料

### PHP 5.6 ドキュメント

- [PHP 5.6 マニュアル](https://www.php.net/manual/ja/migration56.php)
- [PHP 5.6 → 7.0 移行ガイド](https://www.php.net/manual/ja/migration70.php)
- [PHP 7.0 → 8.0 移行ガイド](https://www.php.net/manual/ja/migration80.php)

---

**作成日**: 2026年1月14日
