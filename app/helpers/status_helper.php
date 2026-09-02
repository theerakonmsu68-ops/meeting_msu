<?php

if (!function_exists('rsvpBadgeClass')) {
    function rsvpBadgeClass(string $rsvpStatus, string $attendanceStatus): string
    {
        if ($attendanceStatus === 'present') {
            return 'badge-present';
        }
        if ($attendanceStatus === 'representative') {
            return 'badge-representative';
        }
        if ($attendanceStatus === 'absent' || $rsvpStatus === 'declined') {
            return 'badge-declined';
        }
        if ($rsvpStatus === 'attending') {
            return 'badge-attending';
        }
        return 'badge-pending';
    }
}

if (!function_exists('invitationClass')) {
    function invitationClass(string $rsvpStatus, string $attendanceStatus, string $meetingStatus): string
    {
        if ($meetingStatus === 'closed') {
            return 'event-closed';
        }
        if ($attendanceStatus === 'present') {
            return 'event-present';
        }
        if ($attendanceStatus === 'representative') {
            return 'event-representative';
        }
        if ($attendanceStatus === 'absent' || $rsvpStatus === 'declined') {
            return 'event-declined';
        }
        if ($rsvpStatus === 'attending') {
            return 'event-attending';
        }
        return 'event-pending';
    }
}

if (!function_exists('invitationBadgeClass')) {
    function invitationBadgeClass(string $rsvpStatus, string $attendanceStatus): string
    {
        if ($attendanceStatus === 'present') return 'badge-present';
        if ($attendanceStatus === 'representative') return 'badge-representative';
        if ($attendanceStatus === 'absent' || $rsvpStatus === 'declined') return 'badge-declined';
        if ($rsvpStatus === 'attending') return 'badge-attending';
        return 'badge-pending';
    }
}
