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

if (isset($_POST['category_id'])) {
    $category_id = intval($_POST['category_id']);
    
    // Zähle die Einträge in der Kategorie
    $stmt = $conn->prepare("SELECT COUNT(*) FROM links WHERE category_id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    
    echo $count; // Gib die Anzahl der Einträge zurück
}
?>
