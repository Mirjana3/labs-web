<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$mode = $_GET['mode'] ?? 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? 'login';

    if ($mode === 'register') {
        $ime = trim($_POST['korisnicko_ime'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $loz = $_POST['lozinka'] ?? '';
        $loz2 = $_POST['lozinka2'] ?? '';

        if (strlen($ime) < 3) {
            $error = 'Korisničko ime mora imati najmanje 3 znaka.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Neispravna e-mail adresa.';
        } elseif (strlen($loz) < 6) {
            $error = 'Lozinka mora imati najmanje 6 znakova.';
        } elseif ($loz !== $loz2) {
            $error = 'Lozinke se ne podudaraju.';
        } else {
            $stmt = $conn->prepare("SELECT id FROM korisnici WHERE korisnicko_ime=? OR email=?");
            $stmt->bind_param('ss', $ime, $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $error = 'Korisničko ime ili e-mail već postoji.';
            } else {
                $hash = password_hash($loz, PASSWORD_BCRYPT);
                $ins = $conn->prepare("INSERT INTO korisnici (korisnicko_ime, email, lozinka) VALUES (?,?,?)");
                $ins->bind_param('sss', $ime, $email, $hash);
                $ins->execute();
                $success = 'Registracija uspješna! Možete se prijaviti.';
                $mode = 'login';
            }
        }
    } else {
        $ime = trim($_POST['korisnicko_ime'] ?? '');
        $loz = $_POST['lozinka'] ?? '';

        if (empty($ime) || empty($loz)) {
            $error = 'Unesite korisničko ime i lozinku.';
        } else {
            $stmt = $conn->prepare("SELECT id, korisnicko_ime, lozinka, uloga FROM korisnici WHERE korisnicko_ime=?");
            $stmt->bind_param('s', $ime);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if ($user && password_verify($loz, $user['lozinka'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['korisnicko_ime'];
                $_SESSION['role'] = $user['uloga'];
                header('Location: ' . ($_GET['redirect'] ?? 'index.php'));
                exit;
            } else {
                $error = 'Pogrešno korisničko ime ili lozinka.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prijava – Videoteka</title>
    <link rel="stylesheet" href="style/style.css">
    <link rel="stylesheet" href="style/style_login.css">
</head>

<body>
    <header>
        <div class="header-top">
            <div class="menu-wrapper">
                <input type="checkbox" id="menu-toggle">
                <label for="menu-toggle" class="menu-btn"></label>
                <nav>
                    <ul class="nav-menu">
                        <li class="dropout">
                            <h2 class="nav-toggle" tabindex="0"></h2>
                            <ul class="dropdown-content">
                                <li><a href="index.php">Početna</a></li>
                                <li><a href="filmovi.php">Filmovi</a></li>
                                <li><a href="galerija.php">Galerija</a></li>
                                <li><a href="grafikon.php">Grafikoni</a></li>
                            </ul>
                        </li>
                    </ul>
                </nav>
            </div>
            <h1>Videoteka – Prijava</h1>
        </div>
    </header>
    <div class="auth-wrapper">
        <div class="auth-box">
            <div class="auth-tabs">
                <a href="login.php?mode=login" class="<?= $mode === 'login' ? 'active' : '' ?>">Prijava</a>
                <a href="login.php?mode=register" class="<?= $mode === 'register' ? 'active' : '' ?>">Registracija</a>
            </div>

            <?php if ($error): ?>
                <div class="alert-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?>
                <div class="alert-ok"><?= htmlspecialchars($success) ?></div><?php endif; ?>

            <?php if ($mode === 'login'): ?>
                <form method="POST" action="login.php">
                    <input type="hidden" name="mode" value="login">
                    <div class="form-group">
                        <label>Korisničko ime</label>
                        <input type="text" name="korisnicko_ime" required autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label>Lozinka</label>
                        <input type="password" name="lozinka" required>
                    </div>
                    <button type="submit" class="btn-auth">Prijavi se</button>
                </form>
                <div class="back-link"><small>Admin: <strong>admin</strong> / <strong>password</strong></small></div>
            <?php else: ?>
                <form method="POST" action="login.php">
                    <input type="hidden" name="mode" value="register">
                    <div class="form-group">
                        <label>Korisničko ime</label>
                        <input type="text" name="korisnicko_ime" required minlength="3">
                    </div>
                    <div class="form-group">
                        <label>E-mail</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Lozinka (min. 6 znakova)</label>
                        <input type="password" name="lozinka" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Potvrdi lozinku</label>
                        <input type="password" name="lozinka2" required>
                    </div>
                    <button type="submit" class="btn-auth">Registriraj se</button>
                </form>
            <?php endif; ?>
            <div class="back-link"><a href="index.php">← Natrag na početnu</a></div>
        </div>
    </div>
    <footer>
        <p>&copy; 2025. Web Programiranje. Sva prava pridržana.</p>
    </footer>
</body>

</html>