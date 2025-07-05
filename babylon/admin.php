<?php
session_start();
if (!isset($_SESSION['uid']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" type="image/png" href="images/logo.png">
  <title>Babylon • Admin</title>
  <link rel="preload" href="fonts/pixelcode.woff" as="font" type="font/woff" crossOrigin="anonymous">
  <link rel="stylesheet" href="style/style.css">
  <link rel="stylesheet" href="style/admin.css">
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
      <button id="sidebar_button_new_lang">add language</button>

      <h3 id="tr_translators">Translators</h3>
      <ul id="userListSidebar">...</ul>
      <button id="sidebar_button_new_user">add user</button>

      <h3>File formats</h3>
      <ul id="formatListSidebar">...</ul>
    </div>
  </div>

  <div class="header">
    <div id="tool_title" class="float_tool">
      <span id="tool_title_text">editing language</span>
    </div>
    <div id="tool_save" class="float_tool">
      <button id="button_save">save</button>
    </div>
    <div id="tool_delete" class="float_tool">
      <button id="button_delete">delete</button>
    </div>

    <div class="float_tool_right">
      <span  id="tr_greeting">hello, <?=htmlspecialchars($_SESSION['user'])?>!</span>
      <a id="tr_public" href="index.php">main</a>
      <a id="tr_exit" href="logout.php">exit</a>
    </div>
  </div>

  <div class="main">
    <div id="form_user">
      <p><input id="uname" name="uname" placeholder="name"></p>
      <p><select id="urole" name="role">
        <option value="translator">translator</option>
        <option value="admin">admin</option>
      </select></p>
      <div id="ulangs"></div>
    </div>

    <div id="form_language">
      <p><input id="lcode" name="lcode" placeholder="code (e.g. en)"></p>
      <p><input id="lname" name="lname" placeholder="name (optional)"></p>
    </div>

    <div id="form_format">
      <p><button id="f_import_all">import all</button></p>
      <p><button id="f_export_all">save all</button></p>
      <div id="flangs"></div>
    </div>

    <input id="file_import" type="file">
  </div>

  <script src="scripts/admin.js"></script>
  <script>const current_user = "<?=htmlspecialchars($_SESSION['user'])?>";</script>
</body>
</html>