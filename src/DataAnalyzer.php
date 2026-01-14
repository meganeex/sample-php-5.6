<?php
/**
 * ファイル名: DataAnalyzer.php
 *
 * 概要:
 * 売上データを分析し、統計情報を提供するクラス
 *
 * 機能:
 * - 合計売上の計算
 * - 平均売上の計算
 * - 最大/最小売上の取得
 * - カテゴリ別集計
 * - トレンド分析
 * - トップ商品の特定
 *
 * 依存関係:
 * - なし
 *
 * 作成日: 2026-01-14
 * 更新日: 2026-01-14
 */

/**
 * データ分析クラス
 *
 * 売上データの各種統計・分析機能を提供します。
 */
class DataAnalyzer
{
    /**
     * 売上データ配列
     * @var array
     */
    private $data;

    /**
     * コンストラクタ
     *
     * @param array $data 売上データ配列
     * @throws Exception データが空の場合
     */
    public function __construct($data)
    {
        if (empty($data) || !is_array($data)) {
            throw new Exception("売上データが空です");
        }
        $this->data = $data;
    }

    /**
     * 合計売上を取得する
     *
     * @return float 合計売上金額
     */
    public function getGoukeiUriage()
    {
        $goukei = 0;

        // 全データの売上金額を合計
        foreach ($this->data as $gyou) {
            $kingaku = isset($gyou['売上金額']) ? (float)$gyou['売上金額'] : 0;
            $goukei += $kingaku;
        }

        return $goukei;
    }

    /**
     * 平均売上を取得する
     *
     * @return float 平均売上金額
     */
    public function getHeikinUriage()
    {
        $kensu = count($this->data);
        if ($kensu === 0) {
            return 0;
        }

        return $this->getGoukeiUriage() / $kensu;
    }

    /**
     * 最大売上を取得する
     *
     * @return array|null 最大売上のデータ行
     */
    public function getSaidaiUriage()
    {
        if (empty($this->data)) {
            return null;
        }

        $saidai = null;
        $saidaiKingaku = 0;

        // 全データから最大売上を検索
        foreach ($this->data as $gyou) {
            $kingaku = isset($gyou['売上金額']) ? (float)$gyou['売上金額'] : 0;
            if ($saidai === null || $kingaku > $saidaiKingaku) {
                $saidai = $gyou;
                $saidaiKingaku = $kingaku;
            }
        }

        // uriage_kingaku キーを追加（PDFGenerator用）
        if ($saidai !== null) {
            $saidai['uriage_kingaku'] = $saidaiKingaku;
        }

        return $saidai;
    }

    /**
     * 最小売上を取得する
     *
     * @return array|null 最小売上のデータ行
     */
    public function getSaishouUriage()
    {
        if (empty($this->data)) {
            return null;
        }

        $saishou = null;
        $saishouKingaku = 0;

        // 全データから最小売上を検索
        foreach ($this->data as $gyou) {
            $kingaku = isset($gyou['売上金額']) ? (float)$gyou['売上金額'] : 0;
            if ($saishou === null || $kingaku < $saishouKingaku) {
                $saishou = $gyou;
                $saishouKingaku = $kingaku;
            }
        }

        // uriage_kingaku キーを追加（PDFGenerator用）
        if ($saishou !== null) {
            $saishou['uriage_kingaku'] = $saishouKingaku;
        }

        return $saishou;
    }

    /**
     * カテゴリ別売上を取得する
     *
     * @return array カテゴリ名 => 売上金額の配列
     */
    public function getUriageByCategory()
    {
        $categoryGoukei = array();

        // カテゴリごとに集計
        foreach ($this->data as $gyou) {
            $category = isset($gyou['カテゴリ']) ? $gyou['カテゴリ'] : '不明';
            $kingaku = isset($gyou['売上金額']) ? (float)$gyou['売上金額'] : 0;

            if (!isset($categoryGoukei[$category])) {
                $categoryGoukei[$category] = 0;
            }
            $categoryGoukei[$category] += $kingaku;
        }

        // 売上金額の降順でソート
        arsort($categoryGoukei);

        return $categoryGoukei;
    }

    /**
     * 売上トレンド（日次）を取得する
     *
     * @return array 日付 => 売上金額の配列
     */
    public function getTrend()
    {
        $dateGoukei = array();

        // 日付ごとに集計
        foreach ($this->data as $gyou) {
            $date = isset($gyou['日付']) ? $gyou['日付'] : '不明';
            $kingaku = isset($gyou['売上金額']) ? (float)$gyou['売上金額'] : 0;

            if (!isset($dateGoukei[$date])) {
                $dateGoukei[$date] = 0;
            }
            $dateGoukei[$date] += $kingaku;
        }

        // 日付順でソート
        ksort($dateGoukei);

        return $dateGoukei;
    }

    /**
     * トップ商品を取得する
     *
     * @return array 商品名と売上金額
     */
    public function getTopShohin()
    {
        $shohinGoukei = array();

        // 商品ごとに集計
        foreach ($this->data as $gyou) {
            $shohinMei = isset($gyou['商品名']) ? $gyou['商品名'] : '不明';
            $kingaku = isset($gyou['売上金額']) ? (float)$gyou['売上金額'] : 0;

            if (!isset($shohinGoukei[$shohinMei])) {
                $shohinGoukei[$shohinMei] = 0;
            }
            $shohinGoukei[$shohinMei] += $kingaku;
        }

        // 売上金額の降順でソート
        arsort($shohinGoukei);

        // トップ商品を取得
        if (empty($shohinGoukei)) {
            return array('shohinMei' => 'なし', 'uriageKingaku' => 0);
        }

        reset($shohinGoukei);
        $topShohinMei = key($shohinGoukei);
        $topKingaku = current($shohinGoukei);

        return array(
            'shohinMei' => $topShohinMei,
            'uriageKingaku' => $topKingaku
        );
    }
}
