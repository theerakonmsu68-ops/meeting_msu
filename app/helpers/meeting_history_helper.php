<?php

function meetingStatusText(string $status): string
{
    return match ($status) {
        'ongoing' => 'กำลังประชุม',
        'closed' => 'ปิดประชุมแล้ว',
        default => 'ยังไม่เริ่ม',
    };
}

function invitationStatusText(string $rsvpStatus, string $attendanceStatus): string
{
    if ($attendanceStatus === 'present') {
        return 'เข้าร่วมแล้ว';
    }
    if ($attendanceStatus === 'representative') {
        return 'ส่งผู้แทนเข้าร่วม';
    }
    if ($attendanceStatus === 'absent' || $rsvpStatus === 'declined') {
        return 'ไม่เข้าร่วม';
    }
    if ($rsvpStatus === 'attending') {
        return 'ยืนยันเข้าร่วม';
    }
    return 'รอตอบรับ';
}

function attendanceRoleText(string $role): string
{
    return match ($role) {
        'chairman' => 'ประธานกรรมการ',
        'secretary' => 'เลขานุการ',
        'observer' => 'ผู้สังเกตการณ์',
        default => 'กรรมการ',
    };
}
