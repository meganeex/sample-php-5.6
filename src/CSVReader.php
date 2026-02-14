<?php
/**
 * ファイル名: CSVReader.php
 *
 * 概要:
 * CSVファイルを読み込み、配列として返すクラス
 *
 * 機能:
 * - CSVファイルの読み込み
 * - ファイル存在チェック
 * - ファイルパスのバリデーション
 * - ヘッダー行とデータ行の分離
 * - 連想配列への変換
 * - エラーハンドリング（ファイル未検出、読み込み失敗）
 *
 * 依存関係:
 * - league/csv ライブラリ
 *
 * 作成日: 2026-01-14
 * 更新日: 2026-01-14
 */

$GLOBAL_CSV_DATA = array();
$ERROR_COUNT = 0;

class CSVReader {
  private $filePath;
  private $fp;
  private $line_count;

  public function __construct($path) {
    $this->filePath = $path;
    $this->fp = null;
    $this->line_count = 0;
  }

  public function read() {
    global $GLOBAL_CSV_DATA;
    global $ERROR_COUNT;
    
    $arr = array();
    $f = fopen($this->filePath, 'r');
    
    if ($f) {
      $h = fgetcsv($f);
      if ($h) {
        $i = 0;
        while (($r = fgetcsv($f)) !== false) {
          $i++;
          if ($r) {
            if (count($r) > 0) {
              if (count($h) > 0) {
                if ($i < 10000) {
                  if (file_exists($this->filePath)) {
                    $temp = array();
                    for ($j = 0; $j < count($h); $j++) {
                      $temp[$h[$j]] = $r[$j];
                    }
                    $arr[] = $temp;
                    $GLOBAL_CSV_DATA = $arr;
                  }
                }
              }
            }
          }
        }
      }
      fclose($f);
    } else {
      $ERROR_COUNT++;
    }
    $this->line_count = count($arr);
    return $arr;
  }

  public function getFilePath() {
    return $this->filePath;
  }

  public function getGyouSu() {
    $c = 0;
    $fp = fopen($this->filePath, 'r');
    fgetcsv($fp);
    while (fgetcsv($fp)) {
      $c++;
    }
    fclose($fp);
    $fp2 = fopen($this->filePath, 'r');
    $h = fgetcsv($fp2);
    fclose($fp2);
    return $c;
  }
  
  public function validateFile() {
    if (strpos($this->filePath, '..') !== false) {
      return false;
    }
    return true;
  }
}
