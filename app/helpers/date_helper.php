<?php

if (!function_exists('thai_date')) {


    function thai_date($date_string)
    {

        if (
            !$date_string ||
            $date_string === '0000-00-00'
        ) {
            return '-';
        }


        $months = [

            "",
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

        ];


        try {


            $tz = new DateTimeZone('Asia/Bangkok');


            $date = new DateTime(
                $date_string,
                $tz
            );


            $day =
                $date->format('j');


            $month =
                $months[
                    (int)$date->format('n')
                ];


            $year =
                (int)$date->format('Y')
                + 543;


            return "$day $month $year";


        } catch(Exception $e) {

            return '-';

        }

    }

}