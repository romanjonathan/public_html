<?php
session_start();

if (empty($_SESSION['player_name']) || !isset($_POST['guess'])) {
    header('Location: index.php');
    exit;
}

$guess        = $_POST['guess'] === 'up' ? 'up' : 'down';
$answer_close = (float) $_SESSION['answer_close'];
$prev_close   = (float) $_SESSION['prev_close'];
$ticker       = $_SESSION['current_ticker'];

$actual  = $answer_close > $prev_close ? 'up' : 'down';
$correct = $guess === $actual;
$pct     = $prev_close > 0 ? round(($answer_close - $prev_close) / $prev_close * 100, 3) : 0;

if ($correct) {
    $_SESSION['score']++;
}

$is_final = $_SESSION['round'] >= $_SESSION['total_rounds'];

if ($is_final) {
    // Save score to DB
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
        $stmt->execute([$_SESSION['player_name'], $_SESSION['score'], $_SESSION['total_rounds'], date('Y-m-d H:i:s')]);
    } catch (PDOException $e) {
        // Non-fatal: score just won't be recorded
    }
} else {
    $_SESSION['round']++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Result</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container center">

    <div class="hud">
        <span><?= htmlspecialchars($_SESSION['player_name']) ?></span>
        <span>Round <?= $_SESSION['round'] - ($is_final ? 0 : 1) ?> / <?= $_SESSION['total_rounds'] ?></span>
        <span>Score: <?= $_SESSION['score'] ?></span>
    </div>

    <div class="result-card <?= $correct ? 'correct' : 'wrong' ?>">
        <?php if ($correct): ?>
            <div class="result-icon">✓</div>
            <h2>Correct</h2>
        <?php else: ?>
            <div class="result-icon">✗</div>
            <h2>Wrong</h2>
        <?php endif; ?>

        <p class="result-detail">
            <strong><?= $ticker ?></strong> moved
            <span class="<?= $actual === 'up' ? 'green' : 'red' ?>">
                <?= $actual === 'up' ? '▲' : '▼' ?>
                <?= $actual ?> <?= $pct > 0 ? '+' : '' ?><?= $pct ?>%
            </span>
            &mdash; you guessed <strong><?= $guess ?></strong>
        </p>
    </div>

    <?php if ($is_final): ?>
        <h2 class="final-score">Final score: <?= $_SESSION['score'] ?> / <?= $_SESSION['total_rounds'] ?></h2>
        <div class="actions">
            <a class="btn-up"   href="leaderboard.php">Leaderboard</a>
            <a class="btn-down" href="index.php">Play Again</a>
        </div>
    <?php else: ?>
        <a class="btn-next" href="game.php">Next Round →</a>
    <?php endif; ?>

</div>
</body>
</html>
