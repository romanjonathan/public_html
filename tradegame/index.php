<?php
session_start();
session_unset();

$lock = __DIR__ . '/.last_stock_run';
if (!file_exists($lock) || (time() - filemtime($lock)) > 3600) {
    touch($lock);
    $script = escapeshellarg(__DIR__ . '/get_stock.py');
    exec('nohup /usr/bin/python3 ' . $script . ' > /dev/null 2>&1 &');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Trade Game</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container center">
    <h1>Trade Game</h1>
    <p class="subtitle">Trade 0DTE options through a full market day. Open positions at close expire worthless.</p>
    <form action="game.php" method="post">
        <input type="text" name="player_name" placeholder="Your name" maxlength="32" required autofocus>
        <select name="ticker">
            <option value="AAPL">AAPL — Apple</option>
            <option value="MSFT">MSFT — Microsoft</option>
            <option value="GOOGL">GOOGL — Alphabet</option>
            <option value="AMZN">AMZN — Amazon</option>
            <option value="NVDA">NVDA — Nvidia</option>
            <option value="META">META — Meta</option>
            <option value="TSLA">TSLA — Tesla</option>
        </select>
        <button type="submit">Start Trading</button>
    </form>
    <a class="link" href="leaderboard.php">Leaderboard</a>
</div>
</body>
</html>
