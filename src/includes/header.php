<?php


// Verhindern des direkten Zugriffs auf die Datei
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header("Location: ../../public/errors/403.php");
    exit;
}

// Starten der Session, falls noch nicht aktiv
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/../config/dbconnect.php';

// Logos und Spracheinstellungen aus der settings-Tabelle abrufen
$sql = "SELECT logo_white, logo_dark, link_shortener_enabled, base_url FROM settings WHERE id = 1";
$stmt = $conn->prepare($sql);
$stmt->execute();
$settings = $stmt->get_result()->fetch_assoc();
$link_shortener_enabled = $settings['link_shortener_enabled'];
$logos = [
    'logo_white' => $settings['logo_white'],
    'logo_dark' => $settings['logo_dark'],
];
$base_url = $settings['base_url'];
$stmt->close();

// Prüfen, ob der Benutzer eingeloggt ist
if (!isset($_SESSION['user_id'])) {
    header("Location: " . $base_url . "login.php");
    exit();
}

// Benutzerinformationen einmalig abrufen und in der Session speichern
if (!isset($_SESSION['user_data'])) {
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT first_name, email, username, last_name, city, role, user_language FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $_SESSION['user_data'] = $result->fetch_assoc();
    $stmt->close();
}


$user = $_SESSION['user_data'];
$first_name = $user['first_name'];
$username = $user['username'];
$email = $user['email'];
$role = $user['role'];
$user_language = $user['user_language'] ?? 'en';

// Sprachdatei laden basierend auf der Benutzersprache
$language_file = __DIR__ . "/../languages/{$user_language}.php";
if (file_exists($language_file)) {
    include $language_file;
} else {
    include __DIR__ . '/../languages/en.php'; // Fallback auf Englisch
}

// Gravatar-URL generieren
function getGravatar($email)
{
    $emailHash = md5(strtolower(trim($email)));
    return "https://www.gravatar.com/avatar/$emailHash?s=40";
}

$gravatarUrl = getGravatar($email);

// Aktuelle Seite ermitteln
$current_page = basename($_SERVER['PHP_SELF'], ".php");
?>

<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
        <a class="navbar-brand" href="<?php echo $base_url; ?>">
            <img src="<?php echo $base_url . htmlspecialchars($logos['logo_white']); ?>" alt="Logo" class="logo logo-light">
            <img src="<?php echo $base_url . htmlspecialchars($logos['logo_dark']); ?>" alt="Logo" class="logo logo-dark">
        </a>
        <button class="navbar-toggler" type="button" id="mobileMenuToggle" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'index' ? 'active' : ''; ?>"
                        href="<?php echo $base_url . 'index.php'; ?>"><?php echo $lang['dashboard']; ?></a>
                </li>
                <?php if ($link_shortener_enabled): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'link_shortener' ? 'active' : ''; ?>"
                            href="<?php echo $base_url . 'pages/link_shortener.php'; ?>"><?php echo $lang['link_shortener']; ?></a>
                    </li>
                <?php endif; ?>
                <?php if ($role === 'admin' || $role === 'author'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'link_settings' ? 'active' : ''; ?>"
                            href="<?php echo $base_url . 'settings/link_settings.php'; ?>"><?php echo $lang['link_management']; ?></a>
                    </li>
                <?php endif; ?>
                <?php if ($role === 'admin'): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center <?php echo in_array($current_page, ['email_settings', 'user_dashboard', 'portal_settings']) ? 'active' : ''; ?>"
                            href="#" id="settingsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="me-2"><?php echo $lang['settings']; ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-center" aria-labelledby="settingsDropdown">
                            <li><a class="dropdown-item <?php echo $current_page == 'email_settings' ? 'active' : ''; ?>"
                                    href="<?php echo $base_url . 'settings/email_settings.php'; ?>"><?php echo $lang['email_settings']; ?></a>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item <?php echo $current_page == 'user_dashboard' ? 'active' : ''; ?>"
                                    href="<?php echo $base_url . 'settings/user_dashboard.php'; ?>"><?php echo $lang['user_dashboard']; ?></a>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item <?php echo $current_page == 'portal_settings' ? 'active' : ''; ?>"
                                    href="<?php echo $base_url . 'settings/portal_settings.php'; ?>"><?php echo $lang['main_settings']; ?></a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center <?php echo $current_page == 'user_settings' ? 'active' : ''; ?>"
                        href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?php echo $gravatarUrl; ?>" alt="User Avatar"
                            class="rounded-circle me-2 gravatar-icon">
                        <?php echo htmlspecialchars($first_name); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item <?php echo $current_page == 'user_settings' ? 'active' : ''; ?>"
                                href="<?php echo $base_url . 'settings/user_settings.php'; ?>"><?php echo $lang['user_settings']; ?></a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <form method="post" action="<?php echo $base_url . 'auth/logout.php'; ?>"
                                class="form-inline">
                                <button class="dropdown-item" type="submit"><?php echo $lang['logout_now']; ?></button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
        <div class="mobile-menu" id="mobileMenu">
            <div class="mobile-menu-header">
                <button class="close-btn" id="closeMobileMenu">×</button>
            </div>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'index' ? 'active' : ''; ?>"
                        href="<?php echo $base_url . 'index.php'; ?>"><?php echo $lang['dashboard']; ?></a>
                </li>
                <?php if ($link_shortener_enabled): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'link_shortener' ? 'active' : ''; ?>"
                            href="<?php echo $base_url . 'pages/link_shortener.php'; ?>"><?php echo $lang['link_shortener']; ?></a>
                    </li>
                <?php endif; ?>
                <?php if ($role === 'admin' || $role === 'author'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'link_settings' ? 'active' : ''; ?>"
                            href="<?php echo $base_url . 'settings/link_settings.php'; ?>"><?php echo $lang['link_management']; ?></a>
                    </li>
                <?php endif; ?>
                <?php if ($role === 'admin'): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center <?php echo in_array($current_page, ['email_settings', 'user_dashboard', 'portal_settings']) ? 'active' : ''; ?>"
                            href="#" id="mobileSettingsDropdown" role="button" data-bs-toggle="dropdown"
                            aria-expanded="false">
                            <span class="me-2"><?php echo $lang['settings']; ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="mobileSettingsDropdown">
                            <li><a class="dropdown-item <?php echo $current_page == 'email_settings' ? 'active' : ''; ?>"
                                    href="<?php echo $base_url . 'settings/email_settings.php'; ?>"><?php echo $lang['email_settings']; ?></a>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item <?php echo $current_page == 'user_dashboard' ? 'active' : ''; ?>"
                                    href="<?php echo $base_url . 'settings/user_dashboard.php'; ?>"><?php echo $lang['user_dashboard']; ?></a>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item <?php echo $current_page == 'portal_settings' ? 'active' : ''; ?>"
                                    href="<?php echo $base_url . 'settings/portal_settings.php'; ?>"><?php echo $lang['main_settings']; ?></a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center <?php echo $current_page == 'user_settings' ? 'active' : ''; ?>"
                        href="#" id="mobileUserDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?php echo $gravatarUrl; ?>" alt="User Avatar"
                            class="rounded-circle me-2 gravatar-icon">
                        <?php echo htmlspecialchars($first_name); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="mobileUserDropdown">
                        <li><a class="dropdown-item <?php echo $current_page == 'user_settings' ? 'active' : ''; ?>"
                                href="<?php echo $base_url . 'settings/user_settings.php'; ?>"><?php echo $lang['user_settings']; ?></a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <form method="post" action="<?php echo $base_url . 'auth/logout.php'; ?>"
                                class="form-inline">
                                <button class="dropdown-item" type="submit"><?php echo $lang['logout_now']; ?></button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>