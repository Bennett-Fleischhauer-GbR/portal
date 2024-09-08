<?php
session_start();

function isAjax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Überprüfen, ob der Benutzer eingeloggt ist, andernfalls für AJAX-Anfragen JSON-Antwort und für normale Anfragen Umleitung
if (!isset($_SESSION['user_id'])) {
    if (isAjax()) {
        exit();
    } else {
        // Redirect non-AJAX requests to an error page if the user is not logged in
        header("Location: ../../public/errors/403.php");
        exit();
    }
}

require __DIR__ . '/../config/dbconnect.php'; // Datenbankverbindung

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $order = json_decode(file_get_contents('php://input'), true)['order'];

    $orderString = implode(',', $order);
    
    $sql = "UPDATE users SET box_order = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $orderString, $user_id);
    $stmt->execute();
    $stmt->close();
}
$conn->close();
?>
