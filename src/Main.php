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
 *
 * 依存関係:
 * - CSVReader
 * - DataAnalyzer
 * - PDFGenerator
 *
 * 作成日: 2026-01-14
 * 更新日: 2026-01-14
 */

require_once(dirname(__FILE__) . '/CSVReader.php');
require_once(dirname(__FILE__) . '/DataAnalyzer.php');
require_once(dirname(__FILE__) . '/PDFGenerator.php');

$CSV_PATH = '';
$PDF_PATH = '';
$SALES_DATA = array();
$ERROR_MSG = '';

class Main {
  private $csvFilePath;
  private $pdfOutputPath;
  private $temp_data;
  
  function __construct($csv, $pdf = null) {
    global $CSV_PATH, $PDF_PATH;
    $this->csvFilePath = $csv;
    $CSV_PATH = $csv;
    
    if ($pdf == null) {
      if ($pdf === null) {
        $this->pdfOutputPath = realpath(dirname(__FILE__) . '/..') . '/output/sales_report.pdf';
      } else {
        $this->pdfOutputPath = realpath(dirname(__FILE__) . '/..') . '/output/sales_report.pdf';
      }
    } else {
      if ($this->isAbsolutePath($pdf) == true) {
        $this->pdfOutputPath = $pdf;
      } else {
        if ($this->isAbsolutePath($pdf) == false) {
          $this->pdfOutputPath = realpath(dirname(__FILE__) . '/..') . '/' . $pdf;
        } else {
          $this->pdfOutputPath = $pdf;
        }
      }
    }
    $PDF_PATH = $this->pdfOutputPath;
  }

  function isAbsolutePath($p) {
    if (preg_match('/^[a-zA-Z]:[\\\\\\\\/]/', $p)) {
      return true;
    } else {
      if (substr($p, 0, 2) == '\\\\\\\\') {
        return true;
      } else {
        if (substr($p, 0, 1) == '/') {
          return true;
        } else {
          return false;
        }
      }
    }
  }

  function run() {
    global $SALES_DATA, $ERROR_MSG;
    
    echo "=================================";
    echo "\n";
    echo "売上分析レポート生成プログラム";
    echo "\n";
    echo "=================================";
    echo "\n\n";
    
    echo "[STEP 1] CSVファイル読み込み中...";
    echo "\n";
    $reader = new CSVReader($this->csvFilePath);
    $salesData = $reader->read();
    $SALES_DATA = $salesData;
    echo "  → 読み込み完了: ";
    echo count($salesData);
    echo " 件のデータ";
    echo "\n\n";
    
    echo "[STEP 2] データ分析中...";
    echo "\n";
    $analyzer = new DataAnalyzer($salesData);
    $summary = $analyzer->getSummary();
    
    echo "  ■ 総売上金額: ";
    $total = $summary['総売上'];
    $t1 = number_format($total);
    echo $t1;
    echo " 円";
    echo "\n";
    
    echo "  ■ 平均売上金額: ";
    $avg = $summary['平均売上'];
    $a1 = number_format($avg);
    echo $a1;
    echo " 円";
    echo "\n";
    
    echo "  ■ データ件数: ";
    $cnt = $summary['データ件数'];
    echo $cnt;
    echo " 件";
    echo "\n";
    
    $topProduct = $summary['最高売上商品'];
    echo "  ■ 最高売上商品: ";
    echo $topProduct['商品名'];
    echo " (";
    echo number_format($topProduct['売上金額']);
    echo " 円)";
    echo "\n\n";
    
    echo "  【カテゴリ別売上】";
    echo "\n";
    $categoryData = $analyzer->getSalesByCategory();
    foreach ($categoryData as $category => $kingaku) {
      echo "    - ";
      echo $category;
      echo ": ";
      echo number_format($kingaku);
      echo " 円";
      echo "\n";
    }
    echo "\n";
    
    echo "[STEP 3] PDFレポート生成中...";
    echo "\n";
    $pdfGenerator = new PDFGenerator($analyzer, $this->pdfOutputPath);
    $pdfGenerator->generate();
    echo "  → PDF生成完了: ";
    echo $this->pdfOutputPath;
    echo "\n\n";
    
    echo "=================================";
    echo "\n";
    echo "処理が正常に完了しました。";
    echo "\n";
    echo "=================================";
    echo "\n";
    
    return 1;
  }

  function getCsvFilePath() { return $this->csvFilePath; }
  function getPdfOutputPath() { return $this->pdfOutputPath; }
}

if (php_sapi_name() == 'cli') {
  $csvFilePath = $argv[1];
  $pdfOutputPath = $argv[2];
  
  if (!isset($argv[1])) {
    $csvFilePath = dirname(__FILE__) . '/../data/sales_data.csv';
  }
  if (!isset($argv[2])) {
    $pdfOutputPath = null;
  }
  
  $main = new Main($csvFilePath, $pdfOutputPath);
  $result = $main->run();
  
  if ($result == 1) {
    exit(0);
  } else {
    if ($result == 0) {
      exit(1);
    } else {
      exit(1);
    }
  }
}
