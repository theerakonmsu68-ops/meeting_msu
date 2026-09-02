
    const searchInput = document.getElementById('searchInput');
    const agendaModal = document.getElementById('agendaModal');
    const agendaList = document.getElementById('agendaList');

    document.getElementById('toggle-sidebar').addEventListener('click', function () {
        if (typeof window.toggleUserSidebar === 'function') window.toggleUserSidebar();
    });

    function filterStatus(type, btn) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll("tbody tr").forEach(row => {
            if (row.cells.length > 1) {
                if (type === 'all') {
                    row.style.display = "";
                } else {
                    row.style.display = (row.getAttribute('data-status') === type) ? "" : "none";
                }
            }
        });
    }

    function searchTable() {
        let input = searchInput.value.toLowerCase();
        document.querySelectorAll("tbody tr").forEach(row => {
            if (row.cells.length > 1) {
                row.style.display = row.textContent.toLowerCase().includes(input) ? "" : "none";
            }
        });
    }

    function viewAgenda(id) {
        agendaList.innerHTML = `<div style="text-align:center; color:#94a3b8; padding:30px 0; font-size:13.5px; font-weight:400;">กำลังโหลดข้อมูลวาระ...</div>`;
        document.getElementById('user_meeting_role').innerText = "กำลังโหลด...";
        document.getElementById('user_checkin_status').innerHTML = "กำลังโหลด...";
        
        agendaModal.classList.add("show");

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
                
                if(res && res.attendance_role) {
                    document.getElementById('user_meeting_role').innerText = roleMap[res.attendance_role] || 'ผู้เข้าร่วมประชุม';
                    if(parseInt(res.is_present) === 1) {
                        document.getElementById('user_checkin_status').innerHTML = `<span style="color:#16a34a; font-weight:600;">เข้าร่วมแล้ว</span> <span style="font-size:12px; color:#64748b;">(${res.checkin_time})</span>`;
                    } else {
                        document.getElementById('user_checkin_status').innerHTML = `<span style="color:#dc2626; font-weight:600;">ยังไม่ได้เช็กชื่อเข้าประชุม</span>`;
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

        fetch("/Meeting_msu/app/controllers/get_agenda.php?id=" + id)
            .then(r => r.json())
            .then(data => {
                let html = "";
                if (data && data.length > 0) {
                    data.forEach((a, index) => {
                        html += `
                        <div class="agenda-card">
                            <div style="font-size: 14.5px; font-weight:500; color:#1e293b; display:flex; align-items:center; gap:6px; margin-bottom:6px;">
                                <i data-lucide="bookmark-check" style="width:15px; height:15px; color:#2563eb; flex-shrink:0;"></i>
                                <span>วาระที่ ${index + 1}: ${escapeHtml(a.agenda_title)}</span>
                            </div>
                            <div style="font-size:13px; color:#64748b; line-height:1.6; padding-left:21px; font-weight:400;">
                                ${a.agenda_detail ? escapeHtml(a.agenda_detail) : '<span style="color:#cbd5e1; font-style:italic;">- ไม่มีรายละเอียดเพิ่มเติม -</span>'}
                            </div>
                        </div>`;
                    });
                } else {
                    html = `<div style="text-align:center; color:#94a3b8; padding:30px 0; font-weight:400; font-size:13.5px;">ไม่มีการกำหนดวาระสำหรับการประชุมนี้</div>`;
                }
                agendaList.innerHTML = html;
                if (typeof lucide !== 'undefined') lucide.createIcons();
            })
            .catch(err => {
                agendaList.innerHTML = `<div style="text-align:center; color:#ef4444; padding:30px 0; font-size:13.5px;">เกิดข้อผิดพลาดในการเชื่อมต่อข้อมูลวาระ</div>`;
            });
    }

    function closeModalOutside(e) {
        if (e.target === agendaModal) {
            closeAgenda();
        }
    }

    function closeAgenda() {
        agendaModal.classList.remove("show");
    }

    agendaModal.addEventListener('click', closeModalOutside);
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });
