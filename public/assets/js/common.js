function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

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

function showConfirm(
    message,
    title = 'โปรดยืนยันการดำเนินการ',
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
            confirmButtonColor: '#d97706',
            cancelButtonColor: '#64748b',
            reverseButtons: true,
            focusCancel: true
        });
    }

    const confirmed = confirm((title ? title + '\n' : '') + message);
    return Promise.resolve({ isConfirmed: confirmed });
}

function handleLogout(event) {
    event.preventDefault();
    const logoutUrl = event.currentTarget?.href;
    if (!logoutUrl) return false;

    showConfirm(
        'คุณต้องการออกจากระบบใช่หรือไม่',
        'ออกจากระบบ?',
        'ออกจากระบบ',
        'ยกเลิก'
    ).then(result => {
        if (result.isConfirmed) {
            window.location.href = logoutUrl;
        }
    });

    return false;
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
