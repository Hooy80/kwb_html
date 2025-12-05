// Admin Panel JavaScript
console.log('=== APP.JS LOADED ===');

// State
let isAuthenticated = false;
let currentPage = 'dashboard';

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    console.log('=== DOM CONTENT LOADED ===');
    console.log('Login form:', document.getElementById('login-form'));
    checkAuth();
    setupEventListeners();
    console.log('=== SETUP COMPLETE ===');
});

// Check authentication
async function checkAuth() {
    try {
        const response = await fetch('/php/auth_check.php');
        const data = await response.json();

        if (data.success) {
            isAuthenticated = true;
            sessionStorage.setItem('currentUser', JSON.stringify(data.user));
            showAdminPanel();
            loadDashboardStats();
        } else {
            showLogin();
        }
    } catch (error) {
        console.error('Auth check error:', error);
        showLogin();
    }
}

// Event listeners
function setupEventListeners() {
    console.log('=== SETUP EVENT LISTENERS ===');

    // Login form
    const loginForm = document.getElementById('login-form');
    console.log('Login form element:', loginForm);
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
        console.log('✓ Login form submit listener attached');
    } else {
        console.error('✗ Login form NOT FOUND');
    }

    // Logout button
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', handleLogout);
        console.log('✓ Logout button listener attached');
    }

    // Navigation links
    const navLinks = document.querySelectorAll('.nav-link');
    console.log('Nav links found:', navLinks.length);
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const page = e.currentTarget.getAttribute('data-page');
            navigateToPage(page);
        });
    });
}

// Handle login
async function handleLogin(e) {
    console.log('!!! HANDLE LOGIN CALLED !!!');
    e.preventDefault();
    console.log('!!! PREVENT DEFAULT EXECUTED !!!');

    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const errorEl = document.getElementById('login-error');

    console.log('=== LOGIN DEBUG ===');
    console.log('Username:', username);
    console.log('Password length:', password.length);

    try {
        const requestBody = {
            login: username,
            password: password
        };
        console.log('Request body:', requestBody);

        const response = await fetch('/php/auth_login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(requestBody)
        });

        console.log('Response status:', response.status);
        console.log('Response headers:', [...response.headers.entries()]);

        const responseText = await response.text();
        console.log('Raw response:', responseText);

        let data;
        try {
            data = JSON.parse(responseText);
            console.log('Parsed response:', data);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            errorEl.textContent = 'Server response error: ' + responseText.substring(0, 100);
            return;
        }

        if (data.success) {
            console.log('Login successful!');
            sessionStorage.setItem('currentUser', JSON.stringify(data.user));
            isAuthenticated = true;
            errorEl.textContent = '';
            showAdminPanel();
            loadDashboardStats();
        } else {
            console.log('Login failed:', data.error);
            errorEl.textContent = data.error || 'Ongeldige gebruikersnaam of wachtwoord';
            setTimeout(() => {
                errorEl.textContent = '';
            }, 3000);
        }
    } catch (error) {
        console.error('Login exception:', error);
        errorEl.textContent = 'Er is een fout opgetreden. Probeer het opnieuw.';
        setTimeout(() => {
            errorEl.textContent = '';
        }, 3000);
    }
}

// Handle logout
async function handleLogout() {
    if (confirm('Weet je zeker dat je wilt uitloggen?')) {
        try {
            await fetch('/php/auth_logout.php');
            sessionStorage.removeItem('currentUser');
            isAuthenticated = false;
            showLogin();
        } catch (error) {
            console.error('Logout error:', error);
            // Log out locally anyway
            sessionStorage.removeItem('currentUser');
            isAuthenticated = false;
            showLogin();
        }
    }
}

// Show login screen
function showLogin() {
    document.getElementById('login-container').style.display = 'flex';
    document.getElementById('admin-container').style.display = 'none';
}

// Show admin panel
function showAdminPanel() {
    document.getElementById('login-container').style.display = 'none';
    document.getElementById('admin-container').style.display = 'block';

    const currentUser = JSON.parse(sessionStorage.getItem('currentUser') || '{}');
    const userName = currentUser.voornaam && currentUser.naam
        ? `${currentUser.voornaam} ${currentUser.naam}`
        : currentUser.naam || 'Admin';
    document.getElementById('user-name').textContent = userName;

    // Verberg/toon menu items op basis van functie
    const dashboardLink = document.querySelector('[data-page="dashboard"]');
    const activiteitenLink = document.querySelector('[data-page="activiteiten"]');
    const inschrijvingenLink = document.querySelector('[data-page="inschrijvingen"]');
    const berichtenLink = document.querySelector('[data-page="berichten"]');
    const gebruikersLink = document.querySelector('[data-page="gebruikers"]');

    if (currentUser.functie === 'inschrijving') {
        // Inschrijving: alleen inschrijvingen (geen gebruikers/profiel)
        if (dashboardLink) dashboardLink.parentElement.style.display = 'none';
        if (activiteitenLink) activiteitenLink.parentElement.style.display = 'none';
        if (inschrijvingenLink) inschrijvingenLink.parentElement.style.display = 'block';
        if (berichtenLink) berichtenLink.parentElement.style.display = 'none';
        if (gebruikersLink) gebruikersLink.parentElement.style.display = 'none';

        // Navigeer direct naar inschrijvingen
        setTimeout(() => navigateToPage('inschrijvingen'), 100);
    } else if (currentUser.functie === 'wijkmeester') {
        // Wijkmeester: alles behalve berichten
        if (dashboardLink) dashboardLink.parentElement.style.display = 'block';
        if (activiteitenLink) activiteitenLink.parentElement.style.display = 'block';
        if (inschrijvingenLink) inschrijvingenLink.parentElement.style.display = 'block';
        if (berichtenLink) berichtenLink.parentElement.style.display = 'none';
        if (gebruikersLink) gebruikersLink.parentElement.style.display = 'block';
    } else {
        // Admin en bestuur: alles
        if (dashboardLink) dashboardLink.parentElement.style.display = 'block';
        if (activiteitenLink) activiteitenLink.parentElement.style.display = 'block';
        if (inschrijvingenLink) inschrijvingenLink.parentElement.style.display = 'block';
        if (berichtenLink) berichtenLink.parentElement.style.display = 'block';
        if (gebruikersLink) gebruikersLink.parentElement.style.display = 'block';
    }
}

// Navigate to page
function navigateToPage(page) {
    currentPage = page;

    // Update active nav link
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('data-page') === page) {
            link.classList.add('active');
        }
    });

    // Update active page
    document.querySelectorAll('.admin-page').forEach(pageEl => {
        pageEl.classList.remove('active');
    });
    document.getElementById(`page-${page}`).classList.add('active');

    // Load page data
    switch (page) {
        case 'dashboard':
            loadDashboardStats();
            break;
        case 'activiteiten':
            loadActiviteiten();
            break;
        case 'inschrijvingen':
            loadInschrijvingen();
            break;
        case 'berichten':
            loadBerichten();
            break;
        case 'fotos':
            loadFotos();
            break;
        case 'gebruikers':
            loadGebruikers();
            break;
        case 'folders':
            loadFolders();
            break;
    }
}

// Load dashboard statistics
async function loadDashboardStats() {
    try {
        // Haal activiteiten op
        const activitiesRes = await fetch('/php/calendar.php');
        const activities = await activitiesRes.json();
        const futureActivities = Array.isArray(activities) ? activities.filter(a => a.status === 'future') : [];
        document.getElementById('stat-activities').textContent = futureActivities.length;

        // Haal berichten op
        const berichtenRes = await fetch('/php/berichten.php');
        const berichtenData = await berichtenRes.json();
        console.log('Berichten data:', berichtenData);
        if (berichtenData.success) {
            const ongelezenBerichten = berichtenData.berichten.filter(b => b.gelezen == 0);
            console.log('Ongelezen berichten:', ongelezenBerichten.length);
            document.getElementById('stat-messages').textContent = ongelezenBerichten.length;
        } else {
            console.log('Berichten error:', berichtenData.error);
            document.getElementById('stat-messages').textContent = '0';
        }

        // Haal inschrijvingen op
        await loadInschrijvingenStats();

    } catch (error) {
        console.error('Error loading dashboard stats:', error);
    }
}

async function loadInschrijvingenStats() {
    try {
        const response = await fetch('/php/inschrijvingen.php?action=list');
        const data = await response.json();

        const container = document.getElementById('inschrijvingen-stats');

        if (!data.success || !data.inschrijvingen || data.inschrijvingen.length === 0) {
            container.innerHTML = '<p>Geen inschrijvingen gevonden.</p>';
            return;
        }

        // Groepeer per unieke activiteitnaam en haal alleen het nieuwste jaar
        const grouped = {};
        data.inschrijvingen.forEach(inschrijving => {
            if (!grouped[inschrijving.name] || inschrijving.jaren[0] > grouped[inschrijving.name].jaren[0]) {
                grouped[inschrijving.name] = inschrijving;
            }
        });

        // Haal data op voor elke activiteit en bereken totalen
        const cards = [];
        for (const name in grouped) {
            const inschrijving = grouped[name];
            const jaar = inschrijving.jaren[0]; // Meest recente jaar

            try {
                const dataRes = await fetch(`/php/inschrijvingen.php?action=data&name=${encodeURIComponent(name)}&jaar=${jaar}`);
                const dataResult = await dataRes.json();

                if (dataResult.success) {
                    // Bereken totaal aantal inschrijvingen
                    let totalInschrijvingen = 0;
                    const visibleKolommen = dataResult.kolommen.filter(col =>
                        col.toLowerCase() !== 'id' && col.toLowerCase() !== 'id_act'
                    );

                    visibleKolommen.forEach(col => {
                        if (col.toLowerCase().startsWith('aantal')) {
                            dataResult.data.forEach(row => {
                                const value = parseInt(row[col]) || 0;
                                totalInschrijvingen += value;
                            });
                        }
                    });

                    // Format datum
                    const datum = new Date(dataResult.date).toLocaleDateString('nl-BE', {
                        day: 'numeric',
                        month: 'short',
                        year: 'numeric'
                    });

                    cards.push({
                        name: name,
                        date: datum,
                        total: totalInschrijvingen,
                        activityName: name,
                        jaar: jaar
                    });
                }
            } catch (error) {
                console.error(`Error loading data for ${name}:`, error);
            }
        }

        // Sorteer op datum (nieuwste eerst)
        cards.sort((a, b) => b.jaar - a.jaar);

        // Genereer HTML
        let html = '';
        cards.forEach(card => {
            html += `
                <div class="stat-card" onclick="navigateToInschrijving('${card.activityName.replace(/'/g, "\\'")}', '${card.jaar}')" style="cursor: pointer;">
                    <div class="stat-icon"><i class="fa fa-list-alt"></i></div>
                    <div class="stat-info">
                        <h3>${card.total}</h3>
                        <p>${card.name}</p>
                        <small>${card.date}</small>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html || '<p>Geen inschrijvingen gevonden.</p>';

    } catch (error) {
        console.error('Error loading inschrijvingen stats:', error);
        document.getElementById('inschrijvingen-stats').innerHTML = '<p>Fout bij laden van inschrijvingen.</p>';
    }
}

function navigateToInschrijving(activityName, jaar) {
    // Navigeer naar inschrijvingen pagina
    navigateToPage('inschrijvingen');

    // Wacht tot de pagina geladen is en selecteer dan de activiteit
    setTimeout(() => {
        const select = document.getElementById('inschrijving-select');
        select.value = activityName;
        select.dispatchEvent(new Event('change'));

        // Als er een jaar dropdown is, selecteer het jaar
        setTimeout(() => {
            const jaarSelect = document.getElementById('jaar-select');
            if (jaarSelect && jaarSelect.style.display !== 'none') {
                jaarSelect.value = jaar;
                jaarSelect.dispatchEvent(new Event('change'));
            }
        }, 100);
    }, 100);
}

// Load activiteiten
async function loadActiviteiten() {
    // Toon admin sectie voor admin en bestuur
    const currentUser = JSON.parse(sessionStorage.getItem('currentUser') || '{}');
    const canEdit = currentUser.functie === 'admin' || currentUser.functie === 'bestuur';
    document.getElementById('activiteit-admin-section').style.display = canEdit ? 'block' : 'none';

    // Bepaal huidig werkjaar
    const now = new Date();
    const currentYear = now.getFullYear();
    const currentMonth = now.getMonth() + 1;

    let huidigWerkjaar;
    if (currentMonth < 9) {
        huidigWerkjaar = currentYear - 1;
    } else {
        huidigWerkjaar = currentYear;
    }

    // Haal alle activiteiten op om werkjaren te bepalen
    try {
        const response = await fetch('/php/calendar.php');
        const allActivities = await response.json();

        // Bepaal unieke werkjaren uit de activiteiten
        const werkjaren = new Set();
        if (Array.isArray(allActivities)) {
            allActivities.forEach(activity => {
                const activityDate = new Date(activity.date);
                const actYear = activityDate.getFullYear();
                const actMonth = activityDate.getMonth() + 1;

                // Bepaal werkjaar voor deze activiteit
                let werkjaar;
                if (actMonth < 9) {
                    werkjaar = actYear - 1;
                } else {
                    werkjaar = actYear;
                }
                werkjaren.add(werkjaar);
            });
        }

        // Als er geen activiteiten zijn, voeg dan tenminste het huidige werkjaar toe
        if (werkjaren.size === 0) {
            werkjaren.add(huidigWerkjaar);
        }

        // Sorteer werkjaren (nieuwste eerst)
        const gesorteerdWerkjaren = Array.from(werkjaren).sort((a, b) => b - a);

        // Vul dropdown met werkjaren
        const select = document.getElementById('werkjaar-select');
        select.innerHTML = '';
        gesorteerdWerkjaren.forEach(jaar => {
            const option = document.createElement('option');
            option.value = jaar;
            option.textContent = `${jaar}-${jaar + 1}`;
            if (jaar === huidigWerkjaar) {
                option.selected = true;
            }
            select.appendChild(option);
        });
    } catch (error) {
        console.error('Error loading werkjaren:', error);
        // Fallback: toon alleen huidig werkjaar
        const select = document.getElementById('werkjaar-select');
        select.innerHTML = '';
        const option = document.createElement('option');
        option.value = huidigWerkjaar;
        option.textContent = `${huidigWerkjaar}-${huidigWerkjaar + 1}`;
        option.selected = true;
        select.appendChild(option);
    }

    // Setup form handler
    document.getElementById('add-activiteit-form').onsubmit = handleAddActiviteit;

    // Laad activiteiten voor huidig werkjaar
    loadActiviteitenForWerkjaar();
}

// Load activiteiten voor specifiek werkjaar
function loadActiviteitenForWerkjaar() {
    const werkjaar = document.getElementById('werkjaar-select').value;

    fetch(`/php/calendar.php?werkjaar=${werkjaar}`)
        .then(response => response.json())
        .then(activities => {
            const activitiesList = document.getElementById('activities-list');

            document.getElementById('werkjaar-title').textContent = `Werkjaar ${werkjaar}-${parseInt(werkjaar) + 1}`;

            if (!Array.isArray(activities) || activities.length === 0) {
                activitiesList.innerHTML = '<p>Geen activiteiten gevonden voor dit werkjaar.</p>';
                return;
            }

            // Groepeer per status
            const future = activities.filter(a => a.status === 'future');
            const today = activities.filter(a => a.status === 'today');
            const past = activities.filter(a => a.status === 'past');

            // Check of gebruiker activiteiten kan bewerken
            const currentUser = JSON.parse(sessionStorage.getItem('currentUser') || '{}');
            const canEdit = currentUser.functie === 'admin' || currentUser.functie === 'bestuur';

            let html = '';

            // Aankomende activiteiten
            if (future.length > 0) {
                html += '<h3 style="color: #27ae60; margin-top: 20px;">Aankomende Activiteiten</h3>';
                html += '<table class="users-table"><thead><tr>';
                html += '<th>Datum</th><th>Naam</th><th>Tijd</th><th>Locatie</th><th>Info</th>';
                if (canEdit) html += '<th>Actie</th>';
                html += '</tr></thead><tbody>';

                future.forEach(act => {
                    const datum = new Date(act.date).toLocaleDateString('nl-BE');
                    const startTijd = act.startHour ? act.startHour.substring(0, 5) : '';
                    const stopTijd = act.stopHour ? act.stopHour.substring(0, 5) : '';
                    const tijd = startTijd ? `${startTijd}${stopTijd ? ' - ' + stopTijd : ''}` : '-';
                    html += '<tr>';
                    html += `<td>${datum}</td>`;
                    html += `<td><strong>${act.name}</strong></td>`;
                    html += `<td>${tijd}</td>`;
                    html += `<td>${act.place || '-'}</td>`;
                    html += `<td>${act.comment || '-'}</td>`;
                    if (canEdit) {
                        html += '<td>';
                        html += `<button class="btn-small" onclick="copyActiviteit(${act.id})">Kopiëren</button> `;
                        html += `<button class="btn-small" onclick="editActiviteit(${act.id})">Bewerken</button> `;
                        html += `<button class="btn-small btn-delete" onclick="deleteActiviteit(${act.id}, '${act.name.replace(/'/g, "\\'")}')">Verwijderen</button>`;
                        html += '</td>';
                    }
                    html += '</tr>';
                });

                html += '</tbody></table>';
            }

            // Vandaag
            if (today.length > 0) {
                html += '<h3 style="color: #f39c12; margin-top: 20px;">Vandaag</h3>';
                html += '<table class="users-table"><thead><tr>';
                html += '<th>Datum</th><th>Naam</th><th>Tijd</th><th>Locatie</th><th>Info</th>';
                if (canEdit) html += '<th>Actie</th>';
                html += '</tr></thead><tbody>';

                today.forEach(act => {
                    const datum = new Date(act.date).toLocaleDateString('nl-BE');
                    const startTijd = act.startHour ? act.startHour.substring(0, 5) : '';
                    const stopTijd = act.stopHour ? act.stopHour.substring(0, 5) : '';
                    const tijd = startTijd ? `${startTijd}${stopTijd ? ' - ' + stopTijd : ''}` : '-';
                    html += '<tr>';
                    html += `<td>${datum}</td>`;
                    html += `<td><strong>${act.name}</strong></td>`;
                    html += `<td>${tijd}</td>`;
                    html += `<td>${act.place || '-'}</td>`;
                    html += `<td>${act.comment || '-'}</td>`;
                    if (canEdit) {
                        html += '<td>';
                        html += `<button class="btn-small" onclick="copyActiviteit(${act.id})">Kopiëren</button> `;
                        html += `<button class="btn-small" onclick="editActiviteit(${act.id})">Bewerken</button> `;
                        html += `<button class="btn-small btn-delete" onclick="deleteActiviteit(${act.id}, '${act.name.replace(/'/g, "\\'")}')">Verwijderen</button>`;
                        html += '</td>';
                    }
                    html += '</tr>';
                });

                html += '</tbody></table>';
            }

            // Afgelopen activiteiten
            if (past.length > 0) {
                html += '<h3 style="color: #95a5a6; margin-top: 20px;">Afgelopen Activiteiten</h3>';
                html += '<table class="users-table"><thead><tr>';
                html += '<th>Datum</th><th>Naam</th><th>Tijd</th><th>Locatie</th><th>Info</th>';
                if (canEdit) html += '<th>Actie</th>';
                html += '</tr></thead><tbody>';

                past.forEach(act => {
                    const datum = new Date(act.date).toLocaleDateString('nl-BE');
                    const startTijd = act.startHour ? act.startHour.substring(0, 5) : '';
                    const stopTijd = act.stopHour ? act.stopHour.substring(0, 5) : '';
                    const tijd = startTijd ? `${startTijd}${stopTijd ? ' - ' + stopTijd : ''}` : '-';
                    html += '<tr style="opacity: 0.6;">';
                    html += `<td>${datum}</td>`;
                    html += `<td><strong>${act.name}</strong></td>`;
                    html += `<td>${tijd}</td>`;
                    html += `<td>${act.place || '-'}</td>`;
                    html += `<td>${act.comment || '-'}</td>`;
                    if (canEdit) {
                        html += '<td>';
                        html += `<button class="btn-small" onclick="copyActiviteit(${act.id})">Kopiëren</button> `;
                        html += `<button class="btn-small" onclick="editActiviteit(${act.id})">Bewerken</button> `;
                        html += `<button class="btn-small btn-delete" onclick="deleteActiviteit(${act.id}, '${act.name.replace(/'/g, "\\'")}')">Verwijderen</button>`;
                        html += '</td>';
                    }
                    html += '</tr>';
                });

                html += '</tbody></table>';
            }

            activitiesList.innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading activiteiten:', error);
            console.error('Error details:', error.message, error.stack);
            document.getElementById('activities-list').innerHTML = '<p class="error-message">Fout bij laden activiteiten: ' + error.message + '</p>';
        });
}

// Load berichten (placeholder)
function loadBerichten() {
    fetch('/php/berichten.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const messagesList = document.getElementById('messages-list');

                if (data.berichten.length === 0) {
                    messagesList.innerHTML = '<p>Geen berichten gevonden.</p>';
                    return;
                }

                let html = '<table class="users-table">';
                html += '<thead><tr>';
                html += '<th>Status</th>';
                html += '<th>Datum</th>';
                html += '<th>Naam</th>';
                html += '<th>Email</th>';
                html += '<th>Onderwerp</th>';
                html += '<th>Bericht</th>';
                html += '<th>Actie</th>';
                html += '</tr></thead>';
                html += '<tbody>';

                const currentUser = JSON.parse(sessionStorage.getItem('currentUser') || '{}');
                const isAdmin = currentUser.functie === 'admin';

                data.berichten.forEach(msg => {
                    // Parse datum: verwacht formaat "YYYY-MM-DD HH:MM:SS"
                    const datumObj = new Date(msg.datum_ontvangen.replace(' ', 'T'));
                    const datum = datumObj.toLocaleString('nl-BE', {
                        year: 'numeric',
                        month: '2-digit',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit'
                    });

                    const isGelezen = msg.gelezen == 1;
                    const statusClass = isGelezen ? '' : 'style="font-weight: bold;"';

                    html += `<tr ${statusClass}>`;
                    html += `<td>${isGelezen ? '✓' : '<span style="color: #e74c3c;">●</span>'}</td>`;
                    html += `<td>${datum}</td>`;
                    html += `<td>${msg.naam}</td>`;
                    html += `<td>${msg.email}</td>`;
                    html += `<td>${msg.onderwerp}</td>`;
                    html += `<td style="max-width: 300px; white-space: pre-wrap;">${msg.bericht}</td>`;
                    html += '<td>';
                    if (!isGelezen) {
                        html += `<button class="btn-small" onclick="markAsRead(${msg.id})">Markeer gelezen</button> `;
                    }
                    if (isAdmin) {
                        html += `<button class="btn-small btn-delete" onclick="deleteBericht(${msg.id}, '${msg.onderwerp}')">Verwijderen</button>`;
                    }
                    html += '</td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';
                messagesList.innerHTML = html;
            } else {
                document.getElementById('messages-list').innerHTML = `<p class="error-message">${data.error}</p>`;
            }
        })
        .catch(error => {
            console.error('Error loading berichten:', error);
            document.getElementById('messages-list').innerHTML = '<p class="error-message">Fout bij laden berichten</p>';
        });
}

// Markeer bericht als gelezen
function markAsRead(berichtId) {
    fetch('/php/berichten.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: berichtId })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadBerichten();
                loadDashboardStats();
            } else {
                alert('Fout: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error marking message as read:', error);
            alert('Fout bij markeren als gelezen');
        });
}

// Verwijder bericht (alleen admin)
function deleteBericht(berichtId, onderwerp) {
    if (!confirm(`Weet je zeker dat je het bericht "${onderwerp}" wilt verwijderen?`)) {
        return;
    }

    fetch('/php/berichten.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: berichtId })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadBerichten();
                loadDashboardStats();
            } else {
                alert('Fout: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error deleting message:', error);
            alert('Fout bij verwijderen bericht');
        });
}

// Activity management functions
function handleAddActiviteit(e) {
    e.preventDefault();

    const date = document.getElementById('new-act-date').value;
    const name = document.getElementById('new-act-name').value;
    const startHour = document.getElementById('new-act-start').value || null;
    const stopHour = document.getElementById('new-act-stop').value || null;
    const place = document.getElementById('new-act-place').value || null;
    const comment = document.getElementById('new-act-comment').value || null;

    if (!date || !name) {
        alert('Datum en naam zijn verplicht');
        return;
    }

    fetch('/php/activiteiten.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ date, name, startHour, stopHour, place, comment })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Activiteit toegevoegd!');
                document.getElementById('add-activiteit-form').reset();
                loadActiviteiten();
                loadDashboardStats();
            } else {
                alert('Fout: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error adding activity:', error);
            alert('Fout bij toevoegen activiteit');
        });
}

function editActiviteit(activityId) {
    // Fetch the activity data
    const selectedWerkjaar = document.getElementById('werkjaar-select').value;
    fetch('/php/calendar.php?werkjaar=' + selectedWerkjaar)
        .then(response => response.json())
        .then(activities => {
            const activity = activities.find(a => a.id === activityId);
            if (!activity) {
                alert('Activiteit niet gevonden');
                return;
            }

            // Populate form with activity data
            document.getElementById('new-act-date').value = activity.date;
            document.getElementById('new-act-name').value = activity.name;
            document.getElementById('new-act-start').value = activity.startHour ? activity.startHour.substring(0, 5) : '';
            document.getElementById('new-act-stop').value = activity.stopHour ? activity.stopHour.substring(0, 5) : '';
            document.getElementById('new-act-place').value = activity.place || '';
            document.getElementById('new-act-comment').value = activity.comment || '';

            // Change form submit handler to update instead of add
            const form = document.getElementById('add-activiteit-form');
            form.onsubmit = function (e) {
                e.preventDefault();

                const date = document.getElementById('new-act-date').value;
                const name = document.getElementById('new-act-name').value;
                const startHour = document.getElementById('new-act-start').value || null;
                const stopHour = document.getElementById('new-act-stop').value || null;
                const place = document.getElementById('new-act-place').value || null;
                const comment = document.getElementById('new-act-comment').value || null;

                fetch('/php/activiteiten.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: activityId, date, name, startHour, stopHour, place, comment })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Activiteit bijgewerkt!');
                            form.reset();
                            form.onsubmit = handleAddActiviteit; // Reset to add mode
                            loadActiviteiten();
                            loadDashboardStats();
                        } else {
                            alert('Fout: ' + data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error updating activity:', error);
                        alert('Fout bij bijwerken activiteit');
                    });
            };

            // Scroll to form
            document.getElementById('activiteit-admin-section').scrollIntoView({ behavior: 'smooth' });
        })
        .catch(error => {
            console.error('Error fetching activity:', error);
            alert('Fout bij ophalen activiteit');
        });
}

function copyActiviteit(activityId) {
    // Fetch the activity data
    const selectedWerkjaar = document.getElementById('werkjaar-select').value;
    fetch('/php/calendar.php?werkjaar=' + selectedWerkjaar)
        .then(response => response.json())
        .then(activities => {
            const activity = activities.find(a => a.id === activityId);
            if (!activity) {
                alert('Activiteit niet gevonden');
                return;
            }

            // Populate form with activity data for copying
            document.getElementById('new-act-date').value = activity.date;
            document.getElementById('new-act-name').value = activity.name + ' (kopie)';
            document.getElementById('new-act-start').value = activity.startHour ? activity.startHour.substring(0, 5) : '';
            document.getElementById('new-act-stop').value = activity.stopHour ? activity.stopHour.substring(0, 5) : '';
            document.getElementById('new-act-place').value = activity.place || '';
            document.getElementById('new-act-comment').value = activity.comment || '';

            // Make sure form is in add mode
            const form = document.getElementById('add-activiteit-form');
            form.onsubmit = handleAddActiviteit;

            // Scroll to form
            document.getElementById('activiteit-admin-section').scrollIntoView({ behavior: 'smooth' });

            alert('Activiteit gegevens gekopieerd. Pas de datum aan en klik op "Toevoegen".');
        })
        .catch(error => {
            console.error('Error fetching activity:', error);
            alert('Fout bij kopiëren activiteit');
        });
}

function deleteActiviteit(activityId, activityName) {
    if (!confirm(`Weet je zeker dat je de activiteit "${activityName}" wilt verwijderen?`)) {
        return;
    }

    fetch('/php/activiteiten.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: activityId })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Activiteit verwijderd!');
                loadActiviteiten();
                loadDashboardStats();
            } else {
                alert('Fout: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error deleting activity:', error);
            alert('Fout bij verwijderen activiteit');
        });
}

// Load inschrijvingen
let currentInschrijvingenData = null;

function loadInschrijvingen() {
    console.log('Loading inschrijvingen...');

    // Reset UI
    document.getElementById('inschrijvingen-data-card').style.display = 'none';
    document.getElementById('jaar-select-container').style.display = 'none';

    // Haal huidige gebruiker op voor filtering
    const currentUser = JSON.parse(sessionStorage.getItem('currentUser') || '{}');
    const isInschrijvingUser = currentUser.functie === 'inschrijving';
    const allowedActivity = isInschrijvingUser ? currentUser.login : null;

    // Haal lijst van inschrijvingen op
    fetch('/php/inschrijvingen.php?action=list')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('inschrijving-select');
                select.innerHTML = '<option value="">-- Selecteer een activiteit --</option>';

                // Groepeer per unieke activiteitnaam
                const grouped = {};
                data.inschrijvingen.forEach(inschrijving => {
                    // Filter voor inschrijving gebruikers: alleen hun specifieke activiteit
                    if (isInschrijvingUser) {
                        // Match de activiteitnaam met de login (zonder spaties, hoofdletters)
                        const normalizedName = inschrijving.name.replace(/\s+/g, '').toLowerCase();
                        const normalizedLogin = allowedActivity.toLowerCase();
                        if (normalizedName !== normalizedLogin) {
                            return; // Skip deze inschrijving
                        }
                    }

                    if (!grouped[inschrijving.name]) {
                        grouped[inschrijving.name] = {
                            name: inschrijving.name,
                            jaren: inschrijving.jaren,
                            tabel: inschrijving.tabel,
                            kolommen: inschrijving.kolommen
                        };
                    }
                });

                // Vul dropdown
                Object.values(grouped).forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.name;
                    option.dataset.jaren = JSON.stringify(item.jaren);
                    option.textContent = item.name;
                    select.appendChild(option);
                });

                // Voor inschrijving gebruikers: automatisch selecteren als er maar 1 optie is
                if (isInschrijvingUser && Object.keys(grouped).length === 1) {
                    const singleActivity = Object.keys(grouped)[0];
                    select.value = singleActivity;
                    select.dispatchEvent(new Event('change'));
                }

                // Event listener voor activiteit selectie
                select.onchange = function () {
                    const selectedOption = this.options[this.selectedIndex];
                    if (this.value) {
                        const jaren = JSON.parse(selectedOption.dataset.jaren || '[]');

                        if (jaren.length > 1) {
                            // Toon jaar dropdown
                            const jaarSelect = document.getElementById('jaar-select');
                            jaarSelect.innerHTML = '<option value="">-- Selecteer een jaar --</option>';
                            jaren.forEach(jaar => {
                                const option = document.createElement('option');
                                option.value = jaar;
                                option.textContent = jaar;
                                jaarSelect.appendChild(option);
                            });
                            document.getElementById('jaar-select-container').style.display = 'block';
                            document.getElementById('inschrijvingen-data-card').style.display = 'none';

                            // Event listener voor jaar selectie
                            jaarSelect.onchange = function () {
                                if (this.value) {
                                    loadInschrijvingenData(select.value, this.value);
                                }
                            };
                        } else if (jaren.length === 1) {
                            // Direct laden met het enige beschikbare jaar
                            document.getElementById('jaar-select-container').style.display = 'none';
                            loadInschrijvingenData(this.value, jaren[0]);
                        } else {
                            // Geen jaar beschikbaar, laad zonder jaar filter
                            document.getElementById('jaar-select-container').style.display = 'none';
                            loadInschrijvingenData(this.value, '');
                        }
                    } else {
                        document.getElementById('jaar-select-container').style.display = 'none';
                        document.getElementById('inschrijvingen-data-card').style.display = 'none';
                    }
                };
            } else {
                console.error('Error loading inschrijvingen:', data.error);
            }
        })
        .catch(error => {
            console.error('Error loading inschrijvingen:', error);
        });

    // Excel export button
    document.getElementById('export-excel-btn').onclick = exportToExcel;
}

function loadInschrijvingenData(activityName, jaar) {
    const url = `/php/inschrijvingen.php?action=data&name=${encodeURIComponent(activityName)}&jaar=${jaar}`;

    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentInschrijvingenData = data;
                displayInschrijvingenTable(data);
                document.getElementById('inschrijvingen-data-card').style.display = 'block';
            } else {
                alert('Fout: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error loading inschrijvingen data:', error);
            alert('Fout bij laden van inschrijvingen data');
        });
}

function displayInschrijvingenTable(data) {
    const container = document.getElementById('inschrijvingen-table');
    const title = document.getElementById('inschrijvingen-title');
    const formLink = document.getElementById('inschrijving-form-link');

    // Format datum
    const datum = new Date(data.date).toLocaleDateString('nl-BE', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    title.textContent = `${data.activity} - ${datum}`;

    // Toon knop naar formulier (altijd, ongeacht of inschrijving actief is)
    // Genereer formulier naam op basis van activiteitnaam
    const formName = data.activity.toLowerCase().replace(/\s+/g, '_');

    formLink.href = '#';
    formLink.onclick = (e) => {
        e.preventDefault();
        openInschrijvingFormulier(formName);
    };
    formLink.style.display = 'inline-block';

    if (!data.data || data.data.length === 0) {
        container.innerHTML = '<p>Geen inschrijvingen gevonden.</p>';
        return;
    }

    // Check of gebruiker mag bewerken
    const currentUser = JSON.parse(sessionStorage.getItem('currentUser') || '{}');
    const canEdit = currentUser.functie === 'admin' || currentUser.functie === 'bestuur';

    // Filter kolommen: verberg id en id_act maar bewaar ze in de data
    const visibleKolommen = data.kolommen.filter(col =>
        col.toLowerCase() !== 'id' && col.toLowerCase() !== 'id_act'
    );

    // Bereken totalen voor kolommen die beginnen met "aantal"
    const totals = {};
    visibleKolommen.forEach(col => {
        if (col.toLowerCase().startsWith('aantal')) {
            totals[col] = 0;
            data.data.forEach(row => {
                const value = parseInt(row[col]) || 0;
                totals[col] += value;
            });
        }
    });

    // Maak tabel
    let html = '<table class="users-table"><thead><tr>';
    visibleKolommen.forEach(col => {
        html += `<th>${col}</th>`;
    });
    if (canEdit) html += '<th>Actie</th>';
    html += '</tr></thead><tbody>';

    // Data rijen
    data.data.forEach(row => {
        html += '<tr>';
        visibleKolommen.forEach(col => {
            html += `<td>${row[col] || '-'}</td>`;
        });
        if (canEdit && row.id) {
            html += '<td>';
            html += `<button class="btn-small" onclick="editInschrijving(${row.id})">Bewerken</button> `;
            html += `<button class="btn-small btn-delete" onclick="deleteInschrijving(${row.id})">Verwijderen</button>`;
            html += '</td>';
        } else if (canEdit) {
            html += '<td></td>';
        }
        html += '</tr>';
    });

    // Totalen rij
    if (Object.keys(totals).length > 0) {
        html += '<tr style="font-weight: bold; background-color: #f0f0f0;">';
        visibleKolommen.forEach(col => {
            if (totals[col] !== undefined) {
                html += `<td>Totaal: ${totals[col]}</td>`;
            } else {
                html += '<td></td>';
            }
        });
        html += '</tr>';
    }

    html += '</tbody></table>';

    // Bereken totaal aantal inschrijvingen (som van alle "aantal" kolommen)
    let totalInschrijvingen = 0;
    Object.values(totals).forEach(value => {
        totalInschrijvingen += value;
    });

    // Toon totaal aantal inschrijvingen
    if (totalInschrijvingen > 0) {
        html += `<p style="margin-top: 10px; font-weight: bold;">Totaal aantal inschrijvingen: ${totalInschrijvingen}</p>`;
    }

    container.innerHTML = html;
}

function exportToExcel() {
    if (!currentInschrijvingenData) {
        alert('Geen data om te exporteren');
        return;
    }

    const data = currentInschrijvingenData;

    // Filter kolommen: verberg id en id_act
    const visibleKolommen = data.kolommen.filter(col =>
        col.toLowerCase() !== 'id' && col.toLowerCase() !== 'id_act'
    );

    // Maak worksheet data array
    const wsData = [];

    // Header rij
    wsData.push(visibleKolommen);

    // Data rijen
    data.data.forEach(row => {
        const rowData = visibleKolommen.map(col => row[col] || '');
        wsData.push(rowData);
    });

    // Bereken totalen
    const totals = {};
    let hasTotals = false;
    visibleKolommen.forEach(col => {
        if (col.toLowerCase().startsWith('aantal')) {
            totals[col] = 0;
            data.data.forEach(row => {
                const value = parseInt(row[col]) || 0;
                totals[col] += value;
            });
            hasTotals = true;
        }
    });

    // Totalen rij toevoegen
    if (hasTotals) {
        const totalRow = visibleKolommen.map(col => {
            if (totals[col] !== undefined) {
                return `Totaal: ${totals[col]}`;
            }
            return '';
        });
        wsData.push(totalRow);
    }

    // Lege rij
    wsData.push([]);

    // Bereken totaal aantal inschrijvingen (som van alle "aantal" kolommen)
    let totalInschrijvingen = 0;
    Object.values(totals).forEach(value => {
        totalInschrijvingen += value;
    });

    // Totaal aantal inschrijvingen
    if (totalInschrijvingen > 0) {
        const inschrijvingenRow = ['Totaal aantal inschrijvingen:', totalInschrijvingen];
        wsData.push(inschrijvingenRow);
    }

    // Maak workbook en worksheet
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(wsData);

    // Stel kolombreedtes in (automatisch)
    const colWidths = visibleKolommen.map(col => {
        let maxLen = col.length;
        data.data.forEach(row => {
            const val = (row[col] || '').toString();
            if (val.length > maxLen) maxLen = val.length;
        });
        return { wch: Math.min(maxLen + 2, 50) };
    });
    ws['!cols'] = colWidths;

    // Voeg worksheet toe aan workbook
    XLSX.utils.book_append_sheet(wb, ws, 'Inschrijvingen');

    // Genereer filename met datum uit calendar (via data.date)
    const datumFormatted = data.date; // Format: YYYY-MM-DD
    const filename = `inschrijvingen_${data.activity.replace(/\s+/g, '_')}_${datumFormatted}.xlsx`;

    // Download bestand
    XLSX.writeFile(wb, filename);
}

function editInschrijving(inschrijvingId) {
    if (!currentInschrijvingenData) {
        alert('Geen data beschikbaar');
        return;
    }

    // Vind de inschrijving in de huidige data
    const inschrijving = currentInschrijvingenData.data.find(row => row.id == inschrijvingId);
    if (!inschrijving) {
        alert('Inschrijving niet gevonden');
        return;
    }

    // Filter kolommen (zonder id en id_act)
    const editableKolommen = currentInschrijvingenData.kolommen.filter(col =>
        col.toLowerCase() !== 'id' && col.toLowerCase() !== 'id_act'
    );

    // Maak een eenvoudig edit formulier
    let formHtml = '<div style="background: white; padding: 20px; border-radius: 8px; max-width: 600px;">';
    formHtml += `<h3>Bewerk Inschrijving</h3>`;
    formHtml += '<form id="edit-inschrijving-form">';

    editableKolommen.forEach(col => {
        formHtml += `<div class="form-group">`;
        formHtml += `<label for="edit-${col}">${col}</label>`;
        formHtml += `<input type="text" id="edit-${col}" value="${inschrijving[col] || ''}" />`;
        formHtml += `</div>`;
    });

    formHtml += '<div style="margin-top: 20px;">';
    formHtml += '<button type="submit" class="btn-primary">Opslaan</button> ';
    formHtml += '<button type="button" class="btn-secondary" onclick="closeEditModal()">Annuleren</button>';
    formHtml += '</div>';
    formHtml += '</form></div>';

    // Toon modal (simpele implementatie)
    const modal = document.createElement('div');
    modal.id = 'edit-modal';
    modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; justify-content: center; align-items: center; z-index: 1000;';
    modal.innerHTML = formHtml;
    document.body.appendChild(modal);

    // Form submit handler
    document.getElementById('edit-inschrijving-form').onsubmit = function (e) {
        e.preventDefault();

        const updatedData = {};
        editableKolommen.forEach(col => {
            updatedData[col] = document.getElementById(`edit-${col}`).value;
        });

        fetch('/php/inschrijvingen.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: inschrijvingId,
                tabel: currentInschrijvingenData.tabel,
                data: updatedData
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Inschrijving bijgewerkt!');
                    closeEditModal();
                    // Herlaad data
                    const activityName = document.getElementById('inschrijving-select').value;
                    const jaar = document.getElementById('jaar-select').value || document.getElementById('jaar-select').options[0]?.value || '';
                    loadInschrijvingenData(activityName, jaar);
                } else {
                    alert('Fout: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error updating inschrijving:', error);
                alert('Fout bij bijwerken inschrijving');
            });
    };
}

function closeEditModal() {
    const modal = document.getElementById('edit-modal');
    if (modal) {
        modal.remove();
    }
}

function deleteInschrijving(inschrijvingId) {
    if (!currentInschrijvingenData) {
        alert('Geen data beschikbaar');
        return;
    }

    if (!confirm('Weet je zeker dat je deze inschrijving wilt verwijderen?')) {
        return;
    }

    fetch('/php/inschrijvingen.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: inschrijvingId,
            tabel: currentInschrijvingenData.tabel
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Inschrijving verwijderd!');
                // Herlaad data
                const activityName = document.getElementById('inschrijving-select').value;
                const jaar = document.getElementById('jaar-select').value || document.getElementById('jaar-select').options[0]?.value || '';
                loadInschrijvingenData(activityName, jaar);
                // Herlaad dashboard stats
                loadDashboardStats();
            } else {
                alert('Fout: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error deleting inschrijving:', error);
            alert('Fout bij verwijderen inschrijving');
        });
}

// Load fotos (placeholder)
function loadFotos() {
    console.log('Loading fotos...');
    // TODO: Implementeer foto beheer
}

// Load folders (placeholder)
function loadFolders() {
    console.log('Loading folders...');
    // TODO: Implementeer folders beheer
}

// Load gebruikers page
async function loadGebruikers() {
    console.log('Loading gebruikers...');

    const currentUser = JSON.parse(sessionStorage.getItem('currentUser') || '{}');

    // Toon admin sectie alleen voor admins
    const adminSection = document.getElementById('admin-section');
    if (currentUser.functie === 'admin') {
        adminSection.style.display = 'block';

        // Setup add user form
        const addUserForm = document.getElementById('add-user-form');
        if (!addUserForm.hasAttribute('data-listener')) {
            addUserForm.addEventListener('submit', handleAddUser);
            addUserForm.setAttribute('data-listener', 'true');
        }
    } else {
        adminSection.style.display = 'none';
    }

    // Laad gebruikerslijst (admin en bestuur kunnen dit zien)
    if (currentUser.functie === 'admin' || currentUser.functie === 'bestuur') {
        await loadUsersList();
    } else {
        // Verberg de gebruikerslijst sectie voor wijkmeesters en inschrijving gebruikers
        // Zoek alle cards behalve "Mijn Profiel"
        const cards = document.querySelectorAll('#page-gebruikers .card');
        cards.forEach(card => {
            const heading = card.querySelector('h2');
            if (heading && heading.textContent !== 'Mijn Profiel') {
                card.style.display = 'none';
            }
        });
    }

    // Laad eigen profiel
    await loadOwnProfile();
}

// Handle add user form
async function handleAddUser(e) {
    e.preventDefault();

    const errorEl = document.getElementById('add-user-error');
    const successEl = document.getElementById('add-user-success');
    errorEl.textContent = '';
    successEl.textContent = '';

    const userData = {
        voornaam: document.getElementById('new-voornaam').value,
        naam: document.getElementById('new-naam').value,
        email: document.getElementById('new-email').value,
        functie: document.getElementById('new-functie').value
    };

    try {
        const response = await fetch('/php/auth_users.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(userData)
        });

        const data = await response.json();

        if (data.success) {
            successEl.textContent = data.message || 'Gebruiker toegevoegd!';
            document.getElementById('add-user-form').reset();
            await loadUsersList();
        } else {
            errorEl.textContent = data.error || 'Fout bij toevoegen gebruiker';
        }
    } catch (error) {
        console.error('Add user error:', error);
        errorEl.textContent = 'Er is een fout opgetreden';
    }
}

// Load users list
async function loadUsersList() {
    try {
        const response = await fetch('/php/auth_users.php');
        const data = await response.json();

        if (data.success) {
            const users = data.users;
            const currentUser = JSON.parse(sessionStorage.getItem('currentUser') || '{}');
            const isAdmin = currentUser.functie === 'admin';

            let html = '<table>';
            html += '<thead><tr>';
            html += '<th>Naam</th>';
            html += '<th>Email</th>';
            html += '<th>Functie</th>';
            html += '<th>Status</th>';
            if (isAdmin) html += '<th>Acties</th>';
            html += '</tr></thead><tbody>';

            users.forEach(user => {
                html += '<tr>';
                html += `<td>${user.voornaam} ${user.naam}</td>`;
                html += `<td>${user.email}</td>`;
                html += `<td><span class="badge badge-${user.functie}">${user.functie}</span></td>`;
                html += `<td>${user.actief == 1 ? '<span class="badge badge-wijkmeester">Actief</span>' : '<span class="badge badge-inactive">Inactief</span>'}</td>`;

                if (isAdmin) {
                    html += '<td class="user-actions">';
                    if (user.actief == 1) {
                        html += `<button class="btn-small btn-delete" onclick="deactivateUser(${user.id}, '${user.voornaam} ${user.naam}')">Deactiveren</button>`;
                    }
                    html += '</td>';
                }
                html += '</tr>';
            });

            html += '</tbody></table>';
            document.getElementById('users-list').innerHTML = html;
        } else {
            document.getElementById('users-list').innerHTML = '<p>Fout bij laden gebruikers</p>';
        }
    } catch (error) {
        console.error('Load users error:', error);
        document.getElementById('users-list').innerHTML = '<p>Fout bij laden gebruikers</p>';
    }
}

// Deactivate user (admin only)
async function deactivateUser(userId, userName) {
    if (!confirm(`Weet je zeker dat je ${userName} wilt deactiveren?`)) {
        return;
    }

    try {
        const response = await fetch('/php/auth_users.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: userId })
        });

        const data = await response.json();

        if (data.success) {
            alert('Gebruiker gedeactiveerd');
            await loadUsersList();
        } else {
            alert('Fout: ' + (data.error || 'Kon gebruiker niet deactiveren'));
        }
    } catch (error) {
        console.error('Deactivate user error:', error);
        alert('Er is een fout opgetreden');
    }
}

// Load own profile
async function loadOwnProfile() {
    const currentUser = JSON.parse(sessionStorage.getItem('currentUser') || '{}');

    document.getElementById('profile-user-id').value = currentUser.id;
    document.getElementById('profile-voornaam').value = currentUser.voornaam || '';
    document.getElementById('profile-naam').value = currentUser.naam || '';
    document.getElementById('profile-email').value = currentUser.email || '';
    document.getElementById('profile-login').value = currentUser.login || '';

    // Setup profile form
    const profileForm = document.getElementById('edit-profile-form');
    if (!profileForm.hasAttribute('data-listener')) {
        profileForm.addEventListener('submit', handleUpdateProfile);
        profileForm.setAttribute('data-listener', 'true');
    }
}

// Handle update profile
async function handleUpdateProfile(e) {
    e.preventDefault();

    const errorEl = document.getElementById('profile-error');
    const successEl = document.getElementById('profile-success');
    errorEl.textContent = '';
    successEl.textContent = '';

    const updateData = {
        id: parseInt(document.getElementById('profile-user-id').value),
        voornaam: document.getElementById('profile-voornaam').value,
        naam: document.getElementById('profile-naam').value,
        email: document.getElementById('profile-email').value,
        login: document.getElementById('profile-login').value
    };

    const newPassword = document.getElementById('profile-password').value;
    if (newPassword) {
        updateData.password = newPassword;
    }

    try {
        const response = await fetch('/php/auth_users.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(updateData)
        });

        const data = await response.json();

        if (data.success) {
            successEl.textContent = 'Profiel bijgewerkt!';
            document.getElementById('profile-password').value = '';

            // Update sessie
            const currentUser = JSON.parse(sessionStorage.getItem('currentUser') || '{}');
            currentUser.voornaam = updateData.voornaam;
            currentUser.naam = updateData.naam;
            currentUser.email = updateData.email;
            currentUser.login = updateData.login;
            sessionStorage.setItem('currentUser', JSON.stringify(currentUser));

            // Update naam in header
            document.getElementById('user-name').textContent = `${updateData.voornaam} ${updateData.naam}`;
        } else {
            errorEl.textContent = data.error || 'Fout bij bijwerken profiel';
        }
    } catch (error) {
        console.error('Update profile error:', error);
        errorEl.textContent = 'Er is een fout opgetreden';
    }
}

// Helper functions
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('nl-BE', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

function formatTime(timeString) {
    if (!timeString) return '';
    return timeString.substring(0, 5);
}

function openInschrijvingFormulier(formName) {
    // Maak modal overlay
    const overlay = document.createElement('div');
    overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; display: flex; align-items: center; justify-content: center;';

    // Maak modal content container
    const modal = document.createElement('div');
    modal.style.cssText = 'background: white; border-radius: 10px; max-width: 650px; width: 85%; overflow: hidden; position: relative;';

    // Sluit knop
    const closeBtn = document.createElement('button');
    closeBtn.textContent = '×';
    closeBtn.style.cssText = 'position: absolute; top: 10px; right: 10px; background: white; border: 2px solid #ccc; border-radius: 50%; width: 40px; height: 40px; font-size: 24px; cursor: pointer; color: #666; z-index: 10; box-shadow: 0 2px 5px rgba(0,0,0,0.2);';
    closeBtn.onclick = () => document.body.removeChild(overlay);

    // Iframe met het formulier
    const iframe = document.createElement('iframe');
    iframe.src = `/form_${formName}.html`;
    iframe.style.cssText = 'width: 100%; height: 750px; border: none; display: block;';

    modal.appendChild(closeBtn);
    modal.appendChild(iframe);
    overlay.appendChild(modal);

    // Sluit bij klik op overlay
    overlay.onclick = (e) => {
        if (e.target === overlay) {
            document.body.removeChild(overlay);
        }
    };

    document.body.appendChild(overlay);
}