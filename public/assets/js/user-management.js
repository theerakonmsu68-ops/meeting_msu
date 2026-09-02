/* =========================================================
   SweetAlert2 Helpers (พร้อม Safe Fallback ป้องกัน Swal Undefined)
========================================================= */
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

/* =========================================================
   DOM Elements Selection
========================================================= */
const searchInput = document.getElementById('searchInput');
const tableContainer = document.getElementById('tableContainer');
const modal = document.getElementById('modal');
const modalTitle = document.getElementById('modalTitle');
const userIdInput = document.getElementById('user_id');
const usernameInput = document.getElementById('username');
const nameInput = document.getElementById('name');
const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const pwdLabel = document.getElementById('pwdLabel');
const pwdHelp = document.getElementById('pwdHelp');
const roleSelect = document.getElementById('role_id');
const deptSelect = document.getElementById('department_id');
const statusSelect = document.getElementById('status');
const pictureInput = document.getElementById('picture');
const imgPreview = document.getElementById('imgPreview');
const previewContainer = document.getElementById('previewContainer');
const avatarPlaceholder = document.getElementById('avatarPlaceholder');
const uploadText = document.getElementById('uploadText');
const dropZone = document.getElementById('dropZone');

let isEdit = false;
let searchTimeout = null;

/* =========================================================
   Drag & Drop Image Handling
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
    const files = dt.files;
    if (files.length) {
        pictureInput.files = files;
        previewImage(pictureInput);
    }
});

function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            imgPreview.src = e.target.result;
            previewContainer.style.display = 'block';
            avatarPlaceholder.style.display = 'none';
            uploadText.innerHTML = "เลือกรูปภาพสำเร็จ: <span>เปลี่ยนรูปภาพ</span>";
        }
        reader.readAsDataURL(input.files[0]);
    }
}

/* =========================================================
   Search & Pagination Handling
========================================================= */
function liveSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        let term = searchInput.value.trim();
        fetch(`edit_users.php?ajax=1&page=1&search=${encodeURIComponent(term)}`)
            .then(r => r.text())
            .then(html => {
                tableContainer.innerHTML = html;
                if (typeof lucide !== 'undefined') lucide.createIcons();
            })
            .catch(() => {
                showError("ไม่สามารถดึงข้อมูลค้นหาได้");
            });
    }, 300);
}

tableContainer?.addEventListener('click', function(event) {
    const link = event.target.closest('.pagination-link');
    if (!link || link.classList.contains('disabled') || link.style.pointerEvents === 'none') return;
    event.preventDefault();

    const url = new URL(link.href, window.location.href);
    url.searchParams.set('ajax', '1');

    fetch(url.toString())
        .then(r => r.text())
        .then(html => {
            tableContainer.innerHTML = html;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        })
        .catch(() => {
            showError("ไม่สามารถเปลี่ยนหน้าได้");
        });
});

/* =========================================================
   User Form Modals & CRUD Actions
========================================================= */
function openCreate() {
    isEdit = false;
    modalTitle.innerText = "เพิ่มผู้ใช้งานใหม่";
    userIdInput.value = "";
    usernameInput.value = "";
    usernameInput.disabled = false;
    nameInput.value = "";
    emailInput.value = "";
    passwordInput.value = "";
    pictureInput.value = "";

    previewContainer.style.display = 'none';
    avatarPlaceholder.style.display = 'flex';
    uploadText.innerHTML = "ลากไฟล์รูปภาพมาวางที่นี่ หรือ <span>คลิกเพื่อเลือกไฟล์</span>";

    pwdLabel.innerText = "รหัสผ่าน";
    pwdHelp.style.display = "none";
    roleSelect.value = "2";
    if (deptSelect) deptSelect.value = "";
    statusSelect.value = "active";

    modal.classList.add("show");
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function editUser(id) {
    isEdit = true;
    pictureInput.value = "";
    fetch("api.php?action=get&id=" + id)
        .then(r => r.json())
        .then(d => {
            modalTitle.innerText = "แก้ไขข้อมูลผู้ใช้งาน";
            userIdInput.value = d.user_id;
            usernameInput.value = d.username;
            usernameInput.disabled = true;
            nameInput.value = d.name;
            emailInput.value = d.email;
            passwordInput.value = "";
            pwdLabel.innerText = "เปลี่ยนรหัสผ่านใหม่ (ไม่บังคับ)";
            pwdHelp.style.display = "block";
            roleSelect.value = d.role_id;
            if (deptSelect) deptSelect.value = d.department_id ? d.department_id : "";
            statusSelect.value = d.status ? d.status : "active";

            // ปลดล็อกลิงก์รูปโปรไฟล์กรณีเป็นผู้ใช้จาก Google Login
            if (d.picture) {
                if (d.picture.startsWith('http://') || d.picture.startsWith('https://')) {
                    imgPreview.src = d.picture;
                } else {
                    imgPreview.src = "../../uploads/avatars/" + d.picture + "?t=" + new Date().getTime();
                }
                previewContainer.style.display = 'block';
                avatarPlaceholder.style.display = 'none';
                uploadText.innerHTML = "ผู้ใช้มีรูปโปรไฟล์แล้ว: <span>เปลี่ยนรูปภาพ</span>";
            } else {
                previewContainer.style.display = 'none';
                avatarPlaceholder.style.display = 'flex';
                uploadText.innerHTML = "ลากไฟล์รูปภาพมาวางที่นี่ หรือ <span>คลิกเพื่อเลือกไฟล์</span>";
            }

            modal.classList.add("show");
            if (typeof lucide !== 'undefined') lucide.createIcons();
        })
        .catch(() => {
            showError("ไม่สามารถดึงข้อมูลผู้ใช้ได้");
        });
}

function saveUser() {
    let username = usernameInput.value.trim();
    let name = nameInput.value.trim();
    let email = emailInput.value.trim();
    let password = passwordInput.value;
    let roleId = roleSelect.value;
    let deptId = deptSelect ? deptSelect.value : "";
    let id = userIdInput.value;

    // Validation ปรับเป็น SweetAlert2
    if (!isEdit && !username) {
        showWarning("กรุณากรอก Username", "ข้อมูลไม่ครบถ้วน");
        return;
    }
    if (!name || !email) {
        showWarning("กรุณากรอกชื่อและอีเมลให้ครบถ้วน", "ข้อมูลไม่ครบถ้วน");
        return;
    }
    if (!isEdit && !password) {
        showWarning("กรุณาตั้งรหัสผ่านสำหรับผู้ใช้ใหม่", "ข้อมูลไม่ครบถ้วน");
        return;
    }

    let fd = new FormData();
    fd.append("username", username);
    fd.append("name", name);
    fd.append("email", email);
    fd.append("role_id", roleId);
    fd.append("department_id", deptId);
    fd.append("status", statusSelect.value);
    fd.append("password", password);

    if (pictureInput.files[0]) {
        fd.append("picture", pictureInput.files[0]);
    }

    if (isEdit && id) {
        fd.append("action", "update");
        fd.append("user_id", id);
    } else {
        fd.append("action", "create");
    }

    showLoading(isEdit ? "กำลังบันทึกข้อมูลการแก้ไข..." : "กำลังเพิ่มผู้ใช้งานใหม่...");

    fetch("api.php", {
        method: "POST",
        body: fd
    })
    .then(r => r.json())
    .then(res => {
        closeLoading();
        if (res.status === "success") {
            showSuccess(res.message, "บันทึกสำเร็จ").then(() => {
                closeModal();
                window.location.reload();
            });
        } else {
            showError(res.message || "ไม่สามารถบันทึกข้อมูลได้");
        }
    })
    .catch(err => {
        closeLoading();
        showError("เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์");
    });
}

function deleteUser(id) {
    showConfirm("คุณแน่ใจใช่ไหมว่าต้องการลบผู้ใช้งานนี้?", "ยืนยันการลบผู้ใช้", "ลบผู้ใช้", "ยกเลิก")
    .then(result => {
        if (!result.isConfirmed) return;

        let fd = new FormData();
        fd.append("action", "delete");
        fd.append("id", id);

        showLoading("กำลังลบผู้ใช้งาน...");

        fetch("api.php", {
            method: "POST",
            body: fd
        })
        .then(r => r.json())
        .then(res => {
            closeLoading();
            if (res.status === "success") {
                showSuccess(res.message, "ลบสำเร็จ").then(() => {
                    window.location.reload();
                });
            } else {
                showError(res.message || "ไม่สามารถลบผู้ใช้งานได้");
            }
        })
        .catch(err => {
            closeLoading();
            showError("เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์");
        });
    });
}

function closeModal() {
    modal.classList.remove("show");
}

/* =========================================================
   Sidebar Layout Sync
========================================================= */
(function () {
    'use strict';

    const sidebar = document.getElementById('sidebar');
    const toggleButton = document.getElementById('toggle-sidebar');
    const mainContent =
        document.getElementById('mainContent')
        || document.getElementById('main-content');

    if (!sidebar || !toggleButton) return;

    function isMobile() {
        return window.matchMedia('(max-width: 768px)').matches;
    }

    function syncMainContent() {
        if (!mainContent) return;

        if (isMobile()) {
            mainContent.classList.remove('expanded');
        } else {
            mainContent.classList.toggle(
                'expanded',
                sidebar.classList.contains('collapsed')
            );
        }
    }

    toggleButton?.addEventListener('click', function (event) {
        event.preventDefault();
        sidebar.classList.toggle('collapsed');
        syncMainContent();
    });

    const observer = new MutationObserver(syncMainContent);
    observer.observe(sidebar, {
        attributes: true,
        attributeFilter: ['class']
    });

    window.addEventListener('resize', syncMainContent);
    document.addEventListener('DOMContentLoaded', syncMainContent);
    syncMainContent();
})();