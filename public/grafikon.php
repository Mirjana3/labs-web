<?php
session_start();
require_once '../includes/auth.php';
?>
<!doctype html>
<html lang="hr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Grafikoni" />
    <title>Grafikoni</title>
    <link rel="stylesheet" href="style/style.css" />
    <link rel="stylesheet" href="style/style_grafikon.css" />
  </head>

  <body>
    <header>
      <div class="header-top">
        <div class="menu-wrapper">
          <input type="checkbox" id="menu-toggle" />
          <label for="menu-toggle" class="menu-btn"></label>
          <nav>
            <ul class="nav-menu">
              <li class="dropout">
                <h2 class="nav-toggle" tabindex="0"></h2>
                <ul class="dropdown-content">
                  <li><a href="index.php">Početna</a></li>
                  <li><a href="filmovi.php">🎬 Filmovi (PHP)</a></li>
                  <li><a href="galerija.php">🖼 Galerija (PHP)</a></li>
                  <li><a href="slike.php">Galerija (statički)</a></li>
                  <li><a href="grafikon.php">Grafikoni</a></li>
                  <?php if (isLoggedIn()): ?>
                  <li>
                    <a href="logout.php"
                      >Odjava (<?= htmlspecialchars(currentUsername()) ?>)</a
                    >
                  </li>
                  <?php else: ?>
                  <li><a href="login.php">🔐 Prijava</a></li>
                  <?php endif; ?>
                </ul>
              </li>
            </ul>
          </nav>
        </div>
        <h1>Vizualizacija podataka</h1>
      </div>
    </header>

    <main class="grafikon-container">
      <h2>Distribucija filmova po žanru</h2>
      <div class="pie-chart">
        <div class="tooltip t1">Komedija 30%</div>
        <div class="tooltip t2">Drama 25%</div>
        <div class="tooltip t3">Akcija 15%</div>
        <div class="tooltip t4">Ostalo 30%</div>
      </div>
    </main>

    <footer>
      <p>&copy; 2025. Web Programiranje. Sva prava pridrzana.</p>
    </footer>
  </body>
</html>
