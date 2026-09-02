<style>
/* ===============================
   CLEAN MODERN AGENDA MODAL
================================ */


.modal {

    position: fixed;
    inset: 0;

    z-index: 9999;

    display: flex;
    align-items: center;
    justify-content: center;

    padding: 24px;


    background:
        rgba(15, 23, 42, .35);


    backdrop-filter:
        blur(6px);


    opacity: 0;
    visibility: hidden;

    pointer-events: none;


    transition: .2s ease;

}


.modal.show {

    opacity: 1;
    visibility: visible;
    pointer-events: auto;

}



/* ===============================
   CONTAINER
================================ */


.agenda-modal-box {


    width: min(820px, 100%);


    max-height:
        calc(100vh - 48px);


    display: flex;

    flex-direction: column;


    background: #fff;


    border-radius: 18px;


    overflow: hidden;


    border:
        1px solid #e5e7eb;


    box-shadow:
        0 20px 40px rgba(15, 23, 42, .15);


    transform:
        translateY(15px);


    transition: .25s ease;

}



.modal.show .agenda-modal-box {

    transform:
        translateY(0);

}



/* ===============================
 HEADER
================================ */


.agenda-modal-header {


    height: 78px;


    padding: 0 24px;


    display: flex;

    align-items: center;

    justify-content: space-between;


    border-bottom:
        1px solid #f1f5f9;


}



.modal-title-group {


    display: flex;

    align-items: center;

    gap: 14px;

}



.modal-icon {


    width: 42px;

    height: 42px;


    display: flex;

    align-items: center;

    justify-content: center;


    border-radius: 12px;


    background: #eff6ff;


    color: #2563eb;


}



.modal-icon svg {

    width: 22px;

}



.modal-title-group h3 {


    margin: 0;


    font-size: 18px;


    font-weight: 700;


    color: #111827;


}



.modal-title-group span {


    display: block;


    margin-top: 3px;


    color: #64748b;


    font-size: 12px;


}



/* CLOSE */


.modal-close {


    width: 36px;

    height: 36px;


    border-radius: 10px;


    border: 0;


    background: #f8fafc;


    color: #64748b;


    cursor: pointer;


}



.modal-close:hover {


    background: #fee2e2;

    color: #dc2626;

}



/* ===============================
 BODY
================================ */


.agenda-modal-body {


    padding: 22px;


    background: #f8fafc;


    overflow-y: auto;


}



/* MAIN CARD */


.agenda-card {


    background: white;


    border:

        1px solid #e5e7eb;


    border-radius: 14px;


    padding: 20px;


    margin-bottom: 14px;


}



.agenda-card h3 {


    margin: 0 0 10px;


    font-size: 18px;


    color: #111827;


}



.agenda-card p {


    margin: 0;


    color: #475569;


    line-height: 1.7;


}



/* DETAIL */


.detail-box {


    display: flex;


    justify-content: space-between;


    align-items: center;


    padding: 14px 16px;


    margin-bottom: 10px;


    background: white;


    border:

        1px solid #e5e7eb;


    border-radius: 12px;


}



.detail-box span {


    color: #64748b;


    font-size: 13px;


}



.detail-box strong {


    color: #111827;


    font-size: 14px;


}



/* DOCUMENT */


.section-title {


    margin: 18px 0 10px;


    font-size: 14px;


    font-weight: 700;


    color: #334155;


}



.document-link {


    display: flex;


    align-items: center;


    gap: 8px;


    padding: 12px 14px;


    background: white;


    border-radius: 12px;


    border:

        1px solid #e5e7eb;


    color: #2563eb;


    text-decoration: none;


    margin-bottom: 8px;


    font-size: 14px;


}



.document-link:hover {


    background: #eff6ff;


}



/* ===============================
 FOOTER
================================ */


.agenda-modal-footer {


    height: 72px;


    padding: 0 24px;


    display: flex;


    justify-content: space-between;


    align-items: center;


    background: white;


    border-top:

        1px solid #f1f5f9;


}



.action-group {


    display: flex;

    gap: 10px;

}



/* BUTTON */


.btn-approve,
.btn-reject,
.btn-soft {


    height: 40px;


    padding: 0 18px;


    border-radius: 10px;


    font-size: 14px;


    font-weight: 600;


    cursor: pointer;


    transition: .15s;


}



/* approve */


.btn-approve {


    background: #16a34a;


    border: 1px solid #16a34a;


    color: white;

}


.btn-approve:hover {


    background: #15803d;

}



/* reject */


.btn-reject {


    background: white;


    color: #dc2626;


    border:

        1px solid #fecaca;


}



.btn-reject:hover {


    background: #fef2f2;

}



/* close */


.btn-soft {


    background: white;


    color: #475569;


    border:

        1px solid #cbd5e1;


}



.btn-soft:hover {


    background: #f8fafc;

}



/* MOBILE */

@media(max-width:640px) {


    .agenda-modal-footer {

        height: auto;

        padding: 16px;

        flex-direction: column-reverse;

        gap: 12px;

    }


    .action-group {

        width: 100%;

        flex-direction: column;

    }



    .btn-approve,
    .btn-reject,
    .btn-soft {

        width: 100%;

    }


    .detail-box {

        flex-direction: column;

        align-items: flex-start;

        gap: 5px;

    }


}
</style>

<div class="modal" id="agendaModal" aria-hidden="true">
    <div class="modal-box agenda-modal-box" role="dialog" aria-modal="true" aria-labelledby="agendaModalTitle">

        <div class="modal-header agenda-modal-header">
            <div class="modal-title-group">
                <div class="modal-icon">
                    <i data-lucide="file-text"></i>
                </div>
                <div>
                    <h3 id="agendaModalTitle">รายละเอียดวาระการประชุม</h3>
                </div>
            </div>

            <button type="button" class="modal-close" onclick="closeAgendaModal()" aria-label="Close">
                <i data-lucide="x"></i>
            </button>
        </div>

        <div class="modal-body agenda-modal-body" id="agendaModalBody">
            <div class="loading">
                <i data-lucide="loader-circle"></i>
                <span>กำลังโหลดข้อมูล...</span>
            </div>
        </div>

        <div class="modal-footer agenda-modal-footer">
            <div class="action-group" id="agendaActionGroup">
                <!-- Action Buttons will be injected here -->
            </div>

            <button type="button" class="btn-soft" onclick="closeAgendaModal()">
                ปิดหน้าต่าง
            </button>
        </div>

    </div>
</div>