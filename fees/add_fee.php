<?php
/**
 * fees/add_fee.php — Add Fee Payment with Type-Ahead Student Search
 * Arsalan Model School and College Management System
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/fees_helper.php';
require_login();
require_permission('fees.add');
$user = current_user();

$page_title  = 'Add Fee';
$active_menu = 'fees_add';
$breadcrumb  = ['Fees', 'Add Fee'];
$depth       = '../';

// Fetch active students joined with classes using prepared statement
$students = fee_db_fetch_all(
    "SELECT s.id, s.student_id, s.first_name, s.last_name, c.id AS class_id, c.class_name, c.section
     FROM students s
     LEFT JOIN classes c ON s.class_id = c.id
     WHERE s.status = 'active'
     ORDER BY s.student_id ASC, s.first_name ASC",
    []
);

$months = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

$current_year  = (int)date('Y');
$current_month = date('F');
$years         = range($current_year - 1, $current_year + 3);

$errors = [];
$form   = [
    'student_id'     => '',
    'class_id'       => '',
    'fee_month'      => $current_month,
    'fee_year'       => $current_year,
    'amount'         => '',
    'payment_date'   => date('Y-m-d'),
    'payment_method' => 'Cash',
    'remarks'        => '',
    'status'         => 'paid',
];

// Pre-format student data for client-side search JSON
$students_data = [];
$selected_student_display = '';

foreach ($students as $s) {
    $full_name = trim($s['first_name'] . ' ' . $s['last_name']);
    $class_name = $s['class_name'] ?? 'No Class';
    $section = !empty($s['section']) ? 'Section ' . $s['section'] : '';
    $class_full = $class_name . ($section ? ' (' . $section . ')' : '');
    $display_label = $s['student_id'] . ' — ' . $full_name . ' — ' . $class_full;

    $item = [
        'id'           => (int)$s['id'],
        'student_code' => $s['student_id'],
        'name'         => $full_name,
        'class_id'     => (int)$s['class_id'],
        'class_name'   => $class_name,
        'section'      => $s['section'] ?? '',
        'class_full'   => $class_full,
        'display'      => $display_label,
    ];
    $students_data[] = $item;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['student_id']     = (int)($_POST['student_id'] ?? 0);
    $form['class_id']       = (int)($_POST['class_id'] ?? 0);
    $month_input            = trim($_POST['fee_month'] ?? '');
    $year_input             = trim($_POST['fee_year'] ?? '');
    $form['fee_month']      = $month_input;
    $form['fee_year']       = $year_input;
    $form['amount']         = trim($_POST['amount'] ?? '');
    $form['payment_date']   = trim($_POST['payment_date'] ?? '');
    $form['payment_method'] = trim($_POST['payment_method'] ?? 'Cash');
    $form['remarks']        = trim($_POST['remarks'] ?? '');
    $form['status']         = trim($_POST['status'] ?? 'paid');

    // Validation: Ensure a valid student is selected
    if (!$form['student_id']) {
        $errors[] = 'Please search and select a valid student from the suggestion list.';
    } else {
        // Verify student exists and is active
        $student_info = fee_db_fetch_one("SELECT id, class_id FROM students WHERE id = ? AND status = 'active'", [$form['student_id']], 'i');
        if (!$student_info) {
            $errors[] = 'Selected student does not exist or is inactive.';
            $form['student_id'] = 0;
        } else {
            $form['class_id'] = (int)$student_info['class_id'];
        }
    }

    if ($month_input === '' || !in_array($month_input, $months, true)) {
        $errors[] = 'Please select a valid fee month.';
    }

    if ($year_input === '' || !is_numeric($year_input)) {
        $errors[] = 'Please enter or select a valid fee year.';
    }

    $combined_fee_month = $month_input . ' ' . $year_input;

    if ($form['amount'] === '' || !is_numeric($form['amount']) || (float)$form['amount'] <= 0) {
        $errors[] = 'Amount must be a positive number.';
    }

    if (!in_array($form['payment_method'], ['Cash', 'Bank', 'Online'], true)) {
        $form['payment_method'] = 'Cash';
    }

    if (!in_array($form['status'], ['paid', 'pending'], true)) {
        $form['status'] = 'paid';
    }

    $payment_date = null;
    if ($form['status'] === 'paid') {
        $payment_date = !empty($form['payment_date']) ? $form['payment_date'] : date('Y-m-d');
    } else {
        $payment_date = !empty($form['payment_date']) ? $form['payment_date'] : null;
    }

    if (empty($errors)) {
        fee_db_execute(
            "INSERT INTO fees (student_id, class_id, fee_month, amount, payment_date, payment_method, remarks, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $form['student_id'],
                $form['class_id'],
                $combined_fee_month,
                (float)$form['amount'],
                $payment_date,
                $form['payment_method'],
                $form['remarks'],
                $form['status']
            ],
            'iisdssss'
        );

        $new_id = fee_db_last_id();

        set_flash('success', 'Fee payment recorded successfully!');

        $submit_action = trim($_POST['submit_action'] ?? 'save');

        // If user clicked "Print Fee Receipt", redirect to print receipt; otherwise redirect to fee records
        if ($submit_action === 'print' && $new_id > 0) {
            redirect('print_receipt.php?id=' . $new_id);
        } else {
            redirect('fee_records.php');
        }
    }
}

// Find display label for selected student if prefilled
if (!empty($form['student_id'])) {
    foreach ($students_data as $sd) {
        if ($sd['id'] === (int)$form['student_id']) {
            $selected_student_display = $sd['display'];
            break;
        }
    }
}

include '../layout.php';
?>

<!-- Link Bootstrap Icons if not in layout -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
/* ── Type-Ahead Suggestion Dropdown ── */
.student-search-container {
    position: relative;
}
.student-suggestions-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1050;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    max-height: 280px;
    overflow-y: auto;
    margin-top: 4px;
    display: none;
    box-shadow: 0 10px 25px rgba(0,0,0,0.12);
}
.student-suggestion-item {
    padding: 10px 14px;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    transition: background 0.15s ease-in-out;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.student-suggestion-item:last-child {
    border-bottom: none;
}
.student-suggestion-item:hover,
.student-suggestion-item.active {
    background-color: #f0f7ff;
}
.student-suggestion-item .stu-main {
    font-weight: 600;
    color: #1e293b;
    font-size: 0.9rem;
}
.student-suggestion-item .stu-code {
    font-weight: 700;
    color: #0284c7;
    margin-right: 6px;
}
.student-suggestion-item .stu-class {
    font-size: 0.8rem;
    color: #64748b;
    background: #f1f5f9;
    padding: 2px 8px;
    border-radius: 4px;
}
.student-suggestion-item mark {
    background-color: #fef08a;
    padding: 0 2px;
    border-radius: 2px;
}
.student-suggestion-empty {
    padding: 14px;
    text-align: center;
    color: #94a3b8;
    font-size: 0.875rem;
}
</style>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 text-primary fw-bold">
            <i class="bi bi-cash-stack me-2 text-success"></i> Add Fee Payment
        </h5>
        <a href="fee_records.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Records
        </a>
    </div>
    <div class="card-body p-4">

        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Please correct the following errors:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $e): ?>
                <li><?= h($e) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="POST" action="add_fee.php" id="feeForm">

            <!-- ── SECTION 1: STUDENT SELECTION VIA TYPE-AHEAD ── -->
            <h6 class="text-uppercase text-secondary fw-bold mb-3 pb-2 border-bottom">
                <i class="bi bi-person-fill me-1 text-primary"></i> Student Selection
            </h6>
            <div class="row g-3 mb-4">
                <div class="col-md-12">
                    <label class="form-label fw-semibold" for="studentSearch">
                        Search Student (Type Name or Student ID) <span class="text-danger">*</span>
                    </label>
                    <div class="student-search-container">
                        <div class="input-group">
                            <span class="input-group-text bg-white text-primary border-end-0">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text"
                                   id="studentSearch"
                                   class="form-control border-start-0 ps-0"
                                   placeholder="Type student name or ID (e.g. Hamza, Bilal, STD-001)..."
                                   autocomplete="off"
                                   value="<?= h($selected_student_display) ?>">
                            <button class="btn btn-outline-secondary" type="button" id="clearStudentBtn" title="Clear student" style="display: <?= !empty($selected_student_display) ? 'inline-block' : 'none' ?>;">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        
                        <!-- Hidden student_id storing the chosen student's ID -->
                        <input type="hidden" name="student_id" id="selectedStudentId" value="<?= h($form['student_id']) ?>">
                        <input type="hidden" name="class_id" id="classIdHidden" value="<?= h($form['class_id']) ?>">

                        <!-- Dynamic suggestion dropdown list -->
                        <div id="studentSuggestions" class="student-suggestions-dropdown"></div>
                    </div>
                    <div class="form-text text-muted">
                        <i class="bi bi-info-circle me-1"></i> Start typing to see live matching students, then click a student from the list.
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Student ID (Auto-filled)</label>
                    <input type="text" id="studentIdDisplay" class="form-control bg-light" readonly
                           placeholder="Select student above" style="cursor: not-allowed;">
                </div>

                <div class="col-md-8">
                    <label class="form-label fw-semibold">Class &amp; Section (Auto-filled)</label>
                    <input type="text" id="classDisplay" class="form-control bg-light" readonly
                           placeholder="Select student above" style="cursor: not-allowed;">
                </div>
            </div>

            <!-- ── SECTION 2: FEE PAYMENT DETAILS ── -->
            <h6 class="text-uppercase text-secondary fw-bold mb-3 pb-2 border-bottom">
                <i class="bi bi-receipt me-1 text-primary"></i> Fee Payment Details
            </h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Fee Month <span class="text-danger">*</span></label>
                    <select name="fee_month" class="form-select" required id="feeMonthSelect">
                        <option value="">-- Select Month --</option>
                        <?php foreach ($months as $m): ?>
                        <option value="<?= $m ?>" <?= ($form['fee_month'] === $m) ? 'selected' : '' ?>>
                            <?= $m ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Fee Year <span class="text-danger">*</span></label>
                    <select name="fee_year" class="form-select" required id="feeYearSelect">
                        <?php foreach ($years as $y): ?>
                        <option value="<?= $y ?>" <?= ((string)$form['fee_year'] === (string)$y) ? 'selected' : '' ?>>
                            <?= $y ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Amount (Rs.) <span class="text-danger">*</span></label>
                    <input type="number" name="amount" class="form-control" required
                           min="1" step="0.01"
                           value="<?= h($form['amount']) ?>" placeholder="e.g. 3500">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Payment Date</label>
                    <input type="date" name="payment_date" id="paymentDateInput" class="form-control"
                           value="<?= h($form['payment_date']) ?>">
                    <div class="form-text">Defaults to today's date</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Payment Method <span class="text-danger">*</span></label>
                    <select name="payment_method" class="form-select" required>
                        <option value="Cash"   <?= ($form['payment_method'] === 'Cash') ? 'selected' : '' ?>>Cash</option>
                        <option value="Bank"   <?= ($form['payment_method'] === 'Bank') ? 'selected' : '' ?>>Bank</option>
                        <option value="Online" <?= ($form['payment_method'] === 'Online') ? 'selected' : '' ?>>Online</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required id="feeStatusSelect">
                        <option value="paid"    <?= ($form['status'] === 'paid') ? 'selected' : '' ?>>Paid (Generates Printable Receipt)</option>
                        <option value="pending" <?= ($form['status'] === 'pending') ? 'selected' : '' ?>>Pending</option>
                    </select>
                </div>

                <div class="col-md-12">
                    <label class="form-label fw-semibold">Remarks (Optional)</label>
                    <textarea name="remarks" class="form-control" rows="2"
                              placeholder="e.g. Monthly Tuition fee, Admission fee, Challan number, Easypaisa TID..."><?= h($form['remarks']) ?></textarea>
                </div>
            </div>

            <!-- Action Buttons (Save, Print Receipt, Reset, Cancel) -->
            <div class="d-flex gap-2 mt-4 flex-wrap">
                <input type="hidden" name="submit_action" id="submitActionInput" value="save">
                <button type="submit" name="submit_action" value="save" class="btn btn-primary px-4" id="btnSave">
                    <i class="bi bi-check-circle me-1"></i> Save Fee Record
                </button>
                <button type="submit" name="submit_action" value="print" class="btn btn-success px-4" id="btnPrintReceipt">
                    <i class="bi bi-printer me-1"></i> Print Fee Receipt
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                </button>
                <a href="fee_records.php" class="btn btn-outline-danger">
                    <i class="bi bi-x-circle me-1"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Type-Ahead JavaScript -->
<script>
// Embedded JSON student dataset
const allStudents = <?= json_encode($students_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

const searchInput       = document.getElementById('studentSearch');
const selectedStudentId = document.getElementById('selectedStudentId');
const classIdHidden     = document.getElementById('classIdHidden');
const studentIdDisplay  = document.getElementById('studentIdDisplay');
const classDisplay      = document.getElementById('classDisplay');
const suggestionsBox    = document.getElementById('studentSuggestions');
const clearBtn          = document.getElementById('clearStudentBtn');
const feeForm           = document.getElementById('feeForm');

let activeIndex = -1;
let currentMatches = [];

// Helper to escape HTML characters
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Highlight matching substring
function highlightMatch(text, query) {
    if (!query) return escapeHtml(text);
    const index = text.toLowerCase().indexOf(query.toLowerCase());
    if (index === -1) return escapeHtml(text);
    const before = escapeHtml(text.substring(0, index));
    const match  = escapeHtml(text.substring(index, index + query.length));
    const after  = escapeHtml(text.substring(index + query.length));
    return before + '<mark>' + match + '</mark>' + after;
}

// Filter and render suggestions
function renderSuggestions(query) {
    const q = query.trim().toLowerCase();
    activeIndex = -1;

    if (!q) {
        suggestionsBox.style.display = 'none';
        suggestionsBox.innerHTML = '';
        currentMatches = [];
        return;
    }

    currentMatches = allStudents.filter(s => {
        return s.name.toLowerCase().includes(q) ||
               s.student_code.toLowerCase().includes(q) ||
               s.class_full.toLowerCase().includes(q);
    });

    if (currentMatches.length === 0) {
        suggestionsBox.innerHTML = `
            <div class="student-suggestion-empty">
                <i class="bi bi-exclamation-circle me-1 text-warning"></i>
                No matching students found for "<strong>${escapeHtml(query)}</strong>"
            </div>
        `;
        suggestionsBox.style.display = 'block';
        return;
    }

    let html = '';
    currentMatches.forEach((s, idx) => {
        html += `
            <div class="student-suggestion-item" data-index="${idx}">
                <div class="stu-main">
                    <span class="stu-code">${highlightMatch(s.student_code, q)}</span>
                    <span>${highlightMatch(s.name, q)}</span>
                </div>
                <div class="stu-class">
                    ${highlightMatch(s.class_full, q)}
                </div>
            </div>
        `;
    });

    suggestionsBox.innerHTML = html;
    suggestionsBox.style.display = 'block';

    // Bind click events on items
    const items = suggestionsBox.querySelectorAll('.student-suggestion-item');
    items.forEach(item => {
        item.addEventListener('click', function() {
            const idx = parseInt(this.getAttribute('data-index'), 10);
            if (currentMatches[idx]) {
                selectStudent(currentMatches[idx]);
            }
        });
    });
}

// Select student handler
function selectStudent(student) {
    searchInput.value       = student.display;
    selectedStudentId.value = student.id;
    classIdHidden.value     = student.class_id;
    studentIdDisplay.value  = student.student_code;
    classDisplay.value      = student.class_full;

    clearBtn.style.display       = 'inline-block';
    suggestionsBox.style.display = 'none';
    suggestionsBox.innerHTML     = '';
    currentMatches               = [];
    activeIndex                  = -1;
}

// Clear selected student
function clearStudent() {
    searchInput.value       = '';
    selectedStudentId.value = '';
    classIdHidden.value     = '';
    studentIdDisplay.value  = '';
    classDisplay.value      = '';
    clearBtn.style.display  = 'none';
    suggestionsBox.style.display = 'none';
    suggestionsBox.innerHTML = '';
    currentMatches = [];
    activeIndex = -1;
    searchInput.focus();
}

clearBtn.addEventListener('click', clearStudent);

// Handle input typing
searchInput.addEventListener('input', function() {
    const val = this.value;
    // If user changes text after selecting, invalidate previous selection
    if (selectedStudentId.value) {
        selectedStudentId.value = '';
        classIdHidden.value     = '';
        studentIdDisplay.value  = '';
        classDisplay.value      = '';
    }

    if (val.trim()) {
        clearBtn.style.display = 'inline-block';
        renderSuggestions(val);
    } else {
        clearBtn.style.display = 'none';
        suggestionsBox.style.display = 'none';
    }
});

// Keyboard navigation (Up/Down arrows, Enter, Escape)
searchInput.addEventListener('keydown', function(e) {
    if (suggestionsBox.style.display === 'none' || currentMatches.length === 0) {
        if (e.key === 'ArrowDown' && this.value.trim()) {
            renderSuggestions(this.value);
        }
        return;
    }

    const items = suggestionsBox.querySelectorAll('.student-suggestion-item');

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIndex = (activeIndex + 1) % items.length;
        updateActiveItem(items);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIndex = (activeIndex - 1 + items.length) % items.length;
        updateActiveItem(items);
    } else if (e.key === 'Enter') {
        if (activeIndex >= 0 && activeIndex < currentMatches.length) {
            e.preventDefault();
            selectStudent(currentMatches[activeIndex]);
        }
    } else if (e.key === 'Escape') {
        suggestionsBox.style.display = 'none';
    }
});

function updateActiveItem(items) {
    items.forEach((item, idx) => {
        if (idx === activeIndex) {
            item.classList.add('active');
            item.scrollIntoView({ block: 'nearest' });
        } else {
            item.classList.remove('active');
        }
    });
}

// Close suggestions on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('.student-search-container')) {
        suggestionsBox.style.display = 'none';
    }
});

// Re-open suggestions on focus if query present and no selection
searchInput.addEventListener('focus', function() {
    if (this.value.trim() && !selectedStudentId.value) {
        renderSuggestions(this.value);
    }
});

// Reset entire form
function resetForm() {
    feeForm.reset();
    clearStudent();
}

// Form validation before submit: Block submission if no real student is selected
feeForm.addEventListener('submit', function(e) {
    const studentId = selectedStudentId.value.trim();
    if (!studentId || studentId === '0') {
        e.preventDefault();
        alert('Please search and select a valid student from the suggestion list before saving.');
        searchInput.focus();
        return false;
    }
});

// Button click tracking for submit action
const btnSave = document.getElementById('btnSave');
const btnPrint = document.getElementById('btnPrintReceipt');
const actionInput = document.getElementById('submitActionInput');

if (btnSave && actionInput) {
    btnSave.addEventListener('click', function() { actionInput.value = 'save'; });
}
if (btnPrint && actionInput) {
    btnPrint.addEventListener('click', function() { actionInput.value = 'print'; });
}

// Initialize on DOM load if prefilled
document.addEventListener('DOMContentLoaded', () => {
    const initialId = parseInt(selectedStudentId.value, 10);
    if (initialId) {
        const student = allStudents.find(s => s.id === initialId);
        if (student) {
            selectStudent(student);
        }
    }
});
</script>

<?php include '../layout_end.php'; ?>
