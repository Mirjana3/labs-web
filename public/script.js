let sviFilmovi = [];
let kosarica = [];

/* UČITAVANJE PODATAKA */
fetch('movies.csv')
  .then(res => res.text())
  .then(csv => {

    const rezultat = Papa.parse(csv, {
      header: true,
      skipEmptyLines: true
    });

    sviFilmovi = rezultat.data.map(film => ({
      naslov: film['Naslov']?.trim() || '',
      zanr: film['Zanr']?.trim() || '',
      godina: Number(film['Godina']),
      trajanje: Number(film['Trajanje_min']),
      ocjena: Number(film['Ocjena']),
      reziser: film['Rezisery']?.trim() || '',
      zemlja: film['Zemlja_porijekla']
        ? film['Zemlja_porijekla'].split('/').map(z => z.trim())
        : []
    }));

    prikaziGlavnuTablicu(sviFilmovi.slice(0, 10));
    popuniFiltre();
  })
  .catch(err => console.error('Greška pri dohvaćanju CSV-a:', err));


/* PRIKAZ TABLICA */

// GLAVNA TABLICA
function prikaziGlavnuTablicu(filmovi) {
  const tbody = document.querySelector('#filmovi-tablica tbody');
  tbody.innerHTML = '';

  filmovi.forEach((film, i) => {
    tbody.innerHTML += `
      <tr>
        <td>${i + 1}</td>
        <td>${film.naslov}</td>
        <td>${film.zanr}</td>
        <td>${film.godina}</td>
        <td>${film.trajanje} min</td>
        <td>${film.zemlja.join(', ')}</td>
        <td>${film.ocjena}</td>
      </tr>
    `;
  });
}

/* FILTRIRANA TABLICA */
function prikaziFiltriraneTablicu(filmovi) {
  const tbody = document.querySelector('#filtrirano-tablica tbody');
  tbody.innerHTML = '';

  if (!filmovi.length) {
    tbody.innerHTML = '<tr><td colspan="8">Nema filmova.</td></tr>';
    return;
  }

  filmovi.forEach((film, i) => {
    const uKosarici = kosarica.some(k => k.naslov === film.naslov);

    tbody.innerHTML += `
      <tr>
        <td>${i + 1}</td>
        <td>${film.naslov}</td>
        <td>${film.zanr}</td>
        <td>${film.godina}</td>
        <td>${film.trajanje} min</td>
        <td>${film.zemlja.join(', ')}</td>
        <td>${film.ocjena}</td>
        <td>
          <button class="btn-dodaj ${uKosarici ? 'dodan' : ''}"
            data-naslov="${film.naslov}"
            ${uKosarici ? 'disabled' : ''}>
            ${uKosarici ? '✓ Dodano' : '+ Dodaj'}
          </button>
        </td>
      </tr>
    `;
  });

  aktivirajDugmiceDodavanja();
}

/* FILTRI */
function popuniFiltre() {
  const selectZanr = document.getElementById('filter-zanr');
  const sviZanrovi = new Set();

  sviFilmovi.forEach(f =>
    f.zanr.split(',').forEach(z => sviZanrovi.add(z.trim()))
  );

  [...sviZanrovi].sort().forEach(z => {
    const opt = document.createElement('option');
    opt.value = z;
    opt.textContent = z;
    selectZanr.appendChild(opt);
  });
}

/* SLIDER OCJENA */
const sliderOcjena = document.getElementById('filter-ocjena');
const sliderPrikaz = document.getElementById('ocjena-vrijednost');

sliderOcjena.addEventListener('input', () => {
  sliderPrikaz.textContent = Number(sliderOcjena.value).toFixed(1);
});

/* FILTRIRANJE */
function filtriraj() {
  const zanr = document.getElementById('filter-zanr').value.trim();
  const godinaOd = Number(document.getElementById('filter-godina').value) || 0;
  const minOcjena = Number(sliderOcjena.value);

  const filtrirani = sviFilmovi.filter(f =>
    (!zanr || f.zanr.toLowerCase().includes(zanr.toLowerCase())) &&
    (!godinaOd || f.godina >= godinaOd) &&
    f.ocjena >= minOcjena
  );

  document.getElementById('rezultati-info')
    .textContent = `Pronađeno: ${filtrirani.length} filmova`;

  prikaziFiltriraneTablicu(filtrirani);
}


/* KOŠARICA */

function dodajUKosaricu(film, btn) {
  if (kosarica.some(k => k.naslov === film.naslov)) return;

  kosarica.push(film);

  btn.textContent = '✓ Dodano';
  btn.disabled = true;
  btn.classList.add('dodan');

  osvjeziKosaricu();
}

function ukloniIzKosarice(index) {
  kosarica.splice(index, 1);
  osvjeziKosaricu();
}

function osvjeziKosaricu() {
  const lista = document.getElementById('lista-kosarice');
  const broj = document.getElementById('cart-number');

  lista.innerHTML = '';
  broj.textContent = kosarica.length;

  kosarica.forEach((film, i) => {
    lista.innerHTML += `
      <li>
        <span>${film.naslov}</span>
        <button data-index="${i}">✕</button>
      </li>
    `;
  });

  lista.querySelectorAll('button').forEach(btn => {
    btn.addEventListener('click', () =>
      ukloniIzKosarice(Number(btn.dataset.index))
    );
  });
}

/* DODAVANJE U KOŠARICU */
function aktivirajDugmiceDodavanja() {
  document.querySelectorAll('.btn-dodaj:not([disabled])')
    .forEach(btn => {
      btn.addEventListener('click', () => {
        const film = sviFilmovi.find(f => f.naslov === btn.dataset.naslov);
        if (film) dodajUKosaricu(film, btn);
      });
    });
}


/* UI EVENTI */

document.getElementById('btn-filtriraj')
  .addEventListener('click', filtriraj);

document.getElementById('btn-reset')
  .addEventListener('click', () => {
    document.getElementById('filter-zanr').value = '';
    document.getElementById('filter-godina').value = '';
    sliderOcjena.value = 0;
    sliderPrikaz.textContent = '0.0';
    document.getElementById('rezultati-info').textContent = '';
    document.querySelector('#filtrirano-tablica tbody').innerHTML = '';
  });

document.getElementById('btn-cart')
  .addEventListener('click', () =>
    document.getElementById('kosarica-aside').classList.toggle('otvorena')
  );

document.getElementById('btn-zatvori-kosaricu')
  .addEventListener('click', () =>
    document.getElementById('kosarica-aside').classList.remove('otvorena')
  );

document.getElementById('btn-potvrdi')
  .addEventListener('click', () => {
    if (!kosarica.length) return alert("Košarica je prazna!");

    document.getElementById('modal-poruka')
      .textContent = `Uspješno ste posudili ${kosarica.length} filmova!`;

    document.getElementById('modal').classList.add('vidljiv');

    kosarica = [];
    osvjeziKosaricu();

    document.querySelectorAll('.btn-dodaj').forEach(btn => {
      btn.disabled = false;
      btn.classList.remove('dodan');
      btn.textContent = '+ Dodaj';
    });
  });

document.getElementById('btn-zatvori-modal')
  .addEventListener('click', () =>
    document.getElementById('modal').classList.remove('vidljiv')
  );
