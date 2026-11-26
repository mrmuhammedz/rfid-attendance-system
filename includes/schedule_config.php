<?php
function scheduleDays(): array {
    return ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
}

function schedulePeriods(): array {
    return [
        ['start' => '09:00', 'end' => '10:30'],
        ['start' => '10:30', 'end' => '12:00'],
        ['start' => '12:00', 'end' => '13:30'],
        ['start' => '13:30', 'end' => '15:00'],
        ['start' => '15:00', 'end' => '16:30'],
    ];
}
