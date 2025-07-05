<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    $db = new PDO('sqlite:translations.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $user = trim($_POST['user'] ?? '');
    $pass = trim($_POST['pass'] ?? '');

    $st = $db->prepare('SELECT id,username,password,role FROM users WHERE username=?');
    $st->execute([$user]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if ($row && password_verify($pass, $row['password'])) {
        $_SESSION['uid'] = $row['id'];
        $_SESSION['user'] = $row['username'];
        $_SESSION['role'] = $row['role'];
        header('Location: index.php');
        exit;
    }
    $error = 'Invalid login';
}
?>
<!doctype html>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" type="image/png" href="images/logo.png">
<title>Babylon</title>
<link rel="preload" href="fonts/pixelcode.woff" as="font" type="font/woff" crossOrigin="anonymous">
<link rel="stylesheet" href="style/login.css?1">
</head>
<body>
    <form method="post">
        <img id="logo" src="images/logo.png"/>
        <br/>
      <input id="username" name=user  placeholder=USERNAME required><br>
      <input id="password" name=pass  type=password placeholder=PASSWORD required><br>
      <button id="login" name=login>ENTER BABYLON</button>
    </form>
    <?php if (!empty($error)): ?>
    <p id="error"><?=htmlspecialchars($error)?></p>
    <?php endif; ?>
    </body>
</html>
