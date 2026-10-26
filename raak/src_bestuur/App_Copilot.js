// Admin Panel JavaScript

// State
let isAuthenticated = false;
let currentPage = 'dashboard';

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    checkAuth();
    setupEventListeners();
});

// Check authentication
function checkAuth() {
    const token = sessionStorage.getItem('adminToken');

    if (token === 'authenticated') {
        isAuthenticated = true;
        showAdminPanel();
        loadDashboardStats();
    } else {
        showLogin();
    }
}

// Event listeners
function setupEventListeners() {
    // Login form
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }

    // Logout button
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', handleLogout);
    }

    // Navigation links
    const navLinks = document.querySelectorAll('.nav-link');
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
    e.preventDefault();

    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const errorEl = document.getElementById('login-error');

    // TODO: Vervang dit met echte API call naar PHP backend
    // Voor nu: hardcoded check (NIET VEILIG - alleen voor development)
    if (username === 'admin' && password === 'raak2025') {
        sessionStorage.setItem('adminToken', 'authenticated');
        sessionStorage.setItem('adminUser', username);
        isAuthenticated = true;
        errorEl.textContent = '';
        showAdminPanel();
        loadDashboardStats();
    } else {
        errorEl.textContent = 'Ongeldige gebruikersnaam of wachtwoord';
        setTimeout(() => {
            errorEl.textContent = '';
        }, 3000);
    }
}

// Handle logout
function handleLogout() {
    if (confirm('Weet je zeker dat je wilt uitloggen?')) {
        sessionStorage.removeItem('adminToken');
        sessionStorage.removeItem('adminUser');
        isAuthenticated = false;
        showLogin();
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

    const userName = sessionStorage.getItem('adminUser') || 'Admin';
    document.getElementById('user-name').textContent = userName;
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
        case 'berichten':
            loadBerichten();
            break;
        case 'fotos':
            loadFotos();
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
        const futureActivities = activities.filter(a => a.status === 'future');
        document.getElementById('stat-activities').textContent = futureActivities.length;

        // Haal berichten op (TODO: maak PHP endpoint)
        // Voor nu: placeholder
        document.getElementById('stat-messages').textContent = '0';

        // Haal foto's op
        const photosRes = await fetch('/php/pictures.php');
        const photos = await photosRes.json();
        document.getElementById('stat-photos').textContent = photos.length;

        // Haal folders op
        const foldersRes = await fetch('/php/folders.php');
        const folders = await foldersRes.json();
        document.getElementById('stat-folders').textContent = folders.length;

    } catch (error) {
        console.error('Error loading dashboard stats:', error);
    }
}

// Load activiteiten (placeholder)
function loadActiviteiten() {
    console.log('Loading activiteiten...');
    // TODO: Implementeer activiteiten beheer
}

// Load berichten (placeholder)
function loadBerichten() {
    console.log('Loading berichten...');
    // TODO: Implementeer berichten beheer
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
