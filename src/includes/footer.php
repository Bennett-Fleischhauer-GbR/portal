<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Verhindern des direkten Zugriffs auf die Datei
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header("Location: ../../public/errors/403.php");
    exit;
}

include __DIR__ . '/../config/dbconnect.php';

// Footer-Inhalte abrufen
$sql = "SELECT footer_text, footer_option, foundation_year, show_copyright FROM settings LIMIT 1";
$result = $conn->query($sql);
$settings = $result->fetch_assoc();

$footer_text = htmlspecialchars_decode($settings['footer_text'], ENT_QUOTES);
$footer_option = $settings['footer_option'];
$foundation_year = $settings['foundation_year'];
$show_copyright = $settings['show_copyright'];

?>

<footer class="footer mt-auto py-3">
    <div class="container text-center">
        <?php
        $copyright_symbol = $show_copyright ? "&copy; " : "";

        if ($footer_option == 'text_only') {
            echo $copyright_symbol . html_entity_decode($footer_text, ENT_QUOTES, 'UTF-8');
        } elseif ($footer_option == 'current_year_text') {
            echo $copyright_symbol . date('Y') . " " . html_entity_decode($footer_text, ENT_QUOTES, 'UTF-8');
        } elseif ($footer_option == 'foundation_year_text') {
            echo $copyright_symbol . $foundation_year . ' - ' . date('Y') . " " . html_entity_decode($footer_text, ENT_QUOTES, 'UTF-8');
        }
        ?>
    </div>
</footer>
