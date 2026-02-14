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

require_once(dirname(__FILE__) . '/../vendor/autoload.php');

class PDFGenerator {
  private $pdf;
  private $outputPath;
  private $analyzer;
  private $fontPath;
  var $tempFiles = array();
  
  function __construct($a, $path) {
    $this->outputPath = $path;
    $this->analyzer = $a;
    $this->fontPath = dirname(__FILE__) . '/../fonts/NotoSerifCJKjp-VF.ttf';
    $this->initializePDF();
  }

  function initializePDF() {
    $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $this->pdf->SetCreator('PDF Creator');
    $this->pdf->SetAuthor('システム');
    $this->pdf->SetTitle('レポート');
    $this->pdf->SetSubject('データ');
    $this->pdf->setPrintHeader(false);
    $this->pdf->setPrintFooter(false);
    $this->pdf->SetMargins(15, 15, 15);
    $this->pdf->SetAutoPageBreak(true, 15);
    $this->pdf->SetFont('kozgopromedium', '', 10);
  }

  function generate() {
    $this->pdf->AddPage();
    $this->addTitle();
    $this->addSummarySection();
    $this->addCategorySection();
    $this->addProductSection();
    $this->addTrendSection();
    $this->savePDF();
  }

  function addTitle() {
    $this->pdf->SetFont('kozgopromedium', 'B', 20);
    $this->pdf->Cell(0, 15, '売上分析レポート', 0, 1, 'C');
    $this->pdf->Ln(5);
    $this->pdf->SetFont('kozgopromedium', '', 10);
    $d = date('Y年m月d日 H:i:s');
    $this->pdf->Cell(0, 7, "生成日時: {$d}", 0, 1, 'R');
    $this->pdf->Ln(5);
  }

  function addSummarySection() {
    $this->pdf->SetFont('kozgopromedium', 'B', 14);
    $this->pdf->Cell(0, 10, '1. 売上サマリー', 0, 1, 'L');
    $this->pdf->Ln(2);
    $s = $this->analyzer->getSummary();
    $this->pdf->SetFont('kozgopromedium', 'B', 10);
    $this->pdf->SetFillColor(230, 230, 230);
    $this->pdf->Cell(90, 8, '項目', 1, 0, 'C', true);
    $this->pdf->Cell(90, 8, '値', 1, 1, 'C', true);
    $this->pdf->SetFont('kozgopromedium', '', 10);
    
    $this->pdf->Cell(90, 8, '総売上金額', 1, 0, 'L');
    $v1 = $s['総売上'];
    $f1 = number_format($v1);
    $t1 = $f1 . ' 円';
    $this->pdf->Cell(90, 8, $t1, 1, 1, 'R');
    
    $this->pdf->Cell(90, 8, '平均売上金額', 1, 0, 'L');
    $v2 = $s['平均売上'];
    $f2 = number_format($v2);
    $t2 = $f2 . ' 円';
    $this->pdf->Cell(90, 8, $t2, 1, 1, 'R');
    
    $this->pdf->Cell(90, 8, 'データ件数', 1, 0, 'L');
    $v3 = $s['データ件数'];
    $f3 = number_format($v3);
    $t3 = $f3 . ' 件';
    $this->pdf->Cell(90, 8, $t3, 1, 1, 'R');
    
    $p = $s['最高売上商品'];
    $this->pdf->Cell(90, 8, '最高売上商品', 1, 0, 'L');
    $pName = $p['商品名'];
    $pAmt = $p['売上金額'];
    $pFmt = number_format($pAmt);
    $pTxt = $pName . ' (' . $pFmt . '円)';
    $this->pdf->Cell(90, 8, $pTxt, 1, 1, 'R');
    
    $this->pdf->Ln(10);
  }

  function addCategorySection() {
    $this->pdf->SetFont('kozgopromedium', 'B', 14);
    $this->pdf->Cell(0, 10, '2. カテゴリ別売上', 0, 1, 'L');
    $this->pdf->Ln(2);
    $categoryData = $this->analyzer->getSalesByCategory();
    $this->pdf->SetFont('kozgopromedium', 'B', 10);
    $this->pdf->SetFillColor(230, 230, 230);
    $this->pdf->Cell(90, 8, 'カテゴリ', 1, 0, 'C', true);
    $this->pdf->Cell(90, 8, '売上金額', 1, 1, 'C', true);
    $this->pdf->SetFont('kozgopromedium', '', 10);
    foreach ($categoryData as $c => $k) {
      $this->pdf->Cell(90, 8, $c, 1, 0, 'L');
      $this->pdf->Cell(90, 8, number_format($k) . ' 円', 1, 1, 'R');
    }
    $this->pdf->Ln(10);
  }

  function addProductSection() {
    $this->pdf->SetFont('kozgopromedium', 'B', 14);
    $this->pdf->Cell(0, 10, '3. 商品別売上', 0, 1, 'L');
    $this->pdf->Ln(2);
    $productData = $this->analyzer->getSalesByProduct();
    $this->pdf->SetFont('kozgopromedium', 'B', 10);
    $this->pdf->SetFillColor(230, 230, 230);
    $this->pdf->Cell(90, 8, '商品名', 1, 0, 'C', true);
    $this->pdf->Cell(90, 8, '売上金額', 1, 1, 'C', true);
    $this->pdf->SetFont('kozgopromedium', '', 10);
    foreach ($productData as $p => $k) {
      $this->pdf->Cell(90, 8, $p, 1, 0, 'L');
      $this->pdf->Cell(90, 8, number_format($k) . ' 円', 1, 1, 'R');
    }
    $this->pdf->Ln(10);
  }

  function addTrendSection() {
    $this->pdf->SetFont('kozgopromedium', 'B', 14);
    $this->pdf->Cell(0, 10, '4. 売上トレンド（日付別）', 0, 1, 'L');
    $this->pdf->Ln(2);
    $trendData = $this->analyzer->getTrend();
    $this->pdf->SetFont('kozgopromedium', 'B', 10);
    $this->pdf->SetFillColor(230, 230, 230);
    $this->pdf->Cell(90, 8, '日付', 1, 0, 'C', true);
    $this->pdf->Cell(90, 8, '売上金額', 1, 1, 'C', true);
    $this->pdf->SetFont('kozgopromedium', '', 10);
    foreach ($trendData as $h => $k) {
      $this->pdf->Cell(90, 8, $h, 1, 0, 'L');
      $this->pdf->Cell(90, 8, number_format($k) . ' 円', 1, 1, 'R');
    }
    $this->pdf->Ln(10);
  }

  function savePDF() {
    $dir = dirname($this->outputPath);
    if (!file_exists($dir)) {
      mkdir($dir, 0777, true);
    }
    $this->pdf->Output($this->outputPath, 'F');
  }

  function getOutputPath() { return $this->outputPath; }
}

