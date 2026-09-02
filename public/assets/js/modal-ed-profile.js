(function () {

    'use strict';


    const profileUpdateUrl = window.profileUpdateUrl || '';

    const modal = document.getElementById('profileModal');

    const form = document.getElementById('profileForm');

    const fileInput = document.getElementById('p_file_input');

    const previewImage = document.getElementById('pPreviewImg');

    const nameInput = document.getElementById('p_name_input');

    const submitButton = document.getElementById('pSubmitButton');

    const submitText = document.getElementById('pSubmitText');

    const formMessage = document.getElementById('pFormMessage');

    const originalPreview = previewImage ? previewImage.src : '';

    let isSubmitting = false;





    window.openProfileModal = function () {

        if (!modal) return;

        modal.classList.add('show');

        modal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.style.overflow = 'hidden';


        window.setTimeout(function () {

            if (nameInput) {

                nameInput.focus();

            }

        },120);


        refreshProfileIcons();

    };







    window.closeProfileModal = function () {

        if (!modal || isSubmitting) return;


        modal.classList.remove('show');


        modal.setAttribute(
            'aria-hidden',
            'true'
        );


        document.body.style.overflow = '';


        clearProfileMessage();

    };







    function refreshProfileIcons(){

        if(
            window.lucide &&
            typeof window.lucide.createIcons === 'function'
        ){

            window.lucide.createIcons();

        }

    }








    function clearProfileMessage(){

        if(!formMessage) return;


        formMessage.className =
        'p-form-message';


        formMessage.textContent='';

    }








    function showProfileMessage(message,type){

        if(!formMessage) return;


        formMessage.className =
        'p-form-message show ' +
        (
            type === 'success'
            ?
            'success'
            :
            'error'
        );


        formMessage.textContent = message;

    }








    function setSubmitting(state){

        isSubmitting = state;


        if(!submitButton || !submitText)
        {
            return;
        }


        submitButton.disabled = state;



        if(state){

            submitButton
            .querySelector('[data-lucide]')
            ?.remove();


            const spinner =
            document.createElement('span');


            spinner.className =
            'p-spinner';


            submitButton.prepend(spinner);


            submitText.textContent =
            'กำลังบันทึก...';

        }

        else{


            submitButton
            .querySelector('.p-spinner')
            ?.remove();



            if(
                !submitButton.querySelector('[data-lucide]')
            ){

                const icon =
                document.createElement('i');


                icon.setAttribute(
                    'data-lucide',
                    'save'
                );


                icon.style.width='16px';

                icon.style.height='16px';


                submitButton.prepend(icon);

            }


            submitText.textContent =
            'บันทึกการเปลี่ยนแปลง';


            refreshProfileIcons();

        }

    }








    if(fileInput){

        fileInput.addEventListener(
            'change',
            function(){

                clearProfileMessage();


                const file =
                this.files && this.files[0];



                if(!file){

                    if(previewImage)
                    {
                        previewImage.src =
                        originalPreview;
                    }

                    return;

                }



                const allowedTypes =
                [
                    'image/jpeg',
                    'image/png',
                    'image/webp'
                ];



                if(!allowedTypes.includes(file.type)){

                    showProfileMessage(
                        'รองรับเฉพาะไฟล์ JPG, PNG และ WEBP เท่านั้น',
                        'error'
                    );


                    this.value='';


                    if(previewImage)
                    {
                        previewImage.src =
                        originalPreview;
                    }


                    return;

                }




                if(file.size > 2 * 1024 * 1024){


                    showProfileMessage(
                        'ไฟล์รูปภาพมีขนาดเกิน 2 MB กรุณาเลือกไฟล์ใหม่',
                        'error'
                    );


                    this.value='';


                    if(previewImage)
                    {
                        previewImage.src =
                        originalPreview;
                    }


                    return;

                }






                const reader =
                new FileReader();



                reader.onload =
                function(event){

                    if(
                        previewImage &&
                        event.target &&
                        typeof event.target.result === 'string'
                    ){

                        previewImage.src =
                        event.target.result;

                    }

                };



                reader.readAsDataURL(file);


            }
        );

    }







    if(modal){

        modal.addEventListener(
            'click',
            function(event){

                if(event.target === modal){

                    window.closeProfileModal();

                }

            }
        );

    }








    document.addEventListener(
        'keydown',
        function(event){

            if(
                event.key === 'Escape' &&
                modal &&
                modal.classList.contains('show')
            ){

                window.closeProfileModal();

            }

        }
    );









    window.submitProfileForm =
    async function(event){


        event.preventDefault();


        if(!form || isSubmitting)
        {
            return;
        }


        clearProfileMessage();



        const trimmedName =
        nameInput
        ?
        nameInput.value.trim()
        :
        '';



        if(trimmedName.length < 2){


            showProfileMessage(
                'กรุณาระบุชื่อ–นามสกุลอย่างน้อย 2 ตัวอักษร',
                'error'
            );


            if(nameInput)
            {
                nameInput.focus();
            }


            return;

        }



        if(nameInput)
        {
            nameInput.value =
            trimmedName;
        }



        setSubmitting(true);




        try{


            const response =
            await fetch(
                profileUpdateUrl,
                {
                    method:'POST',

                    body:new FormData(form),

                    credentials:'same-origin',

                    headers:{
                        'X-Requested-With':
                        'XMLHttpRequest'
                    }

                }
            );




            const responseText =
            await response.text();



            let result;



            try{

                result =
                JSON.parse(responseText);

            }

            catch{

                throw new Error(
                    'เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง'
                );

            }






            if(
                !response.ok ||
                !result.success
            ){

                throw new Error(
                    result.message ||
                    'ไม่สามารถบันทึกข้อมูลโปรไฟล์ได้'
                );

            }





            showProfileMessage(
                result.message ||
                'อัปเดตข้อมูลโปรไฟล์เรียบร้อยแล้ว',
                'success'
            );



            window.setTimeout(
                function(){

                    window.location.reload();

                },
                650
            );


        }


        catch(error){


            showProfileMessage(
                error.message ||
                'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์',
                'error'
            );


            setSubmitting(false);


        }



    };






    document.addEventListener(
        'DOMContentLoaded',
        refreshProfileIcons
    );



})();