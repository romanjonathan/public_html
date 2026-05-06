<?php
ini_set('display_errors', '0');
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['player_name'])) {
    echo json_encode(['error' => 'no session']);
    exit;
}

$valid   = ['AAPL','MSFT','GOOGL','AMZN','NVDA','META','TSLA'];
$bankroll = round((float)($_POST['bankroll'] ?? 0), 2);
$ticker   = in_array($_POST['ticker'] ?? '', $valid) ? $_POST['ticker'] : null;

try {
    $db = new PDO('sqlite:' . __DIR__ . '/db.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE IF NOT EXISTS scores (
        id           INTEGER PRIMARY KEY,
        player_name  TEXT NOT NULL,
        score        REAL NOT NULL,
        total_rounds INTEGER NOT NULL DEFAULT 0,
        played_at    TEXT NOT NULL
    )');
    // Add ticker column if not present (migration from old schema)
    try { $db->exec('ALTER TABLE scores ADD COLUMN ticker TEXT'); } catch (PDOException $e) {}

    $stmt = $db->prepare('INSERT INTO scores (player_name, score, total_rounds, ticker, played_at) VALUES (?, ?, 0, ?, ?)');
    $stmt->execute([$_SESSION['player_name'], $bankroll, $ticker, date('Y-m-d H:i:s')]);
    echo json_encode(['ok' => true, 'id' => (int)$db->lastInsertId()]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'db']);
}
