let sviFilmovi = [];
let kosarica   = [];

// ZADATAK 1 – Dohvat i prikaz podataka
fetch('movies.csv')
  .then(res => res.text())
  .then(csv => {

    const rezultat = Papa.parse(csv, {
      header: true,
      skipEmptyLines: true
    });

    // Mapiramo svaki redak CSV-a u strukturirani objekt
    // Number() pretvara stringove u brojeve
    // split(',') razdvaja višestruke žanrove / države u niz
    sviFilmovi = rezultat.data.map(film => ({
      naslov:  film['Naslov']  ? film['Naslov'].trim()  : '',
      zanr:    film['Zanr']   ? film['Zanr'].trim()    : '',
      godina:  Number(film['Godina']),
      trajanje: Number(film['Trajanje_min']),
      ocjena:  Number(film['Ocjena']),
      reziser: film['Rezisery'] ? film['Rezisery'].trim() : '',
      zemlja:  film['Zemlja_porijekla']
                 ? film['Zemlja_porijekla'].split('/').map(z => z.trim())
                 : []
    }));

    prikaziGlavnuTablicu(sviFilmovi.slice(0, 10));

    popuniFiltre();

  })
  .catch(err => {
    console.error('Greška pri dohvaćanju CSV-a:', err);
  });


function prikaziGlavnuTablicu(filmovi) {
  const tbody = document.querySelector('#filmovi-tablica tbody');
  tbody.innerHTML = '';

  filmovi.forEach((film, i) => {
    const row = document.createElement('tr');
    row.innerHTML = `
      <td>${i + 1}</td>
      <td>${film.naslov}</td>
      <td>${film.zanr}</td>
      <td>${film.godina}</td>
      <td>${film.trajanje} min</td>
      <td>${film.zemlja.join(', ')}</td>
      <td>${film.ocjena}</td>
    `;
    tbody.appendChild(row);
  });
}


// ZADATAK 2 – Filtriranje i pretraživanje
function popuniFiltre() {
  const selectZanr = document.getElementById('filter-zanr');

  // Skupi sve žanrove, razdvoji kombinacije (npr. "Crime, Drama")
  const sviZanrovi = new Set();
  sviFilmovi.forEach(film => {
    film.zanr.split(',').forEach(z => sviZanrovi.add(z.trim()));
  });

  // Dodaj opcije u select
  [...sviZanrovi].sort().forEach(zanr => {
    const opt = document.createElement('option');
    opt.value = zanr;
    opt.textContent = zanr;
    selectZanr.appendChild(opt);
  });
}

const sliderOcjena  = document.getElementById('filter-ocjena');
const sliderPrikaz  = document.getElementById('ocjena-vrijednost');

sliderOcjena.addEventListener('input', () => {
  sliderPrikaz.textContent = parseFloat(sliderOcjena.value).toFixed(1);
});

function filtriraj() {
  const zanr      = document.getElementById('filter-zanr').value.trim();
  const godinaOd  = parseInt(document.getElementById('filter-godina').value) || 0;
  const minOcjena = parseFloat(sliderOcjena.value);

  const filtrirani = sviFilmovi.filter(film => {
    const zanrMatch = !zanr || film.zanr.toLowerCase().includes(zanr.toLowerCase());

    const godinaMatch = !godinaOd || film.godina >= godinaOd;

    const ocjenaMatch = film.ocjena >= minOcjena;

    return zanrMatch && godinaMatch && ocjenaMatch;
  });

  const info = document.getElementById('rezultati-info');
  info.textContent = `Pronađeno: ${filtrirani.length} filmova`;

  prikaziFiltriraneTablicu(filtrirani);
}

document.getElementById('btn-filtriraj').addEventListener('click', filtriraj);

document.getElementById('btn-reset').addEventListener('click', () => {
  document.getElementById('filter-zanr').value    = '';
  document.getElementById('filter-godina').value  = '';
  sliderOcjena.value = 0;
  sliderPrikaz.textContent = '0.0';
  document.getElementById('rezultati-info').textContent = '';
  document.querySelector('#filtrirano-tablica tbody').innerHTML = '';
});

function prikaziFiltriraneTablicu(filmovi) {
  const tbody = document.querySelector('#filtrirano-tablica tbody');
  tbody.innerHTML = '';

  if (filmovi.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:1rem; color:#888;">Nema filmova za odabrane filtere.</td></tr>';
    return;
  }

  filmovi.forEach((film, i) => {
    const row = document.createElement('tr');

    const uKosarici = kosarica.some(k => k.naslov === film.naslov);

    row.innerHTML = `
      <td>${i + 1}</td>
      <td>${film.naslov}</td>
      <td>${film.zanr}</td>
      <td>${film.godina}</td>
      <td>${film.trajanje} min</td>
      <td>${film.zemlja.join(', ')}</td>
      <td>${film.ocjena}</td>
      <td>
        <button
          class="btn-dodaj ${uKosarici ? 'dodan' : ''}"
          data-naslov="${film.naslov}"
          ${uKosarici ? 'disabled' : ''}
        >
          ${uKosarici ? '✓ Dodano' : '+ Dodaj'}
        </button>
      </td>
    `;
    tbody.appendChild(row);
  });

  document.querySelectorAll('.btn-dodaj:not([disabled])').forEach(btn => {
    btn.addEventListener('click', () => {
      const naslov = btn.getAttribute('data-naslov');
      const film   = sviFilmovi.find(f => f.naslov === naslov);
      if (film) dodajUKosaricu(film, btn);
    });
  });
}

// ZADATAK 3 – Interaktivno upravljanje košaricom
function dodajUKosaricu(film, btn) {
  // Provjera duplikata
  if (kosarica.some(k => k.naslov === film.naslov)) {
    alert('Film je već u košarici!');
    return;
  }

  kosarica.push(film);

  // Onemogući gumb da ne može dodati dva puta
  btn.textContent = '✓ Dodano';
  btn.disabled    = true;
  btn.classList.add('dodan');

  osvjeziKosaricu();
}


function osvjeziKosaricu() {
  const lista = document.getElementById('lista-kosarice');
  lista.innerHTML = '';

  document.getElementById('kosarica-broj').textContent = kosarica.length;

  kosarica.forEach((film, index) => {
    const li = document.createElement('li');
    li.innerHTML = `
      <div class="kosarica-info">
        <span class="kosarica-naslov">${film.naslov}</span>
        <span class="kosarica-detalj">${film.godina} · ${film.zanr}</span>
      </div>
      <button class="btn-ukloni" data-index="${index}" title="Ukloni">✕</button>
    `;
    lista.appendChild(li);
  });

  document.querySelectorAll('.btn-ukloni').forEach(btn => {
    btn.addEventListener('click', () => {
      ukloniIzKosarice(parseInt(btn.getAttribute('data-index')));
    });
  });
}

function ukloniIzKosarice(index) {
  kosarica.splice(index, 1);
  osvjeziKosaricu();
  // Ako je tablica filtriranih vidljiva, osvježi gumbe (da se resetira "Dodano")
  const zadnjiFilter = document.getElementById('rezultati-info').textContent;
  if (zadnjiFilter) filtriraj();
}

document.getElementById('btn-otvori-kosaricu').addEventListener('click', () => {
  document.getElementById('kosarica-aside').classList.toggle('otvorena');
});

document.getElementById('btn-zatvori-kosaricu').addEventListener('click', () => {
  document.getElementById('kosarica-aside').classList.remove('otvorena');
});

document.getElementById('btn-potvrdi').addEventListener('click', () => {
  if (kosarica.length === 0) {
    alert('Košarica je prazna! Dodajte filmove prije potvrde.');
    return;
  }

  const broj = kosarica.length;

  document.getElementById('modal-poruka').textContent =
    `Uspješno ste dodali ${broj} ${broj === 1 ? 'film' : 'filma'} u svoju košaricu za vikend maraton!`;
  document.getElementById('modal').classList.add('vidljiv');

  kosarica = [];
  osvjeziKosaricu();

  // Osvježi tablicu filtriranih (ukloni "Dodano" oznake)
  const zadnjiFilter = document.getElementById('rezultati-info').textContent;
  if (zadnjiFilter) filtriraj();
});


document.getElementById('btn-zatvori-modal').addEventListener('click', () => {
  document.getElementById('modal').classList.remove('vidljiv');
});
