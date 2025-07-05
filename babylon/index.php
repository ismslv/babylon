<?php
session_start();

if (!file_exists('translations.db')) {
    require_once 'init.php';  // this will create & prefill db
}

if (!isset($_SESSION['uid'])) {
    include 'login.php';
    exit;
}

if(isset($_GET['logout'])){ session_destroy(); header('Location: index.php'); exit; }

function logged()      { return isset($_SESSION['uid']); }
function is_admin()    { return ($_SESSION['role'] ?? '')==='admin'; }

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" type="image/png" href="images/logo.png">
    <title>Babylon</title>
    <link rel="preload" href="fonts/pixelcode.woff" as="font" type="font/woff" crossOrigin="anonymous">
    <link rel="preload" href="fonts/zpix.woff2" as="font" type="font/woff2" crossOrigin="anonymous">
    <link rel="stylesheet" href="style/style.css">
</head>
<body>
    <div class="logo">
        <img class="logo_img" src="images/logo.png">
        <div class="logo_t1">BABYLON</div>
        <div class="logo_t2">Untitled</div>
    </div>
    <div class="sidebar">
        <div class="sidebar_scroll">
            <h3 id="tr_langs">Languages</h3>
            <ul id="langListSidebar">...</ul>
            <h3 id="tr_translators">Translators</h3>
            <ul id="userListSidebar">...</ul>
        </div>
    </div>

    <div class="header">
        <div class="float_tool">
            <button id="prevUn" type="button" class="button_arrow">←</button>
            <span>untranslated: <span id="unCount">0</span></span>
            <button id="nextUn" type="button" class="button_arrow">→</button>
        </div>
        <div class="float_tool">
            <button id="prevFu" type="button" class="button_arrow">←</button>
            <span>fuzzy: <span id="fuzzyCount">0</span></span>
            <button id="nextFu" type="button" class="button_arrow">→</button>
        </div>
        <div class="float_tool">
            <select id="verFilter" style="margin-left:6px;"></select>
        </div>
        <div class="float_tool">
            <img src="loading.gif" id="saving" style="display:none;width: 20px;position: relative;top: 3px;left: 5px;"/>
        </div>
        <div class="float_tool_right">
            <span id="tr_greeting">hello, <?=htmlspecialchars($_SESSION['user'])?>!</span>
            <a href="#" id="pwd">change pwd</a>
            <?php if(is_admin()) echo '<a id="tr_admin" href="admin.php">admin</a>'; ?>
            <a id="tr_exit" href="?logout=1">exit</a>
        </div>
    </div>

    <div class="main">
        <div id="stringsList">...</div>
    </div>
    <script src="scripts/script.js"></script>
    <script>const current_user = "<?=htmlspecialchars($_SESSION['user'])?>";</script>
</body>
</html>