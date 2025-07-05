<?php
require_once __DIR__.'/FormatInterface.php';

class AndroidXML implements FormatInterface {
    public function import(string $filePath, string $lang, PDO $db): array {
        require_once __DIR__.'/../config.php';
        $config = loadConfig();
        $baseLang = $config['base_language'] ?? 'en';

        $xml = simplexml_load_file($filePath);
        if (!$xml) return ['error' => 'bad xml'];

        $st = $db->prepare('SELECT id FROM languages WHERE code=?');
        $st->execute([$lang]);
        $lid = $st->fetchColumn();
        if (!$lid) return ['error'=>'invalid language'];

        if ($lang === $baseLang) {
            $ins = $db->prepare('INSERT OR IGNORE INTO strings(skey,english,version) VALUES(?,?,?)');
            $upd = $db->prepare('UPDATE strings SET english=?,version=? WHERE skey=?');
        } else {
            $selStr = $db->prepare('SELECT id FROM strings WHERE skey=?');
            $insTr  = $db->prepare(
                'INSERT INTO translations(string_id,language_id,value) 
                VALUES(?,?,?) 
                ON CONFLICT(string_id,language_id) DO UPDATE SET value=excluded.value'
            );
        }

        $imported = 0;
        $skipped  = 0;

        $handle = function(string $key, string $val, string $ver) use (
            $lang, $baseLang, $db, $lid,
            &$imported, &$skipped,
            $ins, $upd, $selStr, $insTr
        ) {
            if ($lang === $baseLang) {
                $ins->execute([$key,$val,$ver]);
                $upd->execute([$val,$ver,$key]);
                $imported++;
            } else {
                $selStr->execute([$key]);
                $sid = $selStr->fetchColumn();
                if ($sid) {
                    $insTr->execute([$sid,$lid,$val]);
                    $imported++;
                } else {
                    $skipped++;
                }
            }
        };

        foreach ($xml->string as $s) {
            $key = (string)$s['name'];
            $val = androidUnescape((string)$s);
            $ver = (string)($s['version'] ?: '1.0');
            $handle($key, $val, $ver);
        }

        foreach ($xml->{'string-array'} as $arr) {
            $base = (string)$arr['name'];
            $idx  = 0;
            foreach ($arr->item as $it) {
                $key = "{$base}_{$idx}";
                $val = androidUnescape((string)$it);
                $ver = (string)($it['version'] ?: '1.0');
                $handle($key, $val, $ver);
                $idx++;
            }
        }

        return [
            'success' => 1,
            'imported' => $imported,
            'skipped'  => $skipped
        ];
    }

    public function export(string $lang, PDO $db): string {
        ob_start();
        header('Content-Type: application/xml; charset=utf-8');

        $lid=$db->prepare('SELECT id FROM languages WHERE code=?');
        $lid->execute([$lang]);
        $lid=$lid->fetchColumn();

        $rows=$db->query("SELECT s.skey, s.english, s.version,
            (SELECT value FROM translations WHERE string_id=s.id AND language_id=$lid) AS tr
        FROM strings s")->fetchAll(PDO::FETCH_ASSOC);

        echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<resources>\n";

        $singles = []; $arrays = [];
        foreach($rows as $r){
            $val = $r['tr'] ?: $r['english'];
            if (preg_match('/^(.*)_([0-9]+)$/', $r['skey'], $m)){
                $arrays[$m[1]][(int)$m[2]] = ['text'=>$val, 'version'=>$r['version']];
            } else {
                $singles[$r['skey']] = ['text'=>$val, 'version'=>$r['version']];
            }
        }

        foreach($singles as $k=>$row){
            $verAttr = '';
            if ($config['use_versions'] && !empty($row['version'])) {
                $verAttr = 'version="'.htmlspecialchars($row['version']).'"';
            }
            echo '  <string name="'.htmlspecialchars($k).'" '.$verAttr.'>'
                .androidEscape($row['text'])."</string>\n";
        }
        foreach($arrays as $k=>$items){
            ksort($items);
            echo '  <string-array name="'.htmlspecialchars($k)."\">\n";
            foreach($items as $v){
                $verAttr = '';
                if ($config['use_versions'] && !empty($row['version'])) {
                    $verAttr = 'version="'.htmlspecialchars($row['version']).'"';
                }
                echo '    <item '.$verAttr.'>'
                    .androidEscape($v['text'])."</item>\n";
            }
            echo "  </string-array>\n";
        }

        echo "</resources>";
        return ob_get_clean();
    }

    public static function mimeType(): string { return 'application/xml'; }
    public static function fileExtension(): string { return 'xml'; }
    public static function name(): string { return 'Android XML'; }
    public static function menu(): array {
        return [
            ["by_lang_import_export", "import", "export"],
            ["zip", "export all in zip"]
        ];
    }
}

function androidUnescape(string $s): string {
    $s = str_replace("\\'", "'", $s);
    $s = html_entity_decode($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    return $s;
}

function androidEscape(string $s): string {
    $s = htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    // Fix apostrophes: replace &apos; with \'
    $s = str_replace("&apos;", "\\'", $s);
    return $s;
}