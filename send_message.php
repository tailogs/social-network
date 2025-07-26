<?php
require 'inc/db.php';
require 'inc/auth.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : null;
    $content = trim($_POST['content'] ?? '');
    $image = null;

    // Проверяем загрузку изображения через файл
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_extensions)) {
            echo json_encode(['success' => false, 'error' => 'Разрешены только файлы JPG, PNG или GIF']);
            exit;
        }
        if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Файл слишком большой (максимум 5 МБ)']);
            exit;
        }
        $image = uniqid() . '.' . $ext;
        if (!move_uploaded_file($_FILES['image']['tmp_name'], "Uploads/$image")) {
            echo json_encode(['success' => false, 'error' => 'Ошибка при загрузке изображения']);
            exit;
        }
    }

    // Проверяем, является ли контент ссылкой на изображение
    $is_image_link = false;
    if ($content && !$image && preg_match('/^https?:\/\/.*\.(?:jpg|jpeg|png|gif)$/i', $content, $matches)) {
        $image_url = $matches[0];
        $ext = strtolower(pathinfo($image_url, PATHINFO_EXTENSION));
        $image = uniqid() . '.' . $ext;
        $image_data = @file_get_contents($image_url);
        if ($image_data !== false) {
            if (file_put_contents("Uploads/$image", $image_data) !== false) {
                $is_image_link = true; // Помечаем, что контент — это ссылка на изображение
                $content = ''; // Очищаем текст ссылки, так как изображение будет отображаться
            } else {
                $image = null; // Сбрасываем, если не удалось сохранить
                echo json_encode(['success' => false, 'error' => 'Ошибка при загрузке изображения по ссылке']);
                exit;
            }
        } else {
            $image = null; // Сбрасываем, если не удалось загрузить
        }
    }

    if (!$receiver_id || (!$content && !$image)) {
        echo json_encode(['success' => false, 'error' => 'Неверные данные']);
        exit;
    }

    // Проверяем, существует ли пользователь
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$receiver_id]);
    if (!$stmt->fetch() || $receiver_id == $_SESSION['user_id']) {
        if ($image && file_exists("Uploads/$image")) {
            unlink("Uploads/$image"); // Удаляем загруженное изображение
        }
        echo json_encode(['success' => false, 'error' => 'Пользователь не найден']);
        exit;
    }

    try {
        // Проверяем, не было ли недавно отправлено идентичное сообщение
        $stmt = $pdo->prepare("
            SELECT id FROM messages 
            WHERE sender_id = ? AND receiver_id = ? 
            AND content = ? AND (image = ? OR (image IS NULL AND ? IS NULL))
            AND created_at > NOW() - INTERVAL 5 SECOND
        ");
        $stmt->execute([$_SESSION['user_id'], $receiver_id, $content, $image, $image]);
        if ($stmt->fetch()) {
            if ($image && file_exists("Uploads/$image")) {
                unlink("Uploads/$image"); // Удаляем загруженное изображение
            }
            echo json_encode(['success' => false, 'error' => 'Сообщение уже отправлено']);
            exit;
        }

        // Вставляем новое сообщение
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content, image) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $receiver_id, $content, $image]);
        $message_id = $pdo->lastInsertId();

        // Логирование для отладки
        file_put_contents('send_message_log.txt', date('Y-m-d H:i:s') . " - Message ID: $message_id, Sender: {$_SESSION['user_id']}, Receiver: $receiver_id, Content: $content, Image: $image\n", FILE_APPEND);

        echo json_encode(['success' => true, 'message_id' => $message_id, 'image' => $image]);
    } catch (PDOException $e) {
        if ($image && file_exists("Uploads/$image")) {
            unlink("Uploads/$image"); // Удаляем загруженное изображение в случае ошибки
        }
        echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Неверный запрос']);
}
?>