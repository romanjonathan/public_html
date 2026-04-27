<?php
$page_title = "Unit Tracker";
$today = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, sans-serif;
            background: #fff;
            color: #111;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header {
            padding: 1rem 2rem;
            border-bottom: 1px solid #081f48;
            display: flex;
            align-items: baseline;
            justify-content: space-between;
        }

        header h2 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 2rem;
            color: #081f48;
            font-weight: normal;
            letter-spacing: 0.05em;
        }

        .header-date {
            font-size: 13px;
            color: #888;
        }

        .export-row {
            margin-top: 1rem;
        }

        #export-status {
            font-size: 13px;
            margin-top: 0.5rem;
            color: #888;
        }

        main {
            flex: 1;
            padding: 2rem;
            max-width: 640px;
            width: 100%;
            margin: 0 auto;
        }

        .add-row {
            display: flex;
            gap: 8px;
            margin-top: 1rem;
            align-items: center;
        }

        .unit-card {
            background: #fff;
            border: 0.5px solid #ddd;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .unit-card.active { border-color: #378ADD; }
        .unit-card.done { opacity: 0.6; }

        .unit-label {
            font-size: 15px;
            font-weight: 500;
            min-width: 60px;
        }

        .timer-display {
            font-size: 18px;
            font-weight: 500;
            font-family: monospace;
            min-width: 70px;
        }

        .status-badge {
            font-size: 12px;
            padding: 3px 10px;
            border-radius: 8px;
            min-width: 64px;
            text-align: center;
        }

        .badge-idle    { background: #f1efe8; color: #888; }
        .badge-active  { background: #e6f1fb; color: #185fa5; }
        .badge-paused  { background: #faeeda; color: #854f0b; }
        .badge-done    { background: #eaf3de; color: #3b6d11; }

        .actions {
            margin-left: auto;
            display: flex;
            gap: 8px;
        }

        button.action-btn {
            border: 0.5px solid #ccc;
            background: transparent;
            border-radius: 8px;
            padding: 6px 14px;
            font-size: 13px;
            cursor: pointer;
        }

        button.action-btn:hover { background: #f5f5f5; }
        button.action-btn:disabled { opacity: 0.35; cursor: default; }
        button.action-btn.primary {
            background: #e6f1fb;
            color: #185fa5;
            border-color: #378ADD;
        }

        .summary {
            background: #f5f5f5;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-top: 1.5rem;
        }

        .summary-title {
            font-size: 13px;
            color: #888;
            margin-bottom: 10px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            padding: 4px 0;
            border-bottom: 0.5px solid #ddd;
        }

        .summary-row:last-child { border-bottom: none; }
        .summary-row .time { font-family: monospace; color: #888; }

        @media (max-width: 480px) {
            header { padding: 0.75rem 1rem; }
            header h2 { font-size: 2.4rem; }
            .header-date { font-size: 15px; }
            main { padding: 1rem; }
            .unit-card { padding: 0.9rem 1rem; gap: 10px; }
            .unit-label { font-size: 18px; }
            .timer-display { font-size: 22px; min-width: 80px; }
            .status-badge { font-size: 14px; padding: 4px 12px; min-width: 72px; }
            button.action-btn { font-size: 16px; padding: 10px 18px; border-radius: 10px; }
            .summary { padding: 1rem; }
            .summary-title { font-size: 15px; }
            .summary-row { font-size: 16px; padding: 6px 0; }
            #export-status { font-size: 15px; }
        }
    </style>
</head>
<body>

<header>
    <h2>Unit Tracker</h2>
    <span class="header-date"><?php echo $today; ?></span>
</header>

<main>
    <div id="units-container"></div>
    <div class="summary" id="summary" style="display:none;">
        <div class="summary-title">Session summary</div>
        <div id="summary-rows"></div>
    </div>
    <div class="add-row">
        <button class="action-btn" onclick="addUnit()">+ Add unit</button>
    </div>
    <div class="export-row">
        <button class="action-btn" onclick="exportSession()">Export to Sheets</button>
        <div id="export-status"></div>
    </div>
</main>

<script>
const units = [];
let tickInterval = null;

function fmt(ms) {
    const s = Math.floor(ms / 1000);
    const m = Math.floor(s / 60);
    const h = Math.floor(m / 60);
    return h > 0
        ? h + 'h ' + String(m % 60).padStart(2, '0') + 'm'
        : String(m).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
}

function addUnit() {
    const next = units.length > 0 ? Math.max(...units.map(u => u.num)) + 1 : 1;
    units.push({ num: next, state: 'idle', elapsed: 0, startedAt: null });
    render();
}

function startUnit(num) {
    const u = units.find(u => u.num === num);
    if (!u || u.state === 'done') return;
    units.forEach(x => { if (x.state === 'active') pauseUnit(x.num); });
    u.state = 'active';
    u.startedAt = Date.now();
    if (!tickInterval) tickInterval = setInterval(tick, 1000);
    render();
}

function pauseUnit(num) {
    const u = units.find(u => u.num === num);
    if (!u || u.state !== 'active') return;
    u.elapsed += Date.now() - u.startedAt;
    u.startedAt = null;
    u.state = 'paused';
    render();
}

function finishUnit(num) {
    const u = units.find(u => u.num === num);
    if (!u || u.state === 'done') return;
    if (u.state === 'active') u.elapsed += Date.now() - u.startedAt;
    u.startedAt = null;
    u.state = 'done';
    render();
}

function tick() {
    const hasActive = units.some(u => u.state === 'active');
    if (!hasActive) { clearInterval(tickInterval); tickInterval = null; }
    render();
}

function getElapsed(u) {
    if (u.state === 'active' && u.startedAt) return u.elapsed + (Date.now() - u.startedAt);
    return u.elapsed;
}

function render() {
    const container = document.getElementById('units-container');
    container.innerHTML = units.map(u => {
        const elapsed = getElapsed(u);
        const badgeClass = { idle: 'badge-idle', active: 'badge-active', paused: 'badge-paused', done: 'badge-done' }[u.state];
        const badgeText = { idle: 'not started', active: 'in progress', paused: 'paused', done: 'done' }[u.state];
        const cardClass = u.state === 'active' ? 'active' : u.state === 'done' ? 'done' : '';
        return `<div class="unit-card ${cardClass}">
            <span class="unit-label">Unit ${u.num}</span>
            <span class="timer-display">${fmt(elapsed)}</span>
            <span class="status-badge ${badgeClass}">${badgeText}</span>
            <div class="actions">
                ${u.state === 'idle' || u.state === 'paused'
                    ? `<button class="action-btn primary" onclick="startUnit(${u.num})">Start</button>`
                    : u.state === 'active'
                    ? `<button class="action-btn" onclick="pauseUnit(${u.num})">Pause</button>`
                    : ''}
                <button class="action-btn" onclick="finishUnit(${u.num})" ${u.state === 'done' ? 'disabled' : ''}>Finish</button>
            </div>
        </div>`;
    }).join('');

    const done = units.filter(u => u.state === 'done');
    const summary = document.getElementById('summary');
    if (done.length > 0) {
        summary.style.display = 'block';
        document.getElementById('summary-rows').innerHTML = done.map(u =>
            `<div class="summary-row"><span>Unit ${u.num}</span><span class="time">${fmt(u.elapsed)}</span></div>`
        ).join('');
    } else {
        summary.style.display = 'none';
    }
}

addUnit(); addUnit(); addUnit();

async function exportSession() {
    const done = units.filter(u => u.state === 'done');
    if (done.length === 0) {
        setStatus('No finished units to export.');
        return;
    }
    setStatus('Exporting...');
    const payload = {
        date: '<?php echo $today; ?>',
        units: done.map(u => ({ num: u.num, time: fmt(u.elapsed) }))
    };
    try {
        const res = await fetch('save_session.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        setStatus(json.status === 'ok' ? 'Saved to Google Sheets.' : 'Error: ' + (json.message || 'unknown'));
    } catch (e) {
        setStatus('Request failed: ' + e.message);
    }
}

function setStatus(msg) {
    document.getElementById('export-status').textContent = msg;
}
</script>

</body>
</html>
