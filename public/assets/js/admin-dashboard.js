
(function() {
    'use strict';

    const sidebar = document.getElementById('sidebar');
    const toggleButton = document.getElementById('toggle-sidebar');
    const mainContent =
        document.getElementById('mainContent') ||
        document.getElementById('main-content');

    if (!sidebar || !toggleButton) return;

    function isMobile() {
        return window.matchMedia('(max-width: 768px)').matches;
    }

    function syncMainContent() {
        if (!mainContent) return;

        if (isMobile()) {
            /*
             * Sidebar Admin V11:
             * บนมือถือ collapsed = Drawer เปิด
             * main content จึงต้องกว้าง 100% ตลอด
             */
            mainContent.classList.remove('expanded');
        } else {
            /*
             * บน Desktop collapsed = Sidebar ย่อเหลือ 74px
             */
            mainContent.classList.toggle(
                'expanded',
                sidebar.classList.contains('collapsed')
            );
        }
    }

    toggleButton?.addEventListener('click', function(event) {
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


document.addEventListener("DOMContentLoaded", function(){

    console.log("Admin Dashboard JS Loaded");

    console.log(typeof Chart);

});

document.addEventListener("DOMContentLoaded", function () {


    console.log("Admin Dashboard Chart Loading...");



    // ===============================
    // ตรวจสอบ Chart.js
    // ===============================

    if (typeof Chart === "undefined") {

        console.error("Chart.js ไม่ถูกโหลด");

        return;

    }




    // ===============================
    // โหลดข้อมูลจาก API
    // ===============================


    fetch(BASE_URL + "admin/admin_dashboard_chart.php")


        .then(response => response.json())


        .then(data => {


            console.log("Dashboard Chart Data:", data);



            if (!data.success) {


                console.error(
                    data.message || 
                    "ไม่สามารถโหลดข้อมูลกราฟได้"
                );


                return;

            }





            // ===============================
            // กราฟจำนวนประชุมรายเดือน
            // ===============================


            const monthCanvas =
                document.getElementById(
                    "meetingMonthChart"
                );



            if (monthCanvas) {



                new Chart(monthCanvas, {


                    type: "bar",



                    data: {


                        labels: [

                            "ม.ค.",
                            "ก.พ.",
                            "มี.ค.",
                            "เม.ย.",
                            "พ.ค.",
                            "มิ.ย.",
                            "ก.ค.",
                            "ส.ค.",
                            "ก.ย.",
                            "ต.ค.",
                            "พ.ย.",
                            "ธ.ค."

                        ],



                        datasets: [{

                            label:
                            "จำนวนประชุม",


                            data:
                            data.monthly,


                            borderWidth:1


                        }]


                    },



                    options: {


                        responsive:true,


                        maintainAspectRatio:false,



                        plugins:{


                            legend:{


                                display:true,


                                position:"bottom"


                            }


                        },



                        scales:{


                            y:{


                                beginAtZero:true,


                                ticks:{


                                    precision:0


                                }


                            }


                        }


                    }



                });


            }







            // ===============================
            // กราฟสถานะการประชุม
            // ===============================


            const statusCanvas =
                document.getElementById(
                    "meetingStatusChart"
                );



            if(statusCanvas){



                new Chart(statusCanvas,{


                    type:"doughnut",



                    data:{


                        labels:[

                            "เร็ว ๆ นี้",

                            "กำลังประชุม",

                            "ปิดแล้ว"

                        ],



                        datasets:[{

                            data:[

                                data.status.upcoming || 0,

                                data.status.ongoing || 0,

                                data.status.closed || 0

                            ],


                            borderWidth:1


                        }]


                    },



                    options:{


                        responsive:true,


                        maintainAspectRatio:false,



                        plugins:{


                            legend:{


                                position:"bottom"


                            }


                        }


                    }



                });



            }




        })



        .catch(error => {


            console.error(
                "Dashboard Chart Error:",
                error
            );


        });



});
