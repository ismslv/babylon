<?php
require_once 'config.php';
$config = loadConfig();
$languageMap = require 'languages.php';

$db = new PDO('sqlite:translations.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA busy_timeout=3000');

// schema
$db->exec(<<<SQL
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY,
    username TEXT UNIQUE,
    password TEXT,
    role TEXT
);
CREATE TABLE IF NOT EXISTS languages (
    id INTEGER PRIMARY KEY,
    code TEXT UNIQUE,
    name TEXT
);
CREATE TABLE IF NOT EXISTS assignments (
    user_id INTEGER,
    language_id INTEGER
);
CREATE TABLE IF NOT EXISTS strings (
    id INTEGER PRIMARY KEY,
    skey TEXT UNIQUE,
    english TEXT,
    version TEXT
);
CREATE TABLE IF NOT EXISTS translations (
    id INTEGER PRIMARY KEY,
    string_id INTEGER,
    language_id INTEGER,
    value TEXT,
    fuzzy INTEGER DEFAULT 0,
    UNIQUE(string_id, language_id)
);
CREATE TABLE IF NOT EXISTS user_presence (
    user_id INTEGER PRIMARY KEY,
    last_seen INTEGER
);
SQL
);

// pre-fill languages if empty
if (!$db->query('SELECT COUNT(*) FROM languages')->fetchColumn()) {
    $insL = $db->prepare('INSERT INTO languages(code,name) VALUES(?,?)');
    foreach ($config['languages'] as $l) {
        $code = strtolower($l['code']);
        $name = $l['name'] ?: ($languageMap[$code] ?? $code);
        $insL->execute([$code, $name]);
    }
}

// pre-fill users if empty
if (!$db->query('SELECT COUNT(*) FROM users')->fetchColumn()) {
    $insU = $db->prepare('INSERT INTO users(username,password,role) VALUES(?,?,?)');
    $insA = $db->prepare('INSERT INTO assignments(user_id,language_id) VALUES(?,?)');

    foreach ($config['users'] as $u) {
        $uname = $u['name'];
        $pass = password_hash($uname, PASSWORD_DEFAULT);
        $role = !empty($u['is_admin']) ? 'admin' : 'translator';
        $insU->execute([$uname, $pass, $role]);
        $uid = $db->lastInsertId();

        foreach ($u['langs'] as $lc) {
            $lid = $db->query("SELECT id FROM languages WHERE code='$lc'")->fetchColumn();
            if ($lid) $insA->execute([$uid, $lid]);
        }
    }

    if (!$db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn()) {
        $pass = password_hash('admin', PASSWORD_DEFAULT);
        $db->prepare('INSERT INTO users(username,password,role) VALUES(?,?,?)')
            ->execute(['admin', $pass, 'admin']);
    }
}

return $db;