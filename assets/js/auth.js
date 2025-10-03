/**
 * JavaScript para manejo de autenticación
 * Login y Registro de usuarios
 */

// Configuración de la API
const API_BASE_URL = 'api/auth.php';

// Estado de la aplicación
let isLoading = false;

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    initAuth();
});

/**
 * Inicializar funcionalidades de autenticación
 */
function initAuth() {
    // Verificar si ya está autenticado
    checkAuthStatus();
    
    // Configurar formularios
    setupForms();
    
    // Configurar eventos
    setupEventListeners();
}

/**
 * Utilidad: obtener parámetro de querystring
 */
function getQueryParam(name) {
    const params = new URLSearchParams(window.location.search);
    return params.get(name);
}

/**
 * Verificar estado de autenticación
 */
async function checkAuthStatus() {
    try {
        const response = await fetch(`${API_BASE_URL}?action=check`);
        const data = await response.json();
        
        if (data.success) {
            // Usuario ya autenticado, redirigir al dashboard si está en login/registro
            if (window.location.pathname.includes('login.html') || 
                window.location.pathname.includes('register.html')) {
                window.location.href = 'index.html';
            }
            return true;
        } else {
            // Si no está autenticado y estamos en el index, redirigir a login
            if (isIndexPage()) {
                window.location.href = 'login.html';
            }
            return false;
        }
    } catch (error) {
        // Si falla la comprobación (p. ej. 401), redirigir a login desde el index
        if (isIndexPage()) {
            window.location.href = 'login.html';
        }
        return false;
    }
}

function isIndexPage() {
    const path = window.location.pathname;
    // Considerar tanto '/index.html' como '/' (raíz) como página principal
    return path.endsWith('/') || path.endsWith('/index.html') || path === 'index.html';
}

/**
 * Configurar formularios
 */
function setupForms() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const forgotForm = document.getElementById('forgotForm');
    const resetForm = document.getElementById('resetForm');
    
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
    
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegister);
    }

    if (forgotForm) {
        forgotForm.addEventListener('submit', handleForgotPassword);
    }

    if (resetForm) {
        // Pre-cargar token desde la URL
        const token = getQueryParam('token');
        const tokenInput = document.getElementById('resetToken');
        if (tokenInput) tokenInput.value = token || '';
        resetForm.addEventListener('submit', handleResetPassword);
    }
}

/**
 * Configurar event listeners
 */
function setupEventListeners() {
    // Validación en tiempo real para el formulario de registro
    const usernameInput = document.getElementById('username');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    
    if (usernameInput) {
        usernameInput.addEventListener('input', validateUsername);
    }
    
    if (emailInput) {
        emailInput.addEventListener('input', validateEmail);
    }
    
    if (passwordInput) {
        passwordInput.addEventListener('input', validatePassword);
    }
    
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', validatePasswordConfirmation);
    }
}

/**
 * Manejar login
 */
async function handleLogin(event) {
    event.preventDefault();
    
    if (isLoading) return;
    
    const formData = new FormData(event.target);
    const loginData = {
        username: formData.get('username').trim(),
        password: formData.get('password')
    };
    
    // Validaciones básicas
    if (!loginData.username || !loginData.password) {
        showAlert('Por favor completa todos los campos', 'error');
        return;
    }
    
    try {
        setLoading(true);
        
        const response = await fetch(`${API_BASE_URL}?action=login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(loginData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('¡Login exitoso! Redirigiendo...', 'success');
            
            // Guardar datos del usuario si se seleccionó "recordarme"
            const rememberMe = document.getElementById('remember').checked;
            if (rememberMe) {
                localStorage.setItem('rememberedUser', loginData.username);
            }
            
            // Redirigir después de un breve delay
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 1500);
        } else {
            showAlert(data.message || 'Error al iniciar sesión', 'error');
        }
        
    } catch (error) {
        console.error('Error en login:', error);
        showAlert('Error de conexión. Inténtalo de nuevo.', 'error');
    } finally {
        setLoading(false);
    }
}

/**
 * Manejar registro
 */
async function handleRegister(event) {
    event.preventDefault();
    
    if (isLoading) return;
    
    const formData = new FormData(event.target);
    const registerData = {
        username: formData.get('username').trim(),
        email: formData.get('email').trim(),
        password: formData.get('password'),
        nombre: formData.get('nombre').trim(),
        apellido: formData.get('apellido').trim(),
        telefono: formData.get('telefono').trim()
    };
    
    // Validaciones
    if (!validateRegistrationData(registerData)) {
        return;
    }
    
    // Verificar confirmación de contraseña
    const confirmPassword = formData.get('confirmPassword');
    if (registerData.password !== confirmPassword) {
        showAlert('Las contraseñas no coinciden', 'error');
        return;
    }
    
    // Verificar términos y condiciones
    if (!document.getElementById('terms').checked) {
        showAlert('Debes aceptar los términos y condiciones', 'error');
        return;
    }
    
    try {
        setLoading(true);
        
        const response = await fetch(`${API_BASE_URL}?action=register`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(registerData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('¡Cuenta creada exitosamente! Redirigiendo al login...', 'success');
            
            // Redirigir al login después de un breve delay
            setTimeout(() => {
                window.location.href = 'login.html';
            }, 2000);
        } else {
            showAlert(data.message || 'Error al crear la cuenta', 'error');
        }
        
    } catch (error) {
        console.error('Error en registro:', error);
        showAlert('Error de conexión. Inténtalo de nuevo.', 'error');
    } finally {
        setLoading(false);
    }
}

/**
 * Validar datos de registro
 */
function validateRegistrationData(data) {
    if (!data.username || !data.email || !data.password || !data.nombre || !data.apellido) {
        showAlert('Por favor completa todos los campos obligatorios', 'error');
        return false;
    }
    
    if (data.username.length < 3) {
        showAlert('El nombre de usuario debe tener al menos 3 caracteres', 'error');
        return false;
    }
    
    if (!isValidEmail(data.email)) {
        showAlert('Por favor ingresa un email válido', 'error');
        return false;
    }
    
    if (data.password.length < 6) {
        showAlert('La contraseña debe tener al menos 6 caracteres', 'error');
        return false;
    }
    
    return true;
}

/**
 * Validaciones en tiempo real
 */
function validateUsername() {
    const input = document.getElementById('username');
    const value = input.value.trim();
    
    if (value.length > 0 && value.length < 3) {
        setFieldError(input, 'Mínimo 3 caracteres');
    } else if (value.length > 0 && !/^[a-zA-Z0-9_-]+$/.test(value)) {
        setFieldError(input, 'Solo letras, números, guiones y guiones bajos');
    } else {
        clearFieldError(input);
    }
}

function validateEmail() {
    const input = document.getElementById('email');
    const value = input.value.trim();
    
    if (value.length > 0 && !isValidEmail(value)) {
        setFieldError(input, 'Email no válido');
    } else {
        clearFieldError(input);
    }
}

function validatePassword() {
    const input = document.getElementById('password');
    const value = input.value;
    
    if (value.length > 0 && value.length < 6) {
        setFieldError(input, 'Mínimo 6 caracteres');
    } else {
        clearFieldError(input);
    }
    
    // También validar confirmación si existe
    const confirmInput = document.getElementById('confirmPassword');
    if (confirmInput && confirmInput.value) {
        validatePasswordConfirmation();
    }
}

function validatePasswordConfirmation() {
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirmPassword');
    
    if (confirmInput.value && passwordInput.value !== confirmInput.value) {
        setFieldError(confirmInput, 'Las contraseñas no coinciden');
    } else {
        clearFieldError(confirmInput);
    }
}

/**
 * Utilidades para validación de campos
 */
function setFieldError(input, message) {
    input.style.borderColor = '#dc3545';
    
    // Remover mensaje anterior si existe
    const existingError = input.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
    
    // Agregar nuevo mensaje
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.style.color = '#dc3545';
    errorDiv.style.fontSize = '0.8rem';
    errorDiv.style.marginTop = '4px';
    errorDiv.textContent = message;
    
    input.parentNode.appendChild(errorDiv);
}

function clearFieldError(input) {
    input.style.borderColor = '';
    
    const existingError = input.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
}

/**
 * Validar email
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Mostrar/ocultar contraseña
 */
function togglePassword(fieldId = 'password') {
    const input = document.getElementById(fieldId);
    const icon = fieldId === 'password' ? 
        document.getElementById('toggleIcon') || document.getElementById('toggleIcon1') :
        document.getElementById('toggleIcon2');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

/**
 * Mostrar alerta
 */
function showAlert(message, type = 'error') {
    const alertDiv = document.getElementById('alertMessage');
    const alertText = document.getElementById('alertText');
    const alertIcon = alertDiv.querySelector('i');
    
    // Configurar tipo de alerta
    alertDiv.className = `alert ${type}`;
    
    switch (type) {
        case 'success':
            alertIcon.className = 'fas fa-check-circle';
            break;
        case 'info':
            alertIcon.className = 'fas fa-info-circle';
            break;
        default:
            alertIcon.className = 'fas fa-exclamation-circle';
    }
    
    alertText.textContent = message;
    alertDiv.style.display = 'flex';
    
    // Auto-ocultar después de 5 segundos
    setTimeout(hideAlert, 5000);
}

/**
 * Ocultar alerta
 */
function hideAlert() {
    const alertDiv = document.getElementById('alertMessage');
    alertDiv.style.display = 'none';
}

/**
 * Manejar estado de carga
 */
function setLoading(loading) {
    isLoading = loading;
    const overlay = document.getElementById('loadingOverlay');
    const loginBtn = document.getElementById('loginBtn');
    const registerBtn = document.getElementById('registerBtn');
    const forgotBtn = document.getElementById('forgotBtn');
    const resetBtn = document.getElementById('resetBtn');
    
    if (loading) {
        overlay.style.display = 'flex';
        
        if (loginBtn) {
            loginBtn.disabled = true;
            loginBtn.classList.add('loading');
            const icon = loginBtn.querySelector('i');
            if (icon) icon.className = 'fas fa-spinner fa-spin';
        }
        
        if (registerBtn) {
            registerBtn.disabled = true;
            registerBtn.classList.add('loading');
            const icon = registerBtn.querySelector('i');
            if (icon) icon.className = 'fas fa-spinner fa-spin';
        }
        if (forgotBtn) {
            forgotBtn.disabled = true;
            const icon = forgotBtn.querySelector('i');
            if (icon) icon.className = 'fas fa-spinner fa-spin';
        }
        if (resetBtn) {
            resetBtn.disabled = true;
            const icon = resetBtn.querySelector('i');
            if (icon) icon.className = 'fas fa-spinner fa-spin';
        }
    } else {
        overlay.style.display = 'none';
        
        if (loginBtn) {
            loginBtn.disabled = false;
            loginBtn.classList.remove('loading');
            const icon = loginBtn.querySelector('i');
            if (icon) icon.className = 'fas fa-sign-in-alt';
        }
        
        if (registerBtn) {
            registerBtn.disabled = false;
            registerBtn.classList.remove('loading');
            const icon = registerBtn.querySelector('i');
            if (icon) icon.className = 'fas fa-user-plus';
        }
        if (forgotBtn) {
            forgotBtn.disabled = false;
            const icon = forgotBtn.querySelector('i');
            if (icon) icon.className = 'fas fa-paper-plane';
        }
        if (resetBtn) {
            resetBtn.disabled = false;
            const icon = resetBtn.querySelector('i');
            if (icon) icon.className = 'fas fa-key';
        }
    }
}

/**
 * Cargar usuario recordado
 */
function loadRememberedUser() {
    const rememberedUser = localStorage.getItem('rememberedUser');
    if (rememberedUser) {
        const usernameInput = document.getElementById('username');
        const rememberCheckbox = document.getElementById('remember');
        
        if (usernameInput) {
            usernameInput.value = rememberedUser;
        }
        
        if (rememberCheckbox) {
            rememberCheckbox.checked = true;
        }
    }
}

// Cargar usuario recordado al cargar la página de login
if (window.location.pathname.includes('login.html')) {
    document.addEventListener('DOMContentLoaded', loadRememberedUser);
}

/**
 * Logout (para usar desde otras páginas)
 */
async function logout() {
    try {
        const response = await fetch(`${API_BASE_URL}?action=logout`, {
            method: 'POST'
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Limpiar localStorage
            localStorage.removeItem('rememberedUser');
            
            // Redirigir al login
            window.location.href = 'login.html';
        }
    } catch (error) {
        console.error('Error en logout:', error);
        // Forzar redirección incluso si hay error
        window.location.href = 'login.html';
    }
}

// Exponer funciones globales
window.togglePassword = togglePassword;
window.hideAlert = hideAlert;
window.logout = logout;
window.checkAuthStatus = checkAuthStatus;
window.openProfileModal = openProfileModal;
window.saveProfile = saveProfile;
window.sendEmailVerification = sendEmailVerification;
window.runEmailVerification = runEmailVerification;
