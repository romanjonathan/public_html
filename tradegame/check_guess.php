<?php
ini_set('display_errors', '0');
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['player_name']) || !isset($_POST['guess'], $_POST['bet'], $_POST['strike'])) {
    echo json_encode(['error' => 'invalid']);
    exit;
}

// ── Black-Scholes ─────────────────────────────────────────────────────────────
function norm_cdf(float $x): float {
    $t    = 1 / (1 + 0.2316419 * abs($x));
    $poly = $t * (0.319381530 + $t * (-0.356563782 + $t * (1.781477937 + $t * (-1.821255978 + $t * 1.330274429))));
    $pdf  = exp(-0.5 * $x * $x) / sqrt(2 * M_PI);
    $cdf  = 1 - $pdf * $poly;
    return $x >= 0 ? $cdf : 1 - $cdf;
}

function bs_price(float $S, float $K, float $T, float $sigma, string $type): float {
    if ($T <= 0 || $sigma <= 0 || $K <= 0) {
        return $type === 'call' ? max(0.0, $S - $K) : max(0.0, $K - $S);
    }
    $d1 = (log($S / $K) + 0.5 * $sigma * $sigma * $T) / ($sigma * sqrt($T));
    $d2 = $d1 - $sigma * sqrt($T);
    if ($type === 'call') return max(0.0, $S * norm_cdf($d1)  - $K * norm_cdf($d2));
    return max(0.0, $K * norm_cdf(-$d2) - $S * norm_cdf(-$d1));
}

// ── Inputs ────────────────────────────────────────────────────────────────────
$option_type  = $_POST['guess'] === 'call' ? 'call' : 'put';
$bet          = max(1, min((int) $_POST['bet'], (int) $_SESSION['bankroll']));
$K            = (float) $_POST['strike'];
$S            = (float) $_SESSION['entry_price'];
$sigma        = (float) $_SESSION['sigma'];
$T            = (float) $_SESSION['T'];
$answer_close = (float) $_SESSION['answer_close'];
$prev_close   = $S;
$answer_candle = $_SESSION['answer_candle'];

// ── Pricing & P&L ─────────────────────────────────────────────────────────────
$dt            = 1 / (252 * 390);              // 1 minute in years
$entry_premium = bs_price($S, $K, $T, $sigma, $option_type);
$entry_premium = max(0.001, $entry_premium);   // floor to avoid div/0

$T_exit        = max(0, $T - $dt);
$exit_premium  = bs_price($answer_close, $K, $T_exit, $sigma, $option_type);

$pnl     = round($bet * ($exit_premium / $entry_premium - 1), 2);
$correct = $pnl > 0;

$pct_change = $prev_close > 0 ? round(($answer_close - $prev_close) / $prev_close * 100, 3) : 0;

$_SESSION['bankroll'] = round(max(0, $_SESSION['bankroll'] + $pnl), 2);
$busted   = $_SESSION['bankroll'] <= 0;
$is_final = $busted || $_SESSION['round'] >= $_SESSION['total_rounds'];

if ($is_final) {
    try {
        $db = new PDO('sqlite:' . __DIR__ . '/db.sqlite');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('CREATE TABLE IF NOT EXISTS scores (
            id           INTEGER PRIMARY KEY,
            player_name  TEXT NOT NULL,
            score        INTEGER NOT NULL,
            total_rounds INTEGER NOT NULL,
            played_at    TEXT NOT NULL
        )');
        $stmt = $db->prepare('INSERT INTO scores (player_name, score, total_rounds, played_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$_SESSION['player_name'], $_SESSION['bankroll'], $_SESSION['total_rounds'], date('Y-m-d H:i:s')]);
    } catch (PDOException $e) {}
} else {
    $_SESSION['round']++;
}

echo json_encode([
    'correct'       => $correct,
    'pct'           => $pct_change,
    'premium'       => round($entry_premium, 4),
    'exit_premium'  => round($exit_premium, 4),
    'pnl'           => $pnl,
    'bet'           => $bet,
    'answer_candle' => $answer_candle,
    'bankroll'      => $_SESSION['bankroll'],
    'round'         => $_SESSION['round'],
    'total_rounds'  => $_SESSION['total_rounds'],
    'is_final'      => $is_final,
    'busted'        => $busted,
]);
