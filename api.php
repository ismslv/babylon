<?php
session_start();

// session check
if (!isset($_SESSION['uid'])) {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$uid = $_SESSION['uid'];
$user = $_SESSION['user'];
$role = $_SESSION['role'];

$db = require 'init.php';

// presence tracking
$db->prepare('INSERT INTO user_presence(user_id,last_seen) VALUES(?,?) 
              ON CONFLICT(user_id) DO UPDATE SET last_seen=?')
   ->execute([$uid, time(), time()]);

// load formats
$formats = [];
foreach (glob(__DIR__.'/formats/*.php') as $file) {
    $cls = basename($file, '.php');
    if ($cls === 'FormatInterface') continue;

    require_once $file;
    if (in_array('FormatInterface', class_implements($cls))) {
        $formats[strtolower($cls)] = new $cls;
    }
}

// helpers
function langsOf(PDO $db, $uid) {
    $st = $db->prepare('SELECT code FROM languages l 
                        JOIN assignments a ON a.language_id=l.id 
                        WHERE a.user_id=?');
    $st->execute([$uid]);
    return $st->fetchAll(PDO::FETCH_COLUMN);
}

$act = $_GET['action'] ?? '';

switch ($act) {
case 'users':
    $rows = $db->query('SELECT id,username,role FROM users')->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) {
        $langs = langsOf($db, $r['id']);
        $last = $db->prepare('SELECT last_seen FROM user_presence WHERE user_id=?');
        $last->execute([$r['id']]);
        $seen = $last->fetchColumn();
        $online = $seen && (time() - $seen) < 60;
        $out[] = [
            'username' => $r['username'],
            'id' => $r['id'],
            'role' => $r['role'],
            'langs' => $langs,
            'online' => $online
        ];
    }
    echo json_encode($out);
    break;

case 'languages':
    $total = max(1, (int)$db->query('SELECT COUNT(*) FROM strings')->fetchColumn());
    $rows = $db->query('SELECT id,code,name FROM languages')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$l) {
        if ($l['code'] == $config['base_language']) {
            $done = $db->query("SELECT COUNT(*) FROM strings WHERE english !=''")->fetchColumn();
        } else {
            $done = $db->query("SELECT COUNT(*) FROM translations WHERE language_id={$l['id']} AND value !=''")->fetchColumn();
        }
        $l['done'] = $done;
        $l['total'] = $total;
        $l['progress'] = 100.0 * $done / $total;
    }
    echo json_encode($rows);
    break;

case 'strings':
    $code = strtolower($_GET['lang'] ?? '');
    $st = $db->prepare('SELECT id FROM languages WHERE code=?');
    $st->execute([$code]);
    $lid = $st->fetchColumn();
    if (!$lid) { echo json_encode(['error'=>'invalid lang']); break; }

    $editable = in_array($code, langsOf($db, $uid));
    if ($code == $config['base_language']) {
        $rows = $db->query('SELECT id,skey `key`,"" english,version,english translation,0 fuzzy 
                            FROM strings')->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $rows = $db->query("SELECT s.id,s.skey `key`,s.english,s.version,
                            t.value translation,IFNULL(t.fuzzy,0) fuzzy
                            FROM strings s 
                            LEFT JOIN translations t ON s.id=t.string_id AND t.language_id=$lid")->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode(['editable'=>$editable,'rows'=>$rows]);
    break;

case 'save_translation':
    $in = json_decode(file_get_contents('php://input'),true);
    $sid = intval($in['string_id'] ?? 0);
    $val = $in['value'] ?? '';
    $code = strtolower($in['lang'] ?? '');
    $st = $db->prepare('SELECT id FROM languages WHERE code=?');
    $st->execute([$code]);
    $lid = $st->fetchColumn();
    if (!$lid || !in_array($code, langsOf($db, $uid))) {
        echo json_encode(['error'=>'not allowed']); exit;
    }
    if ($code == $config['base_language']) {
        $db->prepare('UPDATE strings SET english=? WHERE id=?')->execute([$val,$sid]);
        $db->prepare('UPDATE translations SET fuzzy=1 WHERE string_id=?')->execute([$sid]);
    } else {
        $db->prepare('INSERT INTO translations(string_id,language_id,value) VALUES(?,?,?) 
                     ON CONFLICT(string_id,language_id) DO UPDATE SET value=excluded.value')
            ->execute([$sid,$lid,$val]);
    }
    echo json_encode(['success'=>true]);
    break;

case 'mark_fuzzy':
    $in = json_decode(file_get_contents('php://input'),true);
    $sid = intval($in['string_id'] ?? 0);
    $fuzzy = intval($in['fuzzy'] ?? 0);
    $code = strtolower($in['lang'] ?? '');
    $st = $db->prepare('SELECT id FROM languages WHERE code=?');
    $st->execute([$code]);
    $lid = $st->fetchColumn();
    if (!$lid || !in_array($code, langsOf($db, $uid))) {
        echo json_encode(['error'=>'not allowed']); exit;
    }
    $db->prepare('UPDATE translations SET fuzzy=? WHERE string_id=? AND language_id=?')
       ->execute([$fuzzy,$sid,$lid]);
    echo json_encode(['success'=>1]); break;

case 'import':
    $fmt = strtolower($_POST['format']);
    $lang = strtolower($_POST['lang'] ?? '');
    if (!isset($formats[$fmt])) {
        echo json_encode(['error'=>'format']);
        break;
    }
    $res = $formats[$fmt]->import($_FILES['file']['tmp_name'],$lang,$db);
    echo json_encode($res);
    break;

case 'export':
    $fmt  = strtolower($_GET['format'] ?? '');
    $lang = strtolower($_GET['lang'] ?? '');
    if (!isset($formats[$fmt])) {
        echo json_encode(['error'=>'invalid format']); exit;
    }

    if ($lang != '') {
        $st = $db->prepare('SELECT id FROM languages WHERE code=?');
        $st->execute([$lang]);
        $lid = $st->fetchColumn();
        if (!$lid) {
            echo json_encode(['error'=>'invalid language']); exit;
        }
    }

    header('Content-Type: '.$formats[$fmt]::mimeType());
    header('Content-Disposition: attachment; filename="'.$lang.'.'.$formats[$fmt]::fileExtension().'"');
    echo $formats[$fmt]->export($lang, $db);
    exit;

case 'export_zip':
    $fmt  = strtolower($_GET['format'] ?? '');
    if (!isset($formats[$fmt])) {
        echo json_encode(['error'=>'invalid format']); exit;
    }

    $config = loadConfig();
    $project = preg_replace('/\W+/','_', $config['project_name'] ?? 'project');
    $date = date('Ymd');
    $zipName = "{$project}_{$date}.zip";

    $tmpZip = tempnam(sys_get_temp_dir(), 'babylon_zip');
    $zip = new ZipArchive();
    if ($zip->open($tmpZip, ZipArchive::OVERWRITE)!==TRUE) {
        echo json_encode(['error'=>'zip failed']);
        exit;
    }

    $langs = $db->query('SELECT code FROM languages')->fetchAll(PDO::FETCH_COLUMN);

    foreach ($langs as $lang) {
        $content = $formats[$fmt]->export($lang, $db);
        $ext = $formats[$fmt]::fileExtension();
        $zip->addFromString("{$lang}.{$ext}", $content);
    }

    $zip->close();

    header('Content-Type: application/zip');
    header("Content-Disposition: attachment; filename=\"$zipName\"");
    readfile($tmpZip);
    unlink($tmpZip);
    exit;

case 'config':
    echo json_encode($config);
    break;

case 'user_rename':
  $uid = intval($_POST['id'] ?? 0);
  $name = trim($_POST['name'] ?? '');
  $role = ($_POST['role'] === 'admin') ? 'admin' : 'translator';
  $langs = $_POST['langs'] ?? [];
  if (!is_array($langs)) $langs = [$langs];
  if ($uid && $name) {
      $db->prepare('UPDATE users SET username=?,role=? WHERE id=?')->execute([$name,$role,$uid]);
      $db->prepare('DELETE FROM assignments WHERE user_id=?')->execute([$uid]);
      $st = $db->prepare('SELECT id FROM languages WHERE code=?');
      $ins = $db->prepare('INSERT INTO assignments(user_id,language_id) VALUES(?,?)');
      foreach ($langs as $code) {
          $st->execute([$code]);
          if ($lid = $st->fetchColumn()) {
              $ins->execute([$uid, $lid]);
          }
      }
      echo json_encode(['success'=>1]);
      exit;
  }
  echo json_encode(['error'=>'bad input']);
  exit;

case 'user_delete':
  $uid = intval($_POST['id'] ?? 0);
  if ($uid) {
      $db->prepare('DELETE FROM users WHERE id=?')->execute([$uid]);
      $db->prepare('DELETE FROM assignments WHERE user_id=?')->execute([$uid]);
      $db->prepare('DELETE FROM user_presence WHERE user_id=?')->execute([$uid]);
      echo json_encode(['success'=>1]);
      exit;
  }
  echo json_encode(['error'=>'bad input']);
  exit;

case 'user_setrole':
  $uid = intval($_POST['id'] ?? 0);
  $role = ($_POST['role'] === 'admin') ? 'admin' : 'translator';
  if ($uid) {
      $db->prepare('UPDATE users SET role=? WHERE id=?')->execute([$role, $uid]);
      echo json_encode(['success'=>1]);
      exit;
  }
  echo json_encode(['error'=>'bad input']);
  exit;

case 'user_setlangs':
  $uid = intval($_POST['id'] ?? 0);
  $langs = $_POST['langs'] ?? [];
  if (!is_array($langs)) $langs = [$langs];
  if ($uid) {
      $db->prepare('DELETE FROM assignments WHERE user_id=?')->execute([$uid]);
      $st = $db->prepare('SELECT id FROM languages WHERE code=?');
      $ins = $db->prepare('INSERT INTO assignments(user_id,language_id) VALUES(?,?)');
      foreach ($langs as $code) {
          $st->execute([$code]);
          if ($lid = $st->fetchColumn()) {
              $ins->execute([$uid, $lid]);
          }
      }
      echo json_encode(['success'=>1]);
      exit;
  }
  echo json_encode(['error'=>'bad input']);
  exit;

case 'lang_rename':
  $lid = intval($_POST['id'] ?? 0);
  $code = strtolower(trim($_POST['code'] ?? ''));
  $name = trim($_POST['name'] ?? '');

  if (!$code) {
      echo json_encode(['error'=>'missing code']); exit;
  }
  if ($lid && $code) {
      $db->prepare('UPDATE languages SET code=?,name=? WHERE id=?')->execute([$code,$name,$lid]);
      echo json_encode(['success'=>1]);
      exit;
  }
  echo json_encode(['error'=>'bad input']);
  exit;

case 'string_delete':
  $sid = intval($_POST['id'] ?? 0);
  if ($sid) {
      $db->prepare('DELETE FROM translations WHERE string_id=?')->execute([$sid]);
      $db->prepare('DELETE FROM strings WHERE id=?')->execute([$sid]);
      echo json_encode(['success'=>1]);
      exit;
  }
  echo json_encode(['error'=>'bad input']);
  exit;

case 'formats':
  $out = [];
  foreach ($formats as $k => $f) {
    $out[] = [
      'id' => $k,
      'name' => $f::name(),
      'extension' => $f::fileExtension()
    ];
  }
  echo json_encode($out);
  break;

case 'add_user':
  $name = trim($_POST['name'] ?? '');
  $role = ($_POST['role'] === 'admin') ? 'admin' : 'translator';
  $langs = $_POST['langs'] ?? [];
  if (!is_array($langs)) $langs = [$langs];
  if ($name) {
      $pass = password_hash($name, PASSWORD_DEFAULT);
      $db->prepare('INSERT INTO users(username,password,role) VALUES(?,?,?)')
         ->execute([$name, $pass, $role]);
      $uid = $db->lastInsertId();
      $st = $db->prepare('SELECT id FROM languages WHERE code=?');
      $ins = $db->prepare('INSERT INTO assignments(user_id,language_id) VALUES(?,?)');
      foreach ($langs as $code) {
          $st->execute([$code]);
          if ($lid = $st->fetchColumn()) {
              $ins->execute([$uid, $lid]);
          }
      }
      echo json_encode(['success'=>1]);
      exit;
  }
  echo json_encode(['error'=>'bad input']);
  exit;

case 'add_language':
  $code = strtolower(trim($_POST['code'] ?? ''));
  $name = trim($_POST['name'] ?? '');

  if (!$code) {
      echo json_encode(['error'=>'missing code']); exit;
  }

  // fallback to language map
  $langMap = require 'languages.php';
  if (!$name) {
      $name = $langMap[$code] ?? $code;
  }

  $st = $db->prepare('INSERT INTO languages(code,name) VALUES(?,?)');
  try {
      $st->execute([$code, $name]);
      echo json_encode(['success'=>1]);
  } catch (PDOException $e) {
      echo json_encode(['error'=>'language exists']);
  }
  exit;

case 'lang_delete':
  $lid = intval($_POST['id'] ?? 0);
  if (!$lid) {
      echo json_encode(['error'=>'missing id']);
      exit;
  }

  $db->prepare('DELETE FROM translations WHERE language_id=?')->execute([$lid]);
  $db->prepare('DELETE FROM assignments WHERE language_id=?')->execute([$lid]);
  $db->prepare('DELETE FROM languages WHERE id=?')->execute([$lid]);
  echo json_encode(['success'=>1]);
  exit;

case 'format_menu':
    $fmt  = strtolower($_POST['format'] ?? '');
    if (!isset($formats[$fmt])) {
        echo json_encode(['error'=>'invalid format']);
        exit;
    }
    echo json_encode($formats[$fmt]->menu());
    exit;

default:
    echo json_encode(['error'=>'bad request']);
}