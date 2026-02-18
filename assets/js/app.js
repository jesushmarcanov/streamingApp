// Configuración de la API
const API_BASE = 'api/';

// Variables globales
let currentSection = 'dashboard';
let clientes = [];
let proveedores = [];
let servicios = [];
let notificaciones = [];

// Variables para ordenamiento
let sortConfig = {
    clientes: { field: null, direction: 'asc' },
    proveedores: { field: null, direction: 'asc' },
    servicios: { field: null, direction: 'asc' },
    notificaciones: { field: null, direction: 'asc' }
};

// Inicialización de la aplicación
document.addEventListener('DOMContentLoaded', async function () {
    // Verificar sesión antes de cargar la app
    try {
        const res = await fetch(API_BASE + 'auth.php?action=check');
        const data = await res.json();

        if (!data.authenticated) {
            window.location.href = 'login.html';
            return;
        }

        // Mostrar info del usuario en el sidebar
        const user = data.user;
        const nameEl = document.getElementById('sidebarUserName');
        const roleEl = document.getElementById('sidebarUserRole');
        if (nameEl) nameEl.textContent = user.nombre;
        if (roleEl) {
            roleEl.textContent = user.rol === 'admin' ? 'Admin' : 'Usuario';
            roleEl.className = 'sidebar-user-role' + (user.rol === 'admin' ? '' : ' role-usuario');
        }

    } catch (err) {
        console.error('Error verificando sesión:', err);
        window.location.href = 'login.html';
        return;
    }

    // Cargar datos de la aplicación
    loadDashboard();
    loadClientes();
    loadProveedores();
    loadServicios();
    loadNotificaciones();
});

// Cerrar sesión
async function logout() {
    try {
        await fetch(API_BASE + 'auth.php?action=logout', { method: 'POST' });
    } catch (e) { /* ignorar errores de red */ }
    window.location.href = 'login.html';
}


// Navegación entre secciones
function showSection(sectionName) {
    // Ocultar todas las secciones
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.remove('active');
    });

    // Remover clase active de todos los enlaces del menú
    document.querySelectorAll('.sidebar-menu a').forEach(link => {
        link.classList.remove('active');
    });

    // Mostrar la sección seleccionada
    document.getElementById(sectionName).classList.add('active');

    // Agregar clase active al enlace correspondiente
    event.target.classList.add('active');

    currentSection = sectionName;

    // Cargar datos específicos de la sección
    switch (sectionName) {
        case 'dashboard':
            loadDashboard();
            break;
        case 'clientes':
            loadClientes();
            break;
        case 'proveedores':
            loadProveedores();
            break;
        case 'servicios':
            loadServicios();
            break;
        case 'notificaciones':
            loadNotificaciones();
            break;
    }
}

// Funciones para el Dashboard
async function loadDashboard() {
    try {
        // Cargar estadísticas
        const [clientesRes, serviciosRes, serviciosVencerRes] = await Promise.all([
            fetch(API_BASE + 'clientes.php'),
            fetch(API_BASE + 'servicios.php'),
            fetch(API_BASE + 'servicios.php?proximos_vencer=1&dias=7')
        ]);

        const clientesData = await clientesRes.json();
        const serviciosData = await serviciosRes.json();
        const serviciosVencerData = await serviciosVencerRes.json();

        if (clientesData.success) {
            const clientesActivos = clientesData.data.filter(c => c.activo == 1);
            document.getElementById('total-clientes').textContent = clientesActivos.length;
        }

        if (serviciosData.success) {
            const serviciosActivos = serviciosData.data.filter(s => s.estado === 'Activo');
            document.getElementById('total-servicios').textContent = serviciosActivos.length;

            // Calcular ingresos mensuales
            const ingresos = serviciosActivos.reduce((total, servicio) => {
                return total + parseFloat(servicio.precio_mensual || 0);
            }, 0);
            document.getElementById('ingresos-mensuales').textContent = '$' + ingresos.toFixed(2);
        }

        if (serviciosVencerData.success) {
            document.getElementById('servicios-vencer').textContent = serviciosVencerData.data.length;
            displayServiciosVencer(serviciosVencerData.data);
        }

    } catch (error) {
        console.error('Error cargando dashboard:', error);
        showNotification('Error cargando el dashboard', 'error');
    }
}

function displayServiciosVencer(servicios) {
    const container = document.getElementById('servicios-vencer-list');

    if (servicios.length === 0) {
        container.innerHTML = '<p>No hay servicios próximos a vencer</p>';
        return;
    }

    const html = servicios.map(servicio => `
        <div class="servicio-item" style="padding: 15px; border: 1px solid #e9ecef; border-radius: 5px; margin-bottom: 10px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <strong>${servicio.cliente_nombre} ${servicio.cliente_apellido}</strong><br>
                    <small>${servicio.nombre_servicio} - ${servicio.tipo_servicio}</small>
                </div>
                <div style="text-align: right;">
                    <span class="status-badge status-${servicio.estado.toLowerCase()}">${servicio.estado}</span><br>
                    <small>Vence: ${formatDate(servicio.fecha_vencimiento)}</small>
                </div>
            </div>
        </div>
    `).join('');

    container.innerHTML = html;
}

// Funciones para Clientes
async function loadClientes() {
    try {
        const response = await fetch(API_BASE + 'clientes.php');
        const data = await response.json();

        if (data.success) {
            clientes = data.data;
            sortAndDisplayClientes();
        } else {
            showNotification('Error cargando clientes: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error cargando clientes:', error);
        showNotification('Error cargando clientes', 'error');
    }
}

function displayClientes(clientes) {
    const tbody = document.querySelector('#clientes-table tbody');

    if (clientes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7">No hay clientes registrados</td></tr>';
        return;
    }

    const html = clientes.map(cliente => `
        <tr>
            <td>${cliente.nombre}</td>
            <td>${cliente.apellido}</td>
            <td>${cliente.telefono}</td>
            <td>${cliente.email || '-'}</td>
            <td>
                <span class="status-badge status-${cliente.activo == 1 ? 'activo' : 'inactivo'}">
                    <i class="fas fa-${cliente.activo == 1 ? 'check-circle' : 'times-circle'}"></i>
                    ${cliente.activo == 1 ? 'Activo' : 'Inactivo'}
                </span>
            </td>
            <td>
                <button class="btn btn-warning btn-sm" onclick="editCliente(${cliente.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-danger btn-sm" onclick="deleteCliente(${cliente.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');

    tbody.innerHTML = html;
}

function openClienteModal(cliente = null) {
    const modal = document.getElementById('clienteModal');
    const title = document.getElementById('clienteModalTitle');
    const form = document.getElementById('clienteForm');

    form.reset();

    if (cliente) {
        title.textContent = 'Editar Cliente';
        document.getElementById('clienteId').value = cliente.id;
        document.getElementById('clienteNombre').value = cliente.nombre;
        document.getElementById('clienteApellido').value = cliente.apellido;
        document.getElementById('clienteTelefono').value = cliente.telefono;
        document.getElementById('clienteEmail').value = cliente.email || '';
        document.getElementById('clienteDireccion').value = cliente.direccion || '';
        document.getElementById('clienteStatus').value = cliente.activo;
    } else {
        title.textContent = 'Nuevo Cliente';
        document.getElementById('clienteStatus').value = '1'; // Default to active for new clients
    }

    modal.style.display = 'block';
}

function editCliente(id) {
    const cliente = clientes.find(c => c.id == id);
    if (cliente) {
        openClienteModal(cliente);
    }
}

async function saveCliente() {
    const form = document.getElementById('clienteForm');

    // Obtener datos del formulario manualmente para incluir campos vacíos
    const data = {
        nombre: document.getElementById('clienteNombre').value.trim(),
        apellido: document.getElementById('clienteApellido').value.trim(),
        telefono: document.getElementById('clienteTelefono').value.trim(),
        email: document.getElementById('clienteEmail').value.trim(),
        direccion: document.getElementById('clienteDireccion').value.trim(),
        activo: parseInt(document.getElementById('clienteStatus').value)
    };

    // Validar campos requeridos
    if (!data.nombre || !data.apellido || !data.telefono) {
        showNotification('Por favor completa todos los campos requeridos', 'error');
        return;
    }

    const id = document.getElementById('clienteId').value;
    const method = id ? 'PUT' : 'POST';
    const url = id ? `${API_BASE}clientes.php?id=${id}` : `${API_BASE}clientes.php`;

    try {
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showNotification(result.message, 'success');
            closeModal('clienteModal');
            loadClientes();
            loadDashboard(); // Actualizar dashboard
        } else {
            showNotification('Error: ' + result.message, 'error');
        }
    } catch (error) {
        console.error('Error guardando cliente:', error);
        showNotification('Error guardando cliente', 'error');
    }
}

async function deleteCliente(id) {
    if (!confirm('¿Estás seguro de que quieres eliminar este cliente?')) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE}clientes.php?id=${id}`, {
            method: 'DELETE'
        });

        const result = await response.json();

        if (result.success) {
            showNotification(result.message, 'success');
            loadClientes();
            loadDashboard(); // Actualizar dashboard
        } else {
            showNotification('Error: ' + result.message, 'error');
        }
    } catch (error) {
        console.error('Error eliminando cliente:', error);
        showNotification('Error eliminando cliente', 'error');
    }
}

// Funciones para Proveedores
async function loadProveedores() {
    try {
        const response = await fetch(API_BASE + 'proveedores.php');
        const data = await response.json();

        if (data.success) {
            proveedores = data.data;
            sortAndDisplayProveedores();
        } else {
            showNotification('Error cargando proveedores: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error cargando proveedores:', error);
        showNotification('Error cargando proveedores', 'error');
    }
}

function displayProveedores(proveedores) {
    const tbody = document.querySelector('#proveedores-table tbody');

    if (proveedores.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6">No hay proveedores registrados</td></tr>';
        return;
    }

    const html = proveedores.map(proveedor => `
        <tr>
            <td>${proveedor.nombre}</td>
            <td>${proveedor.contacto || '-'}</td>
            <td>${proveedor.telefono || '-'}</td>
            <td>${proveedor.email || '-'}</td>
            <td>
                <button class="btn btn-warning btn-sm" onclick="editProveedor(${proveedor.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-danger btn-sm" onclick="deleteProveedor(${proveedor.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');

    tbody.innerHTML = html;
}

function openProveedorModal(proveedor = null) {
    const modal = document.getElementById('proveedorModal');
    const title = document.getElementById('proveedorModalTitle');
    const form = document.getElementById('proveedorForm');

    form.reset();

    if (proveedor) {
        title.textContent = 'Editar Proveedor';
        document.getElementById('proveedorId').value = proveedor.id;
        document.getElementById('proveedorNombre').value = proveedor.nombre;
        document.getElementById('proveedorContacto').value = proveedor.contacto || '';
        document.getElementById('proveedorTelefono').value = proveedor.telefono || '';
        document.getElementById('proveedorEmail').value = proveedor.email || '';
        document.getElementById('proveedorDireccion').value = proveedor.direccion || '';
    } else {
        title.textContent = 'Nuevo Proveedor';
    }

    modal.style.display = 'block';
}

function editProveedor(id) {
    const proveedor = proveedores.find(p => p.id == id);
    if (proveedor) {
        openProveedorModal(proveedor);
    }
}

async function saveProveedor() {
    const form = document.getElementById('proveedorForm');

    // Obtener datos del formulario manualmente para incluir campos vacíos
    const data = {
        nombre: document.getElementById('proveedorNombre').value.trim(),
        contacto: document.getElementById('proveedorContacto').value.trim(),
        telefono: document.getElementById('proveedorTelefono').value.trim(),
        email: document.getElementById('proveedorEmail').value.trim(),
        direccion: document.getElementById('proveedorDireccion').value.trim()
    };

    // Validar campos requeridos
    if (!data.nombre) {
        showNotification('El nombre del proveedor es requerido', 'error');
        return;
    }

    const id = document.getElementById('proveedorId').value;
    const method = id ? 'PUT' : 'POST';
    const url = id ? `${API_BASE}proveedores.php?id=${id}` : `${API_BASE}proveedores.php`;

    try {
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showNotification(result.message, 'success');
            closeModal('proveedorModal');
            loadProveedores();
        } else {
            showNotification('Error: ' + result.message, 'error');
        }
    } catch (error) {
        console.error('Error guardando proveedor:', error);
        showNotification('Error guardando proveedor', 'error');
    }
}

async function deleteProveedor(id) {
    if (!confirm('¿Estás seguro de que quieres eliminar este proveedor?')) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE}proveedores.php?id=${id}`, {
            method: 'DELETE'
        });

        const result = await response.json();

        if (result.success) {
            showNotification(result.message, 'success');
            loadProveedores();
        } else {
            showNotification('Error: ' + result.message, 'error');
        }
    } catch (error) {
        console.error('Error eliminando proveedor:', error);
        showNotification('Error eliminando proveedor', 'error');
    }
}

// Funciones para Servicios
async function loadServicios() {
    try {
        const response = await fetch(API_BASE + 'servicios.php');
        const data = await response.json();

        if (data.success) {
            servicios = data.data;
            sortAndDisplayServicios();
        } else {
            showNotification('Error cargando servicios: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error cargando servicios:', error);
        showNotification('Error cargando servicios', 'error');
    }
}

function displayServicios(servicios) {
    const tbody = document.querySelector('#servicios-table tbody');

    if (servicios.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8">No hay servicios registrados</td></tr>';
        return;
    }

    const html = servicios.map(servicio => `
        <tr>
            <td>${servicio.cliente_nombre} ${servicio.cliente_apellido}</td>
            <td>${servicio.nombre_servicio}</td>
            <td>${servicio.tipo_servicio}</td>
            <td>$${parseFloat(servicio.precio_mensual).toFixed(2)}</td>
            <td>${formatDate(servicio.fecha_vencimiento)}</td>
            <td><span class="status-badge status-${servicio.estado.toLowerCase()}">${servicio.estado}</span></td>
            <td>
                <button class="btn btn-warning btn-sm" onclick="editServicio(${servicio.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-danger btn-sm" onclick="deleteServicio(${servicio.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');

    tbody.innerHTML = html;
}

function openServicioModal(servicio = null) {
    const modal = document.getElementById('servicioModal');
    const title = document.getElementById('servicioModalTitle');
    const form = document.getElementById('servicioForm');

    form.reset();

    // Cargar opciones de clientes y proveedores
    loadClienteOptions();
    loadProveedorOptions();

    if (servicio) {
        title.textContent = 'Editar Servicio';
        document.getElementById('servicioId').value = servicio.id;
        document.getElementById('servicioCliente').value = servicio.cliente_id;
        document.getElementById('servicioProveedor').value = servicio.proveedor_id;
        document.getElementById('servicioNombre').value = servicio.nombre_servicio;
        document.getElementById('servicioTipo').value = servicio.tipo_servicio;
        document.getElementById('servicioPrecio').value = servicio.precio_mensual;
        document.getElementById('servicioInicio').value = servicio.fecha_inicio;
        document.getElementById('servicioVencimiento').value = servicio.fecha_vencimiento;
        document.getElementById('servicioEstado').value = servicio.estado;
        document.getElementById('servicioObservaciones').value = servicio.observaciones || '';
    } else {
        title.textContent = 'Nuevo Servicio';
    }

    modal.style.display = 'block';
}

function loadClienteOptions() {
    const select = document.getElementById('servicioCliente');
    const options = clientes.map(cliente =>
        `<option value="${cliente.id}">${cliente.nombre} ${cliente.apellido}</option>`
    ).join('');
    select.innerHTML = '<option value="">Seleccionar cliente...</option>' + options;
}

function loadProveedorOptions() {
    const select = document.getElementById('servicioProveedor');
    const options = proveedores.map(proveedor =>
        `<option value="${proveedor.id}">${proveedor.nombre}</option>`
    ).join('');
    select.innerHTML = '<option value="">Seleccionar proveedor...</option>' + options;
}

function editServicio(id) {
    const servicio = servicios.find(s => s.id == id);
    if (servicio) {
        openServicioModal(servicio);
    }
}

async function saveServicio() {
    const form = document.getElementById('servicioForm');

    // Obtener datos del formulario manualmente para incluir campos vacíos
    const data = {
        cliente_id: document.getElementById('servicioCliente').value,
        proveedor_id: document.getElementById('servicioProveedor').value,
        nombre_servicio: document.getElementById('servicioNombre').value.trim(),
        tipo_servicio: document.getElementById('servicioTipo').value,
        precio_mensual: document.getElementById('servicioPrecio').value,
        fecha_inicio: document.getElementById('servicioInicio').value,
        fecha_vencimiento: document.getElementById('servicioVencimiento').value,
        estado: document.getElementById('servicioEstado').value,
        observaciones: document.getElementById('servicioObservaciones').value.trim()
    };

    // Validar campos requeridos
    if (!data.cliente_id || !data.proveedor_id || !data.nombre_servicio ||
        !data.tipo_servicio || !data.precio_mensual || !data.fecha_inicio ||
        !data.fecha_vencimiento) {
        showNotification('Por favor completa todos los campos requeridos', 'error');
        return;
    }

    // Convertir precio a número
    data.precio_mensual = parseFloat(data.precio_mensual);

    const id = document.getElementById('servicioId').value;
    const method = id ? 'PUT' : 'POST';
    const url = id ? `${API_BASE}servicios.php?id=${id}` : `${API_BASE}servicios.php`;

    try {
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showNotification(result.message, 'success');
            closeModal('servicioModal');
            loadServicios();
            loadDashboard(); // Actualizar dashboard
        } else {
            showNotification('Error: ' + result.message, 'error');
        }
    } catch (error) {
        console.error('Error guardando servicio:', error);
        showNotification('Error guardando servicio', 'error');
    }
}

async function deleteServicio(id) {
    if (!confirm('¿Estás seguro de que quieres eliminar este servicio?')) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE}servicios.php?id=${id}`, {
            method: 'DELETE'
        });

        const result = await response.json();

        if (result.success) {
            showNotification(result.message, 'success');
            loadServicios();
            loadDashboard(); // Actualizar dashboard
        } else {
            showNotification('Error: ' + result.message, 'error');
        }
    } catch (error) {
        console.error('Error eliminando servicio:', error);
        showNotification('Error eliminando servicio', 'error');
    }
}

// Funciones para Notificaciones
async function loadNotificaciones() {
    try {
        const response = await fetch(API_BASE + 'notificaciones.php?historial=1');
        const data = await response.json();

        if (data.success) {
            notificaciones = data.data;
            sortAndDisplayNotificaciones();
        } else {
            showNotification('Error cargando notificaciones: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error cargando notificaciones:', error);
        showNotification('Error cargando notificaciones', 'error');
    }
}

function displayNotificaciones(notificaciones) {
    const tbody = document.querySelector('#notificaciones-table tbody');

    if (notificaciones.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7">No hay notificaciones registradas</td></tr>';
        return;
    }

    const html = notificaciones.map(notificacion => `
        <tr>
            <td>${notificacion.id}</td>
            <td>${notificacion.cliente_nombre || '-'}</td>
            <td>${notificacion.nombre_servicio || '-'}</td>
            <td>${notificacion.tipo_notificacion}</td>
            <td>${notificacion.mensaje.length > 50 ? notificacion.mensaje.substring(0, 50) + '...' : notificacion.mensaje}</td>
            <td><span class="status-badge status-${notificacion.estado.toLowerCase()}">${notificacion.estado}</span></td>
            <td>${notificacion.fecha_envio ? formatDateTime(notificacion.fecha_envio) : '-'}</td>
        </tr>
    `).join('');

    tbody.innerHTML = html;
}

async function generarNotificaciones() {
    try {
        const response = await fetch(API_BASE + 'notificaciones.php?action=generar_automaticas', {
            method: 'POST'
        });

        const result = await response.json();

        if (result.success) {
            showNotification(result.message, 'success');
            loadNotificaciones();
            loadDashboard();
        } else {
            showNotification('Error: ' + result.message, 'error');
        }
    } catch (error) {
        console.error('Error generando notificaciones:', error);
        showNotification('Error generando notificaciones', 'error');
    }
}

async function enviarNotificacionesPendientes() {
    try {
        const response = await fetch(API_BASE + 'notificaciones.php?action=enviar_pendientes', {
            method: 'POST'
        });

        const result = await response.json();

        if (result.success) {
            showNotification(result.message, 'success');
            loadNotificaciones();
        } else {
            showNotification('Error: ' + result.message, 'error');
        }
    } catch (error) {
        console.error('Error enviando notificaciones:', error);
        showNotification('Error enviando notificaciones', 'error');
    }
}

// Funciones auxiliares
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES');
}

function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('es-ES');
}

function showNotification(message, type = 'info') {
    // Crear elemento de notificación
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 5px;
        color: white;
        font-weight: 500;
        z-index: 3000;
        animation: slideInRight 0.3s ease;
        max-width: 300px;
    `;

    // Estilos según el tipo
    switch (type) {
        case 'success':
            notification.style.backgroundColor = '#28a745';
            break;
        case 'error':
            notification.style.backgroundColor = '#dc3545';
            break;
        case 'warning':
            notification.style.backgroundColor = '#ffc107';
            notification.style.color = '#212529';
            break;
        default:
            notification.style.backgroundColor = '#17a2b8';
    }

    notification.textContent = message;
    document.body.appendChild(notification);

    // Remover después de 5 segundos
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 5000);
}

// Cerrar modales al hacer clic fuera de ellos
window.onclick = function (event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
}

// Funciones de ordenamiento
function sortTable(tableType, field) {
    const currentSort = sortConfig[tableType];

    // Determinar la dirección del ordenamiento
    if (currentSort.field === field) {
        currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
    } else {
        currentSort.field = field;
        currentSort.direction = 'asc';
    }

    // Actualizar iconos de ordenamiento
    updateSortIcons(tableType, field, currentSort.direction);

    // Ordenar y mostrar datos
    switch (tableType) {
        case 'clientes':
            sortAndDisplayClientes();
            break;
        case 'proveedores':
            sortAndDisplayProveedores();
            break;
        case 'servicios':
            sortAndDisplayServicios();
            break;
        case 'notificaciones':
            sortAndDisplayNotificaciones();
            break;
    }
}

function updateSortIcons(tableType, activeField, direction) {
    const table = document.getElementById(`${tableType}-table`);
    const headers = table.querySelectorAll('th.sortable');

    headers.forEach(header => {
        const icon = header.querySelector('i');
        const field = header.getAttribute('onclick').match(/sortTable\('[\w]+', '([^']+)'\)/)[1];

        if (field === activeField) {
            icon.className = direction === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
        } else {
            icon.className = 'fas fa-sort';
        }
    });
}

function sortAndDisplayClientes() {
    const sortedClientes = [...clientes];
    const { field, direction } = sortConfig.clientes;

    if (field) {
        sortedClientes.sort((a, b) => {
            let aVal = a[field] || '';
            let bVal = b[field] || '';

            // Para valores numéricos
            if (field === 'id' || field === 'telefono') {
                aVal = parseInt(aVal) || 0;
                bVal = parseInt(bVal) || 0;
            }

            // Para fechas
            if (field.includes('fecha')) {
                aVal = new Date(aVal);
                bVal = new Date(bVal);
            }

            if (direction === 'asc') {
                return aVal > bVal ? 1 : aVal < bVal ? -1 : 0;
            } else {
                return aVal < bVal ? 1 : aVal > bVal ? -1 : 0;
            }
        });
    }

    displayClientes(sortedClientes);
}

function sortAndDisplayProveedores() {
    const sortedProveedores = [...proveedores];
    const { field, direction } = sortConfig.proveedores;

    if (field) {
        sortedProveedores.sort((a, b) => {
            let aVal = a[field] || '';
            let bVal = b[field] || '';

            // Para valores numéricos
            if (field === 'id' || field === 'telefono') {
                aVal = parseInt(aVal) || 0;
                bVal = parseInt(bVal) || 0;
            }

            // Para fechas
            if (field.includes('fecha')) {
                aVal = new Date(aVal);
                bVal = new Date(bVal);
            }

            if (direction === 'asc') {
                return aVal > bVal ? 1 : aVal < bVal ? -1 : 0;
            } else {
                return aVal < bVal ? 1 : aVal > bVal ? -1 : 0;
            }
        });
    }

    displayProveedores(sortedProveedores);
}

function sortAndDisplayServicios() {
    const sortedServicios = [...servicios];
    const { field, direction } = sortConfig.servicios;

    if (field) {
        sortedServicios.sort((a, b) => {
            let aVal, bVal;

            // Mapear campos específicos para servicios
            switch (field) {
                case 'cliente_nombre':
                    aVal = `${a.cliente_nombre} ${a.cliente_apellido}`;
                    bVal = `${b.cliente_nombre} ${b.cliente_apellido}`;
                    break;
                case 'precio_mensual':
                    aVal = parseFloat(a.precio_mensual) || 0;
                    bVal = parseFloat(b.precio_mensual) || 0;
                    break;
                case 'fecha_vencimiento':
                    aVal = new Date(a.fecha_vencimiento);
                    bVal = new Date(b.fecha_vencimiento);
                    break;
                default:
                    aVal = a[field] || '';
                    bVal = b[field] || '';
            }

            if (direction === 'asc') {
                return aVal > bVal ? 1 : aVal < bVal ? -1 : 0;
            } else {
                return aVal < bVal ? 1 : aVal > bVal ? -1 : 0;
            }
        });
    }

    displayServicios(sortedServicios);
}

function sortAndDisplayNotificaciones() {
    const sortedNotificaciones = [...notificaciones];
    const { field, direction } = sortConfig.notificaciones;

    if (field) {
        sortedNotificaciones.sort((a, b) => {
            let aVal = a[field] || '';
            let bVal = b[field] || '';

            // Para valores numéricos
            if (field === 'id') {
                aVal = parseInt(aVal) || 0;
                bVal = parseInt(bVal) || 0;
            }

            // Para fechas
            if (field.includes('fecha')) {
                aVal = new Date(aVal);
                bVal = new Date(bVal);
            }

            if (direction === 'asc') {
                return aVal > bVal ? 1 : aVal < bVal ? -1 : 0;
            } else {
                return aVal < bVal ? 1 : aVal > bVal ? -1 : 0;
            }
        });
    }

    displayNotificaciones(sortedNotificaciones);
}

// Agregar estilos CSS para las animaciones de notificaciones y ordenamiento
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .sortable {
        cursor: pointer;
        user-select: none;
        position: relative;
    }
    
    .sortable:hover {
        background-color: #f8f9fa;
    }
    
    .sortable i {
        margin-left: 5px;
        opacity: 0.5;
        transition: opacity 0.2s;
    }
    
    .sortable:hover i {
        opacity: 0.8;
    }
    
    .sortable i.fas.fa-sort-up,
    .sortable i.fas.fa-sort-down {
        opacity: 1;
        color: #007bff;
    }
`;
document.head.appendChild(style);
