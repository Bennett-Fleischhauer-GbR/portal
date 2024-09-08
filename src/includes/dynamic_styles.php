<?php
// Verhindern des direkten Zugriffs auf die Datei
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header("Location: ../../public/errors/403.php");
    exit;
}

include __DIR__ . '/../config/dbconnect.php';

// Abrufen der Farbwerte aus der Datenbank
$sql = "SELECT primary_color, secondary_color FROM settings WHERE id = 1";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
$colors = $result->fetch_assoc();
$stmt->close();

$primary_color = $colors['primary_color'] ?: '#152039'; // Fallback-Wert
$secondary_color = $colors['secondary_color'] ?: '#e5803a'; // Fallback-Wert
?>

<style>
:root {
    --primary-color: <?php echo htmlspecialchars($primary_color); ?>;
    --secondary-color: <?php echo htmlspecialchars($secondary_color); ?>;
}
</style>
