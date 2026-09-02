
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');

    // เรียกเปิดใช้งานระบบ FullCalendar ตั้งค่าภาษาและสิทธิ์รับข้อมูลนัดหมาย
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'th',
        timeZone: 'Asia/Bangkok',
        height: 'auto',
        aspectRatio: 1.6,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,listMonth'
        },
        buttonText: { 
            today: 'วันนี้', 
            month: 'เดือน', 
            list: 'แผนงาน' 
        },
        
        // ดึงข้อมูลอีเวนต์การประชุมจาก API หลังบ้านที่จัดเตรียมไว้
        events: '/Meeting_msu/app/controllers/Get_calendar_events.php'
    });
    
    calendar.render();

    // ฟังก์ชันยืดหดแถบเมนูด้านข้างเมื่อกดปุ่มท็อกเกิลสามขีด (พร้อมอัปเดตขนาดปฏิทินให้พอดีความกว้างหน้าจอใหม่)
    document.getElementById('toggle-sidebar').addEventListener('click', function () {
        if (typeof window.toggleUserSidebar === 'function') window.toggleUserSidebar();
        setTimeout(() => calendar.updateSize(), 300);
    });

    window.addEventListener('meetingSidebarToggled', function () {
        setTimeout(() => calendar.updateSize(), 300);
    });

    // ตรวจจับกรณีผู้ใช้ขยาย/ย่อหน้าจอเบราว์เซอร์ให้ทำการจัดระเบียบโครงสร้างปฏิทินไม่ให้ตกกรอบ
    window.addEventListener('resize', function() { 
        calendar.updateSize(); 
    });
    
    // ตรวจสอบไอคอน Lucide เผื่อกรณีใช้ระบุรูปหน้าหัวข้อ
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});
