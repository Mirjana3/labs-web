let sviFilmovi = [];
let kosarica = [];

// ZADATAK 1 – Dohvat i prikaz podataka
fetch('movies.csv')
  .then(res => res.text())
  .then(csv => {

    const rezultat = Papa.parse(csv, {
      header: true,
      skipEmptyLines: true
    });

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


// ZADATAK 2 – Filtriranje
function popuniFiltre() {
  const selectZanr = document.getElementById('filter-zanr');

  const sviZanrovi = new Set();
  sviFilmovi.forEach(film => {
    film.zanr.split(',').forEach(z => sviZanrovi.add(z.trim()));
  });

  [...sviZanrovi].sort().forEach(zanr => {
    const opt = document.createElement('option');
    opt.value = zanr;
    opt.textContent = zanr;
    selectZanr.appendChild(opt);
  });
}

const sliderOcjena = document.getElementById('filter-ocjena');
const sliderPrikaz = document.getElementById('ocjena-vrijednost');

sliderOcjena.addEventListener('input', () => {
  sliderPrikaz.textContent = parseFloat(sliderOcjena.value).toFixed(1);
});

function filtriraj() {
  const zanr      = document.getElementById('filter-zanr').value.trim();
  const godinaOd  = parseInt(document.getElementById('filter-godina').value) || 0;
  const minOcjena = parseFloat(sliderOcjena.value);

  const filtrirani = sviFilmovi.filter(film => {
    const zanrMatch   = !zanr || film.zanr.toLowerCase().includes(zanr.toLowerCase());
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
  document.getElementById('filter-zanr').value = '';
  document.getElementById('filter-godina').value = '';
  sliderOcjena.value = 0;
  sliderPrikaz.textContent = '0.0';
  document.getElementById('rezultati-info').textContent = '';
  document.querySelector('#filtrirano-tablica tbody').innerHTML = '';
});

function prikaziFiltriraneTablicu(filmovi) {
  const tbody = document.querySelector('#filtrirano-tablica tbody');
  tbody.innerHTML = '';

  if (filmovi.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8">Nema filmova.</td></tr>';
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
      const film = sviFilmovi.find(f => f.naslov === naslov);
      if (film) dodajUKosaricu(film, btn);
    });
  });
}


// KOŠARICA
function dodajUKosaricu(film, btn) {
  if (kosarica.some(k => k.naslov === film.naslov)) return;

  kosarica.push(film);

  btn.textContent = '✓ Dodano';
  btn.disabled = true;
  btn.classList.add('dodan');

  osvjeziKosaricu();
}

function osvjeziKosaricu() {
  const lista = document.getElementById('lista-kosarice');
  lista.innerHTML = '';

  document.getElementById('cart-number').textContent = kosarica.length;

  kosarica.forEach((film, index) => {
    const li = document.createElement('li');
    li.innerHTML = `
      <span>${film.naslov}</span>
      <button data-index="${index}">✕</button>
    `;
    lista.appendChild(li);
  });

  document.querySelectorAll('#lista-kosarice button').forEach(btn => {
    btn.addEventListener('click', () => {
      ukloniIzKosarice(parseInt(btn.getAttribute('data-index')));
    });
  });
}

function ukloniIzKosarice(index) {
  kosarica.splice(index, 1);
  osvjeziKosaricu();
}

document.getElementById('btn-cart').addEventListener('click', () => {
  document.getElementById('kosarica-aside').classList.toggle('otvorena');
});

document.getElementById('btn-zatvori-kosaricu').addEventListener('click', () => {
  document.getElementById('kosarica-aside').classList.remove('otvorena');
});
