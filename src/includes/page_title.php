<?php
// Verhindern des direkten Zugriffs auf die Datei
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header("Location: ../../public/errors/403.php");
    exit;
}

// Verbindung zur Datenbank herstellen
include __DIR__ . '/../config/dbconnect.php';

// Webseitentitel und Firmenname aus der Datenbank abrufen
$sql = "SELECT site_title, company_name FROM settings WHERE id = 1";
$result = $conn->query($sql);

if ($result && $row = $result->fetch_assoc()) {
    $site_title = htmlspecialchars($row['site_title']);
    $company_name = htmlspecialchars($row['company_name']);
} else {
    // Fallback-Demo-Werte, falls nichts in der Datenbank gefunden wird
    $site_title = "Demo-Portal";
    $company_name = "Demo Company GmbH";
}

// Verbindung schließen
$conn->close();
?>
