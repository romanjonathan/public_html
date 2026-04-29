<?php
// ── Config ────────────────────────────────────────────────────────────────────
define('GAS_URL', 'https://script.google.com/macros/s/AKfycbzeP0j1aYkjWqSjwbfC8fQU7sLLDK6_plsdFGBw2fo4QCbAkAENqqjm1SLL3nodbRmN/exec');

// ── Helpers ───────────────────────────────────────────────────────────────────
function gas_fetch(int $n = 16): array {
    if (!GAS_URL) return [];
    $url = GAS_URL . '?' . http_build_query(['n' => $n]);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $rows = json_decode($res, true) ?? [];
    usort($rows, fn($a, $b) => strcmp($a['date'], $b['date']));
    return $rows;
}

function gas_post(string $date, float $weight, float $screentime): void {
    if (!GAS_URL) return;
    $ch = curl_init(GAS_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(compact('date', 'weight', 'screentime')),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// ── Handle form submit ────────────────────────────────────────────────────────
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date    = trim($_POST['date']    ?? '');
    $weight  = trim($_POST['weight']  ?? '');
    $st_hrs  = trim($_POST['st_hrs']  ?? '');
    $st_mins = trim($_POST['st_mins'] ?? '0');

    if (!$date || !DateTime::createFromFormat('Y-m-d', $date)) {
        $error = 'Please enter a valid date.';
    } elseif ($weight === '' || !is_numeric($weight) || (float)$weight <= 0) {
        $error = 'Please enter a valid weight.';
    } elseif ($st_hrs === '' || !ctype_digit($st_hrs)) {
        $error = 'Please enter screen time hours.';
    } elseif (!ctype_digit($st_mins) || (int)$st_mins > 59) {
        $error = 'Minutes must be 0–59.';
    } else {
        $screentime = round((int)$st_hrs + (int)$st_mins / 60, 1);
        gas_post($date, round((float)$weight, 1), $screentime);
        header('Location: health_tracker.php?saved=1');
        exit;
    }
}

// ── Fetch chart data ──────────────────────────────────────────────────────────
$rows = gas_fetch();

$wLabels  = json_encode(array_column($rows, 'date'));
$wValues  = json_encode(array_column($rows, 'weight'));
$stLabels = json_encode(array_column($rows, 'date'));
$stValues = json_encode(array_column($rows, 'screentime'));

$today  = date('Y-m-d');
$saved  = isset($_GET['saved']);
$no_url = !GAS_URL;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Tracker</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0f0f0f;
            color: #e8e8e8;
            min-height: 100vh;
            padding: 24px 16px 60px;
        }

        h1 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #fff;
        }

        .notice {
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 16px;
        }
        .notice.warn  { background: #2a2200; border: 1px solid #5a4500; color: #f0c040; }
        .notice.ok    { background: #0a2a12; border: 1px solid #1a5c2a; color: #6fcf84; }
        .notice.error { background: #2a0a0a; border: 1px solid #5c1a1a; color: #e57373; }

        /* ── Chart ── */
        .chart-card {
            background: #1a1a1a;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
        }

        .chart-controls {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }

        .toggle-btn {
            padding: 6px 16px;
            border-radius: 20px;
            border: 1px solid #333;
            background: transparent;
            color: #555;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: color 0.15s, border-color 0.15s, background 0.15s;
            -webkit-appearance: none;
        }

        .empty {
            text-align: center;
            color: #555;
            font-size: 13px;
            padding: 28px 0;
        }

        /* ── Log form ── */
        .card {
            background: #1a1a1a;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
        }

        .card h2 {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #666;
            margin-bottom: 14px;
        }

        .field {
            margin-bottom: 10px;
        }

        .field label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: #999;
            margin-bottom: 5px;
        }

        .field input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #2e2e2e;
            border-radius: 8px;
            font-size: 16px; /* prevents iOS zoom */
            color: #e8e8e8;
            background: #242424;
            -webkit-appearance: none;
            appearance: none;
        }

        .field input:focus {
            outline: none;
            border-color: #4a90d9;
            background: #2a2a2a;
        }

        .st-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .st-row .field { margin-bottom: 0; }

        .btn {
            display: block;
            width: 100%;
            padding: 12px;
            background: #e8e8e8;
            color: #111;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 14px;
            -webkit-appearance: none;
        }

        .btn:active { background: #bbb; }
    </style>
</head>
<body>
    <h1>Health Tracker</h1>

    <?php if ($no_url): ?>
    <div class="notice warn">GAS_URL not configured.</div>
    <?php elseif ($saved): ?>
    <div class="notice ok">Logged successfully.</div>
    <?php elseif ($error): ?>
    <div class="notice error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="chart-card">
        <?php if (empty($rows)): ?>
            <p class="empty">No data yet.</p>
        <?php else: ?>
            <div class="chart-controls">
                <button class="toggle-btn" id="btn-weight"     onclick="setActive(0)">Weight</button>
                <button class="toggle-btn" id="btn-screentime" onclick="setActive(1)">Screen Time</button>
            </div>
            <canvas id="mainChart"></canvas>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Log Entry</h2>
        <form method="POST">
            <div class="field">
                <label for="date">Date</label>
                <input type="date" id="date" name="date"
                       value="<?= htmlspecialchars($_POST['date'] ?? $today) ?>" required>
            </div>
            <div class="field">
                <label for="weight">Weight (lbs)</label>
                <input type="number" id="weight" name="weight"
                       inputmode="decimal" step="0.1" min="0"
                       placeholder="175.0"
                       value="<?= htmlspecialchars($_POST['weight'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Screen Time</label>
                <div class="st-row">
                    <div class="field">
                        <label for="st_hrs">Hours</label>
                        <input type="number" id="st_hrs" name="st_hrs"
                               inputmode="numeric" min="0" step="1"
                               placeholder="2"
                               value="<?= htmlspecialchars($_POST['st_hrs'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label for="st_mins">Minutes</label>
                        <input type="number" id="st_mins" name="st_mins"
                               inputmode="numeric" min="0" max="59" step="1"
                               placeholder="30"
                               value="<?= htmlspecialchars($_POST['st_mins'] ?? '') ?>">
                    </div>
                </div>
            </div>
            <button type="submit" class="btn">Log</button>
        </form>
    </div>

<?php if (!empty($rows)): ?>
<script>
const COLORS = ['#4a90d9', '#e07b4a'];
const BTNS   = ['btn-weight', 'btn-screentime'];

function decimalToHHMM(v) {
    const h = Math.floor(v);
    const m = Math.round((v - h) * 60);
    return h + ':' + String(m).padStart(2, '0');
}

const chart = new Chart(document.getElementById('mainChart'), {
    type: 'line',
    data: {
        labels: <?= $wLabels ?>,
        datasets: [
            {
                data: <?= $wValues ?>,
                borderColor: COLORS[0],
                backgroundColor: COLORS[0] + '22',
                pointBackgroundColor: COLORS[0],
                pointRadius: 4,
                fill: true,
                tension: 0.3,
            },
            {
                data: <?= $stValues ?>,
                borderColor: COLORS[1],
                backgroundColor: COLORS[1] + '22',
                pointBackgroundColor: COLORS[1],
                pointRadius: 4,
                fill: true,
                tension: 0.3,
                hidden: true,
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => ctx.datasetIndex === 1
                        ? decimalToHHMM(ctx.parsed.y)
                        : ctx.parsed.y
                }
            }
        },
        scales: {
            x: {
                grid: { color: '#2a2a2a' },
                ticks: { color: '#666', font: { size: 11 }, maxRotation: 45 }
            },
            y: {
                beginAtZero: false,
                grid: { color: '#2a2a2a' },
                ticks: { color: '#666', font: { size: 11 } },
                title: {
                    display: true,
                    text: 'lbs',
                    color: '#555',
                    font: { size: 11 }
                }
            }
        },
        elements: { line: { tension: 0.3 } }
    }
});

function setActive(idx) {
    chart.data.datasets.forEach((ds, i) => { ds.hidden = (i !== idx); });

    const yScale = chart.options.scales.y;
    if (idx === 1) {
        yScale.ticks.callback = v => decimalToHHMM(v);
        yScale.title.text = 'hrs';
    } else {
        yScale.ticks.callback = undefined;
        yScale.title.text = 'lbs';
    }
    chart.update();

    BTNS.forEach((id, i) => {
        const btn = document.getElementById(id);
        const active = i === idx;
        btn.style.color       = active ? COLORS[i] : '#555';
        btn.style.borderColor = active ? COLORS[i] : '#333';
        btn.style.background  = active ? COLORS[i] + '18' : 'transparent';
    });
}

setActive(0);
</script>
<?php endif; ?>
</body>
</html>
