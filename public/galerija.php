<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

// ocijeni sliku
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_ocjena'])) {
    header('Content-Type: application/json');
    requireLogin();
    $slika_id = intval($_POST['slika_id'] ?? 0);
    $ocjena = intval($_POST['ocjena'] ?? 0);
    $user_id = currentUserId();

    if ($slika_id < 1 || $ocjena < 1 || $ocjena > 5) {
        echo json_encode(['ok' => false]);
        exit;
    }
    $stmt = $conn->prepare("INSERT INTO ocjene (id_korisnik, id_slika, ocjena) VALUES (?,?,?) ON DUPLICATE KEY UPDATE ocjena=VALUES(ocjena), vrijeme_ocjene=NOW()");
    $stmt->bind_param('iii', $user_id, $slika_id, $ocjena);
    $stmt->execute();

    $avg = $conn->prepare("SELECT ROUND(AVG(ocjena),1) as avg, COUNT(*) as cnt FROM ocjene WHERE id_slika=?");
    $avg->bind_param('i', $slika_id);
    $avg->execute();
    $row = $avg->get_result()->fetch_assoc();
    echo json_encode(['ok' => true, 'avg' => $row['avg'], 'cnt' => $row['cnt']]);
    exit;
}

// Admin - brisanje slike
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_sliku'])) {
    requireAdmin();
    $sid = intval($_POST['delete_sliku']);
    $del = $conn->prepare("DELETE FROM slike WHERE id=?");
    $del->bind_param('i', $sid);
    $del->execute();
    header('Location: galerija.php');
    exit;
}

// Admin - upload slike
$uploadMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_sliku'])) {
    requireAdmin();
    if (!empty($_FILES['slika']['name'])) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($_FILES['slika']['type'], $allowed)) {
            $uploadMsg = 'error:Samo JPEG, PNG, WebP.';
        } elseif ($_FILES['slika']['size'] > 5 * 1024 * 1024) {
            $uploadMsg = 'error:Slika ne smije biti veća od 5MB.';
        } else {
            $ext = strtolower(pathinfo($_FILES['slika']['name'], PATHINFO_EXTENSION));
            $fname = uniqid('img_') . '.' . $ext;
            $dest = __DIR__ . '/images/' . $fname;
            if (move_uploaded_file($_FILES['slika']['tmp_name'], $dest)) {
                $opis = trim($_POST['opis'] ?? '');
                $uid = currentUserId();
                $put = 'images/' . $fname;
                $ins = $conn->prepare("INSERT INTO slike (naziv_datoteke,opis,putanja,izvor,added_by) VALUES (?,?,?,'lokalno',?)");
                $ins->bind_param('sssi', $fname, $opis, $put, $uid);
                $ins->execute();
                $uploadMsg = 'ok:Slika dodana!';
            } else {
                $uploadMsg = 'error:Greška pri pohrani.';
            }
        }
    }
}

// Dohvati slike s prosjekom 
$uid = isLoggedIn() ? (int) currentUserId() : 0;
$sql = "SELECT s.id, s.naziv_datoteke, s.opis, s.putanja,
               ROUND(AVG(o.ocjena),1) AS avg_ocjena,
               COUNT(o.id) AS broj_ocjena"
    . ($uid ? ", MAX(CASE WHEN o.id_korisnik=$uid THEN o.ocjena END) AS moja_ocjena" : ", NULL AS moja_ocjena")
    . " FROM slike s LEFT JOIN ocjene o ON s.id=o.id_slika GROUP BY s.id ORDER BY s.id";
$slike = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="hr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galerija – Videoteka</title>
    <link rel="stylesheet" href="style/style.css">
    <link rel="stylesheet" href="style/style_slike.css">
    <link rel="stylesheet" href="style/style_galerija.css">
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
                                <li><a href="filmovi.php">🎬 Filmovi (PHP)</a></li>
                                <li><a href="galerija.php">🖼 Galerija (PHP)</a></li>
                                <li><a href="slike.php">Galerija (statički)</a></li>
                                <li><a href="grafikon.php">Grafikoni</a></li>
                                <?php if (isLoggedIn()): ?>
                                    <li><a href="logout.php">Odjava (<?= htmlspecialchars(currentUsername()) ?>)</a></li>
                                <?php else: ?>
                                    <li><a href="login.php">Prijava</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    </ul>
                </nav>
            </div>
            <h1>🖼 Galerija s ocjenama</h1>
        </div>
    </header>

    <div class="page-wrap">
        <?php if (isAdmin()): ?>
            <div class="section-box">
                <h2>⬆ Admin – Dodaj sliku</h2>
                <?php if ($uploadMsg): ?>
                    <div class="<?= str_starts_with($uploadMsg, 'ok') ? 'msg-ok' : 'msg-err' ?>">
                        <?= htmlspecialchars(substr($uploadMsg, strpos($uploadMsg, ':') + 1)) ?></div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data"
                    style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
                    <input type="hidden" name="upload_sliku" value="1">
                    <div><label
                            style="font-size:.78rem;font-weight:700;text-transform:uppercase;display:block;margin-bottom:4px">Slika
                            (max 5MB, JPEG/PNG/WebP)</label>
                        <input type="file" name="slika" accept="image/jpeg,image/png,image/webp" required
                            style="padding:7px;border:1px solid #ccc;border-radius:6px">
                    </div>
                    <div><label
                            style="font-size:.78rem;font-weight:700;text-transform:uppercase;display:block;margin-bottom:4px">Opis</label>
                        <input type="text" name="opis" placeholder="Opis slike"
                            style="padding:8px;border:1px solid #ccc;border-radius:6px">
                    </div>
                    <button type="submit" class="btn-glavni">⬆ Uploadaj</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="section-box">
            <h2>📸 Filmske fotografije – ocijeni od 1 do 5 ⭐</h2>
            <?php if (!isLoggedIn()): ?>
                <div
                    style="background:#e8f4fd;border:1px solid #3498db;border-radius:8px;padding:12px;color:#2471a3;margin-bottom:16px">
                    <a href="login.php" style="color:#e94560;font-weight:600">Prijavite se</a> za ocjenjivanje slika.
                </div>
            <?php endif; ?>
            <div class="rating-gallery">
                <?php foreach ($slike as $s): ?>
                    <div class="rating-card">
                        <?php if (isAdmin()): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="delete_sliku" value="<?= $s['id'] ?>">
                                <button type="submit" class="admin-del" onclick="return confirm('Obrisati?')">🗑</button>
                            </form>
                        <?php endif; ?>
                        <img src="<?= htmlspecialchars($s['putanja']) ?>"
                            alt="<?= htmlspecialchars($s['opis'] ?? $s['naziv_datoteke']) ?>" class="thumb" loading="lazy"
                            onclick="openLB('<?= htmlspecialchars($s['putanja']) ?>')">
                        <div class="card-body">
                            <div class="title"><?= htmlspecialchars($s['opis'] ?? $s['naziv_datoteke']) ?></div>
                            <div class="stars" id="stars-<?= $s['id'] ?>" data-id="<?= $s['id'] ?>"
                                data-moja="<?= $s['moja_ocjena'] ?? 0 ?>">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span
                                        class="star <?= ($s['moja_ocjena'] >= $i) ? 'filled' : '' ?> <?= !isLoggedIn() ? 'disabled' : '' ?>"
                                        data-val="<?= $i ?>" <?php if (isLoggedIn()): ?>
                                            onmouseover="prevStar(<?= $s['id'] ?>,<?= $i ?>)"
                                            onmouseout="resetStar(<?= $s['id'] ?>)" onclick="ocijeni(<?= $s['id'] ?>,<?= $i ?>)"
                                        <?php endif; ?>>★</span>
                                <?php endfor; ?>
                                <span class="avg-info" id="avg-<?= $s['id'] ?>">
                                    <?= $s['avg_ocjena'] ? $s['avg_ocjena'] . ' (' . $s['broj_ocjena'] . ' gl.)' : 'Nema ocjena' ?>
                                </span>
                            </div>
                            <?php if (!isLoggedIn()): ?>
                                <div style="font-size:.78rem;color:#aaa">Prijavi se za ocjenu</div><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Statička galerija iz LV1 -->
        <div class="section-box">
            <h2>🎞 Statička galerija (LV1/LV2)</h2>
            <section class="galerija">
                <div class="img-gallery-magnific">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <figure class="galerija_slika">
                            <a href="#img<?= $i ?>"><img src="https://unsplash.it/300/200?random=<?= $i ?>" alt="Slika <?= $i ?>"
                                    loading="lazy"></a>
                            <figcaption>Slika <?= $i ?></figcaption>
                        </figure>
                        <div id="img<?= $i ?>" class="lightbox">
                            <a href="#" class="close">&times;</a>
                            <img src="https://unsplash.it/900/?random=<?= $i ?>" alt="Slika <?= $i ?>">
                        </div>
                    <?php endfor; ?>
                </div>
            </section>
        </div>
    </div>

    <div class="lb-overlay" id="lb" onclick="closeLB()">
        <span class="lb-close">×</span>
        <img id="lb-img" src="" alt="">
    </div>
    <footer>
        <p>&copy; 2025. Web Programiranje. Sva prava pridržana.</p>
    </footer>

    <script>
        function openLB(src) { document.getElementById('lb-img').src = src; document.getElementById('lb').classList.add('active'); }
        function closeLB() { document.getElementById('lb').classList.remove('active'); }
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLB(); });

        function getStars(id) { return document.querySelectorAll(`#stars-${id} .star`); }
        function prevStar(id, val) { getStars(id).forEach((s, i) => { s.classList.toggle('preview', i < val); s.classList.remove('filled'); }); }
        function resetStar(id) { const m = parseInt(document.getElementById('stars-' + id).dataset.moja) || 0; getStars(id).forEach((s, i) => { s.classList.remove('preview'); s.classList.toggle('filled', i < m); }); }

        async function ocijeni(slikaId, ocjena) {
            const fd = new FormData();
            fd.append('ajax_ocjena', '1'); fd.append('slika_id', slikaId); fd.append('ocjena', ocjena);
            const data = await fetch('galerija.php', { method: 'POST', body: fd }).then(r => r.json());
            if (data.ok) {
                document.getElementById('stars-' + slikaId).dataset.moja = ocjena;
                getStars(slikaId).forEach((s, i) => { s.classList.toggle('filled', i < ocjena); s.classList.remove('preview'); });
                document.getElementById('avg-' + slikaId).textContent = `${data.avg} (${data.cnt} gl.)`;
            }
        }
    </script>
</body>

</html>