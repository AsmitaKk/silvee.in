<?php
require_once "config.php";
require_once "functions.php";

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $identifier = $_POST["identifier"] ?? "";
    $password = $_POST["password"] ?? "";

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? OR name=?");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    if ($user) {
        $valid_password = false;
        $stored_hash = $user['password_hash'] ?? null;
        $legacy_hash = $user['password'] ?? null;

        if ($stored_hash && password_verify($password, $stored_hash)) {
            $valid_password = true;
        } elseif ($stored_hash && hash("sha256", $password) === $stored_hash) {
            $valid_password = true;
        } elseif ($legacy_hash && hash("sha256", $password) === $legacy_hash) {
            $valid_password = true;
        } elseif ($legacy_hash && password_verify($password, $legacy_hash)) {
            $valid_password = true;
        }

        if ($valid_password) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            session_regenerate_id(true);

            if (empty($stored_hash) && !empty($legacy_hash)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newHash, $user['id']]);
            }

            if (!empty($_POST['remember_me'])) {
                $remember_payload = $user['id'] . ':' . hash_hmac('sha256', $user['id'] . ':' . $user['email'] . ':' . $user['password_hash'], 'silvee-admin-session');
                setcookie('remember_admin', base64_encode($remember_payload), time() + (86400 * 30), '/', '', false, true);
            } else {
                setcookie('remember_admin', '', time() - 3600, '/');
            }

            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid login details.";
        }
    } else {
        $error = "Invalid login details.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">

<div class="container d-flex justify-content-center align-items-center" style="height:100vh;">
    <div class="card shadow" style="width:350px;">
        <div class="card-body">
            <h3 class="text-center mb-3">Login</h3>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="text" name="identifier" class="form-control mb-3" placeholder="Email or Username" required>
                <div class="input-group mb-3">
                    <input type="password" name="password" id="loginPassword" class="form-control" placeholder="Password" required>
                    <button class="btn btn-outline-secondary" type="button" onclick="let inp = document.getElementById('loginPassword'); if(inp.type === 'password') { inp.type = 'text'; this.innerHTML = '<i class=\'bi bi-eye-slash\'></i>'; } else { inp.type = 'password'; this.innerHTML = '<i class=\'bi bi-eye\'></i>'; }">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="remember_me" id="rememberMe" checked>
                    <label class="form-check-label" for="rememberMe">Keep me logged in</label>
                </div>
                <button class="btn btn-primary w-100">Login</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
