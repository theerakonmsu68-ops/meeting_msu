/* =========================================================
   SweetAlert2 Helpers (พร้อม Safe Fallback)
========================================================= */
function showConfirm(message, title = 'ยืนยันการดำเนินการ', confirmText = 'ยืนยัน', cancelText = 'ยกเลิก', isDangerous = false) {
    if (typeof Swal !== 'undefined') {
        return Swal.fire({
            icon: isDangerous ? 'warning' : 'question',
            title: title,
            text: message,
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: cancelText,
            confirmButtonColor: isDangerous ? '#dc2626' : '#2563eb',
            cancelButtonColor: '#64748b',
            reverseButtons: true,
            focusCancel: true
        });
    } else {
        const confirmed = confirm((title ? title + '\n' : '') + message);
        return Promise.resolve({ isConfirmed: confirmed });
    }
}

/* =========================================================
   Main Script
========================================================= */
document.addEventListener('DOMContentLoaded', function () {

    const tableContainer = document.getElementById('tableContainer');
    const searchInput = document.getElementById('searchInput');
    const sidebar = document.getElementById('sidebar');
    const toggleButton = document.getElementById('toggle-sidebar');
    const mainContent = document.getElementById('mainContent') || document.getElementById('main-content');

    toggleButton?.addEventListener('click', function (event) {
        event.preventDefault();
        if (!sidebar) return;

        sidebar.classList.toggle('collapsed');

        if (!window.matchMedia('(max-width:768px)').matches) {
            mainContent?.classList.toggle('expanded', sidebar.classList.contains('collapsed'));
        } else {
            mainContent?.classList.remove('expanded');
        }
    });

    let searchTimeout = null;

    searchInput?.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            const keyword = searchInput.value.trim();
            loadAgendaTable(`?ajax=1&page=1&search=${encodeURIComponent(keyword)}`);
        }, 300);
    });

    tableContainer?.addEventListener('click', function(event) {
        const link = event.target.closest('.pagination-link');
        if (!link) return;

        event.preventDefault();
        const url = new URL(link.href, window.location.href);
        url.searchParams.set('ajax', '1');

        loadAgendaTable(url.toString());
    });

    if (window.lucide) {
        window.lucide.createIcons();
    }
});

function loadAgendaTable(url) {
    const tableContainer = document.getElementById('tableContainer');
    if (!tableContainer) return;

    fetch(url)
        .then(response => response.text())
        .then(html => {
            tableContainer.innerHTML = html;
            if (window.lucide) {
                window.lucide.createIcons();
            }
        })
        .catch(error => {
            console.error(error);
            showError('ไม่สามารถโหลดข้อมูลตารางได้');
        });
}

async function viewAgenda(id) {
    const modal = document.getElementById('agendaModal');
    const body = document.getElementById('agendaModalBody');

    if (!modal || !body) {
        console.error('ไม่พบ agendaModal');
        showError('ไม่พบส่วนแสดงผลรายละเอียดวาระ');
        return;
    }

    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');
    body.innerHTML = `
        <div class="loading">
            กำลังโหลดข้อมูล...
        </div>
    `;

    try {
        const response = await fetch(`/Meeting_msu/app/controllers/Agenda_api.php?action=detail&agenda_id=${id}`);
        const data = await response.json();

        if (data.status !== 'success') {
            throw new Error(data.message || 'ไม่พบข้อมูล');
        }

        renderAgendaDetail(data.agenda);

    } catch (error) {
        body.innerHTML = `
            <div class="empty-state">
                ${escapeHtml(error.message)}
            </div>
        `;
    }
}

function renderAgendaDetail(agenda) {
    const body = document.getElementById('agendaModalBody');
    let documents = '';

    if (agenda.documents && agenda.documents.length > 0) {
        documents = `
        <div class="section-title">
            เอกสารแนบ
        </div>
        <div class="document-list">
        ${
            agenda.documents.map(doc => `
            <a class="document-link" href="/Meeting_msu/app/controllers/download.php?file=${encodeURIComponent(doc.file_path)}" target="_blank">
                📄 ${escapeHtml(doc.document_name)}
            </a>
            `).join('')
        }
        </div>
        `;
    } else {
        documents = `
            <p>ไม่มีเอกสารแนบ</p>
        `;
    }

    body.innerHTML = `
    <div class="agenda-card">
        <h3>${escapeHtml(agenda.agenda_title)}</h3>
        <p>${escapeHtml(agenda.agenda_detail || '-')}</p>
    </div>

    <div class="detail-box">
        <span>ผู้เสนอ</span>
        <strong>${escapeHtml(agenda.submitter_name || '-')}</strong>
    </div>

    <div class="detail-box">
        <span>หน่วยงาน</span>
        <strong>${escapeHtml(agenda.department_name || '-')}</strong>
    </div>

    <div class="detail-box">
        <span>การประชุม</span>
        <strong>${escapeHtml(agenda.meeting_title || '-')}</strong>
    </div>

    ${documents}
    `;

    if (window.lucide) {
        window.lucide.createIcons();
    }
}

async function postAgendaAction(action, id, message, confirmTitle = 'ยืนยันการทำรายการ', isDangerous = false) {
    const confirmResult = await showConfirm(message, confirmTitle, 'ยืนยัน', 'ยกเลิก', isDangerous);
    if (!confirmResult.isConfirmed) {
        return;
    }

    showLoading('กำลังบันทึกข้อมูล...');

    try {
        const formData = new FormData();
        formData.append('agenda_id', id);

        const response = await fetch(`/Meeting_msu/app/controllers/Agenda_api.php?action=${action}`, {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        closeLoading();

        if (data.status !== 'success') {
            throw new Error(data.message || 'เกิดข้อผิดพลาด');
        }

        await showSuccess(data.message, 'สำเร็จ');
        closeAgendaModal();
        location.reload();

    } catch (error) {
        closeLoading();
        showError(error.message);
    }
}

function approveAgenda(id) {
    postAgendaAction(
        'approve',
        id,
        'คุณต้องการอนุมัติวาระการประชุมนี้ใช่หรือไม่?',
        'ยืนยันการอนุมัติ',
        false
    );
}

function rejectAgenda(id) {
    postAgendaAction(
        'reject',
        id,
        'คุณต้องการไม่อนุมัติวาระการประชุมนี้ใช่หรือไม่?',
        'ยืนยันปฏิเสธวาระ',
        true
    );
}

function deleteAgenda(id) {
    postAgendaAction(
        'delete',
        id,
        'ยืนยันลบวาระนี้? ข้อมูลและไฟล์แนบจะถูกลบทั้งหมด',
        'ยืนยันการลบ',
        true
    );
}

function closeAgendaModal() {
    const modal = document.getElementById('agendaModal');
    if (!modal) return;

    modal.classList.remove('show');
    modal.setAttribute('aria-hidden', 'true');
}