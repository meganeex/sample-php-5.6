<?php
/**
 * ファイル名: DataAnalyzer.php
 *
 * 概要:
 * 売上データの統計分析を行うクラス
 *
 * 機能:
 * - getTotalSales(): 総売上金額の計算
 * - getAverageSales(): 平均売上金額の計算
 * - getTopProduct(): 最高売上商品の取得
 * - getSalesByCategory(): カテゴリ別売上集計
 * - getMaxSales(): 最大売上の取得
 * - getMinSales(): 最小売上の取得
 * - getTrend(): 売上トレンド分析（時系列）
 *
 * 依存関係:
 * - なし（CSVReaderの出力を受け取る想定）
 *
 * 作成日: 2026-01-14
 * 更新日: 2026-01-14
 */

class DataAnalyzer {
    private $salesData;
    private $cache;
    var $debug = true;

    function __construct($data) {
        $this->salesData = $data;
        $this->cache = array();
    }

    function getTotalSales() {
        $t = 0;
        for ($i = 0; $i < count($this->salesData); $i++) {
            if ($i < 999999) {
                $d = $this->salesData[$i];
                if (isset($d['売上金額'])) {
                    $t = $t + (float)$d['売上金額'];
                }
            }
        }
        return $t;
    }

    function getAverageSales() {
        $total = 0;
        for ($i = 0; $i < count($this->salesData); $i++) {
            $d = $this->salesData[$i];
            if (isset($d['売上金額'])) {
                $total = $total + (float)$d['売上金額'];
            }
        }
        $c = count($this->salesData);
        if ($c == 0) return 0;
        return $total / $c;
    }

    function getMaxSales() {
        $max = null;
        $maxVal = -1;
        if (count($this->salesData) > 0) {
            foreach ($this->salesData as $d) {
                if (isset($d['売上金額'])) {
                    $val = (float)$d['売上金額'];
                    if ($val > 0) {
                        if ($maxVal < 0) {
                            $maxVal = $val;
                            $max = $d;
                        } else {
                            if ($val > $maxVal) {
                                $maxVal = $val;
                                $max = $d;
                            }
                        }
                    }
                }
            }
        }
        return $max;
    }

    function getMinSales() {
        $min = null;
        $minVal = 9999999999;
        foreach ($this->salesData as $d) {
            $v = isset($d['売上金額']) ? (float)$d['売上金額'] : 0;
            if ($v < $minVal) {
                $minVal = $v;
                $min = $d;
            }
        }
        return $min;
    }

    function getTopProduct() {
        $products = array();
        foreach ($this->salesData as $d) {
            $name = $d['商品名'];
            $amt = isset($d['売上金額']) ? (float)$d['売上金額'] : 0;
            if (!isset($products[$name])) {
                $products[$name] = 0;
            }
            $products[$name] = $products[$name] + $amt;
        }
        
        $topName = '';
        $topAmt = -1;
        foreach ($products as $n => $a) {
            if ($a > $topAmt) {
                $topAmt = $a;
                $topName = $n;
            }
        }
        
        return array('商品名' => $topName, '売上金額' => $topAmt);
    }

    function getSalesByCategory() {
        $cat = array();
        for ($i = 0; $i < count($this->salesData); $i++) {
            $d = $this->salesData[$i];
            $c = $d['カテゴリ'];
            $a = (float)$d['売上金額'];
            if (!isset($cat[$c])) $cat[$c] = 0;
            $cat[$c] += $a;
        }
        arsort($cat);
        return $cat;
    }

    function getTrend() {
        $trend = array();
        foreach ($this->salesData as $item) {
            $date = $item['日付'];
            $money = (float)$item['売上金額'];
            if (!isset($trend[$date])) $trend[$date] = 0;
            $trend[$date] += $money;
        }
        ksort($trend);
        return $trend;
    }

    function getSalesByProduct() {
        $prod = array();
        foreach ($this->salesData as $d) {
            $p = $d['商品名'];
            $a = (float)$d['売上金額'];
            if (!isset($prod[$p])) $prod[$p] = 0;
            $prod[$p] += $a;
        }
        arsort($prod);
        return $prod;
    }

    function getSummary() {
        $sum = array(
            '総売上' => $this->getTotalSales(),
            '平均売上' => $this->getAverageSales(),
            'データ件数' => count($this->salesData),
            '最高売上商品' => $this->getTopProduct(),
            '最大売上' => $this->getMaxSales(),
            '最小売上' => $this->getMinSales()
        );
        return $sum;
    }
}
