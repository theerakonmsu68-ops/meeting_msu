
const agendaModal = document.getElementById('agendaModal');
const agendaList = document.getElementById('agendaList');

document.getElementById('toggle-sidebar').addEventListener('click', function () {
    if (typeof window.toggleUserSidebar === 'function') window.toggleUserSidebar();
});

function viewAgenda(id) {
    agendaList.innerHTML = `<div style="text-align:center; color:#94a3b8; padding:20px 0; font-size:13px;">กำลังโหลดข้อมูล...</div>`;
    document.getElementById('user_meeting_role').innerText = "กำลังโหลด...";
    document.getElementById('user_checkin_status').innerHTML = "กำลังโหลด...";

    agendaModal.classList.add("show");

    // ดึงบทบาทจากตาราง meeting_attendance ผ่าน API หลังบ้าน
    fetch('api.php?action=get_rsvp&meeting_id=' + id)
        .then(r => r.json())
        .then(res => {
            const roleMap = {
                'chairman': 'ประธานในที่ประชุม',
                'member': 'ผู้เข้าร่วมประชุม',
                'secretary': 'เลขานุการ',
                'observer': 'ผู้สังเกตการณ์',
                'general_user': 'ผู้เข้าร่วมทั่วไป'
            };
            if (res && res.attendance_role) {
                document.getElementById('user_meeting_role').innerText = roleMap[res.attendance_role] || 'ผู้เข้าร่วมประชุม';
                if (parseInt(res.is_present) === 1) {
                    document.getElementById('user_checkin_status').innerHTML = `<span style="color:#16a34a; font-weight:600;">เข้าร่วมแล้ว</span> <span style="font-size:11px; color:#64748b;">(${res.checkin_time})</span>`;
                } else {
                    document.getElementById('user_checkin_status').innerHTML = `<span style="color:#dc2626; font-weight:600;">ผู้ใช้งานทั่วไป</span>`;
                }
            } else {
                document.getElementById('user_meeting_role').innerText = "ผู้เข้าร่วมทั่วไป";
                document.getElementById('user_checkin_status').innerHTML = `<span style="color:#64748b;">ไม่มีข้อมูลการเช็กชื่อ</span>`;
            }
        })
        .catch(() => {
            document.getElementById('user_meeting_role').innerText = "ผู้เข้าร่วมทั่วไป";
            document.getElementById('user_checkin_status').innerHTML = "ไม่มีข้อมูลการเช็กชื่อ";
        });

    // ดึงข้อมูลวาระมาแสดงผลปกติ
    fetch("/app/controllers/get_agenda.php?id=" + id)
        .then(r => r.json())
        .then(data => {
            let html = "";
            if (data && data.length > 0) {
                data.forEach((a, index) => {
                    html += `
                    <div class="agenda-card">
                        <div style="font-size: 14.5px; font-weight:500; color:#1e293b; display:flex; align-items:center; gap:6px; margin-bottom:4px;">
                            <i data-lucide="bookmark-check" style="width:14px; color:#2563eb; flex-shrink:0;"></i>
                            <span>วาระที่ ${index + 1}: ${escapeHtml(a.agenda_title)}</span>
                        </div>
                        <div style="font-size:13px; color:#64748b; line-height:1.5; padding-left:20px;">
                            ${a.agenda_detail ? escapeHtml(a.agenda_detail) : '<span style="color:#cbd5e1; font-style:italic;">- ไม่มีรายละเอียดเพิ่มเติม -</span>'}
                        </div>
                    </div>`;
                });
            } else {
                html = `<div style="text-align:center; color:#94a3b8; padding:20px 0; font-size:13px;">ไม่มีการกำหนดวาระ</div>`;
            }
            agendaList.innerHTML = html;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        })
        .catch(() => {
            agendaList.innerHTML = `<div style="text-align:center; color:#ef4444; padding:20px 0; font-size:13px;">เกิดข้อผิดพลาดในการโหลดวาระ</div>`;
        });
}

function closeAgenda() {
    agendaModal.classList.remove("show");
}

agendaModal.addEventListener('click', function (e) {
    if (e.target === agendaModal) closeAgenda();
});

document.addEventListener('DOMContentLoaded', function () {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});
