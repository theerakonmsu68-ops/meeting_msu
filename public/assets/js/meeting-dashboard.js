const csrfToken = window.MeetingConfig.csrfToken;
const initialOpenMeetingId = window.MeetingConfig.initialOpenMeetingId;
const apiUrl = window.MeetingConfig.apiUrl;
const meetingModal = document.getElementById('meetingModal');
const meetingModalBody = document.getElementById('meetingModalBody');
const meetingActionGroup = document.getElementById('meetingActionGroup');
let activeMeeting = null;
const deletingCommentIds = new Set();
const castingVoteAgendaIds = new Set();

function safeExternalUrl(value) {
    try {
        if (!value || String(value).trim() === '') {
            return '';
        }

        const url = new URL(String(value).trim());

        return ['http:', 'https:'].includes(url.protocol)
            ? url.href
            : '';
    } catch (_) {
        return '';
    }
}

async function apiRequest(action, payload = null, method = 'GET') {
    let url = `${apiUrl}?action=${encodeURIComponent(action)}`;
    const options = {
        method,
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
    };

    if (method === 'GET' && payload) {
        const params = new URLSearchParams(payload);
        url += `&${params.toString()}`;
    } else if (payload) {
        options.headers['Content-Type'] = 'application/json';
        options.headers['X-CSRF-Token'] = csrfToken;
        options.body = JSON.stringify(payload);
    }

    const response = await fetch(url, options);
    let data;
    try {
        data = await response.json();
    } catch (_) {
        throw new Error('เซิร์ฟเวอร์ส่งข้อมูลกลับมาไม่ถูกต้อง');
    }
    if (!response.ok || data.status !== 'success') {
        throw new Error(data.message || 'ไม่สามารถดำเนินการได้');
    }
    return data;
}

async function openMeetingDetail(meetingId) {
    activeMeeting = null;
    meetingModalBody.innerHTML = '<div class="loading">กำลังโหลดข้อมูล...</div>';
    meetingActionGroup.innerHTML = '';
    meetingModal.classList.add('show');
    meetingModal.setAttribute('aria-hidden', 'false');

    try {
        const result = await apiRequest('detail', { meeting_id: meetingId });
        activeMeeting = result.meeting;
        renderMeetingDetail(result);
    } catch (error) {
        meetingModalBody.innerHTML = `<div class="empty-state"><i data-lucide="triangle-alert"></i><strong>${escapeHtml(error.message)}</strong></div>`;
        refreshIcons();
    }
}

function renderMeetingDetail(result) {
    const m = result.meeting;
    const onlineUrl = safeExternalUrl(m.meeting_link);
    const meetingDocuments = Array.isArray(result.meeting_documents) ? result.meeting_documents : [];
    const agendas = Array.isArray(result.agendas) ? result.agendas : [];

    let html = `
            <div>
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
                    <div>
                        <div style="font-size:11px;color:#0284c7;font-weight:700;">${escapeHtml(m.report_header || 'รายงานการประชุม')}</div>
                        <h3 style="margin:4px 0 0;color:#1e293b;font-size:18px;line-height:1.45;">${escapeHtml(m.meeting_title)}</h3>
                    </div>
                    <span class="rsvp-badge ${badgeClass(m)}">${escapeHtml(responseLabel(m))}</span>
                </div>

                <div class="meeting-detail-grid">
                    <div class="detail-box"><span>ครั้งที่</span><strong>${escapeHtml(m.meeting_number || '-')}</strong></div>
                    <div class="detail-box"><span>วันและเวลา</span><strong>${formatDate(m.meeting_date)} ${formatTime(m.meeting_time)}</strong></div>
                    <div class="detail-box"><span>สถานที่</span><strong>${escapeHtml(m.meeting_location || '-')}</strong></div>
                </div>

                <div class="invitation-panel">
                    <div class="invitation-summary">
                        <div><span>หน้าที่ในการประชุม</span><strong>${escapeHtml(roleLabel(m.attendance_role))}</strong></div>
                        <div><span>สถานะการประชุม</span><strong>${escapeHtml(meetingStatusLabel(m.meeting_status))}</strong></div>
                        <div><span>สถานะตอบรับ</span><strong>${escapeHtml(responseLabel(m))}</strong></div>
                        <div><span>เวลาเช็กชื่อ</span><strong>${escapeHtml(m.checkin_time || '-')}</strong></div>
                    </div>
                    ${m.attendance_status === 'representative' ? `
                        <div style="margin-top:10px;padding-top:10px;border-top:1px dashed #bae6fd;color:#475569;font-size:12px;line-height:1.6;">
                            <strong>ผู้เข้าร่วมแทน:</strong> ${escapeHtml(m.representative_name || '-')} ${m.representative_position ? `(${escapeHtml(m.representative_position)})` : ''}
                        </div>` : ''}
                    ${m.attendance_remark ? `<div style="margin-top:7px;color:#64748b;font-size:11.5px;"><strong>หมายเหตุ:</strong> ${escapeHtml(m.attendance_remark)}</div>` : ''}
                    <div id="responseFormContainer"></div>
                </div>
        `;

    if (meetingDocuments.length > 0) {
        html += `<div class="section-title"><i data-lucide="folder-down"></i> เอกสารหลักของการประชุม</div><div class="document-list">`;
        meetingDocuments.forEach(doc => {
            html += documentLink(doc);
        });
        html += '</div>';
    }

    html += `<div class="section-title"><i data-lucide="list-checks"></i> วาระการประชุม</div>`;
    if (agendas.length === 0) {
        html += '<div class="empty-state" style="padding:26px 10px;"><i data-lucide="file-x"></i><strong>ยังไม่มีวาระการประชุม</strong></div>';
    } else {
        agendas.forEach((agenda, index) => {
            const docs = Array.isArray(agenda.documents) ? agenda.documents : [];
            html += `
                    <article class="agenda-card">
                        <h4>วาระที่ ${index + 1}: ${escapeHtml(agenda.agenda_title)}</h4>
                        <p>${agenda.agenda_detail ? escapeHtml(agenda.agenda_detail) : '<span style="color:#94a3b8;">ไม่มีรายละเอียดเพิ่มเติม</span>'}</p>
                        ${docs.length ? `<div class="agenda-documents"><div style="font-size:11px;color:#64748b;margin-bottom:6px;font-weight:700;">เอกสารแนบวาระ (${docs.length})</div><div class="document-list">${docs.map(documentLink).join('')}</div></div>` : ''}
                        ${renderAgendaComments(agenda, m)}
                    </article>`;
        });
    }

    if (onlineUrl) {
        html += `<div style="margin-top:14px;">
                <a href="${escapeHtml(onlineUrl)}" target="_blank" rel="noopener noreferrer" class="btn btn-success">
                    <i data-lucide="video"></i> เข้าร่วมประชุมออนไลน์
                </a>
            </div>`;
    }

    html += '</div>';
    meetingModalBody.innerHTML = html;
    renderActionButtons(m);
    refreshIcons();
}


function renderAgendaComments(agenda, meeting) {
    const comments = Array.isArray(agenda.comments) ? agenda.comments : [];
    const canComment = meeting.meeting_status === 'ongoing';
    const voteCounts = agenda.vote_counts || { approve: 0, reject: 0, abstain: 0 };
    const myVote = agenda.my_vote || '';
    const voteLabels = { approve: 'เห็นชอบ', reject: 'ไม่เห็นชอบ', abstain: 'งดออกเสียง' };
    const canVote = meeting.meeting_status === 'ongoing' && agenda.agenda_status === 'voting';
    const showVoteResult = agenda.agenda_status === 'voting' || agenda.agenda_status === 'closed';

    return `
        <div class="agenda-comments">
            <div class="comment-title">
                <i data-lucide="message-circle"></i>
                ความคิดเห็นระหว่างประชุม (${comments.length})
            </div>

            ${comments.length
            ? comments.map(c => `
                    <div class="comment-item">
                        <div class="comment-item-header">
                            <strong>${escapeHtml(c.user_name || 'ผู้ใช้งาน')}</strong>
                            ${c.is_owner ? `<button type="button" class="comment-delete-button" onclick="deleteComment(${Number(c.comment_id)})" title="ลบความคิดเห็น" aria-label="ลบความคิดเห็น"><i data-lucide="x"></i></button>` : ''}
                        </div>
                        <p>${escapeHtml(c.comment_detail || '')}</p>
                    </div>
                `).join('')
            : `<div style="font-size:12px;color:#94a3b8;">ยังไม่มีความคิดเห็น</div>`
        }

            ${showVoteResult
            ? `<div class="vote-panel">
                    <div class="vote-panel-header">
                        <div class="vote-panel-title"><i data-lucide="vote"></i><span>${agenda.agenda_status === 'closed' ? 'สรุปผลการลงมติ' : 'ผลการลงมติ'}</span></div>
                        <span class="vote-status-badge ${agenda.agenda_status === 'closed' ? 'is-closed' : 'is-live'}"><i data-lucide="${agenda.agenda_status === 'closed' ? 'lock-keyhole' : 'radio'}"></i>${agenda.agenda_status === 'closed' ? 'ปิดแล้ว' : 'กำลังลงมติ'}</span>
                    </div>
                    <div class="vote-summary" aria-label="จำนวนผู้ลงมติ">
                        <span class="vote-count vote-count-approve"><i data-lucide="check"></i><b>${Number(voteCounts.approve) || 0}</b><small>เห็นด้วย</small></span>
                        <span class="vote-count vote-count-reject"><i data-lucide="x"></i><b>${Number(voteCounts.reject) || 0}</b><small>ไม่เห็นด้วย</small></span>
                        <span class="vote-count vote-count-abstain"><i data-lucide="minus"></i><b>${Number(voteCounts.abstain) || 0}</b><small>งดออกเสียง</small></span>
                    </div>
                    ${canVote ? `<div class="vote-actions">
                        ${['approve', 'reject', 'abstain'].map(type => `<button type="button" class="vote-button vote-button-${type}" onclick="castVote(${Number(agenda.agenda_id)}, '${type}')" ${myVote ? 'disabled' : ''}><i data-lucide="${type === 'approve' ? 'check' : type === 'reject' ? 'x' : 'minus'}"></i><span>${voteLabels[type]}</span></button>`).join('')}
                    </div>` : ''}
                    ${myVote ? `<div class="my-vote"><i data-lucide="circle-check"></i><span>คุณลงมติ: <strong>${voteLabels[myVote] || myVote}</strong></span></div>` : ''}
                </div>`
            : ''}

            ${canComment
            ? `
                <div class="comment-form">
                    <textarea class="form-control" id="comment-${agenda.agenda_id}" placeholder="แสดงความคิดเห็นต่อวาระนี้"></textarea>
                    <button type="button" class="btn btn-primary" onclick="submitComment(${agenda.agenda_id})">
                        <i data-lucide="send"></i> ส่ง
                    </button>
                </div>`
            : ''
        }
        </div>`;
}

async function submitComment(agendaId) {
    const box = document.getElementById(`comment-${agendaId}`);
    if (!box) return;

    const message = box.value.trim();
    if (!message) {
        await showAlert('กรุณากรอกความคิดเห็น', 'warning', 'คำเตือน');
        return;
    }

    try {
        await apiRequest('add_comment', {
            agenda_id: Number(agendaId),
            comment_detail: message
        }, 'POST');

        await openMeetingDetail(activeMeeting.meeting_id);
    } catch (error) {
        await showError(error.message);
    }
}

async function deleteComment(commentId) {
    const normalizedCommentId = Number(commentId);
    if (!Number.isInteger(normalizedCommentId) || normalizedCommentId <= 0 || deletingCommentIds.has(normalizedCommentId)) {
        return;
    }
    const confirmResult = await showConfirm('ยืนยันการลบความคิดเห็นนี้หรือไม่?', 'ยืนยันการลบ', 'ลบ', 'ยกเลิก');
    if (!confirmResult.isConfirmed) return;

    const meetingId = Number(activeMeeting?.meeting_id);
    if (!Number.isInteger(meetingId) || meetingId <= 0) return;

    deletingCommentIds.add(normalizedCommentId);
    try {
        await apiRequest('delete_comment', { comment_id: normalizedCommentId }, 'POST');
        await openMeetingDetail(meetingId);
    } catch (error) {
        await showError(error.message);
    } finally {
        deletingCommentIds.delete(normalizedCommentId);
    }
}

async function castVote(agendaId, voteType) {
    const normalizedAgendaId = Number(agendaId);
    if (!Number.isInteger(normalizedAgendaId) || normalizedAgendaId <= 0 || castingVoteAgendaIds.has(normalizedAgendaId)) {
        return;
    }
    if (!['approve', 'reject', 'abstain'].includes(voteType)) return;

    const meetingId = Number(activeMeeting?.meeting_id);
    if (!Number.isInteger(meetingId) || meetingId <= 0) return;

    castingVoteAgendaIds.add(normalizedAgendaId);
    try {
        await apiRequest('cast_vote', { agenda_id: normalizedAgendaId, vote_type: voteType }, 'POST');
        await openMeetingDetail(meetingId);
    } catch (error) {
        await showError(error.message);
    } finally {
        castingVoteAgendaIds.delete(normalizedAgendaId);
    }
}

function documentLink(doc) {
    const path = String(doc.file_path || '');
    const name = escapeHtml(doc.document_name || 'เอกสารแนบ');
    const href = `/Meeting_msu/app/controllers/download.php?file=${encodeURIComponent(path)}`;
    return `<a class="document-link" href="${href}" target="_blank" rel="noopener"><span><i data-lucide="file-text"></i><b>${name}</b></span><i data-lucide="download"></i></a>`;
}

function renderActionButtons(m) {
    const buttons = [];
    const isClosed = m.meeting_status === 'closed';
    const isPresent = m.attendance_status === 'present';

    if (!isClosed && !isPresent) {
        buttons.push(`<button type="button" class="btn btn-primary" onclick="submitResponse('attending')"><i data-lucide="check"></i> ยืนยันเข้าร่วม</button>`);
        buttons.push(`<button type="button" class="btn btn-danger-soft" onclick="showResponseForm('declined')"><i data-lucide="x"></i> ไม่เข้าร่วม</button>`);
        buttons.push(`<button type="button" class="btn btn-purple-soft" onclick="showResponseForm('representative')"><i data-lucide="users-round"></i> ส่งผู้แทน</button>`);
    }

    if (m.meeting_status === 'ongoing' && m.rsvp_status === 'attending' && m.attendance_status === 'pending') {
        buttons.unshift(`<button type="button" class="btn btn-success" onclick="submitCheckin()"><i data-lucide="badge-check"></i> เช็กชื่อเข้าประชุม</button>`);
    }

    if (isPresent) {
        buttons.push('<span class="rsvp-badge badge-present"><i data-lucide="badge-check" style="width:13px;margin-right:4px;"></i>เช็กชื่อเรียบร้อยแล้ว</span>');
    }

    meetingActionGroup.innerHTML = buttons.join('');
    refreshIcons();
}

function showResponseForm(type) {
    if (!activeMeeting) return;
    const container = document.getElementById('responseFormContainer');
    const isRepresentative = type === 'representative';
    container.innerHTML = `
            <div class="response-form show">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                    <strong style="font-size:13px;color:#1e293b;">${isRepresentative ? 'ข้อมูลผู้เข้าร่วมแทน' : 'แจ้งเหตุผลที่ไม่เข้าร่วม'}</strong>
                    <button type="button" onclick="document.getElementById('responseFormContainer').innerHTML=''" style="border:0;background:transparent;color:#94a3b8;cursor:pointer;"><i data-lucide="x" style="width:15px;"></i></button>
                </div>
                ${isRepresentative ? `
                    <div class="form-grid">
                        <div class="form-group"><label>ชื่อ–นามสกุลผู้แทน *</label><input id="representativeName" class="form-control" maxlength="150" value="${escapeHtml(activeMeeting.representative_name || '')}"></div>
                        <div class="form-group"><label>ตำแหน่งผู้แทน</label><input id="representativePosition" class="form-control" maxlength="150" value="${escapeHtml(activeMeeting.representative_position || '')}"></div>
                    </div>` : ''}
                <div class="form-group"><label>หมายเหตุ${isRepresentative ? '' : ' / เหตุผล'}</label><textarea id="responseRemark" class="form-control" maxlength="1000">${escapeHtml(activeMeeting.attendance_remark || '')}</textarea></div>
                <button type="button" class="btn ${isRepresentative ? 'btn-purple-soft' : 'btn-danger-soft'}" onclick="submitResponse('${type}')">
                    <i data-lucide="save"></i> บันทึก${isRepresentative ? 'ผู้แทน' : 'การไม่เข้าร่วม'}
                </button>
            </div>`;
    refreshIcons();
}

async function submitResponse(type) {
    if (!activeMeeting) return;
    const payload = { meeting_id: Number(activeMeeting.meeting_id), response: type, remark: '' };

    if (type === 'attending') {
        const confirmResult = await showConfirm('ยืนยันว่าคุณจะเข้าร่วมการประชุมนี้หรือไม่?', 'ยืนยันการเข้าร่วม', 'ยืนยัน', 'ยกเลิก');
        if (!confirmResult.isConfirmed) return;
    } else {
        payload.remark = document.getElementById('responseRemark')?.value.trim() || '';
    }

    if (type === 'representative') {
        payload.representative_name = document.getElementById('representativeName')?.value.trim() || '';
        payload.representative_position = document.getElementById('representativePosition')?.value.trim() || '';
        if (!payload.representative_name) {
            await showAlert('กรุณาระบุชื่อผู้เข้าร่วมแทน', 'warning', 'คำเตือน');
            return;
        }
    }

    setActionsDisabled(true);
    try {
        const result = await apiRequest('respond', payload, 'POST');
        await showSuccess(result.message);
        await openMeetingDetail(payload.meeting_id);
        window.setTimeout(() => window.location.reload(), 350);
    } catch (error) {
        await showError(error.message);
        setActionsDisabled(false);
    }
}

async function submitCheckin() {
    if (!activeMeeting) return;
    const confirmResult = await showConfirm('ยืนยันการเช็กชื่อเข้าร่วมประชุมครั้งนี้?', 'ยืนยันการเช็กชื่อ', 'ยืนยัน', 'ยกเลิก');
    if (!confirmResult.isConfirmed) return;
    setActionsDisabled(true);
    try {
        const result = await apiRequest('checkin', { meeting_id: Number(activeMeeting.meeting_id) }, 'POST');
        await showSuccess(result.message);
        await openMeetingDetail(activeMeeting.meeting_id);
        window.setTimeout(() => window.location.reload(), 350);
    } catch (error) {
        await showError(error.message);
        setActionsDisabled(false);
    }
}

function setActionsDisabled(disabled) {
    meetingActionGroup.querySelectorAll('button').forEach(button => button.disabled = disabled);
}

function closeMeetingModal() {
    meetingModal.classList.remove('show');
    meetingModal.setAttribute('aria-hidden', 'true');
    activeMeeting = null;
    meetingActionGroup.innerHTML = '';
}

function refreshIcons() {
    if (window.lucide) window.lucide.createIcons();
}

document.getElementById('toggle-sidebar')?.addEventListener('click', () => {
    const sidebar = document.querySelector('.sidebar') || document.querySelector('.sidebar-wrapper');
    const mainContent = document.getElementById('mainContent');
    if (!sidebar) return;

    sidebar.classList.toggle('collapsed');

    if (!window.matchMedia('(max-width: 768px)').matches) {
        mainContent?.classList.toggle('expanded', sidebar.classList.contains('collapsed'));
    } else {
        mainContent?.classList.remove('expanded');
    }
});

meetingModal.addEventListener('click', event => {
    if (event.target === meetingModal) closeMeetingModal();
});

document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && meetingModal.classList.contains('show')) closeMeetingModal();
});

document.addEventListener('DOMContentLoaded', () => {
    refreshIcons();

    if (initialOpenMeetingId > 0) {
        /*
         * เปิดรายละเอียดอัตโนมัติเฉพาะตอนเข้ามาจาก Calendar/History
         * จากนั้นลบ open_meeting ออกจาก URL ทันที
         * เพื่อไม่ให้กด Refresh แล้ว Modal เด้งซ้ำ
         */
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.delete('open_meeting');

        const cleanUrl =
            currentUrl.pathname
            + (currentUrl.searchParams.toString()
                ? '?' + currentUrl.searchParams.toString()
                : '')
            + currentUrl.hash;

        window.history.replaceState({}, document.title, cleanUrl);

        openMeetingDetail(initialOpenMeetingId);
    }
});
