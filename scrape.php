<?php
// Only allow execution from the command line (not via browser)
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit;
}

$url = 'https://ritualhouseseattle.com';

// Fetch HTML using cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
$html = curl_exec($ch);
curl_close($ch);

if (!$html) {
    mail('romanjonathan999@gmail.com', 'Scraper Error', 'Failed to fetch ritual house page.');
    exit(1);
}

// Strip HTML tags to get plain text
$txt = html_entity_decode(strip_tags($html));

// Extract schedule section between "full schedule" and "shoppe"
$start = strpos($txt, 'full schedule');
$end   = strpos($txt, 'shoppe');
if ($start === false || $end === false) {
    mail('romanjonathan999@gmail.com', 'Scraper Error', 'Could not find schedule markers in page.');
    exit(1);
}
$schedule = substr($txt, $start + 20, ($end - 50) - ($start + 20));

// Remove tabs
$scheduleClean = str_replace("\t", "", $schedule);

// Split by newlines, filter empty lines
$schedItems = explode("\n", $scheduleClean);
$justData = array_values(array_filter($schedItems, fn($item) => trim($item) !== ''));

// Group items into individual classes (each class ends with a time ending in 'm.')
$classes = [];
$currClass = [];
for ($i = 0; $i < count($justData); $i++) {
    $item = trim($justData[$i]);
    if ($item === '(sub)') {
        $currClass[count($currClass) - 1] .= ' (sub)';
    } else {
        $currClass[] = $item;
    }
    if (substr($item, -2) === 'm.') {
        $classes[] = $currClass;
        $currClass = [];
    }
}

// Build email body
$date = date('l, F j, Y');
$body = "Ritual House Seattle — Schedule as of $date\n";
$body .= str_repeat("=", 50) . "\n\n";
foreach ($classes as $class) {
    $body .= implode("\n", $class) . "\n\n";
}

// Send email
$to      = 'romanjonathan999@gmail.com';
$subject = "Ritual House Schedule — $date";
$headers = 'From: romanjonathan999@gmail.com';

if (mail($to, $subject, $body, $headers)) {
    echo "Schedule emailed successfully.\n";
} else {
    echo "Scrape succeeded but email failed.\n";
}
