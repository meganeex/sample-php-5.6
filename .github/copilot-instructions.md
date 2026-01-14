# GitHub Copilot コーディング規約

このプロジェクトはPHP 5.6で記述されています。以下のコーディング規約に従ってコードを生成してください。

---

## 1. PHP 5.6の制約事項

PHP 5.6では以下の機能が使用できないため、注意してください。

### 使用不可の機能
- ❌ **戻り値の型宣言**: `function getUser(): array { }`
- ❌ **引数の型宣言（スカラー型）**: `function setName(string $name) { }`
- ❌ **null合体演算子**: `$value = $data['key'] ?? 'default';`
- ❌ **匿名クラス**: `new class { };`
- ❌ **短縮配列構文（推奨しない）**: `[]`

### 推奨される書き方

```php
// ✅ PHP 5.6での正しい書き方
function getUser($userId) {
    return array('id' => $userId, 'name' => 'Taro');
}

// null チェック
$value = isset($data['key']) ? $data['key'] : 'default';

// 配列は array() 構文を使用
$shohinList = array();
$user = array('name' => 'Taro', 'age' => 30);
```

---

## 2. ファイル冒頭の仕様コメント

全ての.phpファイルの冒頭には、以下のようなファイル仕様コメントを記述してください。

```php
<?php
/**
 * ファイル名: CSVReader.php
 *
 * 概要:
 * CSVファイルを読み込み、配列として返すクラス
 *
 * 機能:
 * - CSVファイルの読み込み
 * - ヘッダー行とデータ行の分離
 * - 連想配列への変換
 *
 * 依存関係:
 * - league/csv ライブラリ
 *
 * 作成日: 2024-01-01
 * 更新日: 2024-01-15
 */
```

---

## 3. 命名規則

### クラス名
- **PascalCase + ローマ字表記**を基本とする
- 例: `UriageKeisanki` （売上計算機）, `ShohinKanri` （商品管理）, `DataBunseki` （データ分析）
- **例外**: 以下は英語を使用
  - **ファイル形式を扱うクラス**: `CSVReader`, `PDFGenerator`, `JSONParser`, `XMLWriter`
  - **技術的な基盤クラス**: `Database`, `Logger`, `Router`, `Validator`
  - **ライブラリ連携クラス**: `HttpClient`, `CacheManager`, `SessionHandler`

### メソッド名
- **camelCase + ローマ字表記**を基本とする
- 例: `getGoukeiUriage()` （合計売上取得）, `keisanHeikin()` （平均計算）, `shutokuShohin()` （商品取得）
- **例外**: 以下は英語を使用
  - **ファイル/データ操作**: `readFile()`, `writeFile()`, `openFile()`, `closeFile()`, `readCSV()`, `generatePDF()`, `parseJSON()`
  - **検証系**: `validate*()` （例: `validateUserId()`, `validateFilePath()`）
  - **データベース操作**: `fetch*()`, `save*()`, `load*()`, `insert*()`, `update*()`, `delete*()`
  - **汎用的な取得**: `get*()` でプロパティを返すだけの単純なgetter （例: `getProperty()`, `getId()`）

### 変数名
- **camelCase**を使用
- **基本は日本語のローマ字表記**を使用
- **英語でしか表現できない単語のみ英語を使用**

#### 基本: ローマ字表記の例
  - `$uriage` （売上）
  - `$shohin` （商品）
  - `$kingaku` （金額）
  - `$kakaku` （価格）
  - `$namae` （名前）
  - `$kaisya` （会社）
  - `$tantousha` （担当者）
  - `$goukei` （合計）
  - `$heikin` （平均）
  - `$kensu` （件数）
  - `$gyou` （行）
  - `$retsu` （列）
  - `$atai` （値）
  - `$naiyou` （内容）
  - `$kekka` （結果）

#### 例外: 英語でしか表現できない単語
  - `$data` （データ）
  - `$list` （リスト）
  - `$id` （ID）
  - `$file` （ファイル）
  - `$path` （パス）
  - `$csv` （CSV）
  - `$pdf` （PDF）

#### 組み合わせの例:
  - `$uriageKingaku` （売上金額） - 両方ローマ字
  - `$shohinMei` （商品名） - 両方ローマ字
  - `$goukeiUriage` （合計売上） - 両方ローマ字
  - `$filePath` （ファイルパス） - 両方英語
  - `$csvData` （CSVデータ） - 両方英語
  - `$shohinList` （商品リスト） - 混在（shohin=ローマ字, list=英語）

### 定数
- **UPPER_SNAKE_CASE**を使用
- **基本はローマ字表記**を使用
- 例: `URIAGE_ZEIRITSU` （売上税率）, `SHOHIN_MAX_SU` （商品最大数）
- **例外**: 技術的な定数は英語
  - `MAX_RETRIES`, `DEFAULT_ENCODING`, `DB_HOST`, `API_KEY`

### プライベート変数
- **camelCase**を使用（プレフィックスなし）
- 例: `private $filePath;`

### 命名規則の判断基準

**ビジネスロジック → ローマ字表記**
- 売上、商品、顧客、注文など、業務ドメイン固有の概念
- 例: `UriageKeisanki`, `getGoukeiUriage()`, `$shohinMei`

**技術的処理 → 英語**
- ファイル操作、データ検証、データベース、ネットワーク通信など、技術的な処理
- 例: `CSVReader`, `validateUserId()`, `$filePath`

**迷った場合**
- 日本語の業務用語として一般的に使われている → ローマ字
- プログラミング一般で使われる英単語 → 英語

---

## 4. PHPDocの記述形式

全てのクラス、メソッド、関数、グローバル変数には**必ず**PHPDocコメントを記述してください。

### クラスのPHPDoc

```php
/**
 * CSV読み込みクラス
 *
 * CSVファイルを読み込み、連想配列として返す機能を提供します。
 */
class CSVReader {
    // ...
}
```

### メソッドのPHPDoc

```php
/**
 * CSVデータを読み込む
 *
 * 指定されたCSVファイルを読み込み、ヘッダー行をキーとした
 * 連想配列の配列として返します。
 *
 * @param string $filePath CSVファイルパス
 * @return array データ配列（連想配列の配列）
 * @throws Exception ファイルが見つからない場合
 * @throws Exception ファイルを開けない場合
 */
public function read($filePath) {
    // ...
}
```

### 変数のPHPDoc

```php
/**
 * CSVファイルのパス
 * @var string
 */
private $filePath;
```

---

## 5. 処理ブロックのコメント

処理の区切りごとに、コメントで処理内容を説明してください。

```php
public function read($filePath) {
    $data = array();

    // ファイルの存在確認
    if (!file_exists($filePath)) {
        throw new Exception("ファイルが見つかりません: {$filePath}");
    }

    // ファイルを開く
    $handle = fopen($filePath, 'r');
    if ($handle === false) {
        throw new Exception("ファイルを開けません: {$filePath}");
    }

    // ヘッダー行を読み込み
    $headers = fgetcsv($handle);

    // データ行を読み込み
    while (($gyou = fgetcsv($handle)) !== false) {
        $data[] = array_combine($headers, $gyou);
    }

    // ファイルを閉じる
    fclose($handle);

    return $data;
}
```

---

## 6. コーディング規約

### PSR-1/PSR-2準拠（PHP 5.6対応範囲）

- **インデント**: スペース4つ
- **改行コード**: LF
- **ファイルエンコーディング**: UTF-8（BOM無し）
- **開始タグ**: `<?php`（短縮タグ `<?` は不可）
- **名前空間**: 各クラスは独立したファイルに記述

### 制御構文

```php
// if文
if ($jouken) {
    // 処理
} elseif ($jouken2) {
    // 処理
} else {
    // 処理
}

// foreach文
foreach ($hairetsu as $key => $atai) {
    // 処理
}

// while文
while ($jouken) {
    // 処理
}
```

### クラスの記述

```php
<?php
/**
 * クラスの説明
 */
class MyClass {
    /**
     * プライベート変数
     * @var string
     */
    private $property;

    /**
     * コンストラクタ
     *
     * @param string $atai 値
     */
    public function __construct($atai) {
        $this->property = $atai;
    }

    /**
     * メソッド
     *
     * @return string 結果
     */
    public function getProperty() {
        return $this->property;
    }
}
```

---

## 7. エラーハンドリング

### Exceptionの使用

- `Exception` クラスを使用
- エラーメッセージは**日本語**で記述
- 適切な例外をthrowする

```php
/**
 * ファイルを読み込む
 *
 * @param string $filePath ファイルパス
 * @return string ファイル内容
 * @throws Exception ファイルが見つからない場合
 */
public function readFile($filePath) {
    if (!file_exists($filePath)) {
        throw new Exception("ファイルが見つかりません: {$filePath}");
    }

    $naiyou = file_get_contents($filePath);
    if ($naiyou === false) {
        throw new Exception("ファイルの読み込みに失敗しました: {$filePath}");
    }

    return $naiyou;
}
```

### try-catch

```php
try {
    $reader = new CSVReader($filePath);
    $data = $reader->read();
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage();
}
```

---

## 8. セキュリティ

### ユーザー入力の検証

```php
/**
 * ユーザーIDを検証する
 *
 * @param mixed $userId ユーザーID
 * @return int 検証済みユーザーID
 * @throws Exception 無効なユーザーIDの場合
 */
public function validateUserId($userId) {
    // 数値チェック
    if (!is_numeric($userId)) {
        throw new Exception("ユーザーIDは数値である必要があります");
    }

    // 範囲チェック
    $userId = (int)$userId;
    if ($userId <= 0) {
        throw new Exception("ユーザーIDは正の整数である必要があります");
    }

    return $userId;
}
```

### SQLインジェクション対策

```php
// ✅ プリペアドステートメントを使用
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute(array(':id' => $userId));

// ❌ 直接SQLに値を埋め込まない
$sql = "SELECT * FROM users WHERE id = " . $userId;  // 危険
```

### XSS対策

```php
// 出力時にエスケープ
echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
```

### ファイルパスの検証

```php
/**
 * ファイルパスを検証する
 *
 * @param string $filePath ファイルパス
 * @return string 正規化されたファイルパス
 * @throws Exception 無効なファイルパスの場合
 */
public function validateFilePath($filePath) {
    // ディレクトリトラバーサル対策
    $realPath = realpath($filePath);
    if ($realPath === false) {
        throw new Exception("無効なファイルパスです");
    }

    // 許可されたディレクトリ内かチェック
    $allowedDir = realpath(__DIR__ . '/data');
    if (strpos($realPath, $allowedDir) !== 0) {
        throw new Exception("許可されていないディレクトリです");
    }

    return $realPath;
}
```

---

## 9. コード例

### 完全なクラスの例

```php
<?php
/**
 * ファイル名: UriageKeisanki.php
 *
 * 概要:
 * 売上データの計算を行うクラス
 *
 * 機能:
 * - 合計売上の計算
 * - 平均売上の計算
 * - 最大/最小売上の取得
 *
 * 作成日: 2024-01-01
 */

/**
 * 売上計算機クラス
 *
 * 売上データの各種計算機能を提供します。
 */
class UriageKeisanki {
    /**
     * 売上データ配列
     * @var array
     */
    private $uriageData;

    /**
     * コンストラクタ
     *
     * @param array $uriageData 売上データ配列
     * @throws Exception データが空の場合
     */
    public function __construct($uriageData) {
        if (empty($uriageData)) {
            throw new Exception("売上データが空です");
        }
        $this->uriageData = $uriageData;
    }

    /**
     * 合計売上を計算する
     *
     * @return float 合計売上金額
     */
    public function getGoukeiUriage() {
        $goukei = 0;

        // 全ての売上を合計
        foreach ($this->uriageData as $data) {
            $goukei += (float)$data['uriage_kingaku'];
        }

        return $goukei;
    }

    /**
     * 平均売上を計算する
     *
     * @return float 平均売上金額
     */
    public function getHeikinUriage() {
        $kensu = count($this->uriageData);

        // データ件数チェック
        if ($kensu === 0) {
            return 0;
        }

        // 平均を計算
        return $this->getGoukeiUriage() / $kensu;
    }
}
```

---

## まとめ

このコーディング規約に従って、以下を徹底してください:

1. ✅ PHP 5.6の制約を守る（型宣言なし、array()構文）
2. ✅ 全てのファイル、クラス、メソッドにコメントを記述
3. ✅ 命名は基本ローマ字表記、英語でしか表現できない単語（data, list, csv等）のみ英語
4. ✅ 処理ブロックごとにコメントを記述
5. ✅ セキュリティを考慮したコードを書く
6. ✅ エラーメッセージは日本語で記述
7. ✅ PSR-1/PSR-2に準拠した読みやすいコードを書く
