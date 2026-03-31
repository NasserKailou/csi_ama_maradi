/**
 * Système CSI AMA Maradi – JavaScript principal
 * jQuery + Bootstrap 5 + DataTables
 */

// ─── CSRF token pour toutes les requêtes AJAX ────────────────────────────────
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

$.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
    error(xhr) {
        const res = xhr.responseJSON;
        showToast('danger', res?.message || 'Erreur réseau. Veuillez réessayer.');
    }
});

// ─── Toast notifications ─────────────────────────────────────────────────────
function showToast(type, message, duration = 4000) {
    const colors = {
        success: '#2e7d32', danger: '#d32f2f',
        warning: '#f57f17', info:    '#0277bd'
    };
    const id = 'toast_' + Date.now();
    const html = `
        <div id="${id}" class="toast align-items-center text-white border-0 mb-2"
             style="background:${colors[type]||colors.info};min-width:280px;"
             role="alert" aria-live="assertive">
            <div class="d-flex">
                <div class="toast-body fw-semibold">${escHtml(message)}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto"
                        data-bs-dismiss="toast"></button>
            </div>
        </div>`;
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    container.insertAdjacentHTML('beforeend', html);
    const toastEl = document.getElementById(id);
    const toast = new bootstrap.Toast(toastEl, { delay: duration });
    toast.show();
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

// ─── Loader overlay ──────────────────────────────────────────────────────────
function showLoader() {
    let el = document.getElementById('spinnerOverlay');
    if (!el) {
        el = document.createElement('div');
        el.id = 'spinnerOverlay';
        el.className = 'spinner-overlay';
        el.innerHTML = '<div class="spinner-border text-success" style="width:3rem;height:3rem;" role="status"><span class="visually-hidden">Chargement...</span></div>';
        document.body.appendChild(el);
    }
    el.style.display = 'flex';
}
function hideLoader() {
    const el = document.getElementById('spinnerOverlay');
    if (el) el.style.display = 'none';
}

// ─── DataTables initialisation globale ────────────────────────────────────────
$(document).ready(function () {
    $('[data-datatable]').each(function () {
        $(this).DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/fr-FR.json'
            },
            pageLength: 25,
            responsive: true,
            dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>rtip'
        });
    });
});

// ─── Autocomplete téléphone ────────────────────────────────────────────────────
function initPhoneAutocomplete(inputId, onSelect) {
    const input = document.getElementById(inputId);
    if (!input) return;

    let listEl = null;
    let debounceTimer;

    input.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const val = this.value.replace(/\D/g, '');
        if (val.length < 3) { closeList(); return; }

        debounceTimer = setTimeout(() => {
            $.getJSON('/modules/api/patients.php', { q: val, csrf_token: CSRF_TOKEN })
                .done(data => renderList(data))
                .fail(() => closeList());
        }, 250);
    });

    function renderList(patients) {
        closeList();
        if (!patients.length) return;

        listEl = document.createElement('div');
        listEl.className = 'autocomplete-list';

        patients.forEach(p => {
            const item = document.createElement('div');
            item.className = 'ac-item';
            item.innerHTML = `<strong>${escHtml(p.nom)}</strong> <small class="text-muted">${escHtml(p.telephone)}</small>`;
            item.addEventListener('click', () => {
                input.value = p.telephone;
                closeList();
                if (typeof onSelect === 'function') onSelect(p);
            });
            listEl.appendChild(item);
        });

        const wrapper = input.closest('.position-relative') || input.parentElement;
        wrapper.style.position = 'relative';
        wrapper.appendChild(listEl);
    }

    function closeList() {
        if (listEl) { listEl.remove(); listEl = null; }
    }

    document.addEventListener('click', e => {
        if (!input.contains(e.target)) closeList();
    });
}

// ─── Formater montant ──────────────────────────────────────────────────────────
function formatMontant(n) {
    return new Intl.NumberFormat('fr-FR').format(n) + ' F';
}

// ─── Submit AJAX générique ────────────────────────────────────────────────────
function ajaxPost(url, data, onSuccess) {
    showLoader();
    $.post(url, { ...data, csrf_token: CSRF_TOKEN })
        .done(res => {
            hideLoader();
            if (res.success) {
                showToast('success', res.message || 'Opération réussie');
                if (typeof onSuccess === 'function') onSuccess(res);
            } else {
                showToast('danger', res.message || 'Une erreur est survenue');
            }
        })
        .fail(() => { hideLoader(); showToast('danger', 'Erreur de connexion'); });
}
