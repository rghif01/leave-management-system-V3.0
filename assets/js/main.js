/**
 * APM Leave Management - Main JavaScript
 */

const APM = {
    csrfToken: () => document.getElementById('csrfToken')?.value || '',
    
    // AJAX helper
    async fetch(url, data = {}, method = 'POST') {
        try {
            const body = method === 'GET' ? null : JSON.stringify({ ...data, csrf_token: this.csrfToken() });
            const res = await fetch(url, {
                method,
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body
            });
            return await res.json();
        } catch (e) {
            console.error('AJAX error:', e);
            return { success: false, message: 'Network error. Please try again.' };
        }
    },

    // Show toast notification
    toast(message, type = 'info') {
        const container = document.getElementById('toastContainer') || (() => {
            const d = document.createElement('div');
            d.id = 'toastContainer';
            d.className = 'toast-container position-fixed top-0 end-0 p-3';
            d.style.zIndex = '9999';
            document.body.appendChild(d);
            return d;
        })();

        const icons = { success: '✅', danger: '❌', warning: '⚠️', info: 'ℹ️' };
        const id = 'toast_' + Date.now();
        container.insertAdjacentHTML('beforeend', `
            <div id="${id}" class="toast align-items-center text-bg-${type} border-0 show" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${icons[type] || ''} ${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>`);
        const el = document.getElementById(id);
        const t = new bootstrap.Toast(el, { delay: 4000 });
        t.show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    },

    // Confirm dialog
    confirm(message) {
        return window.confirm(message);
    },

    // Format date to display
    formatDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    },

    // Calculate days between dates (approximate for display)
    daysBetween(start, end) {
        if (!start || !end) return 0;
        const s = new Date(start), e = new Date(end);
        return Math.max(0, Math.round((e - s) / 86400000) + 1);
    },

    // Form serialize
    serializeForm(form) {
        const data = {};
        new FormData(form).forEach((v, k) => data[k] = v);
        return data;
    }
};

// Auto-close alerts
document.addEventListener('DOMContentLoaded', () => {
    // Auto-dismiss alerts after 5 seconds
    document.querySelectorAll('.alert:not(.alert-permanent)').forEach(el => {
        setTimeout(() => {
            const alert = bootstrap.Alert.getOrCreateInstance(el);
            if (alert) alert.close();
        }, 5000);
    });

    // Confirm delete buttons
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', e => {
            if (!APM.confirm(btn.dataset.confirm || 'Are you sure?')) e.preventDefault();
        });
    });

    // Auto-calculate leave days
    const startInput = document.getElementById('start_date');
    const endInput = document.getElementById('end_date');
    const daysDisplay = document.getElementById('calculated_days');
    
    if (startInput && endInput && daysDisplay) {
        const calcDays = () => {
            const s = startInput.value, e = endInput.value;
            if (s && e && s <= e) {
                fetch(`/APM/api/calculate_days.php?start=${s}&end=${e}&csrf=${APM.csrfToken()}`)
                    .then(r => r.json())
                    .then(data => {
                        daysDisplay.textContent = data.days + ' working day(s)';
                        daysDisplay.className = 'text-primary fw-bold';
                    });
            }
        };
        startInput.addEventListener('change', calcDays);
        endInput.addEventListener('change', calcDays);
    }

    // Search/filter tables
    const searchInput = document.getElementById('tableSearch');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.toLowerCase();
            document.querySelectorAll('table tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
            });
        });
    }
});

// PWA Install
let deferredPrompt;
window.addEventListener('beforeinstallprompt', e => {
    e.preventDefault();
    deferredPrompt = e;
    const btn = document.getElementById('pwaInstall');
    if (btn) btn.style.display = 'block';
});

document.getElementById('pwaInstall')?.addEventListener('click', () => {
    if (deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(() => {
            deferredPrompt = null;
            document.getElementById('pwaInstall').style.display = 'none';
        });
    }
});
