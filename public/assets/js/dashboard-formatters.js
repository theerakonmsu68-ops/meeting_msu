function formatDate(value) {
    if (!value) return '-';
    const parts = String(value).split('-');
    return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : escapeHtml(value);
}

function formatTime(value) {
    return value ? `${String(value).slice(0, 5)} น.` : '-';
}

function meetingStatusLabel(value) {
    return ({ upcoming: 'ยังไม่เริ่ม', ongoing: 'กำลังประชุม', closed: 'จบประชุมแล้ว' })[value] || 'ยังไม่เริ่ม';
}

function roleLabel(value) {
    return ({ chairman: 'ประธานกรรมการ', member: 'กรรมการ', secretary: 'เลขานุการ', observer: 'ผู้สังเกตการณ์' })[value] || 'กรรมการ';
}

function responseLabel(data) {
    if (data.attendance_status === 'present') return 'เข้าร่วมแล้ว';
    if (data.attendance_status === 'representative') return 'ส่งผู้แทนเข้าร่วม';
    if (data.attendance_status === 'absent' || data.rsvp_status === 'declined') return 'ไม่เข้าร่วม';
    if (data.rsvp_status === 'attending') return 'ยืนยันเข้าร่วม';
    return 'รอตอบรับ';
}

function badgeClass(data) {
    if (data.attendance_status === 'present') return 'badge-present';
    if (data.attendance_status === 'representative') return 'badge-representative';
    if (data.attendance_status === 'absent' || data.rsvp_status === 'declined') return 'badge-declined';
    if (data.rsvp_status === 'attending') return 'badge-attending';
    return 'badge-pending';
}
