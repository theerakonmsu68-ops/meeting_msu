document.addEventListener('DOMContentLoaded', function () {

    const calendarEl = document.getElementById('calendar');

    if (!calendarEl) return;

    const events = window.CalendarConfig?.events || [];

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'th',
        timeZone: 'Asia/Bangkok',
        height: 'auto',

        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,listMonth'
        },

        buttonText: {
            today: 'วันนี้',
            month: 'เดือน',
            list: 'รายการ'
        },

        events,

        eventTimeFormat: {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        },

        eventClick: function (info) {
            info.jsEvent.preventDefault();

            const meetingId = Number(
                info.event.extendedProps.meetingId ||
                info.event.id ||
                0
            );

            if (meetingId > 0) {
                window.location.href = `index.php?open_meeting=${meetingId}`;
            }
        },

        eventDidMount: function (info) {

            const props = info.event.extendedProps || {};

            info.el.title =
                `${info.event.title}
สถานะ: ${props.invitationStatus || '-'}
สถานที่: ${props.location || '-'}`;
        }
    });


    calendar.render();


    // Sidebar Toggle
    document.getElementById('toggle-sidebar')
        ?.addEventListener('click', function () {

            const sidebar =
                document.querySelector('.sidebar') ||
                document.querySelector('.sidebar-wrapper');

            const mainContent =
                document.getElementById('mainContent');


            if (!sidebar) return;


            sidebar.classList.toggle('collapsed');


            if (!window.matchMedia('(max-width:768px)').matches) {

                mainContent?.classList.toggle(
                    'expanded',
                    sidebar.classList.contains('collapsed')
                );

            } else {

                mainContent?.classList.remove('expanded');

            }


            setTimeout(() => {
                calendar.updateSize();
            }, 300);

        });



    window.addEventListener('resize', () => {
        calendar.updateSize();
    });


    if (window.lucide) {
        window.lucide.createIcons();
    }

});
