const C = window.MAPPA_ESAMI_CONFIG;
const app = document.getElementById('app');

if (!C) {
    document.body.innerHTML = '<div style="font-family:Arial;font-size:40px;padding:40px;color:red">Errore: data/config.js non caricato</div>';
    throw new Error('MAPPA_ESAMI_CONFIG non trovato');
}

let idleTimer = null;
let fullscreenRequested = false;
let currentView = 'home';

function updateViewportHeight() {
    document.documentElement.style.setProperty('--app-height', `${window.innerHeight}px`);
}

function nudgeBrowserChrome() {
    updateViewportHeight();
    setTimeout(updateViewportHeight, 250);
    setTimeout(updateViewportHeight, 900);
}

function requestTvFullscreen() {
    if (!fullscreenRequested) {
        fullscreenRequested = true;
        if (document.documentElement.requestFullscreen) {
            document.documentElement.requestFullscreen().catch(() => { });
        }
    }
    nudgeBrowserChrome();
}

function resetIdle() {
    clearTimeout(idleTimer);
    idleTimer = setTimeout(() => showHome(), C.idleHomeSeconds * 1000);
}

['click', 'touchstart', 'mousemove', 'keydown'].forEach(eventName => {
    document.addEventListener(eventName, resetIdle, { passive: true });
});

['click', 'touchstart'].forEach(eventName => {
    document.addEventListener(eventName, requestTvFullscreen, { passive: true });
});

['resize', 'orientationchange'].forEach(eventName => {
    window.addEventListener(eventName, nudgeBrowserChrome, { passive: true });
});

function isAuleCommissioni(c) {
    return c && c.prova === 'AULE COMMISSIONI';
}

function topbar() {
    return `<div class="topbar"><div class="brand"><div class="logo">Buonarroti</div><div><div class="school">${C.schoolName}</div><div class="subtitle">Mappa interattiva</div></div></div><div class="subtitle">${C.title}</div></div>`;
}

function uniqueCommissions() {
    const seen = new Set();
    return C.commissions.filter(c => {
        const key = `${c.prova}|${c.id}|${c.zone}|${c.classes.map(x => x.name).join('|')}`;
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
    });
}

function tvHomeItems() {
    return [
                {
            title: 'AULE COMMISSIONI',
            subtitle: 'Presidenti, commissari e orali',
            className: 'theme-1',
            action: "showProva('AULE COMMISSIONI')"
        },
        {
            title: 'PRIMA PROVA',
            subtitle: 'Commissioni e aule',
            className: 'theme-2',
            action: "showProva('PRIMA PROVA')"
        },
        {
            title: 'SECONDA PROVA',
            subtitle: 'Commissioni e aule',
            className: 'theme-3',
            action: "showProva('SECONDA PROVA')"
        },
        {
            title: 'PIANTINE',
            subtitle: 'Scegli un piano',
            className: 'theme-4',
            action: 'showMapTypePicker()'
        },
        {
            title: 'Buonarroti',
            subtitle: C.title,
            className: 'school-logo-tile',
            action: ''
        },
        {
            title: 'CALENDARIO',
            subtitle: 'Eventi di oggi',
            className: 'theme-5',
            action: "location.href='calendario.php'"
        }
    ];
}

function floorHomeItem(f, index) {
    return {
        title: f.label,
        subtitle: 'Apri piantina',
        className: `theme-${(index % 6) + 1}`,
        action: `showFloor('${f.id}')`
    };
}

function commissionHomeItem(c, index) {
    return {
        title: c.id,
        subtitle: `${c.classes.map(x => x.name).join(' - ')} | ${c.roomText || ''}`,
        className: `theme-${(index % 6) + 1}`,
        action: `showCommission('${c.id}','${c.prova}')`
    };
}

function showHome() {
    resetIdle();
    currentView = 'home';
    document.body.classList.add('tv-home-mode');

    app.innerHTML = `
    <main class="tv-home">
        <div class="tv-grid">
            ${tvHomeItems().map(item => item.action ? `
                <button class="tv-tile ${item.className}" onclick="${item.action}">
                    <span class="tv-tile-title">${item.title}</span>
                    <span class="tv-tile-subtitle">${item.subtitle}</span>
                </button>
            ` : item.title ? `
                <div class="tv-tile ${item.className}">
                    <span class="tv-tile-title">${item.title}</span>
                    <span class="tv-tile-subtitle">${item.subtitle}</span>
                </div>
            ` : `
                <div class="tv-tile ${item.className}" aria-hidden="true"></div>
            `).join('')}
        </div>
    </main>`;
    nudgeBrowserChrome();
}

function showMapTypePicker() {
    resetIdle();
    currentView = 'map-type-picker';
    document.body.classList.add('tv-home-mode');

    const items = [
        {
            title: 'PIANTINE PROVE',
            subtitle: 'Prima e seconda prova',
            className: 'theme-2',
            action: "showFloorPicker('prove')"
        },
        {
            title: 'PIANTINE COMMISSIONI',
            subtitle: 'Aule commissioni e orali',
            className: 'theme-5',
            action: "showFloorPicker('commissioni')"
        },
        {
            title: 'HOME',
            subtitle: 'Torna al menu',
            className: 'theme-6',
            action: 'showHome()'
        }
    ];

    while (items.length < 6) {
        items.push({ title: '', subtitle: '', className: 'is-empty', action: '' });
    }

    app.innerHTML = `
    <main class="tv-home">
        <div class="tv-grid">
            ${items.map(item => item.action ? `
                <button class="tv-tile ${item.className}" onclick="${item.action}">
                    <span class="tv-tile-title">${item.title}</span>
                    <span class="tv-tile-subtitle">${item.subtitle}</span>
                </button>
            ` : `
                <div class="tv-tile ${item.className}" aria-hidden="true"></div>
            `).join('')}
        </div>
    </main>`;

    nudgeBrowserChrome();
}

function showFloorPicker(mapType = 'commissioni') {
    resetIdle();
    currentView = 'floor-picker';
    document.body.classList.add('tv-home-mode');

    const floorItems = C.floors.map((f, index) => ({
        title: f.label,
        subtitle: mapType === 'prove' ? 'Piantina prove' : 'Piantina commissioni',
        className: `theme-${(index % 6) + 1}`,
        action: `showFloor('${f.id}', '${mapType}')`
    }));

    floorItems.push({
        title: 'Home',
        subtitle: 'Torna al menu',
        className: 'theme-6',
        action: 'showHome()'
    });

    while (floorItems.length < 12) {
        floorItems.push({ title: '', subtitle: '', className: 'is-empty', action: '' });
    }

    app.innerHTML = `
    <main class="tv-home">
        <div class="tv-grid tv-grid-floors">
            ${floorItems.slice(0, 12).map(item => item.action ? `
                <button class="tv-tile ${item.className}" onclick="${item.action}">
                    <span class="tv-tile-title">${item.title}</span>
                    <span class="tv-tile-subtitle">${item.subtitle}</span>
                </button>
            ` : `
                <div class="tv-tile ${item.className}" aria-hidden="true"></div>
            `).join('')}
        </div>
    </main>`;

    nudgeBrowserChrome();
}

function showProva(prova) {
    resetIdle();
    currentView = 'prova';
    document.body.classList.add('tv-home-mode');

    const list = C.commissions.filter(c => c.prova === prova);
    const theme = prova === 'SECONDA PROVA' ? 'theme-4' : prova === 'AULE COMMISSIONI' ? 'theme-3' : 'theme-1';

    app.innerHTML = `
    <main class="tv-sub-page">
        <button class="tv-home-button" onclick="showHome()">Home</button>

        <div class="tv-sub-grid">
            ${list.map(c => `
                <button class="tv-tile ${theme}"
                        onclick="showCommission('${c.id}','${c.prova}')">
                    <span class="tv-tile-title">${c.id}</span>
                    <span class="tv-tile-subtitle">
                        ${c.classes.map(x => x.name).join(' - ')}
                        ${isAuleCommissioni(c) ? `<br>${c.roomTextAulaCommissione || ''} ${c.roomTextAulaOrali ? ' | ' + c.roomTextAulaOrali : ''}` : ''}
                    </span>
                </button>
            `).join('') || `
                <div class="tv-empty-message">Nessuna commissione inserita.</div>
            `}
        </div>
    </main>`;
    nudgeBrowserChrome();
}

function showAllCommissions() {
    resetIdle();
    currentView = 'commissions';
    document.body.classList.add('tv-home-mode');

    const list = C.commissions.filter(c => c.prova === 'AULE COMMISSIONI');

    app.innerHTML = `
    <main class="tv-sub-page">
        <button class="tv-home-button" onclick="showHome()">Home</button>

        <div class="tv-sub-grid">
            ${list.map(c => `
                <button class="tv-tile theme-5"
                        onclick="showCommission('${c.id}','${c.prova}')">
                    <span class="tv-tile-title">${c.id}</span>
                    <span class="tv-tile-subtitle">
                        ${c.classes.map(x => x.name).join(' - ')}
                    </span>
                </button>
            `).join('')}
        </div>
    </main>`;
    nudgeBrowserChrome();
}

function searchText(q) {
    q = q.trim().toUpperCase();
    const box = document.getElementById('searchResults');
    if (!box) return;
    if (!q) {
        box.innerHTML = '';
        return;
    }

    const res = C.commissions.filter(c => c.id.includes(q) || c.classes.some(x => x.name.includes(q)));
    box.innerHTML = `<div class="commission-list" style="margin-top:18px">${res.map(c => commissionCard(c)).join('') || '<p style="font-size:24px">Nessun risultato</p>'}</div>`;
}

function showFloor(id, mapType = 'commissioni') {
    resetIdle();
    currentView = 'floor';
    document.body.classList.add('tv-home-mode');

    const f = C.floors.find(x => x.id === id);
    const imagePath = mapImagePath(id, mapType);

    app.innerHTML = `
    <main class="tv-map-page">
        <button class="tv-home-button" onclick="showHome()">Home</button>

        <div class="map-wrap tv-map-wrap tv-map-static">
            <img src="${imagePath}" alt="${f ? f.label : id}">
        </div>
    </main>`;

    nudgeBrowserChrome();
}

function showCommissionOnMap(id) {
    showCommission(id);
}

function hasCommissions(zoneId) {
    return C.commissions.some(c => c.zone === zoneId);
}

function commissionCard(c) {
    return `<div class="commission-card" onclick="showCommission('${c.id}','${c.prova}')">
        <h3>${c.id} - ${c.prova}</h3>
        <p>${c.classes.map(x => x.name).join(' - ')}</p>
        <p>${c.building} - ${c.floorText} - ${c.roomText || ''}</p>
    </div>`;
}

function showZone(zoneId) {
    resetIdle();
    currentView = 'zone';
    document.body.classList.add('tv-home-mode');

    const z = C.zones.find(x => x.id === zoneId);
    const list = C.commissions.filter(c => c.zone === zoneId);

    app.innerHTML = `
    <main class="tv-sub-page">
        <button class="tv-home-button" onclick="showHome()">Home</button>
        <div class="tv-sub-grid">
            ${list.map(c => `
                <button class="tv-tile theme-1" onclick="showCommission('${c.id}','${c.prova}')">
                    <span class="tv-tile-title">${c.id}</span>
                    <span class="tv-tile-subtitle">${c.prova} | ${c.classes.map(x => x.name).join(' - ')} | ${c.roomText || ''}</span>
                </button>
            `).join('') || `
                <div class="tv-empty-message">Nessuna commissione inserita in questa zona.</div>
            `}
        </div>
    </main>`;
    nudgeBrowserChrome();
}

function renderAuleCommissioni(c) {
    return `
        <div class="tv-aule-header">
            <div class="tv-aule-title">${c.id}</div>
            <div class="tv-aule-prova">${c.prova}</div>
        </div>

        <div class="tv-aule-info-grid">
            <div class="tv-aule-info-box">
                <div class="tv-aule-label">Presidente</div>
                <div class="tv-aule-value">${c.president || '-'}</div>
            </div>

            <div class="tv-aule-info-box">
                <div class="tv-aule-label">Aula commissione</div>
                <div class="tv-aule-value">${c.roomTextAulaCommissione || '-'}</div>
            </div>

            <div class="tv-aule-info-box">
                <div class="tv-aule-label">Aula orali</div>
                <div class="tv-aule-value">${c.roomTextAulaOrali || '-'}</div>
            </div>
        </div>

        <div class="tv-aule-place">
            ${c.building || ''} | ${c.floorText || ''}
        </div>

        <div class="tv-class-commission-list">
            ${c.classes.map(classe => `
                <div class="tv-class-commission-card">
                    <div class="tv-class-commission-head">
                        <div class="tv-class-name">${classe.name}</div>
                        <div class="tv-class-course">${classe.course || ''}</div>
                    </div>

                    <div class="tv-commissioners-table-wrap">
                        <table class="tv-commissioners-table">
                            <thead>
                                <tr>
                                    <th>Docente</th>
                                    <th>Materia</th>
                                    <th>Tipo</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${(classe.commissioners || []).map(doc => `
                                    <tr>
                                        <td>${doc.name || ''}</td>
                                        <td>${doc.subject || ''}</td>
                                        <td>${doc.type || ''}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function renderProvaCommissione(c) {
    return `
        <div class="tv-sign-code">${c.id}</div>
        <div class="tv-sign-room">${c.roomText || ''}</div>
        <div class="tv-sign-place">${c.building || ''} | ${c.floorText || ''}</div>
        <div class="tv-sign-classes">${c.classes.map(x => `<div>${x.name}</div>`).join('')}</div>
        <div class="tv-course-list">
            ${c.classes.map(x => `
                <div class="tv-course-row">
                    <strong>${x.name}</strong>
                    <span>${x.course}</span>
                </div>
            `).join('')}
        </div>
    `;
}

function commissionMapType(c) {
    if (c.prova === 'AULE COMMISSIONI') return 'commissioni';
    return 'prove';
}

function mapImagePath(floorId, mapType) {
    const v = Date.now();

    if (mapType === 'prove') {
        return `assets/maps/${floorId}_prove.jpg?v=${v}`;
    }

    return `assets/maps/${floorId}.jpg?v=${v}`;
}

function showCommission(id, prova = null) {
    resetIdle();
    currentView = 'commission';
    document.body.classList.add('tv-home-mode');

    const c = C.commissions.find(
        x => x.id === id && (!prova || x.prova === prova)
    );

    if (!c) {
        app.innerHTML = `
        <main class="tv-sub-page">
            <button class="tv-home-button" onclick="showHome()">Home</button>
            <div class="tv-empty-message">Commissione non trovata.</div>
        </main>`;
        return;
    }

    const zone = C.zones.find(z => z.id === c.zone);
    const floor = zone ? C.floors.find(f => f.id === zone.floor) : null;
    const mapType = commissionMapType(c);
    const imagePath = floor ? mapImagePath(floor.id, mapType) : '';

    app.innerHTML = `
    <main class="tv-commission-page">
        <button class="tv-home-button" onclick="showHome()">Home</button>

        <section class="tv-commission-card ${isAuleCommissioni(c) ? 'tv-commission-card-aule' : ''}">
            <div class="tv-commission-map-panel">
                ${floor ? `
<div class="tv-commission-map-crop tv-map-zoom-${zone ? zone.id : ''}"
     style="background-image:url('${imagePath}')">
</div>
                    <div class="tv-map-caption">
                        ${floor.label} | ${c.floorText || ''}
                    </div>
                ` : `
                    <div class="tv-empty-message">Piantina non disponibile</div>
                `}
            </div>

            <div class="tv-commission-info ${isAuleCommissioni(c) ? 'tv-commission-info-scroll' : ''}">
                ${isAuleCommissioni(c) ? renderAuleCommissioni(c) : renderProvaCommissione(c)}
            </div>
        </section>
    </main>`;

    nudgeBrowserChrome();
}

updateViewportHeight();

setInterval(() => {
    if (currentView === 'home') {
        window.location.reload();
    }
}, (C.autoReloadSeconds || 300) * 1000);

showHome();