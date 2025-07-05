<?php
require_once __DIR__.'/FormatInterface.php';

class CSV implements FormatInterface {

    public function import(string $filePath, string $langIgnored, PDO $db): array {
        $config = loadConfig();
        $baseLang   = $config['base_language'] ?? 'en';
        $useVersions = !empty($config['use_versions']);
        $csvConf    = $config['config_csv'] ?? [];
        $delimiter  = $csvConf['delimiter'] ?? ',';
        $keyColumn  = $csvConf['key_column'] ?? 'key';

        $handle = fopen($filePath, 'r');
        if (!$handle) return ['error'=>'cannot open file'];

        $headerRow = fgetcsv($handle, 0, $delimiter);
        if (!$headerRow) return ['error'=>'empty file'];

        $headerMap = [];
        $langCols = [];
        $versionCol = null;

        foreach ($headerRow as $i => $col) {
            $name = trim(strtolower($col));
            if ($name === strtolower($keyColumn)) {
                $headerMap['key'] = $i;
            } elseif ($useVersions && $name === 'version') {
                $versionCol = $i;
            } elseif (preg_match('/\b([a-z]{2})\b/i', $name, $m)) {
                $code = strtolower($m[1]);
                $stLang = $db->prepare('SELECT id FROM languages WHERE code=?');
                $stLang->execute([$code]);
                $lid = $stLang->fetchColumn();
                if ($lid) {
                    $langCols[$i] = $lid;
                }
            }
        }

        if (!isset($headerMap['key'])) {
            return ['error'=>"missing '$keyColumn' column"];
        }

        $stInsStr = $db->prepare('INSERT INTO strings(skey, english, version) VALUES(?, ?, ?)');
        $stGetStr = $db->prepare('SELECT id FROM strings WHERE skey=?');
        $stUpdStr = $db->prepare('UPDATE strings SET english=?, version=? WHERE id=?');
        $stUpdStrNoVer = $db->prepare('UPDATE strings SET english=? WHERE id=?');
        $stUpdTr = $db->prepare('INSERT INTO translations(string_id,language_id,value)
                                 VALUES(?,?,?)
                                 ON CONFLICT(string_id,language_id) DO UPDATE SET value=excluded.value');

        $langs = $db->query('SELECT id,code FROM languages')->fetchAll(PDO::FETCH_KEY_PAIR);
        $baseLangId = array_search($baseLang, $langs);
        
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $key = trim($row[$headerMap['key']] ?? '');
            if (!$key) continue;

            $stGetStr->execute([$key]);
            $sid = $stGetStr->fetchColumn();

            if (!$sid && $baseLangId !== null) {
                // Check if baseLang column exists in row & fill it
                foreach ($langCols as $colIdx => $lid) {
                    if ($lid == $baseLangId) {
                        $val = trim($row[$colIdx] ?? '');
                        $ver = ($useVersions && $versionCol !== null) ? trim($row[$versionCol] ?? '1.0') : '1.0';
                        $stInsStr->execute([$key, $val, $ver]);
                        $stGetStr->execute([$key]);
                        $sid = $stGetStr->fetchColumn();
                        break;
                    }
                }
            }
            if (!$sid) continue; // skip if still no sid

            foreach ($langCols as $colIdx => $lid) {
                $val = trim($row[$colIdx] ?? '');
                if ($lid == $baseLangId) {
                    if ($useVersions && $versionCol !== null) {
                        $ver = trim($row[$versionCol] ?? '1.0');
                        $stUpdStr->execute([$val, $ver, $sid]);
                    } else {
                        $stUpdStrNoVer->execute([$val, $sid]);
                    }
                } else {
                    $stUpdTr->execute([$sid, $lid, $val]);
                }
            }
        }

        fclose($handle);
        return ['success'=>1];
    }

    public function export(string $langIgnored, PDO $db): string {
        $config      = loadConfig();
        $baseLang    = $config['base_language'] ?? 'en';
        $useVersions = $config['use_versions'];
        $csvConf     = $config['config_csv'] ?? [];
        $delimiter   = $csvConf['delimiter'] ?? ',';
        $keyColumn   = $csvConf['key_column'] ?? 'key';

        $langs = $db->query('SELECT id,code FROM languages')->fetchAll(PDO::FETCH_KEY_PAIR);

        $fp = fopen('php://temp', 'w+');

        $header = [$keyColumn];
        foreach ($langs as $code) {
            $header[] = $code;
        }
        if ($useVersions) $header[] = 'version';

        fputcsv($fp, $header, $delimiter);

        $rowSql = "SELECT s.skey, s.english, s.version, ";
        foreach ($langs as $lid => $code) {
            $rowSql .= "(SELECT t.value FROM translations t WHERE t.string_id=s.id AND t.language_id=$lid) AS `$code`, ";
        }
        $rowSql = rtrim($rowSql, ', ') . " FROM strings s";

        $rows = $db->query($rowSql)->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $line = [$r['skey']];
            foreach ($langs as $code) {
                $val = $r[$code] ?? ($code === $baseLang ? $r['english'] : '');
                $line[] = $val;
            }
            if ($useVersions) {
                $line[] = $r['version'] ?? '1.0';
            }
            fputcsv($fp, $line, $delimiter);
        }

        rewind($fp);
        return stream_get_contents($fp);
    }

    public static function mimeType(): string { return 'text/csv'; }
    public static function fileExtension(): string { return 'csv'; }
    public static function name(): string { return 'CSV'; }
    public static function menu(): array {
        return [
            ["text", "CSV delimited by '[config_csv.delimiter]', first column is '[config_csv.key_column]'"],
            ["import", "import csv"],
            ["export", "download csv"]
        ];
    }
}