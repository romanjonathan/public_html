<?php
$today    = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// Fetch schedule from API for today and tomorrow
$url = 'https://ritualhouseseattle.com/api/getschedule?from=' . $today . '&to=' . $tomorrow;
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
$response = curl_exec($ch);
curl_close($ch);

if (!$response) {
    $msg = 'Failed to fetch schedule from API.';
    echo $msg . "\n";
    mail('romanjonathan999@gmail.com', 'Scraper Error', $msg);
    exit(1);
}

$json = json_decode($response, true);
if (!isset($json['data'])) {
    $msg = 'Unexpected API response: ' . $response;
    echo $msg . "\n";
    mail('romanjonathan999@gmail.com', 'Scraper Error', $msg);
    exit(1);
}

// Group classes by date
$byDay = [];
foreach ($json['data'] as $class) {
    $day = substr($class['start_date_time'], 0, 10); // "YYYY-MM-DD"
    $byDay[$day][] = $class;
}

// Format a single day's classes into a string
function formatDay($date, $classes) {
    $label = date('l, F j', strtotime($date));
    $out = $label . "\n" . str_repeat('-', strlen($label)) . "\n";
    if (empty($classes)) {
        $out .= "No classes scheduled.\n";
    } else {
        foreach ($classes as $c) {
            $start   = date('g:ia', strtotime($c['start_date_time']));
            $end     = date('g:ia', strtotime($c['end_date_time']));
            $teacher = $c['teacher']['first_name'] . ' ' . $c['teacher']['last_name'];
            $sub     = $c['isSubbed'] ? ' (sub)' : '';
            $out .= "{$c['name']} — {$teacher}{$sub}\n{$start} – {$end}\n\n";
        }
    }
    return $out;
}

$body  = "Ritual House Seattle — Upcoming Classes\n";
$body .= str_repeat('=', 50) . "\n\n";
$body .= formatDay($today, $byDay[$today] ?? []);
$body .= "\n";
$body .= formatDay($tomorrow, $byDay[$tomorrow] ?? []);

// Send email
$to      = 'romanjonathan999@gmail.com';
$subject = 'Ritual House — ' . date('D M j') . ' & ' . date('D M j', strtotime('+1 day'));
$headers = 'From: noreply@jonathanroman.me';

if (mail($to, $subject, $body, $headers)) {
    echo "Schedule emailed successfully.\n";
} else {
    echo "Scrape succeeded but email failed.\n";
}
