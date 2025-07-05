<?php
require_once __DIR__.'/FormatInterface.php';

class PO implements FormatInterface {

    public static function menu(): array {
        return [
            ['text', 'PO/POT (gettext) — English strings are keys'],
            ['by_lang_import_export', 'import', 'export'],
            ['zip', 'export all languages as .po in zip'],
        ];
    }

    public function import(string $filePath, string $lang, PDO $db): array {
        $config = loadConfig();
        $baseLang = $config['base_language'] ?? 'en';

        $useVersions = !empty($config['use_versions']);
        $lines = file($filePath);
        if (!$lines) return ['error' => 'cannot open file'];

        $langID = null;
        if ($lang !== $baseLang) {
            $st = $db->prepare('SELECT id FROM languages WHERE code=?');
            $st->execute([$lang]);
            $langID = $st->fetchColumn();
            if (!$langID) return ['error'=>'invalid language'];
        }

        $stFind = $db->prepare('SELECT id FROM strings WHERE english=?');
        $stUpdStr = $db->prepare('UPDATE strings SET english=?, version=? WHERE id=?');
        $stUpdTr  = $db->prepare('INSERT INTO translations(string_id,language_id,value)
                                  VALUES(?,?,?)
                                  ON CONFLICT(string_id,language_id) DO UPDATE SET value=excluded.value');

        $msgid = null; $msgstr = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, 'msgid') === 0) {
                $msgid = stripcslashes(trim(substr($line, 6), '"'));
            } elseif (strpos($line, 'msgstr') === 0) {
                $msgstr = stripcslashes(trim(substr($line, 7), '"'));

                if ($msgid !== null) {
                    $stFind->execute([$msgid]);
                    $sid = $stFind->fetchColumn();
                    if ($sid) {
                        if ($lang === $baseLang) {
                            $ver = '1.0';
                            $stUpdStr->execute([$msgid, $ver, $sid]);
                        } else {
                            $stUpdTr->execute([$sid, $langID, $msgstr]);
                        }
                    }
                    $msgid = $msgstr = null;
                }
            }
        }
        return ['success'=>1];
    }

    public function export(string $lang, PDO $db): string {
        $config = loadConfig();
        $baseLang = $config['base_language'] ?? 'en';

        $useVersions = !empty($config['use_versions']);
        $out = '';

        $out .= "msgid \"\"\n";
        $out .= "msgstr \"\"\n";
        $out .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
        $out .= "\"Language: {$lang}\\n\"\n\n";

        if ($lang === $baseLang) {
            $rows = $db->query("SELECT english, english AS tr FROM strings")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $st = $db->prepare("SELECT s.english, t.value AS tr
                FROM strings s
                LEFT JOIN translations t ON s.id=t.string_id AND t.language_id=?
            ");
            $st->execute([$db->query("SELECT id FROM languages WHERE code='$lang'")->fetchColumn()]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        }

        foreach ($rows as $r) {
            $msgid = addslashes($r['english']);
            $msgstr = addslashes($r['tr'] ?? '');
            $out .= "msgid \"{$msgid}\"\n";
            $out .= "msgstr \"{$msgstr}\"\n\n";
        }

        return $out;
    }

    public static function mimeType(): string { return 'text/x-gettext-translation'; }
    public static function fileExtension(): string { return 'po'; }
    public static function name(): string { return 'PO/POT'; }
}