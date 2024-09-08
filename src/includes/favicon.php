<?php
// Verhindern des direkten Zugriffs auf die Datei
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header("Location: ../../public/errors/403.php");
    exit;
}

include __DIR__ . '/../config/dbconnect.php';

// Favicon-URL und Version aus der Datenbank abrufen
$sql = "SELECT favicon, favicon_version FROM settings WHERE id = 1";
$result = $conn->query($sql);

if ($result && $row = $result->fetch_assoc()) {
    $favicon_url = $row['favicon'];
    $favicon_version = $row['favicon_version']; // Favicon-Version
    $file_extension = $favicon_url ? pathinfo($favicon_url, PATHINFO_EXTENSION) : '';

    // Unterstützte Bildformate
    $supported_formats = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'ico'];

    if (in_array($file_extension, $supported_formats)) {
        $favicon_with_version = htmlspecialchars($favicon_url) . '?v=' . $favicon_version;
        if ($file_extension === 'svg') {
            echo '<link rel="icon" type="image/svg+xml" href="' . $favicon_with_version . '">';
        } elseif ($file_extension === 'ico') {
            echo '<link rel="icon" type="image/x-icon" href="' . $favicon_with_version . '">';
        } else {
            echo '<link rel="icon" type="image/png" sizes="32x32" href="' . $favicon_with_version . '">';
        }
    } else {
        echo '<link rel="icon" type="image/x-icon" href="../../assets/icons/default-favicon.ico">';
    }
} else {
    echo '<link rel="icon" type="image/x-icon" href="../../assets/icons/default-favicon.ico">';
}

$conn->close();
?>
