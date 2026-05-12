<?php
session_start(); 
require_once '../includes/db.php';   
require_once '../includes/auth.php'; 

// DODAJ ILI UKLONI FILM IZ VIDEOTEKE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json'); 
    requireLogin(); // Ako korisnik nije prijavljen, zaustavi izvođenje

    $action = $_POST['action'] ?? ''; 
    $film_id = intval($_POST['film_id'] ?? 0); 
    $user_id = currentUserId(); 

    if ($action === 'dodaj') {
        // Provjera ocjene filma – ako je ispod 5.0, upozorit ćemo korisnika na frontendu
        $stmt = $conn->prepare("SELECT ocjena FROM filmovi WHERE id=?");
        $stmt->bind_param('i', $film_id); // 'i' = integer tip parametra
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $upozorenje = ($row && $row['ocjena'] < 5.0); 

        // INSERT IGNORE – ako zapis već postoji (isti korisnik + isti film), neće baciti grešku
        $ins = $conn->prepare("INSERT IGNORE INTO zeljeni_filmovi (korisnik_id, film_id) VALUES (?,?)");
        $ins->bind_param('ii', $user_id, $film_id);
        $ins->execute();

        echo json_encode(['ok' => true, 'upozorenje' => $upozorenje]);

    } elseif ($action === 'ukloni') {
        // Brišemo samo zapis koji pripada ovom korisniku i ovom filmu
        $del = $conn->prepare("DELETE FROM zeljeni_filmovi WHERE korisnik_id=? AND film_id=?");
        $del->bind_param('ii', $user_id, $film_id);
        $del->execute();
        echo json_encode(['ok' => true]);

    } else {
        echo json_encode(['ok' => false]); 
    }
    exit; 
}

// DOHVAĆANJE FILMOVA S FILTRIMA I SORTIRANJEM
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax_filmovi'])) {
    header('Content-Type: application/json');

    $zanr = trim($_GET['zanr'] ?? '');
    $godinaOd = intval($_GET['godinaOd'] ?? 0);
    $minOcj = floatval($_GET['ocjena'] ?? 0);

    $sort = in_array($_GET['sort'] ?? '', ['naslov', 'godina', 'ocjena', 'trajanje'])
        ? $_GET['sort'] : 'naslov';

    $dir = ($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

    // Dinamično gradimo WHERE uvjete ovisno o tome što je korisnik filtrirao
    $where = [];
    $params = [];
    $types = '';

    if ($zanr) {
        $where[] = 'zanr LIKE ?';    
        $params[] = "%$zanr%";
        $types .= 's';              
    }
    if ($godinaOd) {
        $where[] = 'godina >= ?';    
        $params[] = $godinaOd;
        $types .= 'i';
    }
    if ($minOcj > 0) {
        $where[] = 'ocjena >= ?';    
        $params[] = $minOcj;
        $types .= 'd';            
    }

    $sql = "SELECT id, naslov, zanr, godina, trajanje, ocjena, reziser, zemlja FROM filmovi";
    if ($where)
        $sql .= " WHERE " . implode(' AND ', $where); 
    $sql .= " ORDER BY $sort $dir LIMIT 200";

    $stmt = $conn->prepare($sql);
    if ($params)
        $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $filmovi = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 

    // Dohvaćamo koji filmovi su već u videoteci prijavljenog korisnika
    $uVideoteci = [];
    if (isLoggedIn()) {
        $uid = currentUserId();
        $q = $conn->prepare("SELECT film_id FROM zeljeni_filmovi WHERE korisnik_id=?");
        $q->bind_param('i', $uid);
        $q->execute();
        foreach ($q->get_result()->fetch_all(MYSQLI_ASSOC) as $r)
            $uVideoteci[$r['film_id']] = true;
    }

    foreach ($filmovi as &$f)
        $f['u_videoteci'] = isset($uVideoteci[$f['id']]);

    if (!$stmt) {
        echo json_encode(['greska' => $conn->error]);
        exit;
    }

    echo json_encode($filmovi); // Vraćamo JSON array filmova
    exit;
}

// BRISANJE FILMA (samo admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_film'])) {
    requireAdmin(); // Ako korisnik nije admin, zaustavljamo izvođenje
    $id = intval($_POST['delete_film']);
    $stmt = $conn->prepare("DELETE FROM filmovi WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header('Location: filmovi.php?msg=obrisan'); // Preusmjeravamo natrag s porukom
    exit;
}

// DODAVANJE ILI UREĐIVANJE FILMA (samo admin)
$editFilm = null; 
$formMsg = '';   

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_film'])) {
    requireAdmin();

    // Čitamo i čistimo podatke iz forme
    $n = trim($_POST['naslov'] ?? '');
    $z = trim($_POST['zanr'] ?? '');
    $g = intval($_POST['godina'] ?? 0);
    $t = intval($_POST['trajanje'] ?? 0);
    $o = floatval($_POST['ocjena'] ?? 0);
    $r = trim($_POST['reziser'] ?? '');
    $e = trim($_POST['zemlja'] ?? '');

    // Validacija 
    if (!$n || !$z || $g < 1888 || $g > 2030 || $t < 1 || $t > 600 || $o < 0 || $o > 10) {
        $formMsg = 'Neispravni podaci. Provjeri sva polja.';
    } else {
        $fid = intval($_POST['film_id'] ?? 0); // 0 = novi film, >0 = uređivanje postojećeg

        if ($fid) {
            // UPDATE postojećeg filma
            $stmt = $conn->prepare("UPDATE filmovi SET naslov=?,zanr=?,godina=?,trajanje=?,ocjena=?,reziser=?,zemlja=? WHERE id=?");
            $stmt->bind_param('ssiiddsi', $n, $z, $g, $t, $o, $r, $e, $fid);
        } else {
            // INSERT novog filma
            $stmt = $conn->prepare("INSERT INTO filmovi (naslov,zanr,godina,trajanje,ocjena,reziser,zemlja) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param('ssiidss', $n, $z, $g, $t, $o, $r, $e);
        }
        $stmt->execute();
        header('Location: filmovi.php?msg=spremljeno');
        exit;
    }
}

// dohvaćamo podatke filma za popunjavanje forme
if (isset($_GET['edit'])) {
    requireAdmin();
    $eid = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM filmovi WHERE id=?");
    $stmt->bind_param('i', $eid);
    $stmt->execute();
    $editFilm = $stmt->get_result()->fetch_assoc(); // Popunjava formu s postojećim podacima
}

$zanrovi = [];
$res = $conn->query("SELECT DISTINCT zanr FROM filmovi ORDER BY zanr");
while ($r = $res->fetch_assoc())
    $zanrovi[] = $r['zanr'];

// Dohvaćamo filmove prijavljenog korisnika
$mojaVideoteka = [];
if (isLoggedIn()) {
    $uid = currentUserId();
    $stmt = $conn->prepare("
        SELECT f.*, zf.dodano_at
        FROM filmovi f
        JOIN zeljeni_filmovi zf ON f.id = zf.film_id
        WHERE zf.korisnik_id = ?
        ORDER BY zf.dodano_at DESC
    ");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $mojaVideoteka = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="hr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filmovi – Videoteka</title>
    <link rel="stylesheet" href="style/style.css">
    <link rel="stylesheet" href="style/style_filmovi.css">
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
                                <li><a href="slike.php">Galerija</a></li>
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
            <h1>🎬 Filmovi</h1>
            <?php if (isLoggedIn()): ?>
                <button onclick="document.getElementById('kosarica-aside').classList.toggle('otvorena')"
                    class="btn-cart-header">
                    📽 Videoteka <span id="vid-count" class="cart-badge"><?= count($mojaVideoteka) ?></span>
                </button>
            <?php endif; ?>
        </div>
    </header>

    <div class="page-wrap">
        <?php if (isset($_GET['msg'])): ?>
            <div class="msg-ok"><?= $_GET['msg'] === 'obrisan' ? 'Film obrisan.' : 'Film spremljen.' ?></div>
        <?php endif; ?>

        <!-- FILTRIRANJE -->
        <div class="section-box">
            <h2>Filtriranje filmova</h2>
            <div class="filter-row">
                <div>
                    <label>Žanr</label>
                    <select id="f-zanr">
                        <option value="">— Svi —</option>
                        <?php foreach ($zanrovi as $z): ?>
                            <option value="<?= htmlspecialchars($z) ?>"><?= htmlspecialchars($z) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Godina od</label>
                    <input type="number" id="f-godina" placeholder="npr. 1990" min="1888" max="2030">
                </div>
                <div>
                    <label>Min. ocjena: <strong id="oc-val">0.0</strong></label>
                    <input type="range" id="f-ocjena" min="0" max="10" step="0.1" value="0">
                </div>
                <div>
                    <label>Sortiraj</label>
                    <select id="f-sort">
                        <option value="naslov">Naslovu</option>
                        <option value="godina">Godini</option>
                        <option value="ocjena">Ocjeni</option>
                        <option value="trajanje">Trajanju</option>
                    </select>
                </div>
                <div><label>&nbsp;</label><button class="btn-glavni" onclick="filtriraj()">Filtriraj</button></div>
                <div><label>&nbsp;</label><button class="btn-sekundarni" onclick="resetiraj()">Reset</button></div>
            </div>
            <div class="upoz" id="upoz">⚠️ Jedan od filmova ima ocjenu ispod 5.0 – jeste li sigurni da ga želite dodati?
            </div>
            <p style="color:#555;font-size:.85rem;font-style:italic" id="info-row"></p>
            <div style="overflow-x:auto">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th onclick="sortBy('naslov')">Naslov ↕</th>
                            <th>Žanr</th>
                            <th onclick="sortBy('godina')">Godina ↕</th>
                            <th onclick="sortBy('trajanje')">Trajanje ↕</th>
                            <th>Zemlja</th>
                            <th onclick="sortBy('ocjena')">Ocjena ↕</th>
                            <th>Videoteka</th>
                            <?php if (isAdmin()): ?>
                                <th>Admin</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="tbody-filmovi">
                        <tr>
                            <td colspan="9" style="text-align:center;padding:30px;color:#999">Pritisnite Filtriraj…</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ADMIN FORMA -->
        <?php if (isAdmin()): ?>
            <div class="section-box">
                <h2><?= $editFilm ? '✏️ Uredi film' : '➕ Dodaj novi film' ?></h2>
                <?php if ($formMsg): ?>
                    <div class="msg-err"><?= htmlspecialchars($formMsg) ?></div><?php endif; ?>
                <form method="POST" action="filmovi.php">
                    <input type="hidden" name="save_film" value="1">
                    <?php if ($editFilm): ?><input type="hidden" name="film_id"
                            value="<?= $editFilm['id'] ?>"><?php endif; ?>
                    <div class="admin-form">
                        <div class="fg"><label>Naslov *</label><input type="text" name="naslov" required
                                value="<?= htmlspecialchars($editFilm['naslov'] ?? '') ?>"></div>
                        <div class="fg"><label>Žanr *</label><input type="text" name="zanr" required
                                value="<?= htmlspecialchars($editFilm['zanr'] ?? '') ?>"></div>
                        <div class="fg"><label>Godina *</label><input type="number" name="godina" required min="1888"
                                max="2030" value="<?= $editFilm['godina'] ?? '' ?>"></div>
                        <div class="fg"><label>Trajanje (min) *</label><input type="number" name="trajanje" required min="1"
                                max="600" value="<?= $editFilm['trajanje'] ?? '' ?>"></div>
                        <div class="fg"><label>Ocjena (0–10) *</label><input type="number" name="ocjena" required step="0.1"
                                min="0" max="10" value="<?= $editFilm['ocjena'] ?? '' ?>"></div>
                        <div class="fg"><label>Redatelj</label><input type="text" name="reziser"
                                value="<?= htmlspecialchars($editFilm['reziser'] ?? '') ?>"></div>
                        <div class="fg full"><label>Zemlja</label><input type="text" name="zemlja"
                                value="<?= htmlspecialchars($editFilm['zemlja'] ?? '') ?>"></div>
                        <div class="full" style="display:flex;gap:10px">
                            <button type="submit" class="btn-glavni"><?= $editFilm ? '💾 Spremi' : '➕ Dodaj' ?></button>
                            <?php if ($editFilm): ?><a href="filmovi.php" class="btn-sekundarni"
                                    style="padding:9px 18px;text-decoration:none">Odustani</a><?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- MOJA VIDEOTEKA (aside) -->
        <?php if (isLoggedIn()): ?>
            <aside id="kosarica-aside" class="kosarica-aside">
                <div class="kosarica-zaglavlje">
                    <h3>📽 Moja videoteka</h3>
                    <button onclick="document.getElementById('kosarica-aside').classList.remove('otvorena')"
                        class="btn-zatvori">✕</button>
                </div>
                <ul class="lista-kosarice" id="videoteka-lista">
                    <?php if (empty($mojaVideoteka)): ?>
                        <li style="text-align:center;color:#999;padding:20px">Prazna videoteka</li>
                    <?php else: ?>
                        <?php foreach ($mojaVideoteka as $f): ?>
                            <li>
                                <span><?= htmlspecialchars($f['naslov']) ?> <span
                                        style="background:#e94560;color:#fff;border-radius:10px;padding:1px 7px;font-size:.75rem"><?= $f['ocjena'] ?></span></span>
                                <button class="btn-sm btn-del" onclick="ukloniFilm(<?= $f['id'] ?>, this)">✕</button>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
                <div class="kosarica-podnozje">
                    <small style="color:#999;display:block;text-align:center;margin-bottom:8px"><?= count($mojaVideoteka) ?>
                        filmova</small>
                </div>
            </aside>
        <?php else: ?>
            <div class="section-box" style="background:#e8f4fd;border:1px solid #3498db;text-align:center;color:#2471a3">
                <a href="login.php" style="color:#e94560;font-weight:600">Prijavite se</a> za osobnu videoteku.
            </div>
        <?php endif; ?>
    </div>

    <footer>
        <p>&copy; 2025. Web Programiranje. Sva prava pridržana.</p>
    </footer>

    <script>
        const jeLogiran = <?= isLoggedIn() ? 'true' : 'false' ?>;
        const jeAdmin = <?= isAdmin() ? 'true' : 'false' ?>;

        let sortKol = 'naslov'; // Stupac po kojem se sortira (defaultno naslov)
        let sortDir = 'ASC';    // Smjer sortiranja (ASC = uzlazno, DESC = silazno)

        document.getElementById('f-ocjena').addEventListener('input', function () {
            document.getElementById('oc-val').textContent = parseFloat(this.value).toFixed(1);
        });

        
        async function filtriraj() {
            const zanr = document.getElementById('f-zanr').value;
            const god = document.getElementById('f-godina').value;
            const ocj = document.getElementById('f-ocjena').value;

            sortKol = document.getElementById('f-sort').value;

            const url =
                `filmovi.php?ajax_filmovi=1` +
                `&zanr=${encodeURIComponent(zanr)}` +
                `&godinaOd=${god}` +
                `&ocjena=${ocj}` +
                `&sort=${sortKol}` +
                `&dir=${sortDir}`;

            try {
                const response = await fetch(url);
                const data = await response.json();

                document.getElementById('info-row').textContent = `Pronađeno: ${data.length} filmova`;

                renderTablica(data); // Crtamo tablicu s primljenim podacima

            } catch (err) {
                console.error(err);
                document.getElementById('tbody-filmovi').innerHTML =
                    `<tr><td colspan="9" style="text-align:center;color:red;padding:20px">Greška kod dohvaćanja filmova.</td></tr>`;
            }
        }

        function sortBy(kol) {
            sortDir = (sortKol === kol && sortDir === 'ASC') ? 'DESC' : 'ASC';
            sortKol = kol;
            filtriraj(); 
        }

        function resetiraj() {
            document.getElementById('f-zanr').value = '';
            document.getElementById('f-godina').value = '';
            document.getElementById('f-ocjena').value = 0;
            document.getElementById('oc-val').textContent = '0.0';
            document.getElementById('info-row').textContent = '';
            document.getElementById('tbody-filmovi').innerHTML =
                '<tr><td colspan="9" style="text-align:center;padding:30px;color:#999">Pritisnite Filtriraj…</td></tr>';
        }

        // renderTablica
        function renderTablica(filmovi) {
            const tbody = document.getElementById('tbody-filmovi');

            if (!filmovi.length) {
                tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:20px;color:#999">Nema rezultata.</td></tr>';
                return;
            }

            tbody.innerHTML = filmovi.map((f, i) => {

                // Gumb za dodavanje u videoteku
                const vidBtn = jeLogiran
                    ? `<button class="btn-sm btn-dodaj-v ${f.u_videoteci ? 'dodan' : ''}"
                                data-id="${f.id}"
                                ${f.u_videoteci ? 'disabled' : ''}
                                onclick="dodajFilm(${f.id}, this)">
                            ${f.u_videoteci ? '✓ Dodano' : '+ Dodaj'}
                       </button>`
                    : '<small style="color:#aaa">Prijavi se</small>'; 

                // prikazuju se samo adminima
                const adminBtns = jeAdmin
                    ? `<td style="display:flex;gap:4px">
                            <a href="filmovi.php?edit=${f.id}" class="btn-sm btn-edit">✏️</a>
                            <button class="btn-sm btn-del" onclick="obrisiFilm(${f.id})">🗑</button>
                       </td>`
                    : '';

                const boja = f.ocjena >= 7 ? '#27ae60' : f.ocjena >= 5 ? '#f39c12' : '#e74c3c';

                // Vraćamo HTML za jedan redak tablice
                return `<tr>
                    <td>${i + 1}</td>
                    <td><strong>${esc(f.naslov)}</strong></td>
                    <td>${esc(f.zanr)}</td>
                    <td>${f.godina}</td>
                    <td>${f.trajanje} min</td>
                    <td>${esc(f.zemlja || '—')}</td>
                    <td><strong style="color:${boja}">${f.ocjena}</strong></td>
                    <td>${vidBtn}</td>
                    ${adminBtns}
                </tr>`;
            }).join(''); // Spajamo sve retke u jedan string
        }

        async function dodajFilm(filmId, btn) {
            const fd = new FormData();
            fd.append('ajax', '1');
            fd.append('action', 'dodaj');
            fd.append('film_id', filmId);

            const data = await fetch('filmovi.php', { method: 'POST', body: fd }).then(r => r.json());

            if (data.ok) {
                btn.textContent = '✓ Dodano';
                btn.disabled = true;
                btn.classList.add('dodan');


                const naslov = btn.closest('tr').querySelector('td:nth-child(2)').textContent;
                addToSidebar(filmId, naslov); 
                updateCount(1);              

                if (data.upozorenje)
                    document.getElementById('upoz').style.display = 'block';
            }
        }

        async function ukloniFilm(filmId, btn) {
            const fd = new FormData();
            fd.append('ajax', '1');
            fd.append('action', 'ukloni');
            fd.append('film_id', filmId);

            await fetch('filmovi.php', { method: 'POST', body: fd }); 

            btn.closest('li').remove(); 
            updateCount(-1);          

            const tBtn = document.querySelector(`.btn-dodaj-v[data-id="${filmId}"]`);
            if (tBtn) {
                tBtn.textContent = '+ Dodaj';
                tBtn.disabled = false;
                tBtn.classList.remove('dodan');
            }
        }

        function obrisiFilm(id) {
            if (!confirm('Obrisati film?')) return; // potvrdu od korisnika

            const f = document.createElement('form');
            f.method = 'POST';
            f.action = 'filmovi.php';
            f.innerHTML = `<input name="delete_film" value="${id}">`;
            document.body.appendChild(f);
            f.submit();
        }

        // Ažurira brojač filmova
        function updateCount(d) {
            const el = document.getElementById('vid-count');
            if (el) el.textContent = parseInt(el.textContent) + d;
        }

        // dodavanje u kosaricu
        function addToSidebar(id, naslov) {
            const ul = document.getElementById('videoteka-lista');

            // Uklanjanje placeholdera ako postoji
            const empty = ul.querySelector('li[style*="text-align:center"]');
            if (empty) empty.remove();

            // Kreiramo novi film s gumbom za uklanjanje
            const li = document.createElement('li');
            li.innerHTML = `<span>${naslov}</span>
                            <button class="btn-sm btn-del" onclick="ukloniFilm(${id}, this)">✕</button>`;
            ul.prepend(li); // Dodajemo na POČETAK liste
        }

        // ============================================================
        // esc(s) – Sigurnosna funkcija: escapira HTML znakove
        // Sprječava XSS napade pri umetanju podataka iz baze u HTML
        // ============================================================
        function esc(s) {
            return String(s)
                .replace(/&/g, '&amp;')   
                .replace(/</g, '&lt;')  
                .replace(/>/g, '&gt;');
        }

        // Automatski učitavamo filmove pri prvom otvaranju stranice
        filtriraj();
    </script>
</body>

</html>