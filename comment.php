<?php
require 'inc/db.php';
require 'inc/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = (int) $_POST['post_id'];
    $content = trim($_POST['content']);
    $parent_id = isset($_POST['parent_id']) && is_numeric($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;

    if ($content) {
        $stmt = $pdo->prepare("INSERT INTO comments (user_id, post_id, content, parent_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $post_id, $content, $parent_id]);
    }
}

header('Location: index.php');
exit;
