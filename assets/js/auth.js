/**
 * Auth JS - Login y Registro para StreamingApp
 */

document.addEventListener('DOMContentLoaded', function () {
    createParticles();
    initTabs();
    initForms();
    initPasswordToggles();
});

/* ── Partículas de fondo ── */
function createParticles() {
    const container = document.querySelector('.particles');
    if (!container) return;
    for (let i = 0; i < 20; i++) {
        const p = document.createElement('div');
        p.className = 'particle';
        p.style.left = Math.random() * 100 + '%';
        p.style.width = (Math.random() * 4 + 2) + 'px';
        p.style.height = p.style.width;
        p.style.animationDuration = (Math.random() * 15 + 10) + 's';
        p.style.animationDelay = (Math.random() * 10) + 's';
        container.appendChild(p);
    }
}

/* ── Tabs ── */
function initTabs() {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.dataset.tab;
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(target).classList.add('active');
            clearAlerts();
        });
    });
}

/* ── Toggle contraseña ── */
function initPasswordToggles() {
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = btn.previousElementSibling;
            const isText = input.type === 'text';
            input.type = isText ? 'password' : 'text';
            btn.querySelector('i').className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
        });
    });
}

/* ── Formularios ── */
function initForms() {
    document.getElementById('loginForm').addEventListener('submit', handleLogin);
    document.getElementById('registerForm').addEventListener('submit', handleRegister);
}

/* ── Login ── */
async function handleLogin(e) {
    e.preventDefault();
    const btn = document.getElementById('loginBtn');
    const username = document.getElementById('loginUsername').value.trim();
    const password = document.getElementById('loginPassword').value.trim();

    if (!username || !password) {
        showAlert('login', 'Por favor ingresa usuario y contraseña.', 'error');
        return;
    }

    setLoading(btn, true);
    clearAlerts();

    try {
        const res = await fetch('api/auth.php?action=login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });
        const data = await res.json();

        if (data.success) {
            showAlert('login', '¡Bienvenido! Redirigiendo...', 'success');
            setTimeout(() => { window.location.href = 'index.html'; }, 800);
        } else {
            showAlert('login', data.message || 'Credenciales incorrectas.', 'error');
            setLoading(btn, false);
        }
    } catch (err) {
        showAlert('login', 'Error de conexión. Intenta de nuevo.', 'error');
        setLoading(btn, false);
    }
}

/* ── Registro ── */
async function handleRegister(e) {
    e.preventDefault();
    const btn = document.getElementById('registerBtn');
    const nombre = document.getElementById('regNombre').value.trim();
    const username = document.getElementById('regUsername').value.trim();
    const email = document.getElementById('regEmail').value.trim();
    const password = document.getElementById('regPassword').value.trim();
    const confirm = document.getElementById('regConfirm').value.trim();

    if (!nombre || !username || !email || !password || !confirm) {
        showAlert('register', 'Todos los campos son requeridos.', 'error');
        return;
    }

    if (password !== confirm) {
        showAlert('register', 'Las contraseñas no coinciden.', 'error');
        return;
    }

    if (password.length < 6) {
        showAlert('register', 'La contraseña debe tener al menos 6 caracteres.', 'error');
        return;
    }

    setLoading(btn, true);
    clearAlerts();

    try {
        const res = await fetch('api/auth.php?action=register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nombre, username, email, password })
        });
        const data = await res.json();

        if (data.success) {
            showAlert('register', data.message, 'success');
            document.getElementById('registerForm').reset();
            // Cambiar a tab de login después de 2 segundos
            setTimeout(() => {
                document.querySelector('[data-tab="loginPanel"]').click();
                showAlert('login', 'Registro exitoso. Ahora puedes iniciar sesión.', 'success');
            }, 2000);
        } else {
            showAlert('register', data.message || 'Error al registrar.', 'error');
        }
    } catch (err) {
        showAlert('register', 'Error de conexión. Intenta de nuevo.', 'error');
    } finally {
        setLoading(btn, false);
    }
}

/* ── Helpers ── */
function showAlert(panel, message, type) {
    const alertId = panel === 'login' ? 'loginAlert' : 'registerAlert';
    const el = document.getElementById(alertId);
    if (!el) return;
    el.className = `auth-alert ${type} show`;
    el.querySelector('.alert-msg').textContent = message;
}

function clearAlerts() {
    document.querySelectorAll('.auth-alert').forEach(el => {
        el.classList.remove('show');
    });
}

function setLoading(btn, loading) {
    btn.disabled = loading;
    btn.classList.toggle('loading', loading);
}
