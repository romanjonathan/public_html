<?php
$scores   = [];
$score_id = (int)($_GET['score_id'] ?? 0);
$db_path  = __DIR__ . '/db.sqlite';

if (file_exists($db_path)) {
    try {
        $db = new PDO('sqlite:' . $db_path);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $db->query('
            SELECT id, player_name, score, ticker, played_at
            FROM scores
            ORDER BY score DESC, played_at ASC
            LIMIT 20
        ');
        $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Leaderboard</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container">
    <h1>Leaderboard</h1>

    <?php if (empty($scores)): ?>
        <p class="subtitle">No scores yet. Be the first!</p>
    <?php else: ?>
        <table class="leaderboard">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Player</th>
                    <th>Ticker</th>
                    <th>Final Bankroll</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($scores as $i => $row): ?>
                <tr class="<?= (int)$row['id'] === $score_id ? 'you' : '' ?>">
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($row['player_name']) ?></td>
                    <td><?= htmlspecialchars($row['ticker'] ?? '—') ?></td>
                    <td>$<?= number_format($row['score'], 2) ?></td>
                    <td><?= $row['played_at'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <a class="link" href="index.php">← Play</a>
</div>
</body>
</html>
