<?php
require_once __DIR__.'/FormatInterface.php';
require_once __DIR__.'/../config.php';

class CSVSingle implements FormatInterface {

    public function import(string $filePath, string $lang, PDO $db): array {

        $config = loadConfig();
        $baseLang = $config['base_language'] ?? 'en';
        $useVersions = !empty($config['use_versions']);

        $csvConf = $config['config_csvsingle'] ?? [];
        $delimiter = $csvConf['delimiter'] ?? ',';
        $headers = $csvConf['headers'] ?? false;
        $keyColumn = $csvConf['key_column'] ?? 'key';
        $valueColumn = $csvConf['value_column'] ?? 'value';

        if (strlen($delimiter) !== 1) {
            return ['error' => 'invalid delimiter'];
        }

        // Find selected language.
        $st = $db->prepare('SELECT id FROM languages WHERE code=?');
        $st->execute([$lang]);
        $lid = $st->fetchColumn();

        if (!$lid) {
            return ['error' => 'invalid language'];
        }

        $isBase = ($lang === $baseLang);

        $fp = fopen($filePath, 'r');

        if (!$fp) {
            return ['error' => 'cannot open file'];
        }

        $imported = 0;
        $skipped = 0;

        try {
            // Skip UTF-8 BOM if present.
            if (fread($fp, 3) !== "\xEF\xBB\xBF") {
                rewind($fp);
            }

            $keyIdx = 0;
            $valueIdx = 1;
            $versionIdx = $useVersions ? 2 : null;

            // Resolve column positions from headers.
            if ($headers) {
                $header = fgetcsv($fp, 0, $delimiter, '"', '');

                if ($header === false) {
                    return ['error' => 'empty CSV file'];
                }

                $header = array_map(function ($v) {
                    return strtolower(trim((string)$v));
                }, $header);

                $keyIdx = array_search(
                    strtolower($keyColumn), $header, true
                );

                $valueIdx = array_search(
                    strtolower($valueColumn), $header, true
                );

                if ($keyIdx === false || $valueIdx === false) {
                    return ['error' => 'missing key or value column'];
                }

                $versionIdx = $useVersions
                    ? array_search('version', $header, true)
                    : false;

                if ($versionIdx === false) {
                    $versionIdx = null;
                }
            }

            $stFind = $db->prepare(
                'SELECT id, english FROM strings WHERE skey=?'
            );

            $stInsert = $db->prepare(
                'INSERT INTO strings(skey, english, version)
                 VALUES(?,?,?)'
            );

            $stUpdate = $db->prepare(
                'UPDATE strings SET english=? WHERE id=?'
            );

            $stVersion = $db->prepare(
                'UPDATE strings SET version=? WHERE id=?'
            );

            $stTranslation = $db->prepare(
                'INSERT INTO translations(string_id, language_id, value)
                 VALUES(?,?,?)
                 ON CONFLICT(string_id, language_id)
                 DO UPDATE SET value=excluded.value'
            );

            $stFuzzy = $db->prepare(
                'UPDATE translations SET fuzzy=1 WHERE string_id=?'
            );

            // Import the entire file as one transaction.
            $db->beginTransaction();

            while (($row = fgetcsv(
                $fp, 0, $delimiter, '"', ''
            )) !== false) {

                if (!isset($row[$keyIdx], $row[$valueIdx])) {
                    $skipped++;
                    continue;
                }

                $key = trim($row[$keyIdx]);
                $val = $row[$valueIdx];

                if ($key === '') {
                    $skipped++;
                    continue;
                }

                $ver = null;

                if ($versionIdx !== null &&
                    isset($row[$versionIdx])) {

                    $ver = trim($row[$versionIdx]);

                    if ($ver === '') {
                        $ver = null;
                    }
                }

                $stFind->execute([$key]);
                $existing = $stFind->fetch(PDO::FETCH_ASSOC);
                $stFind->closeCursor();

                if ($isBase) {

                    if (!$existing) {
                        // Create new source string.
                        $stInsert->execute([
                            $key,
                            $val,
                            $ver ?? '1.0'
                        ]);

                    } else {
                        $sid = $existing['id'];

                        // Update source and mark translations fuzzy
                        // only if the source value actually changed.
                        if ($existing['english'] !== $val) {
                            $stUpdate->execute([$val, $sid]);
                            $stFuzzy->execute([$sid]);
                        }

                        if ($ver !== null) {
                            $stVersion->execute([$ver, $sid]);
                        }
                    }

                    $imported++;

                } else {

                    // Translated files cannot create source keys.
                    if (!$existing) {
                        $skipped++;
                        continue;
                    }

                    $stTranslation->execute([
                        $existing['id'],
                        $lid,
                        $val
                    ]);

                    $imported++;
                }
            }

            $db->commit();

            return [
                'success' => 1,
                'imported' => $imported,
                'skipped' => $skipped
            ];

        } catch (Throwable $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log('CSVSingle import: '.$e->getMessage());

            return ['error' => 'CSV import failed'];

        } finally {
            fclose($fp);
        }
    }


    public function export(string $lang, PDO $db): string {

        $config = loadConfig();
        $baseLang = $config['base_language'] ?? 'en';
        $useVersions = !empty($config['use_versions']);

        $csvConf = $config['config_csvsingle'] ?? [];
        $delimiter = $csvConf['delimiter'] ?? ',';
        $headers = $csvConf['headers'] ?? false;
        $keyColumn = $csvConf['key_column'] ?? 'key';
        $valueColumn = $csvConf['value_column'] ?? 'value';
        $fallback = $csvConf['fallback_to_base'] ?? true;

        if (strlen($delimiter) !== 1) {
            throw new InvalidArgumentException('Invalid delimiter');
        }

        $st = $db->prepare('SELECT id FROM languages WHERE code=?');
        $st->execute([$lang]);
        $lid = $st->fetchColumn();

        if (!$lid) {
            throw new InvalidArgumentException('Invalid language');
        }

        $fp = fopen('php://temp', 'w+');

        if (!$fp) {
            throw new RuntimeException('Cannot open CSV stream');
        }

        try {

            if ($headers) {
                $header = [$keyColumn, $valueColumn];

                if ($useVersions) {
                    $header[] = 'version';
                }

                fputcsv($fp, $header, $delimiter, '"', '');
            }

            $st = $db->prepare(
                'SELECT s.skey, s.english, s.version,
                        t.value AS translation
                 FROM strings s
                 LEFT JOIN translations t
                    ON t.string_id = s.id
                    AND t.language_id = ?
                 ORDER BY s.id'
            );

            $st->execute([$lid]);

            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {

                if ($lang === $baseLang) {
                    $val = $row['english'];

                } else {
                    $val = $row['translation'];

                    if ($val === null || $val === '') {
                        $val = $fallback ? $row['english'] : '';
                    }
                }

                $line = [$row['skey'], $val];

                if ($useVersions) {
                    $line[] = $row['version'] ?? '1.0';
                }

                fputcsv($fp, $line, $delimiter, '"', '');
            }

            rewind($fp);

            return stream_get_contents($fp);

        } finally {
            fclose($fp);
        }
    }


    public static function mimeType(): string {
        return 'text/csv';
    }

    public static function fileExtension(): string {
        return 'csv';
    }

    public static function name(): string {
        return 'CSV (single language)';
    }

    public static function menu(): array {
        return [
            ["text", "Separate CSV file for each language"],
            ["by_lang_import_export", "import", "export"],
            ["zip", "export all in zip"]
        ];
    }
}