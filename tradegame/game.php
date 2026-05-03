<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['player_name'])) {
    $_SESSION['player_name']  = htmlspecialchars(trim($_POST['player_name']), ENT_QUOTES);
    $_SESSION['bankroll']     = 1000;
    $_SESSION['round']        = 1;
    $_SESSION['total_rounds'] = 5;
}

if (empty($_SESSION['player_name'])) {
    header('Location: index.php');
    exit;
}

$round        = (int) $_SESSION['round'];
$total_rounds = (int) $_SESSION['total_rounds'];
$bankroll     = (float) $_SESSION['bankroll'];

if ($round > $total_rounds || $bankroll <= 0) {
    header('Location: index.php');
    exit;
}

// Find a window where the answer candle moves >= ±0.3%
$pool = ['AAPL', 'MSFT', 'GOOGL', 'AMZN', 'NVDA', 'META', 'TSLA'];
shuffle($pool);

$display = null;
$answer  = null;
$ticker  = null;

foreach ($pool as $t) {
    $file = __DIR__ . '/data/' . $t . '_1m.json';
    if (!file_exists($file)) continue;
    $data = json_decode(file_get_contents($file), true);
    if (count($data) < 52) continue;

    for ($i = 0; $i < 300; $i++) {
        $s    = rand(10, count($data) - 52);
        $d    = array_slice($data, $s, 50);
        $a    = $data[$s + 50];
        $prev = end($d)['close'];
        if ($prev <= 0 || $a['volume'] <= 0) continue;
        if (abs(($a['close'] - $prev) / $prev * 100) >= 0.3) {
            $ticker  = $t;
            $display = $d;
            $answer  = $a;
            break 2;
        }
    }
}

if (!$display) {
    die('No qualifying candle found. Run get_stock.py to refresh data.');
}

// Historical volatility from 50 displayed candles
$log_returns = [];
for ($i = 1; $i < count($display); $i++) {
    $p = $display[$i - 1]['close'];
    $c = $display[$i]['close'];
    if ($p > 0 && $c > 0) $log_returns[] = log($c / $p);
}
$n      = count($log_returns);
$mean   = array_sum($log_returns) / $n;
$var    = array_sum(array_map(fn($r) => ($r - $mean) ** 2, $log_returns)) / max(1, $n - 1);
$sigma  = max(0.05, sqrt($var) * sqrt(252 * 390)); // annualized, floor at 5%

// Time to expiry: minutes remaining in trading day (ET) from last candle
$last_ts = end($display)['ts'];
$last_dt = new DateTime('@' . $last_ts);
$last_dt->setTimezone(new DateTimeZone('America/New_York'));
$mins_since_open = max(1, ((int)$last_dt->format('H') - 9) * 60 + ((int)$last_dt->format('i') - 30));
$mins_remaining  = max(1, 390 - $mins_since_open);
$T = $mins_remaining / (252 * 390);

$entry_price = end($display)['close'];

$_SESSION['answer_candle'] = $answer;
$_SESSION['answer_close']  = $answer['close'];
$_SESSION['entry_price']   = $entry_price;
$_SESSION['sigma']         = $sigma;
$_SESSION['T']             = $T;

$chart_data = array_map(fn($c) => [
    'time'  => $c['ts'],
    'open'  => $c['open'],
    'high'  => $c['high'],
    'low'   => $c['low'],
    'close' => $c['close'],
], $display);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Round <?= $round ?> / <?= $total_rounds ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container">
    <div class="hud">
        <span><?= htmlspecialchars($_SESSION['player_name']) ?></span>
        <span>Round <?= $round ?> / <?= $total_rounds ?></span>
        <span>Bankroll: $<span id="hud-bankroll"><?= number_format($bankroll, 2) ?></span></span>
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
            <input type="number" id="bet-input" min="1" max="<?= floor($bankroll) ?>" value="<?= min(100, floor($bankroll)) ?>" step="1">
            <button class="bet-quick" data-pct="0.25">25%</button>
            <button class="bet-quick" data-pct="0.5">50%</button>
            <button class="bet-quick" data-pct="1">All In</button>
        </div>

        <div class="guess-form">
            <input type="hidden" id="selected-strike" value="">
            <button id="buy-call" class="guess-btn btn-up"   data-guess="call" disabled>Buy Call ↑</button>
            <button id="buy-put"  class="guess-btn btn-down" data-guess="put"  disabled>Buy Put ↓</button>
        </div>

        <p id="bet-error" class="bet-error hidden">Enter a valid bet between $1 and $<?= number_format(floor($bankroll)) ?>.</p>
        <div id="result-bar" class="hidden"></div>
    </div>
</div>

<script src="https://unpkg.com/lightweight-charts@4.2.0/dist/lightweight-charts.standalone.production.js"></script>
<script>
const candles     = <?= json_encode($chart_data) ?>;
const ROUND       = <?= $round ?>;
const TOTAL       = <?= $total_rounds ?>;
const S           = <?= $entry_price ?>;
const SIGMA       = <?= $sigma ?>;
const T           = <?= $T ?>;
let   bankroll    = <?= $bankroll ?>;
let   strikeLine  = null;
let   currentStrike = null;

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
    if (type === 'call') return Math.max(0, S * normCDF(d1)  - K * normCDF(d2));
    return Math.max(0, K * normCDF(-d2) - S * normCDF(-d1));
}

// ── Chart setup ──────────────────────────────────────────────────────────────
const chart = LightweightCharts.createChart(document.getElementById('chart'), {
    width:  document.getElementById('chart').offsetWidth,
    height: 420,
    layout: {
        background: { color: '#0f0f17' },
        textColor:  '#a0a0b0',
    },
    grid: {
        vertLines: { color: '#1e1e2e' },
        horzLines: { color: '#1e1e2e' },
    },
    timeScale: {
        timeVisible:    true,
        secondsVisible: false,
        borderColor:    '#2a2a3e',
    },
    rightPriceScale: { borderColor: '#2a2a3e' },
});

const series = chart.addCandlestickSeries({
    upColor:       '#26a69a',
    downColor:     '#ef5350',
    borderVisible: false,
    wickUpColor:   '#26a69a',
    wickDownColor: '#ef5350',
});

series.setData(candles);
chart.timeScale().fitContent();

window.addEventListener('resize', () => {
    chart.applyOptions({ width: document.getElementById('chart').offsetWidth });
});

// ── Strike selection ─────────────────────────────────────────────────────────
chart.subscribeClick(param => {
    if (!param.point) return;
    const rawPrice = series.coordinateToPrice(param.point.y);
    if (!rawPrice || rawPrice <= 0) return;

    const strike = Math.round(rawPrice); // snap to nearest $1
    currentStrike = strike;
    document.getElementById('selected-strike').value = strike;

    // Draw/update strike line
    if (strikeLine) series.removePriceLine(strikeLine);
    strikeLine = series.createPriceLine({
        price:            strike,
        color:            '#ffd700',
        lineWidth:        1,
        lineStyle:        2,
        axisLabelVisible: true,
        title:            'K',
    });

    // Compute premiums
    const callPremium = bsPrice(S, strike, T, SIGMA, 'call');
    const putPremium  = bsPrice(S, strike, T, SIGMA, 'put');

    document.getElementById('strike-display').textContent = '$' + strike.toFixed(0);
    document.getElementById('call-price').textContent     = '$' + callPremium.toFixed(3) + '/sh';
    document.getElementById('put-price').textContent      = '$' + putPremium.toFixed(3)  + '/sh';

    const moneyness = ((strike - S) / S * 100).toFixed(2);
    const noteEl    = document.getElementById('option-note');
    noteEl.textContent = strike === Math.round(S)
        ? 'ATM'
        : (strike > S ? `${moneyness}% OTM call / ITM put` : `${Math.abs(moneyness)}% ITM call / OTM put`);

    document.getElementById('option-info').classList.remove('hidden');
    document.getElementById('click-hint').classList.add('hidden');
    document.getElementById('buy-call').disabled = false;
    document.getElementById('buy-put').disabled  = false;
});

// ── Quick-bet buttons ─────────────────────────────────────────────────────────
document.querySelectorAll('.bet-quick').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('bet-input').value = Math.max(1, Math.round(bankroll * parseFloat(btn.dataset.pct)));
    });
});

// ── Buy Call / Buy Put ────────────────────────────────────────────────────────
document.querySelectorAll('.guess-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const bet    = parseInt(document.getElementById('bet-input').value);
        const strike = parseInt(document.getElementById('selected-strike').value);
        const errEl  = document.getElementById('bet-error');

        if (isNaN(bet) || bet < 1 || bet > bankroll) {
            errEl.classList.remove('hidden');
            return;
        }
        if (!strike) return;
        errEl.classList.add('hidden');
        document.querySelectorAll('.guess-btn').forEach(b => b.disabled = true);

        let data;
        try {
            const res = await fetch('check_guess.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    `guess=${btn.dataset.guess}&bet=${bet}&strike=${strike}`,
            });
            data = await res.json();
        } catch (e) {
            console.error('Fetch error:', e);
            document.querySelectorAll('.guess-btn').forEach(b => b.disabled = false);
            return;
        }

        // Reveal answer candle
        const c = data.answer_candle;
        try {
            series.setData([...candles, { time: c.ts, open: c.open, high: c.high, low: c.low, close: c.close }]);
            chart.timeScale().fitContent();
        } catch (e) { console.error('Chart update error:', e); }

        // Update HUD
        bankroll = data.bankroll;
        document.getElementById('hud-bankroll').textContent = data.bankroll.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        // Result bar
        const bar       = document.getElementById('result-bar');
        const type      = btn.dataset.guess === 'call' ? 'Call' : 'Put';
        const pnl       = data.pnl;
        const pnlFmt    = (pnl >= 0 ? '+' : '') + '$' + Math.abs(pnl).toFixed(2);
        const cls       = pnl >= 0 ? 'correct' : 'wrong';
        const entryPremium = data.premium.toFixed(3);
        const exitPremium  = data.exit_premium.toFixed(3);
        const pctSign      = data.pct > 0 ? '+' : '';

        let html = `<span class="result-verdict ${cls}">${type} K=$${strike} → ${pnlFmt}</span>
                    <span class="result-move">${pctSign}${data.pct}% | Entry: $${entryPremium} → Exit: $${exitPremium}</span>
                    <span class="result-score">Bankroll: $${data.bankroll.toLocaleString('en-US', {minimumFractionDigits:2})}</span>`;

        if (data.is_final) {
            html += `${data.busted ? '<span class="bust">Busted!</span>' : ''}
                     <a class="btn-sm" href="leaderboard.php">Leaderboard</a>
                     <a class="btn-sm btn-next" href="index.php">Play Again</a>`;
        } else {
            html += `<a class="btn-sm btn-next" href="game.php">Next Round →</a>`;
        }

        document.querySelector('.guess-form').classList.add('hidden');
        bar.innerHTML = html;
        bar.classList.remove('hidden');
    });
});
</script>
</body>
</html>
