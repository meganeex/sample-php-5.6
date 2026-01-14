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
 * - ファイル検証
 *
 * 依存関係:
 * - なし
 *
 * 作成日: 2026-01-14
 * 更新日: 2026-01-14
 */

/**
 * CSV読み込みクラス
 *
 * CSVファイルを読み込み、連想配列として返す機能を提供します。
 */
class CSVReader
{
    /**
     * CSVファイルパス
     * @var string
     */
    private $filePath;

    /**
     * コンストラクタ
     *
     * @param string $filePath CSVファイルパス
     * @throws Exception ファイルパスが空の場合
     */
    public function __construct($filePath)
    {
        if (empty($filePath)) {
            throw new Exception("CSVファイルパスが指定されていません");
        }
        $this->filePath = $filePath;
    }

    /**
     * CSVデータを読み込む
     *
     * @return array データ配列（連想配列の配列）
     * @throws Exception ファイルが見つからない場合
     * @throws Exception ファイルを開けない場合
     */
    public function read()
    {
        $data = array();

        // ファイルの存在確認
        if (!file_exists($this->filePath)) {
            throw new Exception("CSVファイルが見つかりません: {$this->filePath}");
        }

        // ファイルを開く
        $handle = fopen($this->filePath, 'r');
        if ($handle === false) {
            throw new Exception("CSVファイルを開けません: {$this->filePath}");
        }

        // ヘッダー行を読み込み
        $headers = fgetcsv($handle);
        if ($headers === false || empty($headers)) {
            fclose($handle);
            throw new Exception("CSVファイルのヘッダー行が読み込めません");
        }

        // データ行を読み込み
        $gyouBangou = 1;
        while (($gyou = fgetcsv($handle)) !== false) {
            $gyouBangou++;

            // 空行をスキップ
            if (empty(array_filter($gyou))) {
                continue;
            }

            // ヘッダーと列数が一致するかチェック
            if (count($gyou) !== count($headers)) {
                fclose($handle);
                throw new Exception("CSVファイルの{$gyouBangou}行目の列数が不正です");
            }

            // 連想配列に変換
            $data[] = array_combine($headers, $gyou);
        }

        // ファイルを閉じる
        fclose($handle);

        // データが空の場合はエラー
        if (empty($data)) {
            throw new Exception("CSVファイルにデータがありません");
        }

        return $data;
    }
}
