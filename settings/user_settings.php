<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();

// Überprüfen, ob der Benutzer eingeloggt ist, andernfalls zur Login-Seite umleiten
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

include __DIR__ . '/../src/config/dbconnect.php';
require __DIR__ . '/../src/controllers/email_sender.php'; // Inkludiere die Datei mit den E-Mail-Funktionen

// Benutzerinformationen aus der Datenbank abrufen und Session-Daten aktualisieren
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$_SESSION['user_data'] = $user;
$stmt->close();

$first_name = $user['first_name'];
$username = $user['username'];
$email = $user['email'];
$role = $user['role'];
$last_name = $user['last_name'];
$city = $user['city'];
$show_boxes = $user['show_boxes'];
$user_language = $user['user_language'] ?? 'en';
$temperature_unit = $user['temperature_unit'] ?? 'metric'; // Füge hier die Temperatureinheit hinzu

$sql = "SELECT show_boxes FROM settings WHERE id = 1";
$stmt = $conn->prepare($sql);
$stmt->execute();
$portal_settings = $stmt->get_result()->fetch_assoc();
$show_boxes_global = $portal_settings['show_boxes'];
$stmt->close();

$language_file = __DIR__ . "/../src/languages/$user_language.php";
if (file_exists($language_file)) {
    include $language_file;
} else {
    include __DIR__ . '/../src/languages/en.php';
}

$profile_message = '';
$password_message = '';

// Verfügbare Sprachen
$available_languages = [
    'de' => 'Deutsch (German)',
    'en' => 'English (English)',
    'es' => 'Español (Spanish)',
    'zh' => '中文 (Chinese)',
    'fr' => 'Français (French)',
    'pt' => 'Português (Portuguese)',
    'ja' => '日本語 (Japanese)',
    'it' => 'Italiano (Italian)',
    'ar' => 'العربية (Arabic)',
    'tr' => 'Türkçe (Turkish)',
];

// Verfügbare Temperatureinheiten
$available_units = ['metric' => $lang['celsius'], 'imperial' => $lang['fahrenheit']]; // Beispiel für Sprachunterstützung

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $new_username = $_POST['username'];
        $new_email = $_POST['email'];
        $new_first_name = $_POST['first_name'];
        $new_last_name = $_POST['last_name'];
        $new_city = $_POST['city'];
        $new_show_boxes = isset($_POST['show_boxes']) ? 1 : 0;
        $new_user_language = $_POST['user_language'];
        $new_temperature_unit = $_POST['temperature_unit']; // Neue Temperatureinheit

        $changes = [];
        $has_changes = false;

        if ($user['username'] !== $new_username) {
            $changes[] = "<li><strong>{$lang['username']}:</strong> {$lang['changed_from']} {$user['username']} {$lang['to']} $new_username</li>";
            $has_changes = true;
        }

        if ($user['email'] !== $new_email) {
            $changes[] = "<li><strong>{$lang['email']}:</strong> {$lang['changed_from']} {$user['email']} {$lang['to']} $new_email</li>";
            $has_changes = true;
        }

        if ($user['first_name'] !== $new_first_name) {
            $changes[] = "<li><strong>{$lang['first_name']}:</strong> {$lang['changed_from']} {$user['first_name']} {$lang['to']} $new_first_name</li>";
            $has_changes = true;
        }

        if ($user['last_name'] !== $new_last_name) {
            $changes[] = "<li><strong>{$lang['last_name']}:</strong> {$lang['changed_from']} {$user['last_name']} {$lang['to']} $new_last_name</li>";
            $has_changes = true;
        }

        if ($user['city'] !== $new_city) {
            $changes[] = "<li><strong>{$lang['city']}:</strong> {$lang['changed_from']} {$user['city']} {$lang['to']} $new_city</li>";
            $has_changes = true;
        }

        if ($user['show_boxes'] != $new_show_boxes) {
            $changes[] = "<li><strong>{$lang['show_hide_date_time_weather']}:</strong> " . ($new_show_boxes ? $lang['enabled'] : $lang['disabled']) . "</li>";
            $has_changes = true;
        }

        if ($user['user_language'] !== $new_user_language) {
            $changes[] = "<li><strong>{$lang['language']}:</strong> {$lang['changed_from']} {$available_languages[$user['user_language']]} {$lang['to']} {$available_languages[$new_user_language]}</li>";
            $has_changes = true;
        }

        if ($user['temperature_unit'] !== $new_temperature_unit) { // Überprüfe Änderungen an der Temperatureinheit
            $changes[] = "<li><strong>{$lang['temperature_unit']}:</strong> {$lang['changed_from']} {$available_units[$user['temperature_unit']]} {$lang['to']} {$available_units[$new_temperature_unit]}</li>";
            $has_changes = true;
        }

        if ($has_changes) {
            $update_sql = "UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, city = ?, show_boxes = ?, user_language = ?, temperature_unit = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ssssssssi", $new_username, $new_email, $new_first_name, $new_last_name, $new_city, $new_show_boxes, $new_user_language, $new_temperature_unit, $user['id']);
            $update_stmt->execute();
            $update_stmt->close();

            $_SESSION['user_data'] = array_merge($_SESSION['user_data'], [
                'username' => $new_username,
                'email' => $new_email,
                'first_name' => $new_first_name,
                'last_name' => $new_last_name,
                'city' => $new_city,
                'show_boxes' => $new_show_boxes,
                'user_language' => $new_user_language,
                'temperature_unit' => $new_temperature_unit, // Update der Session-Daten
            ]);

            // Speichern der Nachricht und E-Mail-Benachrichtigung in der Session
            $_SESSION['profile_message'] = "<div class='alert alert-success'>{$lang['profile_updated_success']}</div>";

            // E-Mail senden
            $email_body = "<p>{$lang['email_body_profile_updated']}</p><ul>" . implode("", $changes) . "</ul><p>{$lang['contact_us_if_questions']}</p>";
            send_email($new_email, $lang['email_subject_profile_updated'], $lang['email_header_profile_updated'], $email_body, get_smtp_settings($conn), $conn);

            header("Location: " . $_SERVER['PHP_SELF']);
            exit();

        } else {
            $_SESSION['profile_message'] = "<div class='alert alert-info'>{$lang['no_profile_changes']}.</div>";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    if (isset($_POST['update_password'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            $password_sql = "UPDATE users SET password = ? WHERE id = ?";
            $password_stmt = $conn->prepare($password_sql);
            $password_stmt->bind_param("si", $hashed_password, $user['id']);
            $password_stmt->execute();
            $password_stmt->close();

            $_SESSION['password_message'] = "<div class='alert alert-success'>{$lang['password_success']}.</div>";

            $subject = $lang['email_subject_password_changed'];
            $header = $lang['email_header_password_changed'];
            $content = "
                <p>{$lang['dear_user']},</p>
                <p>{$lang['email_body_password_changed']}</p>
                <p>{$lang['contact_us_immediately']}</p>
            ";
            send_email($email, $subject, $header, $content, get_smtp_settings($conn), $conn);

            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $_SESSION['password_message'] = "<div class='alert alert-danger'>{$lang['password_error']}.</div>";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }
}

// Nachrichten nach dem Reload anzeigen
if (isset($_SESSION['profile_message'])) {
    $profile_message = $_SESSION['profile_message'];
    unset($_SESSION['profile_message']);
}

if (isset($_SESSION['password_message'])) {
    $password_message = $_SESSION['password_message'];
    unset($_SESSION['password_message']);
}

// Bereite die Variablen vor für das aus/einblenden der Boxen
$checked = ($show_boxes == 1 && $show_boxes_global == 1) ? 'checked' : '';
$disabled = ($show_boxes_global != 1) ? 'disabled' : '';
$tooltip = ($show_boxes_global != 1) ? $lang['admin_disabled_boxes'] : '';

function get_smtp_settings($conn)
{
    $sql = "SELECT smtp_host, smtp_port, smtp_user, smtp_password, smtp_encryption FROM smtp_settings WHERE id = 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $smtp_settings = $result->fetch_assoc();
    $stmt->close();

    return $smtp_settings ?: null;
}
?>

<!DOCTYPE html>
<html lang="<?php echo $user_language; ?>">

<head>
    <!-- Meta-Tags -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="Bennett & Fleischhauer GbR">
    <meta name="robots" content="noindex, nofollow">

    <!-- Titel der Seite -->
    <?php
    $page_specific_title = $lang['user_settings'];
    include __DIR__ . '/../src/includes/page_title.php';
    ?>
    <title><?php echo $page_specific_title . " | " . $site_title . " | " . $company_name; ?></title>

    <!-- Favicon -->
    <?php include __DIR__ . '/../src/includes/favicon.php'; ?>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome für Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Icons für Passwort Logik-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Benutzerdefinierte Styles -->
    <link rel="stylesheet" href="../css/index.css">

    <!-- Dynamische Styles (falls notwendig) -->
    <?php include __DIR__ . '/../src/includes/dynamic_styles.php'; ?>
</head>

<body>

    <?php include __DIR__ . '/../src/includes/header.php'; ?>

    <div class="container main-content">
        <div class="row mb-4">
            <div class="col-12">
                <h2><?php echo $lang['user_settings']; ?></h2>
                <p><?php echo $lang['general_user_info']; ?></p>
            </div>
        </div>

        <div class="card mb-5">
            <div class="card-body">
                <h3><?php echo $lang['general_user_info']; ?></h3>
                <?php if (!empty($profile_message))
                    echo $profile_message; ?>
                <p class="text-info"><?php echo $lang['assigned_role']; ?>
                    <strong><?php echo ucfirst($role); ?></strong>.
                </p><br>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label"><?php echo $lang['username']; ?></label>
                        <input type="text" class="form-control" id="username" name="username"
                            value="<?php echo htmlspecialchars($username); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label"><?php echo $lang['email']; ?></label>
                        <input type="email" class="form-control" id="email" name="email"
                            value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="first_name" class="form-label"><?php echo $lang['first_name']; ?></label>
                        <input type="text" class="form-control" id="first_name" name="first_name"
                            value="<?php echo htmlspecialchars($first_name); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="last_name" class="form-label"><?php echo $lang['last_name']; ?></label>
                        <input type="text" class="form-control" id="last_name" name="last_name"
                            value="<?php echo htmlspecialchars($last_name); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="city" class="form-label"><?php echo $lang['city']; ?></label>
                        <input type="text" class="form-control" id="city" name="city"
                            value="<?php echo htmlspecialchars($city); ?>" required>
                    </div>
                    <!-- Show/Hide Boxes Setting -->
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="show_boxes" name="show_boxes" <?php echo $checked; ?> <?php echo $disabled; ?>>
                        <label class="form-check-label" for="show_boxes">
                            <?php echo $lang['show_hide_date_time_weather']; ?>
                            <?php if ($tooltip): ?>
                                <span class="info-icon" data-toggle="tooltip" title="<?php echo $tooltip; ?>">
                                    <i class="fas fa-info-circle"></i>
                                </span>
                            <?php endif; ?>
                        </label>
                    </div>

                    <div class="mb-3">
                        <label for="user_language" class="form-label"><?php echo $lang['language']; ?></label>
                        <select class="form-control" id="user_language" name="user_language">
                            <?php foreach ($available_languages as $lang_code => $lang_name): ?>
                                <option value="<?php echo $lang_code; ?>" <?php echo $user_language === $lang_code ? 'selected' : ''; ?>>
                                    <?php echo $lang_name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Temperatureinheit -->
                    <div class="mb-3">
                        <label for="temperature_unit" class="form-label"><?php echo $lang['temperature_unit']; ?></label>
                        <select class="form-control" id="temperature_unit" name="temperature_unit">
                            <?php foreach ($available_units as $unit_code => $unit_name): ?>
                                <option value="<?php echo $unit_code; ?>" <?php echo $temperature_unit === $unit_code ? 'selected' : ''; ?>>
                                    <?php echo $unit_name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" name="update_profile"
                        class="btn btn-primary"><?php echo $lang['save']; ?></button>
                </form>
            </div>
        </div>

        <div class="card mb-5">
            <div class="card-body">
                <h3><?php echo $lang['password_change']; ?></h3>
                <?php if (!empty($password_message))
                    echo $password_message; ?>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="new_password" class="form-label"><?php echo $lang['new_password']; ?></label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required
                            oninput="checkPasswordStrength()">
                        <div class="container-passwort-ändern">
                            <div class="password-strength-label"><?php echo $lang['password_strength']; ?></div>
                            <div class="password-strength-bar">
                                <div class="password-strength-level" id="password-strength-level"></div>
                                <span></span><span></span>
                            </div>
                            <div class="requirements" id="requirements">
                                <div class="requirement" id="length-requirement">
                                    <span class="icon bi bi-x-circle-fill text-danger"></span>
                                    <?php echo $lang['password_requirement_length']; ?>
                                </div>
                                <div class="requirement" id="lowercase-requirement">
                                    <span class="icon bi bi-x-circle-fill text-danger"></span>
                                    <?php echo $lang['password_requirement_lowercase']; ?>
                                </div>
                                <div class="requirement" id="uppercase-requirement">
                                    <span class="icon bi bi-x-circle-fill text-danger"></span>
                                    <?php echo $lang['password_requirement_uppercase']; ?>
                                </div>
                                <div class="requirement" id="number-requirement">
                                    <span class="icon bi bi-x-circle-fill text-danger"></span>
                                    <?php echo $lang['password_requirement_number']; ?>
                                </div>
                                <div class="requirement" id="special-requirement">
                                    <span class="icon bi bi-x-circle-fill text-danger"></span>
                                    <?php echo $lang['password_requirement_special']; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password"
                            class="form-label"><?php echo $lang['confirm_password']; ?></label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                            required>
                    </div>
                    <button type="submit" name="update_password" class="btn btn-primary" id="password-submit-btn"
                        disabled><?php echo $lang['save_password']; ?></button>
                </form>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../src/includes/footer.php'; ?>

    <div class="theme-toggle">
        <i class="fas fa-adjust"></i><span class="theme-name"></span>
    </div>

    <!-- jQuery (Bootstrap's JavaScript benötigt jQuery) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Deine Skripte -->
    <script src="../js/scripts.js"></script>
    <script src="../js/passwordStrengthChecker.js"></script>
    <script>
        $(document).ready(function () {
            $('[data-toggle="tooltip"]').tooltip(); // Tooltip Initialisierung
        });
    </script>

</body>

</html>
