<?php
$page_title = "Unit Tracker";
$today = date('m/d/Y');
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
            flex-direction: column;
            align-items: center;
        }

        header h2 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 2.5rem;
            color: #081f48;
            font-weight: normal;
            letter-spacing: 0.05em;
        }

        .header-date {
            font-size: 13px;
            color: #888;
            margin-top: 2px;
            text-align: center;
        }

        .export-row {
            margin-top: 1rem;
            text-align: center;
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
            justify-content: center;
        }

        .unit-card {
            background: #fff;
            border: 0.5px solid #ddd;
            border-radius: 12px;
            padding: 0.6rem 0.5rem;
            margin-bottom: 10px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            align-items: center;
            gap: 4px;
        }

        .unit-card.active { border-color: #378ADD; }
        .unit-card.done { opacity: 0.6; }

        .unit-label {
            font-size: 15px;
            font-weight: 600;
            text-align: center;
            min-width: 0;
        }

        .timer-display {
            font-size: 14px;
            font-weight: 500;
            font-family: monospace;
            text-align: center;
            min-width: 0;
        }

        .action-cell {
            display: flex;
            justify-content: center;
            min-width: 0;
        }

        button.action-btn {
            border: 0.5px solid #ccc;
            background: transparent;
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 13px;
            cursor: pointer;
            width: 100%;
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
            text-align: center;
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

        @media (max-width: 768px) {
            header { padding: 0.75rem 1rem; }
            header h2 { font-size: 3rem; }
            .header-date { font-size: 16px; }
            main { padding: 0.75rem; }
            .unit-card { padding: 0.6rem 0.25rem; gap: 2px; }
            .unit-label { font-size: 8vw; }
            .timer-display { font-size: 6vw; }
            button.action-btn { font-size: 5vw; padding: 2.5vw 1vw; border-radius: 8px; }
            .summary { padding: 1rem; }
            .summary-title { font-size: 18px; }
            .summary-row { font-size: 20px; padding: 8px 0; }
            #export-status { font-size: 16px; }
        }
    </style>
</head>
<body>

<header>
    <h2>Unit Tracker</h2>
    <span class="header-date" id="header-date"></span>
</header>

<main>
    <div id="units-container"></div>
    <div class="add-row">
        <button class="action-btn" onclick="addUnit()">+ Add Unit</button>
    </div>
    <div class="summary" id="summary" style="display:none;">
        <div class="summary-title">Session Summary</div>
        <div id="summary-rows"></div>
    </div>
    <div class="export-row">
        <button class="action-btn" onclick="exportSession()">Export to Sheets</button>
        <div id="export-status"></div>
        <button class="action-btn" onclick="window.open('https://docs.google.com/spreadsheets/d/1t4akmAF4D69uLrm4xjwJOdQEv9ydnx4utpmQb_ynhSo/edit?usp=sharing','_blank')" style="margin-top:0.5rem;">Go To Sheet</button>
    </div>
</main>

<script>
const now = new Date();
document.getElementById('header-date').textContent =
    (now.getMonth()+1).toString().padStart(2,'0') + '/' +
    now.getDate().toString().padStart(2,'0') + '/' +
    now.getFullYear();

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
        const cardClass = u.state === 'active' ? 'active' : u.state === 'done' ? 'done' : '';
        return `<div class="unit-card ${cardClass}">
            <span class="unit-label">${u.num}</span>
            <span class="timer-display">${fmt(elapsed)}</span>
            <div class="action-cell">
                ${u.state === 'idle' || u.state === 'paused'
                    ? `<button class="action-btn primary" onclick="startUnit(${u.num})">Start</button>`
                    : u.state === 'active'
                    ? `<button class="action-btn" onclick="pauseUnit(${u.num})">Pause</button>`
                    : ''}
            </div>
            <div class="action-cell">
                <button class="action-btn" onclick="finishUnit(${u.num})" ${u.state === 'done' ? 'disabled' : ''}>Finish</button>
            </div>
        </div>`;
    }).join('');

    const done = units.filter(u => u.state === 'done');
    const summary = document.getElementById('summary');
    if (done.length > 0) {
        summary.style.display = 'block';
        document.getElementById('summary-rows').innerHTML = done.map(u =>
            `<div class="summary-row"><span>${u.num}</span><span class="time">${fmt(u.elapsed)}</span></div>`
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
        date: document.getElementById('header-date').textContent,
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
