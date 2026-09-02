/* =========================================================
   DOM Elements Selection
========================================================= */
const searchInput = document.getElementById('searchInput');
const modal = document.getElementById('modal');
const modalTitle = document.getElementById('modalTitle');
const meeting_id = document.getElementById('meeting_id');
const meeting_title = document.getElementById('meeting_title');
const meeting_date = document.getElementById('meeting_date');
const meeting_time = document.getElementById('meeting_time');
const meeting_location = document.getElementById('meeting_location');
const meeting_link = document.getElementById('meeting_link');
const report_header = document.getElementById('report_header');
const meeting_number = document.getElementById('meeting_number');
const meeting_status = document.getElementById('meeting_status');
const inviteeSearch = document.getElementById('inviteeSearch');
const inviteeSelectAll = document.getElementById('inviteeSelectAll');
const inviteeList = document.getElementById('inviteeList');
const statusControlBox = document.getElementById('statusControlBox');
const meeting_documents = document.getElementById('meeting_documents');
const fileOldNotice = document.getElementById('fileOldNotice');
const previewContainer = document.getElementById('filePreviewContainer');
const agendaContainer = document.getElementById('agenda-container');
const agendaModal = document.getElementById('agendaModal');
const agendaList = document.getElementById('agendaList');
const dropZone = document.getElementById('dropZone');
const attendanceModal = document.getElementById('attendanceModal');
const attendanceMeetingId = document.getElementById('attendanceMeetingId');
const attendanceMeetingInfo = document.getElementById('attendanceMeetingInfo');
const attendanceSummary = document.getElementById('attendanceSummary');
const attendanceTableBody = document.getElementById('attendanceTableBody');
const invitationModal = document.getElementById('invitationModal');
const invitationModalTitle = document.getElementById('invitationModalTitle');
const invitationMeetingId = document.getElementById('invitationMeetingId');
const managerInviteeSearch = document.getElementById('managerInviteeSearch');
const managerInviteeSelectAll = document.getElementById('managerInviteeSelectAll');
const managerInviteeList = document.getElementById('managerInviteeList');
const managerInviteeSelectedCount = document.getElementById('managerInviteeSelectedCount');

/* =========================================================
   State Variables
========================================================= */
let isEdit = false;
let agendaKeyCounter = 0;
let inviteeRows = [];
let managerInviteeRows = [];
const deletedAgendaDocumentIds = new Set();

/* =========================================================
   SweetAlert2 Helpers (พร้อม Safe Fallback ป้องกัน Swal Undefined)
========================================================= */
function showAlert(message, icon = 'info', title = '') {
    if (typeof Swal !== 'undefined') {
        return Swal.fire({
            icon: icon,
            title: title,
            text: message,
            confirmButtonText: 'ตกลง',
            confirmButtonColor: '#2563eb'
        });
    } else {
        alert((title ? title + '\n' : '') + message);
        return Promise.resolve({ isConfirmed: true });
    }
}

function showSuccess(message, title = 'สำเร็จ') {
    return showAlert(message, 'success', title);
}

function showError(message, title = 'เกิดข้อผิดพลาด') {
    return showAlert(message, 'error', title);
}

function showWarning(message, title = 'แจ้งเตือน') {
    return showAlert(message, 'warning', title);
}

function showConfirm(
    message,
    title = 'ยืนยันการดำเนินการ',
    confirmText = 'ยืนยัน',
    cancelText = 'ยกเลิก'
) {
    if (typeof Swal !== 'undefined') {
        return Swal.fire({
            icon: 'warning',
            title: title,
            text: message,
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: cancelText,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            reverseButtons: true,
            focusCancel: true
        });
    } else {
        const confirmed = confirm((title ? title + '\n' : '') + message);
        return Promise.resolve({ isConfirmed: confirmed });
    }
}

function showLoading(title = 'กำลังดำเนินการ...') {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: title,
            text: 'กรุณารอสักครู่',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    }
}

function closeLoading() {
    if (typeof Swal !== 'undefined' && Swal.isVisible()) {
        Swal.close();
    }
}

/* =========================================================
   Sidebar Toggle
========================================================= */
document.getElementById('toggle-sidebar')?.addEventListener('click', function (event) {
    event.preventDefault();

    const sidebar = document.getElementById('sidebar')
        || document.querySelector('.sidebar')
        || document.querySelector('.sidebar-wrapper');

    const mainContent = document.getElementById('mainContent')
        || document.getElementById('main-content');

    if (!sidebar) return;

    sidebar.classList.toggle('collapsed');

    if (mainContent) {
        mainContent.classList.toggle('expanded', sidebar.classList.contains('collapsed'));
    }
});

/* =========================================================
   Drag & Drop File Handling
========================================================= */
['dragenter', 'dragover'].forEach(eventName => {
    dropZone?.addEventListener(eventName, (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    }, false);
});

['dragleave', 'drop'].forEach(eventName => {
    dropZone?.addEventListener(eventName, (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
    }, false);
});

dropZone?.addEventListener('drop', (e) => {
    const dt = e.dataTransfer;
    if (meeting_documents) {
        meeting_documents.files = dt.files;
        updateFilePreview();
    }
});

/* =========================================================
   File Preview
========================================================= */
function updateFilePreview() {
    if (!previewContainer || !meeting_documents) return;
    previewContainer.innerHTML = '';
    const files = meeting_documents.files;

    if (files.length > 0) {
        for (let i = 0; i < files.length; i++) {
            const item = document.createElement('div');
            item.className = 'file-preview-item';
            item.innerHTML = `
                <div class="file-info">
                    <i data-lucide="file"></i>
                    <span>${escapeHtml(files[i].name)} (${(files[i].size / 1024 / 1024).toFixed(2)} MB)</span>
                </div>
                <span style="color:#16a34a;font-weight:500;">เตรียมอัปโหลด</span>
            `;
            previewContainer.appendChild(item);
        }
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
}

/* =========================================================
   Search Table
========================================================= */
function searchTable() {
    if (!searchInput) return;
    let input = searchInput.value.toLowerCase();
    document.querySelectorAll("tbody tr").forEach(row => {
        if (row.cells.length > 1) {
            row.style.display = row.textContent.toLowerCase().includes(input) ? "" : "none";
        }
    });
}

/* =========================================================
   Security & Escaping Helpers
========================================================= */
function escapeAttribute(value) {
    return escapeHtml(value);
}

/* =========================================================
   Agenda Management
========================================================= */
function createAgendaKey(agendaId = null) {
    agendaKeyCounter += 1;
    return agendaId ? `existing_${agendaId}` : `new_${Date.now()}_${agendaKeyCounter}`;
}

function addAgenda(title = "", detail = "", agendaId = null, documents = [], agendaStatus = "pending") {
    const key = createAgendaKey(agendaId);
    const div = document.createElement('div');
    div.className = 'agenda-item';
    div.dataset.key = key;
    div.dataset.agendaId = agendaId ? String(agendaId) : '';

    const existingDocuments = Array.isArray(documents)
        ? documents.map(doc => `
            <div class="agenda-existing-doc" data-document-id="${Number(doc.agenda_document_id)}">
                <a href="/app/controllers/download.php?file=${encodeURIComponent(doc.file_path)}" target="_blank" title="${escapeAttribute(doc.document_name)}">
                    📄 ${escapeHtml(doc.document_name)}
                </a>
                <button type="button" class="btn-remove-existing-doc" onclick="markAgendaDocumentForDeletion(this, ${Number(doc.agenda_document_id)})" title="ลบไฟล์นี้">
                    <i data-lucide="x-circle"></i>
                </button>
            </div>
        `).join('')
        : '';
    const statusLabel = {
        pending: 'รอดำเนินการ',
        discussing: 'กำลังอภิปราย',
        voting: 'กำลังลงมติ',
        closed: 'ปิดแล้ว'
    }[agendaStatus] || 'รอดำเนินการ';
    const votingControl = agendaId
        ? `<div class="agenda-voting-control" data-agenda-status="${escapeAttribute(agendaStatus)}">
            <div class="agenda-voting-status"><span class="agenda-voting-status-icon"><i data-lucide="${agendaStatus === 'voting' ? 'radio' : agendaStatus === 'closed' ? 'lock-keyhole' : 'circle-dashed'}"></i></span><span><small>สถานะการลงมติ</small><strong>${statusLabel}</strong></span></div>
            ${agendaStatus === 'pending' ? `<button type="button" class="btn-agenda-vote-open" onclick="changeAgendaVoting(${Number(agendaId)}, 'open_voting', this)"><i data-lucide="vote"></i>เปิดลงมติ</button>` : ''}
            ${agendaStatus === 'voting' ? `<button type="button" class="btn-agenda-vote-close" onclick="changeAgendaVoting(${Number(agendaId)}, 'close_voting', this)"><i data-lucide="lock-keyhole"></i>ปิดการลงมติ</button>` : ''}
            </div>`
        : '';

    div.innerHTML = `
        <div class="agenda-input-row">
            <input type="text" class="agenda-title" placeholder="หัวข้อวาระ" value="${escapeAttribute(title)}">
            <textarea class="agenda-detail" placeholder="รายละเอียดเบื้องต้น">${escapeHtml(detail || '')}</textarea>
            <button type="button" class="btn-remove-agenda" onclick="removeAgendaItem(this)" title="ลบวาระ">
                <i data-lucide="trash-2"></i>
            </button>
        </div>
        <div class="agenda-file-row">
            <label style="font-size:15px;color:#475569;">เอกสารแนบของวาระนี้</label>
            <input type="file" class="agenda-file-input" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx">
            <div class="agenda-existing-docs">${existingDocuments}</div>
        </div>
        ${votingControl}
    `;

    agendaContainer.appendChild(div);
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

async function changeAgendaVoting(agendaId, action, button) {
    if (!Number.isInteger(Number(agendaId)) || !['open_voting', 'close_voting'].includes(action) || button.disabled) {
        return;
    }

    button.disabled = true;
    const formData = new FormData();
    formData.append('action', action);
    formData.append('agenda_id', String(Number(agendaId)));

    try {
        const response = await fetch('api.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (!response.ok || result.status !== 'success') {
            throw new Error(result.message || 'ไม่สามารถเปลี่ยนสถานะการลงมติได้');
        }

        const meetingId = Number(document.getElementById('meeting_id')?.value || 0);
        if (meetingId > 0) editMeeting(meetingId);
    } catch (error) {
        button.disabled = false;
        alert(error.message);
    }
}

function markAgendaDocumentForDeletion(button, documentId) {
    deletedAgendaDocumentIds.add(Number(documentId));
    const item = button.closest('.agenda-existing-doc');
    if (item) item.remove();
}

function removeAgendaItem(button) {
    const agendaItem = button.closest('.agenda-item');
    if (!agendaItem) return;

    agendaItem.querySelectorAll('.agenda-existing-doc').forEach(doc => {
        const documentId = Number(doc.dataset.documentId || 0);
        if (documentId > 0) deletedAgendaDocumentIds.add(documentId);
    });

    agendaItem.remove();
}

/* =========================================================
   RSVP Utilities
========================================================= */
function rsvpLabel(status) {
    const labels = { pending: 'รอตอบรับ', attending: 'ตอบรับแล้ว', declined: 'ปฏิเสธ' };
    return labels[status] || labels.pending;
}

/* =========================================================
   Invitee Management (Inside Meeting Modal)
========================================================= */
function loadInvitableUsers(meetingId = 0) {
    if (!inviteeList) return;
    inviteeRows = [];
    inviteeList.innerHTML = '<div class="invitee-empty">กำลังโหลดรายชื่อผู้ใช้งาน...</div>';
    inviteeSelectAll.checked = false;
    inviteeSelectAll.indeterminate = false;
    inviteeSelectAll.disabled = false;

    fetch(`api.php?action=get_invitable_users&meeting_id=${meetingId}`)
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'success') {
                throw new Error(res.message || 'โหลดรายชื่อผู้ใช้งานไม่สำเร็จ');
            }
            inviteeRows = (res.rows || []).map(row => {
                const locked = Number(row.is_locked) === 1;
                return {
                    ...row,
                    locked,
                    selected: Number(row.is_invited) === 1 || locked
                };
            });
            renderInviteeList();
        })
        .catch(error => {
            inviteeList.innerHTML = `<div class="invitee-empty" style="color:#dc2626;">${escapeHtml(error.message)}</div>`;
        });
}

function renderInviteeList() {
    const keyword = (inviteeSearch.value || '').trim().toLowerCase();
    const visibleRows = inviteeRows.filter(row => {
        const haystack = [row.name, row.email, row.position_name, row.department_name].join(' ').toLowerCase();
        return !keyword || haystack.includes(keyword);
    });

    if (!visibleRows.length) {
        inviteeList.innerHTML = '<div class="invitee-empty">ไม่พบรายชื่อที่ค้นหา</div>';
        updateInviteeSelectAllState();
        return;
    }

    inviteeList.innerHTML = visibleRows.map(row => `
        <label class="invitee-item ${row.selected ? ' is-selected' : ''} ${row.locked ? ' is-locked' : ''}"
            ${row.locked ? 'title="รายชื่อนี้ตอบกลับแล้ว จึงไม่สามารถยกเลิกคำเชิญได้"' : ''}>
            <input type="checkbox" class="invitee-checkbox" data-user-id="${Number(row.user_id)}"
                ${row.selected ? 'checked' : ''} ${row.locked ? 'disabled' : ''}
                onchange="setInviteeSelected(${Number(row.user_id)}, this)">
            <span class="invitee-checkmark" aria-hidden="true"></span>
            <span>
                <strong>${escapeHtml(row.name)}</strong>
                <small>${escapeHtml(row.position_name)} · ${escapeHtml(row.department_name)} · ${escapeHtml(row.email)}</small>
            </span>
            ${Number(row.is_invited) === 1
                ? row.locked
                    ? `<span class="invitee-rsvp is-locked">🔒 ${escapeHtml(rsvpLabel(row.rsvp_status))}</span>`
                    : `<span class="invitee-rsvp">${escapeHtml(rsvpLabel(row.rsvp_status))}</span>`
                : '<span></span>'
            }
        </label>
    `).join('');

    updateInviteeSelectAllState();
}

function setInviteeSelected(userId, checkbox) {
    const row = inviteeRows.find(item => Number(item.user_id) === Number(userId));
    if (!row) return;

    if (row.locked) {
        row.selected = true;
        checkbox.checked = true;
        return;
    }

    row.selected = checkbox.checked;
    const item = checkbox.closest('.invitee-item');
    if (item) item.classList.toggle('is-selected', row.selected);

    updateInviteeSelectAllState();
}

function toggleAllInvitees(checked) {
    const keyword = (inviteeSearch.value || '').trim().toLowerCase();
    inviteeRows.forEach(row => {
        const haystack = [row.name, row.email, row.position_name, row.department_name].join(' ').toLowerCase();
        if ((!keyword || haystack.includes(keyword)) && !row.locked) {
            row.selected = checked;
        }
        if (row.locked) row.selected = true;
    });
    renderInviteeList();
}

function updateInviteeSelectAllState() {
    const keyword = (inviteeSearch.value || '').trim().toLowerCase();
    const visibleRows = inviteeRows.filter(row => {
        const haystack = [row.name, row.email, row.position_name, row.department_name].join(' ').toLowerCase();
        return !keyword || haystack.includes(keyword);
    });

    const selectableRows = visibleRows.filter(row => !row.locked);
    inviteeSelectAll.disabled = selectableRows.length === 0;
    inviteeSelectAll.checked = selectableRows.length > 0 && selectableRows.every(row => row.selected);
    inviteeSelectAll.indeterminate = selectableRows.some(row => row.selected) && !inviteeSelectAll.checked;
}

function getSelectedInviteeIds() {
    return inviteeRows
        .filter(row => row.selected || row.locked)
        .map(row => Number(row.user_id));
}

/* =========================================================
   Invitation Manager Modal
========================================================= */
function openInvitationManager(meetingId, meetingTitle = '') {
    invitationMeetingId.value = Number(meetingId || 0);
    invitationModalTitle.textContent = meetingTitle ? `เชิญสมาชิกเข้าร่วม: ${meetingTitle}` : 'เชิญสมาชิกเข้าร่วมประชุม';
    managerInviteeSearch.value = '';
    managerInviteeSelectAll.checked = false;
    managerInviteeSelectAll.indeterminate = false;
    managerInviteeSelectedCount.textContent = '0';
    managerInviteeRows = [];
    managerInviteeList.innerHTML = '<div class="invitee-empty">กำลังโหลดรายชื่อสมาชิก...</div>';

    invitationModal.classList.add('show');

    fetch(`api.php?action=get_invitable_users&meeting_id=${Number(meetingId)}`)
        .then(response => response.json())
        .then(result => {
            if (result.status !== 'success') {
                throw new Error(result.message || 'โหลดรายชื่อสมาชิกไม่สำเร็จ');
            }
            managerInviteeRows = (result.rows || []).map(row => {
                const locked = Number(row.is_locked) === 1;
                return {
                    ...row,
                    locked,
                    selected: Number(row.is_invited) === 1 || locked
                };
            });
            renderManagerInviteeList();
        })
        .catch(error => {
            managerInviteeList.innerHTML = `<div class="invitee-empty" style="color:#dc2626;">${escapeHtml(error.message)}</div>`;
        });

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

function renderManagerInviteeList() {
    const keyword = (managerInviteeSearch.value || '').trim().toLowerCase();
    const visibleRows = managerInviteeRows.filter(row => {
        const haystack = [row.name, row.email, row.position_name, row.department_name].join(' ').toLowerCase();
        return !keyword || haystack.includes(keyword);
    });

    if (!visibleRows.length) {
        managerInviteeList.innerHTML = '<div class="invitee-empty">ไม่พบรายชื่อสมาชิก</div>';
        updateManagerInviteeState();
        return;
    }

    managerInviteeList.innerHTML = visibleRows.map(row => `
        <label class="invitee-item ${row.selected ? ' is-selected' : ''} ${row.locked ? ' is-locked' : ''}"
            ${row.locked ? 'title="รายชื่อนี้ตอบกลับแล้ว จึงไม่สามารถยกเลิกคำเชิญได้"' : ''}>
            <input type="checkbox" data-user-id="${Number(row.user_id)}"
                ${row.selected ? 'checked' : ''} ${row.locked ? 'disabled' : ''}
                onchange="setManagerInviteeSelected(${Number(row.user_id)}, this)">
            <span class="invitee-checkmark" aria-hidden="true"></span>
            <span>
                <strong>${escapeHtml(row.name)}</strong>
                <small>${escapeHtml(row.position_name)} · ${escapeHtml(row.department_name)} · ${escapeHtml(row.email)}</small>
            </span>
            ${Number(row.is_invited) === 1
                ? row.locked
                    ? `<span class="invitee-rsvp is-locked">🔒 ${escapeHtml(rsvpLabel(row.rsvp_status))}</span>`
                    : `<span class="invitee-rsvp">${escapeHtml(rsvpLabel(row.rsvp_status))}</span>`
                : '<span></span>'
            }
        </label>
    `).join('');

    updateManagerInviteeState();
}

function setManagerInviteeSelected(userId, checkbox) {
    const row = managerInviteeRows.find(item => Number(item.user_id) === Number(userId));
    if (!row) return;

    if (row.locked) {
        row.selected = true;
        checkbox.checked = true;
        return;
    }

    row.selected = checkbox.checked;
    const item = checkbox.closest('.invitee-item');
    if (item) item.classList.toggle('is-selected', row.selected);

    updateManagerInviteeState();
}

function toggleAllManagerInvitees(checked) {
    const keyword = (managerInviteeSearch.value || '').trim().toLowerCase();
    managerInviteeRows.forEach(row => {
        const haystack = [row.name, row.email, row.position_name, row.department_name].join(' ').toLowerCase();
        if ((!keyword || haystack.includes(keyword)) && !row.locked) {
            row.selected = checked;
        }
        if (row.locked) row.selected = true;
    });
    renderManagerInviteeList();
}

function updateManagerInviteeState() {
    const keyword = (managerInviteeSearch.value || '').trim().toLowerCase();
    const visibleRows = managerInviteeRows.filter(row => {
        const haystack = [row.name, row.email, row.position_name, row.department_name].join(' ').toLowerCase();
        return !keyword || haystack.includes(keyword);
    });

    const selectableRows = visibleRows.filter(row => !row.locked);
    managerInviteeSelectAll.disabled = selectableRows.length === 0;
    managerInviteeSelectAll.checked = selectableRows.length > 0 && selectableRows.every(row => row.selected);
    managerInviteeSelectAll.indeterminate = selectableRows.some(row => row.selected) && !managerInviteeSelectAll.checked;
    managerInviteeSelectedCount.textContent = managerInviteeRows.filter(row => row.selected || row.locked).length;
}

function saveMeetingInvitations() {
    const meetingId = Number(invitationMeetingId.value || 0);
    if (!meetingId) {
        showError('ไม่พบรหัสการประชุม');
        return;
    }

    const selectedIds = managerInviteeRows
        .filter(row => row.selected || row.locked)
        .map(row => Number(row.user_id));

    const formData = new FormData();
    formData.append('action', 'save_invitations');
    formData.append('meeting_id', meetingId);
    formData.append('invited_user_ids', JSON.stringify(selectedIds));

    showLoading('กำลังบันทึกคำเชิญ...');

    fetch('api.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(result => {
            closeLoading();
            if (result.status === 'success') {
                showSuccess(result.message, 'บันทึกคำเชิญสำเร็จ').then(() => {
                    closeInvitationManager();
                    window.location.reload();
                });
            } else {
                showError(result.message || 'ไม่สามารถบันทึกคำเชิญได้');
            }
        })
        .catch(() => {
            closeLoading();
            showError('ไม่สามารถบันทึกคำเชิญได้ กรุณาลองใหม่');
        });
}

function closeInvitationManager() {
    invitationModal.classList.remove('show');
}

/* =========================================================
   Default Agendas Generator
========================================================= */
function createDefaultAgendas() {
    const defaultAgendas = [
        "เรื่องแจ้งเพื่อทราบ",
        "เรื่องรับรองรายงานการประชุม",
        "เรื่องสืบเนื่อง",
        "เรื่องเพื่อพิจารณา",
        "เรื่องอื่นๆ (ถ้ามี)"
    ];
    defaultAgendas.forEach(title => addAgenda(title, "", null, []));
}

/* =========================================================
   Create & Edit Meeting Modals
========================================================= */
function openCreate() {
    isEdit = false;
    modalTitle.innerText = "เพิ่มการประชุม";
    meeting_id.value = "";
    meeting_title.value = "";
    meeting_date.value = "";
    meeting_time.value = "";
    meeting_location.value = "";
    meeting_link.value = "";
    report_header.value = "คณะกรรมการประจำคณะวิทยาการสารสนเทศ";
    meeting_number.value = "";
    meeting_status.value = "upcoming";

    inviteeSearch.value = "";
    loadInvitableUsers(0);

    statusControlBox.style.display = "none";
    fileOldNotice.style.display = "none";
    agendaContainer.innerHTML = "";
    deletedAgendaDocumentIds.clear();
    agendaKeyCounter = 0;

    createDefaultAgendas();
    modal.classList.add("show");

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

function editMeeting(id) {
    isEdit = true;
    fetch("api.php?action=get&id=" + id)
        .then(r => r.json())
        .then(d => {
            modalTitle.innerText = "แก้ไขข้อมูลการประชุม";
            meeting_id.value = d.meeting_id;
            meeting_title.value = d.meeting_title;
            meeting_date.value = d.meeting_date;
            meeting_time.value = d.meeting_time;
            meeting_location.value = d.meeting_location;
            meeting_link.value = d.meeting_link ? d.meeting_link : "";
            report_header.value = d.report_header || "คณะกรรมการประจำคณะวิทยาการสารสนเทศ";
            meeting_number.value = d.meeting_number || "";

            inviteeSearch.value = "";
            loadInvitableUsers(id);

            statusControlBox.style.display = "block";
            meeting_status.value = d.meeting_status ? d.meeting_status : "upcoming";
            meeting_documents.value = "";
            previewContainer.innerHTML = '';
            fileOldNotice.style.display = "block";

            agendaContainer.innerHTML = "";
            deletedAgendaDocumentIds.clear();
            agendaKeyCounter = 0;

            fetch("get_agenda.php?id=" + id)
                .then(r => r.json())
                .then(list => {
                    if (list && list.length > 0) {
                        list.forEach(a => {
                            addAgenda(a.agenda_title, a.agenda_detail, a.agenda_id, a.documents || [], a.agenda_status);
                        });
                    } else {
                        addAgenda();
                    }
                })
                .catch(() => {
                    addAgenda();
                });

            modal.classList.add("show");
        })
        .catch(() => {
            showError('ไม่สามารถโหลดข้อมูลการประชุมได้ กรุณาลองใหม่');
        });
}

/* =========================================================
   Save Meeting
========================================================= */
function saveMeeting() {
    const fd = new FormData();
    fd.append("meeting_title", meeting_title.value);
    fd.append("meeting_date", meeting_date.value);
    fd.append("meeting_time", meeting_time.value);
    fd.append("meeting_location", meeting_location.value);
    fd.append("meeting_link", meeting_link.value);
    fd.append("report_header", report_header.value);
    fd.append("meeting_number", meeting_number.value);
    fd.append("meeting_status", meeting_status.value);
    fd.append("invited_user_ids", JSON.stringify(getSelectedInviteeIds()));

    if (meeting_documents.files.length > 0) {
        for (let i = 0; i < meeting_documents.files.length; i++) {
            fd.append("meeting_documents[]", meeting_documents.files[i]);
        }
    }

    let agendas = [];
    document.querySelectorAll(".agenda-item").forEach((a, index) => {
        const title = a.querySelector(".agenda-title").value.trim();
        const detail = a.querySelector(".agenda-detail").value.trim();
        const key = a.dataset.key;
        const agendaId = a.dataset.agendaId ? Number(a.dataset.agendaId) : null;

        if (title) {
            agendas.push({
                agenda_id: agendaId,
                key: key,
                title: title,
                detail: detail,
                order_index: index + 1
            });

            const agendaFiles = a.querySelector('.agenda-file-input').files;
            for (let i = 0; i < agendaFiles.length; i++) {
                fd.append(`agenda_files_${key}[]`, agendaFiles[i]);
            }
        }
    });

    fd.append("agendas", JSON.stringify(agendas));
    fd.append("deleted_agenda_document_ids", JSON.stringify([...deletedAgendaDocumentIds]));

    if (isEdit) {
        fd.append("action", "update");
        fd.append("meeting_id", meeting_id.value);
    } else {
        fd.append("action", "create");
    }

    showLoading(isEdit ? 'กำลังบันทึกการแก้ไข...' : 'กำลังสร้างการประชุม...');

    fetch("api.php", { method: "POST", body: fd })
        .then(r => r.json())
        .then(res => {
            closeLoading();
            if (res.status === "success") {
                showSuccess(res.message, isEdit ? 'แก้ไขข้อมูลสำเร็จ' : 'สร้างการประชุมสำเร็จ').then(() => {
                    location.reload();
                });
            } else {
                showError(res.message || 'ไม่สามารถบันทึกข้อมูลได้');
            }
        })
        .catch(error => {
            console.error('saveMeeting error:', error);
            closeLoading();
            showError('ไม่สามารถบันทึกข้อมูลการประชุมได้ กรุณาลองใหม่');
        });
}

/* =========================================================
   Delete Meeting
========================================================= */
function deleteMeeting(id) {
    showConfirm(
        "คุณต้องการลบการประชุมนี้พร้อมโฟลเดอร์เอกสารแนบทั้งหมดใช่ไหม?",
        "ยืนยันการลบการประชุม",
        "ลบการประชุม",
        "ยกเลิก"
    ).then(result => {
        if (!result.isConfirmed) return;

        const fd = new FormData();
        fd.append("action", "delete");
        fd.append("id", id);

        showLoading("กำลังลบการประชุม...");

        fetch("api.php", { method: "POST", body: fd })
            .then(r => r.json())
            .then(res => {
                closeLoading();
                if (res.status === "success") {
                    showSuccess(res.message, "ลบข้อมูลสำเร็จ").then(() => {
                        location.reload();
                    });
                } else {
                    showError(res.message || "ไม่สามารถลบการประชุมได้");
                }
            })
            .catch(error => {
                console.error('deleteMeeting error:', error);
                closeLoading();
                showError("ไม่สามารถลบการประชุมได้ กรุณาลองใหม่");
            });
    });
}

/* =========================================================
   View Agenda Modal
========================================================= */
function viewAgenda(id) {
    fetch("get_agenda.php?id=" + id)
        .then(r => r.json())
        .then(data => {
            const defaultAgendas = [
                "เรื่องแจ้งเพื่อทราบ",
                "เรื่องรับรองรายงานการประชุม",
                "เรื่องสืบเนื่อง",
                "เรื่องเพื่อพิจารณา",
                "เรื่องอื่นๆ (ถ้ามี)"
            ];

            let html = "";
            defaultAgendas.forEach((title, index) => {
                const agenda = data.find(a => Number(a.order_index) === index + 1);
                const docs = agenda && Array.isArray(agenda.documents) && agenda.documents.length > 0
                    ? `
                        <div class="agenda-card-documents">
                            <strong style="font-size:12px;color:#475569;">เอกสารแนบ</strong>
                            ${agenda.documents.map(doc => `
                                <a href="/app/controllers/download.php?file=${encodeURIComponent(doc.file_path)}" target="_blank">
                                    📄 ${escapeHtml(doc.document_name)}
                                </a>
                            `).join('')}
                        </div>
                    `
                    : "";

                html += `
                    <div class="agenda-card">
                        <div class="agenda-card-title">
                            <i data-lucide="bookmark-check"></i>
                            <span>วาระที่ ${index + 1}: ${escapeHtml(title)}</span>
                        </div>
                        <div class="agenda-card-detail">
                            ${agenda && agenda.agenda_detail
                                ? escapeHtml(agenda.agenda_detail)
                                : `<span style="color:#cbd5e1;">- ไม่มีรายละเอียด -</span>`
                            }
                        </div>
                        ${docs}
                    </div>
                `;
            });

            agendaList.innerHTML = html;
            if (agendaModal) agendaModal.classList.add("show");
            if (typeof lucide !== 'undefined') lucide.createIcons();
        })
        .catch(error => {
            console.error("viewAgenda error:", error);
            showError("ไม่สามารถโหลดข้อมูลวาระได้");
        });
}

function modalModalRenderLucide() {
    if (agendaModal) agendaModal.classList.add("show");
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function closeAgenda() {
    agendaModal.classList.remove("show");
}

/* =========================================================
   Attendance Labels & Status
========================================================= */
function attendanceStatusLabel(status) {
    const labels = {
        pending: 'ยังไม่ระบุ',
        present: 'เข้าร่วมประชุม',
        absent: 'ไม่เข้าร่วมประชุม',
        representative: 'เข้าร่วมประชุมแทน'
    };
    return labels[status] || labels.pending;
}

function attendanceRoleLabel(role) {
    const labels = {
        chairman: 'ประธาน',
        member: 'กรรมการ/สมาชิก',
        secretary: 'เลขานุการ',
        observer: 'ผู้สังเกตการณ์'
    };
    return labels[role] || labels.member;
}

/* =========================================================
   Attendance Report Modal
========================================================= */
function openAttendanceReport(meetingId) {
    attendanceMeetingId.value = meetingId;
    attendanceTableBody.innerHTML = `
        <tr>
            <td colspan="8" style="text-align:center;padding:30px;">กำลังโหลดข้อมูล...</td>
        </tr>
    `;
    attendanceModal.classList.add('show');

    fetch(`api.php?action=get_attendance&id=${meetingId}`)
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'success') {
                throw new Error(res.message || 'โหลดข้อมูลไม่สำเร็จ');
            }

            const meeting = res.meeting;
            attendanceMeetingInfo.innerHTML = `
                <strong>${escapeHtml(meeting.meeting_title)}</strong><br>
                วันที่ ${escapeHtml(meeting.meeting_date)} 
                เวลา ${escapeHtml(meeting.meeting_time)} 
                ณ ${escapeHtml(meeting.meeting_location)}
            `;

            attendanceTableBody.innerHTML = '';
            res.rows.forEach((row, index) => {
                const tr = document.createElement('tr');
                const included = Number(row.is_included) === 1;

                tr.className = included ? 'attendance-row' : 'attendance-row is-excluded';
                tr.dataset.userId = row.user_id;

                tr.innerHTML = `
                    <td style="text-align:center;">
                        <input type="checkbox" class="attendance-include" ${included ? 'checked' : ''}
                            onchange="toggleAttendanceRow(this); updateAttendanceSummary();">
                    </td>
                    <td>${index + 1}</td>
                    <td>
                        <strong>${escapeHtml(row.name)}</strong><br>
                        <span style="color:#94a3b8;">${escapeHtml(row.email)}</span>
                    </td>
                    <td>
                        ${escapeHtml(row.position_name)}<br>
                        <span style="color:#64748b;">${escapeHtml(row.department_name)}</span>
                    </td>
                    <td>
                        <select class="attendance-role">
                            ${['chairman', 'member', 'secretary', 'observer'].map(value => `
                                <option value="${value}" ${row.attendance_role === value ? 'selected' : ''}>
                                    ${attendanceRoleLabel(value)}
                                </option>
                            `).join('')}
                        </select>
                    </td>
                    <td>
                        <select class="attendance-status" onchange="toggleRepresentativeFields(this); updateAttendanceSummary();">
                            ${['pending', 'present', 'absent', 'representative'].map(value => `
                                <option value="${value}" ${row.attendance_status === value ? 'selected' : ''}>
                                    ${attendanceStatusLabel(value)}
                                </option>
                            `).join('')}
                        </select>
                    </td>
                    <td>
                        <div class="representative-fields">
                            <input type="text" class="representative-name" placeholder="ชื่อผู้แทน" value="${escapeAttribute(row.representative_name || '')}">
                            <input type="text" class="representative-position" placeholder="ตำแหน่งผู้แทน" value="${escapeAttribute(row.representative_position || '')}">
                        </div>
                    </td>
                    <td>
                        <input type="text" class="attendance-remark" placeholder="หมายเหตุ" value="${escapeAttribute(row.attendance_remark || '')}">
                    </td>
                `;

                attendanceTableBody.appendChild(tr);
                toggleAttendanceRow(tr.querySelector('.attendance-include'));
                toggleRepresentativeFields(tr.querySelector('.attendance-status'));
            });

            updateAttendanceSummary();
            if (typeof lucide !== 'undefined') lucide.createIcons();
        })
        .catch(error => {
            attendanceTableBody.innerHTML = `
                <tr>
                    <td colspan="8" style="text-align:center;padding:30px;color:#dc2626;">
                        ${escapeHtml(error.message)}
                    </td>
                </tr>
            `;
        });
}

function toggleAttendanceRow(checkbox) {
    const row = checkbox.closest('.attendance-row');
    if (!row) return;

    const included = checkbox.checked;
    row.classList.toggle('is-excluded', !included);

    row.querySelectorAll('select, input:not(.attendance-include)').forEach(control => {
        control.disabled = !included;
    });

    if (included) {
        toggleRepresentativeFields(row.querySelector('.attendance-status'));
    }
}

function toggleAllAttendanceRows(checked) {
    document.querySelectorAll('.attendance-row .attendance-include').forEach(checkbox => {
        checkbox.checked = checked;
        toggleAttendanceRow(checkbox);
    });
    updateAttendanceSummary();
}

function toggleRepresentativeFields(select) {
    const row = select.closest('.attendance-row');
    if (!row) return;

    const included = row.querySelector('.attendance-include')?.checked ?? true;
    const disabled = !included || select.value !== 'representative';

    row.querySelector('.representative-name').disabled = disabled;
    row.querySelector('.representative-position').disabled = disabled;
}

function updateAttendanceSummary() {
    const counts = { pending: 0, present: 0, absent: 0, representative: 0 };

    document.querySelectorAll('.attendance-row').forEach(row => {
        const included = row.querySelector('.attendance-include')?.checked;
        if (!included) return;

        const select = row.querySelector('.attendance-status');
        counts[select.value] = (counts[select.value] || 0) + 1;
    });

    attendanceSummary.innerHTML = `
        <div class="attendance-summary-card">
            <strong>${counts.present}</strong>
            <span>เข้าร่วมประชุม</span>
        </div>
        <div class="attendance-summary-card">
            <strong>${counts.absent}</strong>
            <span>ไม่เข้าร่วม</span>
        </div>
        <div class="attendance-summary-card">
            <strong>${counts.representative}</strong>
            <span>เข้าร่วมแทน</span>
        </div>
        <div class="attendance-summary-card">
            <strong>${counts.pending}</strong>
            <span>ยังไม่ระบุ</span>
        </div>
    `;
}

function saveAttendanceReport() {
    const rows = [];
    document.querySelectorAll('.attendance-row').forEach(row => {
        rows.push({
            user_id: Number(row.dataset.userId),
            included: row.querySelector('.attendance-include').checked,
            attendance_role: row.querySelector('.attendance-role').value,
            attendance_status: row.querySelector('.attendance-status').value,
            representative_name: row.querySelector('.representative-name').value.trim(),
            representative_position: row.querySelector('.representative-position').value.trim(),
            attendance_remark: row.querySelector('.attendance-remark').value.trim()
        });
    });

    const invalidRepresentative = rows.find(row =>
        row.included && row.attendance_status === 'representative' && !row.representative_name
    );

    if (invalidRepresentative) {
        showWarning('กรุณาระบุชื่อผู้เข้าร่วมประชุมแทนให้ครบ', 'ข้อมูลไม่ครบถ้วน');
        return;
    }

    const fd = new FormData();
    fd.append('action', 'save_attendance');
    fd.append('meeting_id', attendanceMeetingId.value);
    fd.append('attendance_rows', JSON.stringify(rows));

    showLoading('กำลังบันทึกข้อมูลการเข้าร่วมประชุม...');

    fetch('api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            closeLoading();
            if (res.status === 'success') {
                showSuccess(res.message, 'บันทึกข้อมูลสำเร็จ').then(() => {
                    openAttendanceReport(attendanceMeetingId.value);
                });
            } else {
                showError(res.message || 'ไม่สามารถบันทึกข้อมูลได้');
            }
        })
        .catch(error => {
            console.error('saveAttendanceReport error:', error);
            closeLoading();
            showError('ไม่สามารถบันทึกข้อมูลการเข้าร่วมประชุมได้ กรุณาลองใหม่');
        });
}

function printAttendanceReport() {
    const meetingId = Number(attendanceMeetingId.value || 0);
    if (!meetingId) {
        showError('ไม่พบรหัสการประชุม');
        return;
    }
    window.open(`print_meeting_report.php?id=${meetingId}&print=1`, '_blank');
}

/* =========================================================
   Close Modals
========================================================= */
function closeAttendanceReport() {
    attendanceModal.classList.remove('show');
}

function closeModal() {
    modal.classList.remove("show");
}