<?php
require 'inc/db.php';
require 'inc/auth.php';
require_login();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : null;
$comment_id = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : null;
$is_dislike = isset($_POST['dislike']) ? 1 : 0;

if ($post_id === null && $comment_id === null) {
    http_response_code(400);
    echo json_encode(['error' => 'post_id или comment_id обязательны']);
    exit;
}

if ($post_id !== null) {
    $stmt = $pdo->prepare("SELECT 1 FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    if (!$stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Пост не найден']);
        exit;
    }
}

if ($comment_id !== null) {
    $stmt = $pdo->prepare("SELECT 1 FROM comments WHERE id = ?");
    $stmt->execute([$comment_id]);
    if (!$stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Комментарий не найден']);
        exit;
    }
}

// Проверяем, есть ли уже лайк/дизлайк от пользователя
if ($post_id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM likes WHERE user_id = ? AND post_id = ? AND comment_id IS NULL");
    $stmt->execute([$user_id, $post_id]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM likes WHERE user_id = ? AND comment_id = ? AND post_id IS NULL");
    $stmt->execute([$user_id, $comment_id]);
}
$existing = $stmt->fetch();

if (!$existing) {
    // Вставляем лайк/дизлайк
    $stmt = $pdo->prepare("INSERT INTO likes (user_id, post_id, comment_id, is_dislike) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $post_id, $comment_id, $is_dislike]);
} else {
    if ((int)$existing['is_dislike'] === $is_dislike) {
        // Удаляем, если нажали повторно тот же тип
        $stmt = $pdo->prepare("DELETE FROM likes WHERE id = ?");
        $stmt->execute([$existing['id']]);
    } else {
        // Переключаем лайк ↔ дизлайк
        $stmt = $pdo->prepare("UPDATE likes SET is_dislike = ? WHERE id = ?");
        $stmt->execute([$is_dislike, $existing['id']]);
    }
}

// Считаем новые значения
if ($post_id !== null) {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(is_dislike = 0) AS likes,
            SUM(is_dislike = 1) AS dislikes
        FROM likes 
        WHERE post_id = ? AND comment_id IS NULL
    ");
    $stmt->execute([$post_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(is_dislike = 0) AS likes,
            SUM(is_dislike = 1) AS dislikes
        FROM likes 
        WHERE comment_id = ?
    ");
    $stmt->execute([$comment_id]);
}
$count = $stmt->fetch();

echo json_encode([
    'likes' => (int)$count['likes'],
    'dislikes' => (int)$count['dislikes']
]);
