<?php
require 'inc/db.php';
require 'inc/auth.php';
require_login();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$following_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;

if (!$following_id || $following_id == $user_id) {
    echo json_encode(['success' => false, 'error' => 'Неверный ID пользователя']);
    exit;
}

// Проверяем, существует ли пользователь
$stmt = $pdo->prepare("SELECT 1 FROM users WHERE id = ?");
$stmt->execute([$following_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Пользователь не найден']);
    exit;
}

// Проверяем, есть ли уже подписка
$stmt = $pdo->prepare("SELECT id FROM followers WHERE follower_id = ? AND following_id = ?");
$stmt->execute([$user_id, $following_id]);
$existing = $stmt->fetch();

if ($existing) {
    // Отписываемся
    $stmt = $pdo->prepare("DELETE FROM followers WHERE id = ?");
    $stmt->execute([$existing['id']]);
    echo json_encode(['success' => true, 'action' => 'unfollowed']);
} else {
    // Подписываемся
    $stmt = $pdo->prepare("INSERT INTO followers (follower_id, following_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $following_id]);
    echo json_encode(['success' => true, 'action' => 'followed']);
}