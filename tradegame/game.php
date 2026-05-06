<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['player_name'], $_POST['ticker'])) {
    $valid = ['AAPL','MSFT','GOOGL','AMZN','NVDA','META','TSLA'];
    $_SESSION['player_name'] = htmlspecialchars(trim($_POST['player_name']), ENT_QUOTES);
    $_SESSION['ticker']      = in_array($_POST['ticker'], $valid) ? $_POST['ticker'] : 'AAPL';
    $_SESSION['bankroll']    = 1000.0;
}

if (empty($_SESSION['player_name'])) {
    header('Location: index.php');
    exit;
}

$ticker = $_SESSION['ticker'];
$file   = __DIR__ . '/data/' . $ticker . '_1m.json';

if (!file_exists($file)) {
    die('No data for ' . htmlspecialchars($ticker) . '. Data will refresh on the next visit.');
}

$all = json_decode(file_get_contents($file), true);

// Filter to market hours (9:30–16:00 ET), group by date
$tz   = new DateTimeZone('America/New_York');
$days = [];
foreach ($all as $c) {
    $dt = new DateTime('@' . $c['ts']);
    $dt->setTimezone($tz);
    $h = (int)$dt->format('H');
    $m = (int)$dt->format('i');
    if ($h < 9 || ($h === 9 && $m < 30) || $h >= 16) continue;
    $days[$dt->format('Y-m-d')][] = $c;
}

// Most recent day with >= 200 candles
krsort($days);
$candles   = null;
$game_date = null;
foreach ($days as $date => $dc) {
    if (count($dc) >= 200) {
        $candles   = array_values($dc);
        $game_date = $date;
        break;
    }
}

if (!$candles) {
    die('No qualifying trading day found. Data will refresh on the next visit.');
}

// Annualized volatility from this day
$log_returns = [];
for ($i = 1; $i < count($candles); $i++) {
    $p = $candles[$i-1]['close'];
    $c = $candles[$i]['close'];
    if ($p > 0 && $c > 0) $log_returns[] = log($c / $p);
}
$n     = count($log_returns);
$mean  = $n > 0 ? array_sum($log_returns) / $n : 0;
$var   = $n > 1 ? array_sum(array_map(fn($r) => ($r - $mean) ** 2, $log_returns)) / ($n - 1) : 0;
$sigma = max(0.05, sqrt($var) * sqrt(252 * 390));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($ticker) ?> — Trade Game</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container">

    <div class="hud">
        <span><?= htmlspecialchars($_SESSION['player_name']) ?></span>
        <span id="hud-ticker"><?= htmlspecialchars($ticker) ?></span>
        <span id="hud-time">—</span>
        <span>$<span id="hud-bankroll">1,000.00</span></span>
    </div>

    <div id="chart"></div>

    <div id="action-area">
        <p id="click-hint" class="hint">Click the chart to select a strike price</p>

        <div id="option-info" class="option-info hidden">
            <span>Strike: <strong id="strike-display">—</strong></span>
            <span>Call: <strong id="call-price">—</strong></span>
            <span>Put: <strong id="put-price">—</strong></span>
            <span class="muted" id="option-note"></span>
        </div>

        <div class="bet-row">
            <label for="bet-input">Bet</label>
            <span class="bet-prefix">$</span>
            <input type="number" id="bet-input" min="1" max="1000" value="100" step="1">
            <button class="bet-quick" data-pct="0.25">25%</button>
            <button class="bet-quick" data-pct="0.5">50%</button>
            <button class="bet-quick" data-pct="1">All In</button>
        </div>

        <div class="guess-form">
            <button id="buy-call" class="guess-btn btn-up"   disabled>Buy Call ↑</button>
            <button id="buy-put"  class="guess-btn btn-down" disabled>Buy Put ↓</button>
        </div>

        <p id="bet-error" class="bet-error hidden">Enter a valid bet amount.</p>
    </div>

    <div id="positions-panel" class="hidden">
        <h3 class="positions-title">Open Positions</h3>
        <table class="positions-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Strike</th>
                    <th>Bet</th>
                    <th>Entry</th>
                    <th>Current</th>
                    <th>P&amp;L</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="positions-body"></tbody>
        </table>
    </div>

</div>

<script src="https://unpkg.com/lightweight-charts@4.2.0/dist/lightweight-charts.standalone.production.js"></script>
<script>
const CANDLES  = <?= json_encode($candles) ?>;
const SIGMA    = <?= $sigma ?>;
const TICKER   = <?= json_encode($ticker) ?>;
let   bankroll = <?= (float) $_SESSION['bankroll'] ?>;

let currentIdx    = 1;
let positions     = [];
let posIdCounter  = 0;
let currentStrike = null;
let strikeLine    = null;
let gameOver      = false;

// ── Black-Scholes ─────────────────────────────────────────────────────────────
function normCDF(x) {
    const t    = 1 / (1 + 0.2316419 * Math.abs(x));
    const poly = t * (0.319381530 + t * (-0.356563782 + t * (1.781477937 + t * (-1.821255978 + t * 1.330274429))));
    const pdf  = Math.exp(-0.5 * x * x) / Math.sqrt(2 * Math.PI);
    const cdf  = 1 - pdf * poly;
    return x >= 0 ? cdf : 1 - cdf;
}

function bsPrice(S, K, T, sigma, type) {
    if (T <= 0 || sigma <= 0 || K <= 0) {
        return type === 'call' ? Math.max(0, S - K) : Math.max(0, K - S);
    }
    const d1 = (Math.log(S / K) + 0.5 * sigma * sigma * T) / (sigma * Math.sqrt(T));
    const d2 = d1 - sigma * Math.sqrt(T);
    if (type === 'call') return Math.max(0, S * normCDF(d1) - K * normCDF(d2));
    return Math.max(0, K * normCDF(-d2) - S * normCDF(-d1));
}

// ── Chart ─────────────────────────────────────────────────────────────────────
const chart = LightweightCharts.createChart(document.getElementById('chart'), {
    width:  document.getElementById('chart').offsetWidth,
    height: 380,
    layout: { background: { color: '#0f0f17' }, textColor: '#a0a0b0' },
    grid:   { vertLines: { color: '#1e1e2e' }, horzLines: { color: '#1e1e2e' } },
    timeScale: { timeVisible: true, secondsVisible: false, borderColor: '#2a2a3e' },
    rightPriceScale: { borderColor: '#2a2a3e' },
});

const series = chart.addCandlestickSeries({
    upColor: '#26a69a', downColor: '#ef5350',
    borderVisible: false, wickUpColor: '#26a69a', wickDownColor: '#ef5350',
});

const c0 = CANDLES[0];
series.setData([{ time: c0.ts, open: c0.open, high: c0.high, low: c0.low, close: c0.close }]);
chart.timeScale().fitContent();

window.addEventListener('resize', () => {
    chart.applyOptions({ width: document.getElementById('chart').offsetWidth });
});

// ── Helpers ───────────────────────────────────────────────────────────────────
function currentS() { return CANDLES[currentIdx - 1].close; }
function currentT() { return Math.max(0, (CANDLES.length - currentIdx) / (252 * 390)); }

function formatET(ts) {
    return new Intl.DateTimeFormat('en-US', {
        hour: '2-digit', minute: '2-digit', timeZone: 'America/New_York',
    }).format(new Date(ts * 1000));
}

function updateHUD() {
    document.getElementById('hud-bankroll').textContent =
        bankroll.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('hud-time').textContent = formatET(CANDLES[currentIdx - 1].ts);
    document.getElementById('bet-input').max = Math.max(0, Math.floor(bankroll));
}

// ── Positions panel ───────────────────────────────────────────────────────────
function renderPositions() {
    const panel = document.getElementById('positions-panel');
    if (positions.length === 0) { panel.classList.add('hidden'); return; }
    panel.classList.remove('hidden');

    const S = currentS();
    const T = currentT();

    document.getElementById('positions-body').innerHTML = positions.map(pos => {
        const cur  = Math.max(0, bsPrice(S, pos.K, T, SIGMA, pos.type));
        const val  = pos.shares * cur;
        const pnl  = val - pos.bet;
        const cls  = pnl >= 0 ? 'green' : 'red';
        const sign = pnl >= 0 ? '+' : '-';
        return `<tr>
            <td class="${pos.type === 'call' ? 'green' : 'red'}">${pos.type.toUpperCase()}</td>
            <td>$${pos.K}</td>
            <td>$${pos.bet.toFixed(2)}</td>
            <td>$${pos.entryPremium.toFixed(3)}/sh</td>
            <td>$${cur.toFixed(3)}/sh</td>
            <td class="${cls}">${sign}$${Math.abs(pnl).toFixed(2)}</td>
            <td><button class="sell-btn" onclick="sellPosition(${pos.id})">Sell</button></td>
        </tr>`;
    }).join('');
}

// ── Tick ──────────────────────────────────────────────────────────────────────
const ticker = setInterval(() => {
    if (currentIdx >= CANDLES.length) {
        clearInterval(ticker);
        endOfDay();
        return;
    }
    const c = CANDLES[currentIdx];
    series.update({ time: c.ts, open: c.open, high: c.high, low: c.low, close: c.close });
    currentIdx++;
    updateHUD();
    renderPositions();
    if (currentStrike !== null) updateOptionInfo();
}, 250);

// ── Strike selection ─────────────────────────────────────────────────────────
function updateOptionInfo() {
    const S    = currentS();
    const T    = currentT();
    const K    = currentStrike;
    const call = bsPrice(S, K, T, SIGMA, 'call');
    const put  = bsPrice(S, K, T, SIGMA, 'put');

    document.getElementById('strike-display').textContent = '$' + K.toFixed(0);
    document.getElementById('call-price').textContent     = '$' + call.toFixed(3) + '/sh';
    document.getElementById('put-price').textContent      = '$' + put.toFixed(3)  + '/sh';

    const mono = ((K - S) / S * 100).toFixed(2);
    document.getElementById('option-note').textContent =
        K === Math.round(S)  ? 'ATM' :
        K > S                ? `${mono}% OTM call / ITM put` :
                               `${Math.abs(mono)}% ITM call / OTM put`;
}

chart.subscribeClick(param => {
    if (!param.point || gameOver) return;
    const raw = series.coordinateToPrice(param.point.y);
    if (!raw || raw <= 0) return;

    currentStrike = Math.round(raw);
    if (strikeLine) series.removePriceLine(strikeLine);
    strikeLine = series.createPriceLine({
        price: currentStrike, color: '#ffd700', lineWidth: 1, lineStyle: 2,
        axisLabelVisible: true, title: 'K',
    });

    document.getElementById('option-info').classList.remove('hidden');
    document.getElementById('click-hint').classList.add('hidden');
    document.getElementById('buy-call').disabled = false;
    document.getElementById('buy-put').disabled  = false;
    updateOptionInfo();
});

// ── Buy ───────────────────────────────────────────────────────────────────────
function buyOption(type) {
    if (gameOver || currentStrike === null) return;
    const bet   = parseFloat(document.getElementById('bet-input').value);
    const errEl = document.getElementById('bet-error');

    if (isNaN(bet) || bet < 1 || bet > bankroll) {
        errEl.classList.remove('hidden');
        return;
    }
    errEl.classList.add('hidden');

    const S       = currentS();
    const T       = currentT();
    const premium = Math.max(0.001, bsPrice(S, currentStrike, T, SIGMA, type));
    const shares  = bet / premium;

    bankroll -= bet;
    positions.push({ id: posIdCounter++, type, K: currentStrike, entryPremium: premium, bet, shares });
    renderPositions();
    updateHUD();
}

document.getElementById('buy-call').addEventListener('click', () => buyOption('call'));
document.getElementById('buy-put').addEventListener('click',  () => buyOption('put'));

// ── Sell ──────────────────────────────────────────────────────────────────────
function sellPosition(id) {
    const pos = positions.find(p => p.id === id);
    if (!pos) return;
    const val = pos.shares * Math.max(0, bsPrice(currentS(), pos.K, currentT(), SIGMA, pos.type));
    bankroll  += val;
    positions  = positions.filter(p => p.id !== id);
    renderPositions();
    updateHUD();
}

// ── Quick-bet ─────────────────────────────────────────────────────────────────
document.querySelectorAll('.bet-quick').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('bet-input').value =
            Math.max(1, Math.round(bankroll * parseFloat(btn.dataset.pct)));
    });
});

// ── End of day ────────────────────────────────────────────────────────────────
function endOfDay() {
    gameOver = true;
    document.getElementById('buy-call').disabled = true;
    document.getElementById('buy-put').disabled  = true;

    const expiredCount = positions.length;
    const expiredCost  = positions.reduce((s, p) => s + p.bet, 0);
    positions = [];
    renderPositions();

    const pnl      = bankroll - 1000;
    const sign     = pnl >= 0 ? '+' : '-';
    const pnlClass = pnl >= 0 ? 'green' : 'red';
    const expiredNote = expiredCount > 0
        ? `<p class="hint" style="margin-top:0.5rem">${expiredCount} position${expiredCount > 1 ? 's' : ''} expired worthless — $${expiredCost.toFixed(2)} lost</p>`
        : '';

    document.getElementById('action-area').innerHTML = `
        <div class="end-day">
            <h2>Market Closed</h2>
            <p class="end-bankroll">
                Final bankroll: <strong>$${bankroll.toLocaleString('en-US', {minimumFractionDigits:2})}</strong>
                <span class="${pnlClass}">(${sign}$${Math.abs(pnl).toFixed(2)})</span>
            </p>
            ${expiredNote}
            <div class="actions" style="margin-top:1.25rem">
                <a class="btn-sm btn-next" href="leaderboard.php">Leaderboard</a>
                <a class="btn-sm" href="index.php">Play Again</a>
            </div>
        </div>`;

    fetch('submit_score.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    `bankroll=${encodeURIComponent(bankroll.toFixed(2))}&ticker=${encodeURIComponent(TICKER)}`,
    }).catch(() => {});
}

updateHUD();
</script>
</body>
</html>
