<?php
/**
 * ファイル名: Main.php
 *
 * 概要:
 * エントリポイント: CSV読み込みからPDF生成までのワークフローを実行するスクリプト
 *
 * 機能:
 * - CSV読み込みの呼び出し
 * - データ分析の実行
 * - PDFレポート生成の呼び出し
 * - エラーハンドリング
 *
 * 依存関係:
 * - CSVReader
 * - DataAnalyzer
 * - PDFGenerator
 *
 * 作成日: 2026-01-14
 * 更新日: 2026-01-14
 */

// Composer オートロード
require_once __DIR__ . '/../vendor/autoload.php';

// 各クラスを読み込み
require_once __DIR__ . '/CSVReader.php';
require_once __DIR__ . '/DataAnalyzer.php';
require_once __DIR__ . '/PDFGenerator.php';

use App\PDFGenerator;

/**
 * メイン処理を実行する
 *
 * @return int 終了ステータス（0=成功、1=失敗）
 */
function main()
{
    try {
        // 処理開始メッセージ
        echo "=== 売上分析レポート生成を開始します ===\n";
        
        // 拡張モジュールチェック（デバッグ用）
        echo "\n--- 環境チェック ---\n";
        echo "PHP Version: " . phpversion() . "\n";
        echo "GD拡張: " . (extension_loaded('gd') ? '有効' : '無効') . "\n";
        echo "mbstring拡張: " . (extension_loaded('mbstring') ? '有効' : '無効') . "\n";
        echo "TCPDF: " . (class_exists('TCPDF') ? '読み込み済み' : '未読み込み') . "\n";

        // 設定を定義
        $csvFilePath = __DIR__ . '/../data/sales_data.csv';
        $outputFilePath = __DIR__ . '/../output/sales_report.pdf';

        // PDF生成の設定
        $pdfConfig = array(
            'allowedOutputDir' => __DIR__ . '/../output',
            'logDir' => __DIR__ . '/../logs',
            'maxDataKensu' => 50,
            'maxBarChartCategories' => 20,
            'maxPieChartCategories' => 10,
            'logRetentionDays' => 7,
            'tempFileTtl' => 86400,
            'graphWidth' => 600,
            'graphHeight' => 300
        );

        // 1. CSVファイルを読み込む
        echo "\n[1/3] CSVファイルを読み込んでいます...\n";
        $reader = new CSVReader($csvFilePath);
        $data = $reader->read();
        echo "  → " . count($data) . " 件のデータを読み込みました\n";

        // 2. データを分析する
        echo "\n[2/3] データを分析しています...\n";
        $analyzer = new DataAnalyzer($data);

        // 統計情報を表示
        $goukei = $analyzer->getGoukeiUriage();
        $heikin = $analyzer->getHeikinUriage();
        echo "  → 総売上金額: " . number_format($goukei) . " 円\n";
        echo "  → 平均売上金額: " . number_format($heikin) . " 円\n";

        // 3. PDFレポートを生成する
        echo "\n[3/3] PDFレポートを生成しています...\n";
        $generator = new PDFGenerator('売上分析レポート', $outputFilePath, $pdfConfig);
        $generator->generate($analyzer);
        $generator->output();

        echo "  → PDF出力完了: {$outputFilePath}\n";

        // ログ情報を表示（オプション）
        $logMessages = $generator->getLog();
        if (!empty($logMessages)) {
            echo "\n--- ログ情報 ---\n";
            foreach (array_slice($logMessages, -5) as $logMessage) {
                echo "  " . $logMessage . "\n";
            }
        }

        // 処理完了メッセージ
        echo "\n=== 処理が正常に完了しました ===\n";

        return 0;

    } catch (Exception $e) {
        // エラーメッセージを表示
        echo "\n!!! エラーが発生しました !!!\n";
        echo "エラー内容: " . $e->getMessage() . "\n";
        echo "ファイル: " . $e->getFile() . "\n";
        echo "行番号: " . $e->getLine() . "\n";

        // スタックトレースを表示（デバッグ用）
        if (getenv('DEBUG') === '1') {
            echo "\nスタックトレース:\n";
            echo $e->getTraceAsString() . "\n";
        }

        return 1;
    }
}

// メイン処理を実行
$exitCode = main();
exit($exitCode);
