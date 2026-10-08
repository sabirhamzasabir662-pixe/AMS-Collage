/**
 * script.js — Global JavaScript
 * Arsalan Model School and College Management System
 */

'use strict';

/* ── DOM Ready ────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initActiveMenu();
    initTableSearch();
    initDeleteConfirm();
    initFileUploadPreview();
    initRadioPills();
    initFormValidation();
    initAutoFade();
    initDateDefaults();
});

/* ── Sidebar Toggle ───────────────────────────────────────── */
function initSidebar() {
    const toggle  = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('appSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (!toggle || !sidebar) return;

    toggle.addEventListener('click', () => toggleSidebar());
    overlay?.addEventListener('click', () => closeSidebar());

    // Submenu toggles
    document.querySelectorAll('.nav-parent').forEach(parent => {
        parent.addEventListener('click', e => {
            e.preventDefault();
            const submenu = parent.nextElementSibling;
            const isOpen  = parent.classList.contains('open');
            // Close all
            document.querySelectorAll('.nav-parent.open').forEach(el => {
                el.classList.remove('open');
                el.nextElementSibling?.classList.remove('open');
            });
            // Open clicked
            if (!isOpen && submenu) {
                parent.classList.add('open');
                submenu.classList.add('open');
            }
        });
    });

    // Close sidebar on resize to desktop
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) closeSidebar();
    });
}

function toggleSidebar() {
    const sidebar = document.getElementById('appSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar?.classList.toggle('open');
    overlay?.classList.toggle('active');
}
function closeSidebar() {
    document.getElementById('appSidebar')?.classList.remove('open');
    document.getElementById('sidebarOverlay')?.classList.remove('active');
}

/* ── Active Menu Highlighting ─────────────────────────────── */
function initActiveMenu() {
    const current = window.location.pathname.split('/').pop();
    document.querySelectorAll('.sidebar .nav-link').forEach(link => {
        const href = link.getAttribute('href') || '';
        if (href === current || href.includes(current)) {
            link.classList.add('active');
            // Open parent submenu
            const submenu = link.closest('.nav-submenu');
            if (submenu) {
                submenu.classList.add('open');
                submenu.previousElementSibling?.classList.add('open');
            }
        }
    });
}

/* ── Table Live Search ─────────────────────────────────────── */
function initTableSearch() {
    document.querySelectorAll('[data-search-table]').forEach(input => {
        const tableId = input.dataset.searchTable;
        const table   = document.getElementById(tableId);
        if (!table) return;
        const rows = table.querySelectorAll('tbody tr');

        input.addEventListener('input', () => {
            const q = input.value.toLowerCase().trim();
            let visible = 0;
            rows.forEach(row => {
                const match = row.textContent.toLowerCase().includes(q);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            // Update count badge
            const countEl = document.getElementById(tableId + 'Count');
            if (countEl) countEl.textContent = visible;
        });
    });
}

/* ── Delete Confirmation ───────────────────────────────────── */
function initDeleteConfirm() {
    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-confirm]');
        if (!btn) return;
        const msg = btn.dataset.confirm || 'Are you sure you want to delete this record? This action cannot be undone.';
        if (!confirm(msg)) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }
    });
}

/* ── File Upload Photo Preview ─────────────────────────────── */
function initFileUploadPreview() {
    document.querySelectorAll('[data-photo-input]').forEach(input => {
        const previewId = input.dataset.photoInput;
        const preview   = document.getElementById(previewId);
        if (!preview) return;

        input.addEventListener('change', () => {
            const file = input.files[0];
            if (!file) return;
            // Validate type
            if (!['image/jpeg','image/png','image/gif'].includes(file.type)) {
                showAlert('Only JPG, PNG, GIF files are allowed.', 'danger');
                input.value = '';
                return;
            }
            // Validate size (2MB)
            if (file.size > 2 * 1024 * 1024) {
                showAlert('File size must be under 2MB.', 'danger');
                input.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
            reader.readAsDataURL(file);
        });

        // Click on area triggers input
        const area = input.closest('.file-upload-area');
        area?.addEventListener('click', () => input.click());
    });
}

/* ── Radio Pill Styling ────────────────────────────────────── */
function initRadioPills() {
    document.querySelectorAll('.radio-pill input').forEach(radio => {
        radio.addEventListener('change', () => {
            // Handled by CSS :has() — just trigger repaint
        });
    });
}

/* ── Form Validation ───────────────────────────────────────── */
function initFormValidation() {
    document.querySelectorAll('form[data-validate]').forEach(form => {
        form.addEventListener('submit', function(e) {
            let valid = true;
            form.querySelectorAll('[required]').forEach(field => {
                if (!field.value.trim()) {
                    markInvalid(field);
                    valid = false;
                } else {
                    markValid(field);
                }
            });
            // Email validation
            form.querySelectorAll('input[type="email"]').forEach(field => {
                if (field.value && !isValidEmail(field.value)) {
                    markInvalid(field, 'Invalid email address.');
                    valid = false;
                }
            });
            if (!valid) {
                e.preventDefault();
                showAlert('Please fill all required fields correctly.', 'danger');
                // Scroll to first error
                const firstErr = form.querySelector('.is-invalid');
                firstErr?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        // Live validation on blur
        form.querySelectorAll('[required]').forEach(field => {
            field.addEventListener('blur', () => {
                if (!field.value.trim()) markInvalid(field);
                else markValid(field);
            });
            field.addEventListener('input', () => {
                if (field.value.trim()) markValid(field);
            });
        });
    });
}

function markInvalid(field, msg) {
    field.classList.add('is-invalid');
    field.classList.remove('is-valid');
    let fb = field.parentElement.querySelector('.invalid-feedback');
    if (!fb) {
        fb = document.createElement('div');
        fb.className = 'invalid-feedback';
        field.parentElement.appendChild(fb);
    }
    fb.style.display = 'block';
    fb.textContent = msg || 'This field is required.';
}
function markValid(field) {
    field.classList.remove('is-invalid');
    const fb = field.parentElement.querySelector('.invalid-feedback');
    if (fb) fb.style.display = 'none';
}
function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

/* ── Auto-fade Alerts ──────────────────────────────────────── */
function initAutoFade() {
    document.querySelectorAll('.alert').forEach(alert => {
        if (alert.classList.contains('alert-permanent')) return;
        setTimeout(() => {
            alert.style.transition = 'opacity .5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
}

/* ── Dynamic Alert ─────────────────────────────────────────── */
function showAlert(msg, type = 'info') {
    const div = document.createElement('div');
    div.className = `alert alert-${type} alert-dismissible fade show`;
    div.role = 'alert';
    div.innerHTML = msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    const container = document.getElementById('alertContainer') || document.querySelector('.page-body');
    if (container) container.prepend(div);
    setTimeout(() => { div.style.opacity = '0'; setTimeout(() => div.remove(), 500); }, 5000);
}

/* ── Default Dates ─────────────────────────────────────────── */
function initDateDefaults() {
    const today = new Date().toISOString().split('T')[0];
    document.querySelectorAll('[data-default-today]').forEach(input => {
        if (!input.value) input.value = today;
    });
}

/* ── Fee Auto-fill Class ───────────────────────────────────── */
function autoFillStudentClass() {
    const sel = document.getElementById('feeStudentSelect');
    const classInput = document.getElementById('feeClassInput');
    if (!sel || !classInput) return;
    sel.addEventListener('change', () => {
        const opt = sel.options[sel.selectedIndex];
        if (opt && opt.dataset.classId) {
            classInput.value = opt.dataset.className || '';
        }
    });
}

/* ── Load Students for Attendance ─────────────────────────── */
function loadStudentsForAttendance() {
    const form = document.getElementById('loadStudentsForm');
    if (!form) return;
    // Handled server-side via GET; this just shows a loading state
    form.addEventListener('submit', () => {
        const btn = form.querySelector('[type="submit"]');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-ams d-inline-block me-2"></span> Loading...';
        }
    });
}

/* ── Select-All Attendance ─────────────────────────────────── */
function initSelectAllAttendance() {
    const presentAll = document.getElementById('markAllPresent');
    const absentAll  = document.getElementById('markAllAbsent');
    if (presentAll) {
        presentAll.addEventListener('click', () => {
            document.querySelectorAll('.att-present-radio').forEach(r => { r.checked = true; });
        });
    }
    if (absentAll) {
        absentAll.addEventListener('click', () => {
            document.querySelectorAll('.att-absent-radio').forEach(r => { r.checked = true; });
        });
    }
}
document.addEventListener('DOMContentLoaded', () => {
    autoFillStudentClass();
    loadStudentsForAttendance();
    initSelectAllAttendance();
});

/* ── Print page ────────────────────────────────────────────── */
function printPage() { window.print(); }

/* ── Confirm delete inline (for href-based links) ─────────── */
function confirmDelete(url, name) {
    if (confirm(`Delete "${name}"?\n\nThis action cannot be undone.`)) {
        window.location.href = url;
    }
}
