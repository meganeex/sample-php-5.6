<?php
/**
 * ファイル名: PDFGenerator.php
 *
 * 概要:
 * 売上分析レポートをPDF形式で生成するクラス
 *
 * 機能:
 * - レポートテンプレートの組み立て
 * - 統計情報の表組み生成
 * - グラフの生成と埋め込み（棒・折れ線・円グラフ）
 * - ヘッダー・フッターの設定
 * - PDF出力（TCPDF連携）
 * - 依存関係チェック
 * - 一時ファイル管理・自動クリーンアップ
 * - ファイルロック機構
 * - 大量データ対策
 * - 設定の外部化
 *
 * 依存関係:
 * - tecnickcom/tcpdf ライブラリ
 * - GD拡張（グラフ生成用）
 * - mbstring拡張（マルチバイト処理用）
 * - Noto Serif CJK JP フォント（fonts/NotoSerifCJKjp-VF.ttf）
 *   - 用途: グラフの日本語テキスト表示
 *   - ライセンス: SIL Open Font License
 *   - 対応環境: Windows/macOS/Linux
 *
 * 作成日: 2026-01-14
 * 更新日: 2026-01-14
 */

namespace App;

use TCPDF;

/**
 * PDF生成クラス
 *
 * 売上分析レポートをPDF形式で生成し、ファイル出力またはブラウザ出力を行います。
 */
class PDFGenerator
{
    /**
     * TCPDF インスタンス
     * @var TCPDF
     */
    private $pdf;

    /**
     * レポートタイトル
     * @var string
     */
    private $title;

    /**
     * 出力ファイルパス
     * @var string
     */
    private $outputPath;

    /**
     * 一時ファイル保存ディレクトリ
     * @var string
     */
    private $tempDir;

    /**
     * 生成した一時ファイルのリスト
     * @var array
     */
    private $tempFiles;

    /**
     * 最大データ件数（ページ分割の閾値）
     * @var int
     */
    private $maxDataKensu;

    /**
     * ログメッセージ配列
     * @var array
     */
    private $logMessages;

    /**
     * ログファイルパス
     * @var string
     */
    private $logFilePath;

    /**
     * ファイルロックリソース
     * @var resource
     */
    private $lockHandle;

    /**
     * ロックファイルパス
     * @var string
     */
    private $lockFilePath;

    /**
     * 設定配列
     * @var array
     */
    private $config;

    /**
     * コンストラクタ
     *
     * @param string $title レポートタイトル
     * @param string $outputPath 出力ファイルパス（省略時はブラウザ出力）
     * @param array $config 設定配列（省略時はデフォルト）
     * @throws \Exception タイトルが空の場合
     * @throws \Exception 依存関係が満たされていない場合
     */
    public function __construct($title, $outputPath = null, $config = null)
    {
        // 設定を初期化
        $this->initializeConfig($config);

        // 一時ディレクトリを早期に設定（ログ用）
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pdf_generator_' . uniqid();
        if (!mkdir($this->tempDir, 0700, true)) {
            throw new \Exception("一時ディレクトリの作成に失敗しました");
        }
        if (!is_writable($this->tempDir)) {
            throw new \Exception("一時ディレクトリに書き込みできません");
        }

        $this->logFilePath = $this->tempDir . DIRECTORY_SEPARATOR . 'generator.log';
        $this->logMessages = array();
        $this->log("一時ディレクトリを作成: {$this->tempDir}");

        // 依存関係チェック（ログ初期化後に移動）
        $this->checkDependencies();

        // フォント検出結果をログに記録
        $fontPath = $this->getConfigValue('ttfFontPath');
        if ($fontPath !== null) {
            $this->log("TTFフォント検出: {$fontPath}");
        } else {
            $this->log("TTFフォント未検出（imagestringにフォールバック）");
        }

        // タイトル検証
        if (empty($title) || !is_string($title)) {
            throw new \Exception("レポートタイトルが不正です");
        }

        $this->title = $title;
        $this->outputPath = $outputPath;
        $this->tempFiles = array();
        $this->maxDataKensu = $this->getConfigValue('maxDataKensu', 50);
        $this->lockHandle = null;

        // 起動時クリーンアップ（古い一時ファイル・ロックを削除）
        $this->cleanupStaleFiles();

        // シャットダウン時に確実にロックを解放する
        register_shutdown_function(array($this, 'onShutdown'));

        // TCPDF インスタンスを初期化
        $this->initializePDF();
    }

    /**
     * 設定を初期化する
     *
     * @param array $config 設定配列
     */
    private function initializeConfig($config)
    {
        $defaults = array(
            'allowedOutputDir' => __DIR__ . '/../output',
            'logDir' => __DIR__ . '/../logs',
            'maxDataKensu' => 50,
            'maxBarChartCategories' => 20,
            'maxPieChartCategories' => 10,
            'logRetentionDays' => 7,
            'tempFileTtl' => 86400,
            'graphWidth' => 600,
            'graphHeight' => 300,
            'ttfFontPath' => null // 実行時に検出して設定
        );

        $this->config = is_array($config) ? array_merge($defaults, $config) : $defaults;

        // 実行環境の日本語フォントを検出して config にセット
        $detected = $this->detectTtfFont();
        if ($detected !== null) {
            $this->config['ttfFontPath'] = $detected;
            // ログは後で初期化されるので、ここではまだ記録できない
        } else {
            $this->config['ttfFontPath'] = null;
        }
    }

    /**
     * 設定値を取得する
     *
     * @param string $key 設定キー
     * @param mixed $default デフォルト値
     * @return mixed 設定値
     */
    private function getConfigValue($key, $default = null)
    {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }

    /**
     * プロジェクト内のフォントファイルパスを返す
     *
     * @return string|null フォントファイルパス（未検出時null）
     */
    private function detectTtfFont()
    {
        $fontPath = __DIR__ . '/../fonts/NotoSerifCJKjp-VF.ttf';
        if (file_exists($fontPath)) {
            return $fontPath;
        }
        return null;
    }

    /**
     * 依存関係をチェックする
     *
     * @throws \Exception TCPDFが存在しない場合
     * @throws \Exception GD拡張が無効な場合
     * @throws \Exception mbstring拡張が無効な場合
     */
    private function checkDependencies()
    {
        // TCPDF クラスの存在チェック
        if (!class_exists('TCPDF')) {
            throw new \Exception("TCPDF ライブラリがインストールされていません");
        }

        // GD拡張のチェック（警告のみに変更）
        if (!extension_loaded('gd')) {
            // 例外をスローせずに続行
        }

        // mbstring拡張のチェック
        if (!extension_loaded('mbstring')) {
            throw new \Exception("mbstring拡張が有効になっていません（マルチバイト文字処理に必要）");
        }

        // メモリ制限チェック
        $memoryLimit = ini_get('memory_limit');
        $this->log("メモリ制限: {$memoryLimit}");
    }

    /**
     * PDF を初期化する
     *
     * TCPDF のインスタンスを作成し、基本設定を行います。
     *
     * @throws \Exception PDF初期化に失敗した場合
     */
    private function initializePDF()
    {
        try {
            // PDF インスタンスを作成
            $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

            // ドキュメント情報を設定
            $this->pdf->SetCreator('Sales Report Generator');
            $this->pdf->SetAuthor('System');
            $this->pdf->SetTitle($this->title);
            $this->pdf->SetSubject('売上分析レポート');
            $this->pdf->SetKeywords('売上, 分析, レポート');

            // ヘッダー・フッターの表示設定（ヘッダーデータの設定は後で行う）
            $this->pdf->setPrintHeader(true);
            $this->pdf->setPrintFooter(true);

            // フォント設定（フォールバック付き） --- ここで日本語フォントを確実にセット
            $this->setFontWithFallback();

            // ヘッダー用フォントを日本語フォントに設定（追加）
            // KozGoPro-Medium 等を使用している想定。フォント名は setFontWithFallback で使っているものに合わせる。
            $this->pdf->setHeaderFont(array('kozgopromedium', '', 10));

            // ヘッダーデータをフォント設定後にセット（修正）
            $this->pdf->SetHeaderData('', 0, $this->title, date('Y-m-d H:i:s'));

            // マージン設定
            $this->pdf->SetMargins(15, 30, 15);
            $this->pdf->SetHeaderMargin(5);
            $this->pdf->SetFooterMargin(10);

            // 自動改ページ設定
            $this->pdf->SetAutoPageBreak(true, 25);

            $this->log("PDF初期化完了");
        } catch (\Exception $e) {
            $this->log("PDF初期化失敗: " . $e->getMessage());
            throw new \Exception("PDF初期化に失敗しました: " . $e->getMessage());
        }
    }

    /**
     * フォントを設定する（フォールバック付き）
     */
    private function setFontWithFallback()
    {
        // 優先順位でフォントを試行
        $fonts = array('kozgopromedium', 'kozminproregular', 'helvetica');
        $fontSet = false;

        foreach ($fonts as $font) {
            try {
                $this->pdf->SetFont($font, '', 10);
                $fontSet = true;
                $this->log("フォント設定成功: {$font}");
                break;
            } catch (\Exception $e) {
                $this->log("フォント設定失敗: {$font}");
                continue;
            }
        }

        if (!$fontSet) {
            throw new \Exception("利用可能なフォントが見つかりません");
        }
    }

    /**
     * レポートを生成する
     *
     * @param object $analyzer データ分析オブジェクト
     * @throws \Exception 分析オブジェクトが不正な場合
     * @throws \Exception レポート生成に失敗した場合
     */
    public function generate($analyzer)
    {
        // 必要メソッドの存在チェック
        $requiredMethods = array(
            'getGoukeiUriage', 'getHeikinUriage', 'getSaidaiUriage',
            'getSaishouUriage', 'getUriageByCategory', 'getTrend', 'getTopShohin'
        );
        foreach ($requiredMethods as $m) {
            if (!method_exists($analyzer, $m)) {
                $this->log("必須メソッド不足: {$m}");
                throw new \Exception("データ分析オブジェクトに必要なメソッドがありません");
            }
        }

        try {
            $this->pdf->AddPage();
            $this->addSummarySection($analyzer);
            $this->addCategorySectionWithGraph($analyzer);
            $this->addTrendSectionWithGraph($analyzer);
            $this->addProductRankingSection($analyzer);
            $this->addCategoryPieChart($analyzer);
            $this->log("レポート生成完了");
        } catch (\Exception $e) {
            $this->log("レポート生成失敗（詳細）: " . $e->getMessage());
            throw new \Exception("レポート生成に失敗しました");
        }
    }

    /**
     * サマリーセクションを追加する
     *
     * 総売上、平均売上、最大/最小売上を表形式で出力します。
     *
     * @param \DataAnalyzer $analyzer データ分析オブジェクト
     */
    private function addSummarySection($analyzer)
    {
        // セクションタイトル
        $this->addSectionTitle('売上サマリー');

        // 統計情報を取得
        $goukei = $analyzer->getGoukeiUriage();
        $heikin = $analyzer->getHeikinUriage();
        $saidai = $analyzer->getSaidaiUriage();
        $saishou = $analyzer->getSaishouUriage();

        // テーブルヘッダーを追加
        $this->addTableHeader(array('項目', '金額'));

        // テーブルデータ
        $this->pdf->SetFont('kozgopromedium', '', 10);
        $this->addTableRow('総売上金額', number_format($goukei) . ' 円');
        $this->addTableRow('平均売上金額', number_format($heikin) . ' 円');

        $saidaiKingaku = isset($saidai['uriage_kingaku']) ? $saidai['uriage_kingaku'] : 0;
        $this->addTableRow('最大売上金額', number_format($saidaiKingaku) . ' 円');

        $saishouKingaku = isset($saishou['uriage_kingaku']) ? $saishou['uriage_kingaku'] : 0;
        $this->addTableRow('最小売上金額', number_format($saishouKingaku) . ' 円');

        $this->pdf->Ln(5);
    }

    /**
     * カテゴリ別売上セクションを追加する（グラフ付き）
     *
     * @param \DataAnalyzer $analyzer データ分析オブジェクト
     */
    private function addCategorySectionWithGraph($analyzer)
    {
        // 新しいページを追加
        $this->pdf->AddPage();

        // セクションタイトル
        $this->addSectionTitle('カテゴリ別売上');

        // カテゴリ別売上を取得
        $categoryData = $analyzer->getUriageByCategory();

        // テーブルヘッダーを追加
        $this->addTableHeader(array('カテゴリ', '売上金額'));

        // テーブルデータ
        $this->pdf->SetFont('kozgopromedium', '', 10);
        foreach ($categoryData as $category => $kingaku) {
            $this->addTableRow($category, number_format($kingaku) . ' 円');
        }

        $this->pdf->Ln(10);

        // 棒グラフを生成して埋め込み（画像埋め込みは例外保護）
        $graphPath = $this->generateBarChart($categoryData, 'カテゴリ別売上');
        if ($graphPath !== null) {
            try {
                $this->pdf->Image($graphPath, 15, null, 180, 0, 'PNG');
            } catch (\Exception $e) {
                $this->log("画像埋め込み失敗（棒グラフ）: " . $e->getMessage());
            }
        }

        $this->pdf->Ln(5);
    }

    /**
     * トレンドセクションを追加する（グラフ付き）
     *
     * @param \DataAnalyzer $analyzer データ分析オブジェクト
     */
    private function addTrendSectionWithGraph($analyzer)
    {
        // 新しいページを追加
        $this->pdf->AddPage();

        // セクションタイトル
        $this->addSectionTitle('売上トレンド（日次）');

        // トレンドデータを取得
        $trendData = $analyzer->getTrend();

        // データ件数チェック
        $kensu = count($trendData);
        $hyoujiKensu = min($kensu, $this->maxDataKensu);

        // テーブルヘッダーを追加
        $this->addTableHeader(array('日付', '売上金額'));

        // テーブルデータ（制限付き）
        $this->pdf->SetFont('kozgopromedium', '', 10);
        $count = 0;
        foreach ($trendData as $date => $kingaku) {
            if ($count >= $hyoujiKensu) {
                $this->pdf->Cell(180, 7, "... 以下 " . ($kensu - $hyoujiKensu) . " 件省略 ...", 1, 1, 'C');
                break;
            }
            $this->pdf->Cell(90, 7, $date, 1, 0, 'C');
            $this->pdf->Cell(90, 7, number_format($kingaku) . ' 円', 1, 1, 'R');
            $count++;
        }

        $this->pdf->Ln(10);

        // 折れ線グラフを生成して埋め込み
        $graphPath = $this->generateLineChart($trendData, '売上トレンド');
        if ($graphPath !== null) {
            try {
                $this->pdf->Image($graphPath, 15, null, 180, 0, 'PNG');
            } catch (\Exception $e) {
                $this->log("画像埋め込み失敗（折れ線）: " . $e->getMessage());
            }
        }

        $this->pdf->Ln(5);
    }

    /**
     * 商品ランキングセクションを追加する
     *
     * トップ商品の情報を出力します。
     *
     * @param \DataAnalyzer $analyzer データ分析オブジェクト
     */
    private function addProductRankingSection($analyzer)
    {
        // セクションタイトル
        $this->addSectionTitle('トップ商品');

        // トップ商品を取得
        $topShohin = $analyzer->getTopShohin();

        // テーブルヘッダーを追加
        $this->addTableHeader(array('商品名', '売上金額'));

        // テーブルデータ
        $this->pdf->SetFont('kozgopromedium', '', 10);
        $shohinMei = isset($topShohin['shohinMei']) ? $topShohin['shohinMei'] : 'N/A';
        $kingaku = isset($topShohin['uriageKingaku']) ? $topShohin['uriageKingaku'] : 0;
        $this->addTableRow($shohinMei, number_format($kingaku) . ' 円');

        $this->pdf->Ln(5);
    }

    /**
     * カテゴリ別構成比（円グラフ）を追加する
     *
     * @param \DataAnalyzer $analyzer データ分析オブジェクト
     */
    private function addCategoryPieChart($analyzer)
    {
        // 新しいページを追加
        $this->pdf->AddPage();

        // セクションタイトル
        $this->addSectionTitle('カテゴリ別構成比');

        // カテゴリ別売上を取得
        $categoryData = $analyzer->getUriageByCategory();

        // 円グラフを生成して埋め込み
        $graphPath = $this->generatePieChart($categoryData, 'カテゴリ別構成比');
        if ($graphPath !== null) {
            try {
                $this->pdf->Image($graphPath, 15, null, 180, 0, 'PNG');
            } catch (\Exception $e) {
                $this->log("画像埋め込み失敗（円グラフ）: " . $e->getMessage());
            }
        }

        $this->pdf->Ln(5);
    }

    /**
     * 棒グラフを生成する
     *
     * @param array $data カテゴリ名 => 金額の配列
     * @param string $graphTitle グラフタイトル
     * @return string|null 生成した画像ファイルパス（失敗時はnull）
     */
    private function generateBarChart($data, $graphTitle)
    {
        // GDチェックを追加
        if (!extension_loaded('gd')) {
            $this->log("棒グラフ: GD拡張が無効のためスキップ");
            return null;
        }

        try {
            if (empty($data)) {
                $this->log("棒グラフ: データが空です");
                return null;
            }

            // カテゴリ数制限（設定から取得）
            $maxCategories = $this->getConfigValue('maxBarChartCategories', 20);
            if (count($data) > $maxCategories) {
                $this->log("棒グラフ: 上位{$maxCategories}件に制限");
                arsort($data);
                $data = array_slice($data, 0, $maxCategories, true);
            }

            // 画像サイズ（設定から取得、メモリ対策）
            $width = $this->getConfigValue('graphWidth', 600);
            $height = $this->getConfigValue('graphHeight', 300);
            $margin = 50;

            $image = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($image, 255, 255, 255);
            $black = imagecolorallocate($image, 0, 0, 0);
            $blue = imagecolorallocate($image, 66, 133, 244);

            imagefill($image, 0, 0, $white);

            // TTFフォントパスを取得
            $fontPath = $this->getConfigValue('ttfFontPath');

            // タイトルを描画（TTFフォント使用）
            if ($fontPath !== null && file_exists($fontPath)) {
                imagettftext($image, 14, 0, $width / 2 - 80, 30, $black, $fontPath, $graphTitle);
            } else {
                imagestring($image, 5, $width / 2 - 80, 10, $graphTitle, $black);
            }

            $maxValue = max($data);
            if ($maxValue == 0) $maxValue = 1;

            $kensu = count($data);
            $barWidth = max(10, ($width - 2 * $margin) / $kensu - 10);
            $x = $margin;

            foreach ($data as $category => $kingaku) {
                $barHeight = ($kingaku / $maxValue) * ($height - 2 * $margin);
                $y = $height - $margin - $barHeight;

                imagefilledrectangle($image, $x, $y, $x + $barWidth, $height - $margin, $blue);
                $categoryText = mb_strimwidth($category, 0, 15, '...', 'UTF-8');

                // カテゴリ名を描画（TTFフォント使用）
                if ($fontPath !== null && file_exists($fontPath)) {
                    imagettftext($image, 9, 0, $x, $height - $margin + 20, $black, $fontPath, $categoryText);
                } else {
                    imagestring($image, 2, $x, $height - $margin + 5, $categoryText, $black);
                }

                // 金額を描画（TTFフォント使用）
                if ($fontPath !== null && file_exists($fontPath)) {
                    imagettftext($image, 9, 0, $x, $y - 5, $black, $fontPath, number_format($kingaku));
                } else {
                    imagestring($image, 2, $x, $y - 15, number_format($kingaku), $black);
                }

                $x += $barWidth + 10;
            }

            imageline($image, $margin, $height - $margin, $width - $margin, $height - $margin, $black);
            imageline($image, $margin, $margin, $margin, $height - $margin, $black);

            $tempPath = $this->createTempFile('bar_chart_', '.png');
            imagepng($image, $tempPath);
            imagedestroy($image);

            $this->log("棒グラフ生成完了: {$tempPath}");
            return $tempPath;
        } catch (\Exception $e) {
            $this->log("棒グラフ生成失敗（詳細）: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 折れ線グラフを生成する
     *
     * @param array $data 日付 => 金額の配列
     * @param string $graphTitle グラフタイトル
     * @return string|null 生成した画像ファイルパス（失敗時はnull）
     */
    private function generateLineChart($data, $graphTitle)
    {
        // GDチェックを追加
        if (!extension_loaded('gd')) {
            $this->log("折れ線グラフ: GD拡張が無効のためスキップ");
            return null;
        }

        try {
            if (empty($data)) {
                $this->log("折れ線グラフ: データが空です");
                return null;
            }

            $width = $this->getConfigValue('graphWidth', 600);
            $height = $this->getConfigValue('graphHeight', 300);
            $margin = 50;

            $data = array_slice($data, 0, $this->maxDataKensu, true);

            $image = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($image, 255, 255, 255);
            $black = imagecolorallocate($image, 0, 0, 0);
            $red = imagecolorallocate($image, 219, 68, 55);

            imagefill($image, 0, 0, $white);

            // TTFフォントパスを取得
            $fontPath = $this->getConfigValue('ttfFontPath');

            // タイトルを描画（TTFフォント使用）
            if ($fontPath !== null && file_exists($fontPath)) {
                imagettftext($image, 14, 0, $width / 2 - 80, 30, $black, $fontPath, $graphTitle);
            } else {
                imagestring($image, 5, $width / 2 - 80, 10, $graphTitle, $black);
            }

            $maxValue = max($data);
            if ($maxValue == 0) $maxValue = 1;

            $kensu = count($data);
            if ($kensu < 2) {
                $this->log("折れ線グラフ: データ点が不足");
                imagedestroy($image);
                return null;
            }

            $stepX = ($width - 2 * $margin) / ($kensu - 1);
            $points = array();

            $i = 0;
            foreach ($data as $date => $kingaku) {
                $x = $margin + $i * $stepX;
                $y = $height - $margin - ($kingaku / $maxValue) * ($height - 2 * $margin);
                $points[] = array('x' => $x, 'y' => $y);
                $i++;
            }

            for ($j = 0; $j < count($points); $j++) {
                imagefilledellipse($image, $points[$j]['x'], $points[$j]['y'], 6, 6, $red);
                if ($j > 0) {
                    imageline($image, $points[$j - 1]['x'], $points[$j - 1]['y'], $points[$j]['x'], $points[$j]['y'], $red);
                }
            }

            imageline($image, $margin, $height - $margin, $width - $margin, $height - $margin, $black);
            imageline($image, $margin, $margin, $margin, $height - $margin, $black);

            $tempPath = $this->createTempFile('line_chart_', '.png');
            imagepng($image, $tempPath);
            imagedestroy($image);

            $this->log("折れ線グラフ生成完了: {$tempPath}");
            return $tempPath;
        } catch (\Exception $e) {
            $this->log("折れ線グラフ生成失敗（詳細）: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 円グラフを生成する
     *
     * @param array $data カテゴリ名 => 金額の配列
     * @param string $graphTitle グラフタイトル
     * @return string|null 生成した画像ファイルパス（失敗時はnull）
     */
    private function generatePieChart($data, $graphTitle)
    {
        // GDチェックを追加
        if (!extension_loaded('gd')) {
            $this->log("円グラフ: GD拡張が無効のためスキップ");
            return null;
        }

        try {
            if (empty($data)) {
                $this->log("円グラフ: データが空です");
                return null;
            }

            $maxCategories = $this->getConfigValue('maxPieChartCategories', 10);
            if (count($data) > $maxCategories) {
                $this->log("円グラフ: 上位{$maxCategories}件に制限");
                arsort($data);
                $data = array_slice($data, 0, $maxCategories, true);
            }

            $width = $this->getConfigValue('graphWidth', 600);
            $height = $this->getConfigValue('graphHeight', 300);
            $centerX = $width / 2;
            $centerY = $height / 2 + 20;
            $radius = 80;

            $image = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($image, 255, 255, 255);
            $black = imagecolorallocate($image, 0, 0, 0);

            imagefill($image, 0, 0, $white);

            // TTFフォントパスを取得
            $fontPath = $this->getConfigValue('ttfFontPath');

            // タイトルを描画（TTFフォント使用）
            if ($fontPath !== null && file_exists($fontPath)) {
                imagettftext($image, 14, 0, $width / 2 - 80, 30, $black, $fontPath, $graphTitle);
            } else {
                imagestring($image, 5, $width / 2 - 80, 10, $graphTitle, $black);
            }

            $goukei = array_sum($data);
            if ($goukei == 0) {
                $this->log("円グラフ: 合計金額が0です");
                imagedestroy($image);
                return null;
            }

            $colors = array(
                imagecolorallocate($image, 66, 133, 244),
                imagecolorallocate($image, 219, 68, 55),
                imagecolorallocate($image, 244, 180, 0),
                imagecolorallocate($image, 15, 157, 88),
                imagecolorallocate($image, 171, 71, 188),
                imagecolorallocate($image, 255, 112, 67)
            );

            $startAngle = 0;
            $colorIndex = 0;

            foreach ($data as $category => $kingaku) {
                $angle = ($kingaku / $goukei) * 360;
                $endAngle = $startAngle + $angle;

                $color = $colors[$colorIndex % count($colors)];
                imagefilledarc($image, $centerX, $centerY, $radius * 2, $radius * 2, $startAngle, $endAngle, $color, IMG_ARC_PIE);

                $legendY = 50 + $colorIndex * 20;
                imagefilledrectangle($image, 20, $legendY, 35, $legendY + 12, $color);
                $percentage = round(($kingaku / $goukei) * 100, 1);
                $categoryText = mb_strimwidth($category, 0, 20, '...', 'UTF-8');
                $legendText = $categoryText . " ({$percentage}%)";

                // 凡例テキストを描画（TTFフォント使用）
                if ($fontPath !== null && file_exists($fontPath)) {
                    imagettftext($image, 9, 0, 40, $legendY + 10, $black, $fontPath, $legendText);
                } else {
                    imagestring($image, 2, 40, $legendY, $legendText, $black);
                }

                $startAngle = $endAngle;
                $colorIndex++;
            }

            $tempPath = $this->createTempFile('pie_chart_', '.png');
            imagepng($image, $tempPath);
            imagedestroy($image);

            $this->log("円グラフ生成完了: {$tempPath}");
            return $tempPath;
        } catch (\Exception $e) {
            $this->log("円グラフ生成失敗（詳細）: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 一時ファイルを作成する
     *
     * @param string $prefix ファイル名プレフィックス
     * @param string $suffix ファイル名サフィックス
     * @return string 作成したファイルパス
     */
    private function createTempFile($prefix, $suffix)
    {
        $fileName = $prefix . uniqid() . $suffix;
        $filePath = $this->tempDir . DIRECTORY_SEPARATOR . $fileName;
        $this->tempFiles[] = $filePath;
        return $filePath;
    }

    /**
     * セクションタイトルを追加する
     *
     * @param string $title タイトル文字列
     */
    private function addSectionTitle($title)
    {
        $this->pdf->SetFont('kozgopromedium', 'B', 14);
        $this->pdf->Cell(0, 10, $title, 0, 1, 'L');
        $this->pdf->Ln(2);
    }

    /**
     * テーブルヘッダーを追加する
     *
     * @param array $headers ヘッダー項目の配列（2要素）
     */
    private function addTableHeader($headers)
    {
        $this->pdf->SetFont('kozgopromedium', 'B', 10);
        $this->pdf->SetFillColor(220, 220, 220);
        $this->pdf->Cell(90, 7, $headers[0], 1, 0, 'C', true);
        $this->pdf->Cell(90, 7, $headers[1], 1, 1, 'C', true);
    }

    /**
     * テーブル行を追加する
     *
     * @param string $koumoku 項目名
     * @param string $atai 値
     */
    private function addTableRow($koumoku, $atai)
    {
        $this->pdf->Cell(90, 7, $koumoku, 1, 0, 'L');
        $this->pdf->Cell(90, 7, $atai, 1, 1, 'R');
    }

    /**
     * PDF を出力する
     *
     * ファイル出力またはブラウザ出力を行います。
     *
     * @throws \Exception 出力に失敗した場合
     */
    public function output()
    {
        try {
            // 出力パスが指定されている場合はファイル出力
            if ($this->outputPath !== null) {
                // 出力パスを検証
                $validatedPath = $this->validateOutputPath($this->outputPath);

                // ファイルロックを取得
                $this->acquireFileLock($validatedPath);

                // ファイルに出力
                $this->pdf->Output($validatedPath, 'F');
                $this->log("PDF出力完了: {$validatedPath}");

                // ファイルロックを解放
                $this->releaseFileLock();
            } else {
                // ブラウザに出力
                $this->pdf->Output('report.pdf', 'I');
                $this->log("PDF出力完了: ブラウザ");
            }
        } catch (\Exception $e) {
            $this->log("PDF出力失敗: " . $e->getMessage());
            // ロック解放を確実に実行
            $this->releaseFileLock();
            throw new \Exception("PDF出力に失敗しました: " . $e->getMessage());
        } finally {
            // 一時ファイルをクリーンアップ
            $this->cleanup();
        }
    }

    /**
     * 出力ファイルパスを検証する
     *
     * @param string $outputPath 出力ファイルパス
     * @return string 検証済みファイルパス
     * @throws \Exception 無効なパスの場合
     */
    private function validateOutputPath($outputPath)
    {
        if (empty($outputPath)) {
            throw new \Exception("出力パスが空です");
        }

        $dirPath = dirname($outputPath);
        $fileName = basename($outputPath);

        $realDirPath = realpath($dirPath);
        if ($realDirPath === false) {
            $this->log("出力ディレクトリが存在しません: {$dirPath}");
            throw new \Exception("出力ディレクトリが存在しません");
        }

        // 設定から許可ディレクトリを取得
        $allowedDir = realpath($this->getConfigValue('allowedOutputDir'));
        if ($allowedDir === false) {
            $this->log("許可ディレクトリが存在しません");
            throw new \Exception("許可ディレクトリが存在しません");
        }

        $allowedDir = rtrim($allowedDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $realDirPathForCheck = rtrim($realDirPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (strpos($realDirPathForCheck, $allowedDir) !== 0) {
            $this->log("許可されていないディレクトリ: {$outputPath}");
            throw new \Exception("許可されていないディレクトリです");
        }

        if (!preg_match('/^[a-zA-Z0-9_\-\.\x{3000}-\x{303F}\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FFF}]+$/u', $fileName)) {
            $this->log("無効なファイル名: {$fileName}");
            throw new \Exception("無効なファイル名です");
        }

        $fullPath = $realDirPath . DIRECTORY_SEPARATOR . $fileName;

        if (!is_writable($realDirPath)) {
            $this->log("書き込み権限なし: {$realDirPath}");
            throw new \Exception("書き込み権限がありません");
        }

        if (file_exists($fullPath) && !is_writable($fullPath)) {
            $this->log("ファイル書き込み権限なし: {$fullPath}");
            throw new \Exception("ファイルに書き込み権限がありません");
        }

        return $fullPath;
    }

    /**
     * シャットダウン時の後片付け
     *
     * @return void
     */
    public function onShutdown()
    {
        // ロックが残っていれば解放
        try {
            $this->releaseFileLock();
        } catch (\Exception $e) {
            // シャットダウン時はログだけ残す
            @file_put_contents($this->logFilePath, "[" . date('Y-m-d H:i:s') . "] onShutdown error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
    }

    /**
     * ファイルロックを取得する
     *
     * @param string $filePath ロック対象のファイルパス
     * @throws \Exception ロック取得に失敗した場合
     */
    private function acquireFileLock($filePath)
    {
        $lockFilePath = $filePath . '.lock';
        $this->lockFilePath = $lockFilePath;

        // ロックファイルを開く
        $this->lockHandle = fopen($lockFilePath, 'c+');
        if ($this->lockHandle === false) {
            throw new \Exception("ロックファイルを開けません: {$lockFilePath}");
        }

        // 排他ロックを取得（最大10秒待機）
        $maxRetries = 10;
        $retryCount = 0;
        while ($retryCount < $maxRetries) {
            if (is_resource($this->lockHandle) && flock($this->lockHandle, LOCK_EX | LOCK_NB)) {
                $this->log("ファイルロック取得: {$lockFilePath}");
                // ロックファイルにプロセス情報を書き込む（上書き）
                ftruncate($this->lockHandle, 0);
                rewind($this->lockHandle);
                fwrite($this->lockHandle, getmypid() . PHP_EOL);
                fflush($this->lockHandle);
                return;
            }
            sleep(1);
            $retryCount++;
        }

        // ロック取得失敗時はハンドルを閉じて例外
        if (is_resource($this->lockHandle)) {
            fclose($this->lockHandle);
        }
        $this->lockHandle = null;
        throw new \Exception("ファイルロックの取得に失敗しました（タイムアウト）: {$filePath}");
    }

    /**
     * ファイルロックを解放する
     */
    private function releaseFileLock()
    {
        if ($this->lockHandle !== null && is_resource($this->lockHandle)) {
            // ロック解除とハンドルクローズ
            @flock($this->lockHandle, LOCK_UN);
            @fclose($this->lockHandle);
            $this->lockHandle = null;
            $this->log("ファイルロック解放");
            // ロックファイルを削除（存在する場合）
            if (!empty($this->lockFilePath) && file_exists($this->lockFilePath)) {
                @unlink($this->lockFilePath);
                $this->log("ロックファイル削除: {$this->lockFilePath}");
                $this->lockFilePath = null;
            }
        } elseif (!empty($this->lockFilePath) && file_exists($this->lockFilePath)) {
            // ハンドルが無いがファイルだけ残っている場合は単純に削除
            @unlink($this->lockFilePath);
            $this->log("残存ロックファイル削除: {$this->lockFilePath}");
            $this->lockFilePath = null;
        }
    }

    /**
     * 一時ファイルをクリーンアップする
     */
    private function cleanup()
    {
        // 一時ファイルを削除
        foreach ($this->tempFiles as $filePath) {
            if (file_exists($filePath)) {
                unlink($filePath);
                $this->log("一時ファイル削除: {$filePath}");
            }
        }

        // ログファイルをローテーション（保存）
        $this->rotateLogFile();

        // 一時ディレクトリを削除
        if (is_dir($this->tempDir)) {
            // ログファイルが残っている場合は削除
            if (file_exists($this->logFilePath)) {
                unlink($this->logFilePath);
            }
            rmdir($this->tempDir);
            $this->log("一時ディレクトリ削除: {$this->tempDir}");
        }
    }

    /**
     * 古い一時ファイルとロックをクリーンアップする
     */
    private function cleanupStaleFiles()
    {
        $tempBaseDir = sys_get_temp_dir();
        $ttl = $this->getConfigValue('tempFileTtl', 86400);
        $cutoff = time() - $ttl;

        // pdf_generator_* パターンのディレクトリを探索
        $pattern = $tempBaseDir . DIRECTORY_SEPARATOR . 'pdf_generator_*';
        foreach (glob($pattern, GLOB_ONLYDIR) as $dir) {
            if (file_exists($dir) && filemtime($dir) < $cutoff) {
                $this->removeDirectoryRecursive($dir);
            }
        }

        // 出力ディレクトリ内の古い .lock ファイルを削除
        $outputDir = $this->getConfigValue('allowedOutputDir');
        if (is_dir($outputDir)) {
            foreach (glob($outputDir . DIRECTORY_SEPARATOR . '*.lock') as $lock) {
                if (file_exists($lock) && filemtime($lock) < $cutoff) {
                    @unlink($lock);
                }
            }
        }
    }

    /**
     * ディレクトリを再帰的に削除する
     *
     * @param string $dir ディレクトリパス
     */
    private function removeDirectoryRecursive($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirectoryRecursive($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    /**
     * ログメッセージを記録する
     *
     * @param string $message ログメッセージ
     */
    private function log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$message}";
        $this->logMessages[] = $logEntry;

        // ファイルにも出力
        if ($this->logFilePath !== null) {
            file_put_contents($this->logFilePath, $logEntry . PHP_EOL, FILE_APPEND);
        }
    }

    /**
     * ログファイルをローテーションする
     */
    private function rotateLogFile()
    {
        if (!file_exists($this->logFilePath)) {
            return;
        }

        $logDir = $this->getConfigValue('logDir');
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $rotatedLogPath = $logDir . DIRECTORY_SEPARATOR . 'pdf_generator_' . date('Ymd_His') . '.log';

        if (copy($this->logFilePath, $rotatedLogPath)) {
            $this->log("ログファイルをローテーション: {$rotatedLogPath}");
            $this->cleanupOldLogs($logDir, $this->getConfigValue('logRetentionDays', 7));
        }
    }

    /**
     * 古いログファイルを削除する
     *
     * @param string $logDir ログディレクトリパス
     * @param int $daysToKeep 保持日数
     */
    private function cleanupOldLogs($logDir, $daysToKeep)
    {
        $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);

        // ログディレクトリ内のファイルを走査
        $files = glob($logDir . DIRECTORY_SEPARATOR . 'pdf_generator_*.log');
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                $this->log("古いログファイル削除: {$file}");
            }
        }
    }

    /**
     * デストラクタ
     *
     * オブジェクト破棄時に一時ファイルをクリーンアップします。
     */
    public function __destruct()
    {
        $this->cleanup();
    }

    /**
     * ログメッセージを取得する
     *
     * @return array ログメッセージ配列
     */
    public function getLog()
    {
        return $this->logMessages;
    }
}