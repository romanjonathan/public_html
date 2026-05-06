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
    <title>Stock Game</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container center">
    <h1>Stock Game</h1>
    <p class="subtitle">5 rounds. Predict whether the next candle closes <strong>Up</strong> or <strong>Down</strong>.</p>
    <form action="game.php" method="post">
        <input type="text" name="player_name" placeholder="Enter your name" maxlength="32" required autofocus>
        <button type="submit">Start Game</button>
    </form>
    <a class="link" href="leaderboard.php">Leaderboard</a>
</div>
</body>
</html>
