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
    <link rel="stylesheet" href="style/style.css?1">
</head>
<body id="body_public">
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
            <div id="rulesSidebar" hidden>
                <h3>Guidelines</h3>
                <ul id="rulesListSidebar"></ul>
            </div>
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
            <input type="search" id="keySearch"
                placeholder="search by key..."
                autocomplete="off"
                oninput="filterByKey()">
        </div>
        <?php if (is_admin()): ?>
        <div class="float_tool">
            <button type="button" id="newString">
                new
            </button>
        </div>
        <div class="float_tool" id="quickSaveTool" hidden>
            <button type="button" id="quickSave"
                    title="Right-click to change file location">
                save
            </button>
            <button type="button" id="quickSaveAll"
                    title="Right-click to change destination directory">
                save all
            </button>
            <span id="quickSaveStatus"></span>
        </div>
        <?php endif; ?>
        <div class="float_tool">
            <img src="images/loading.gif" id="saving" style="display:none;width: 20px;position: relative;top: 3px;left: 5px;"/>
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

    <?php if (is_admin()): ?>
    <dialog id="newStringDialog">
        <form id="newStringForm">
            <h3>New string</h3>

            <div>
                <label for="newStringKey">Key</label>
                <input type="text"
                    id="newStringKey"
                    autocomplete="off"
                    required>
            </div>

            <div>
                <label for="newStringValue">Base</label>
                <textarea id="newStringValue" rows="4"></textarea>
            </div>

            <div id="newStringVersionBlock">
                <label for="newStringVersion">Version</label>
                <input type="text"
                    id="newStringVersion"
                    value="1.0">
            </div>

            <div id="newStringError" role="alert"></div>

            <div>
                <button type="button" id="newStringCancel">
                    cancel
                </button>
                <button type="submit" id="newStringSubmit">
                    create
                </button>
            </div>
        </form>
    </dialog>
    <?php endif; ?>

    <script src="scripts/script.js?1"></script>
    <script src="scripts/files.js?1"></script>
    <script>const current_user = "<?=htmlspecialchars($_SESSION['user'])?>";</script>
</body>
</html>
