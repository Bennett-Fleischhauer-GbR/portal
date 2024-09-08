document.addEventListener('DOMContentLoaded', function () {
    const themeToggle = document.querySelector('.theme-toggle');
    const themeName = document.querySelector('.theme-name');

    let currentTheme = localStorage.getItem('theme') || 'system'; // Set default theme to light
    setTheme(currentTheme);

    themeToggle.addEventListener('click', function () {
        currentTheme = getNextTheme();
        setTheme(currentTheme);
        if (currentTheme === 'system') {
            applySystemTheme();
        }
        updateErrorFavicons(currentTheme);
    });

    function setTheme(theme) {
        if (theme === 'system') {
            applySystemTheme();
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', applySystemTheme);
        } else {
            document.documentElement.setAttribute('data-bs-theme', theme);
            localStorage.setItem('theme', theme);
            themeName.textContent = theme.charAt(0).toUpperCase() + theme.slice(1);
            console.log(`Theme set to: ${theme}`);
            updateFavicons(theme);
        }
    }

    function getNextTheme() {
        const currentTheme = localStorage.getItem('theme');
        switch (currentTheme) {
            case 'light':
                return 'dark';
            case 'dark':
                return 'system';
            case 'system':
                return 'light';
            default:
                return 'light';
        }
    }

    function applySystemTheme() {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const systemTheme = prefersDark ? 'dark' : 'light';
        document.documentElement.setAttribute('data-bs-theme', systemTheme);
        localStorage.setItem('theme', 'system');
        themeName.textContent = 'System';
        console.log(`System theme applied: ${systemTheme}`);
        updateFavicons(systemTheme);
        updateErrorFavicons(systemTheme);
    }

    function updateFavicons(theme) {
        const favicons = document.querySelectorAll("img.icon");
        favicons.forEach(favicon => {
            favicon.onerror = function () {
                handleFaviconError(favicon);
            };
            checkFavicon(favicon, theme);
        });
    }

    function checkFavicon(favicon, theme) {
        const img = new Image();
        img.src = favicon.src;
        img.onload = function () {
            console.log('Favicon loaded: ' + favicon.src);
        };
        img.onerror = function () {
            console.log('Favicon failed to load: ' + favicon.src);
            handleFaviconError(favicon);
        };
    }

    function handleFaviconError(favicon) {
        const currentTheme = document.documentElement.getAttribute('data-bs-theme');
        if (currentTheme === 'dark') {
            favicon.src = '../../assets/icons/default-icon-hell.svg';
        } else {
            favicon.src = '../../assets/icons/default-icon-dunkel.svg';
        }
    }

    function updateErrorFavicons(theme) {
        const favicons = document.querySelectorAll("img.icon");
        favicons.forEach(favicon => {
            if (favicon.src.includes('default-icon-dunkel.svg') || favicon.src.includes('default-icon-hell.svg')) {
                handleFaviconError(favicon);
            }
        });
    }

    if (currentTheme === 'system') {
        applySystemTheme();
    } else {
        document.documentElement.setAttribute('data-bs-theme', currentTheme);
        themeName.textContent = currentTheme.charAt(0).toUpperCase() + currentTheme.slice(1);
        updateFavicons(currentTheme);
    }

    // Mobile Menu
    document.getElementById('mobileMenuToggle').addEventListener('click', function () {
        document.getElementById('mobileMenu').style.display = 'block';
        setTimeout(function () {
            document.getElementById('mobileMenu').style.right = '0';
        }, 10); // Small delay to ensure the right property transition
    });

    document.getElementById('closeMobileMenu').addEventListener('click', function () {
        document.getElementById('mobileMenu').style.right = '-100%';
        setTimeout(function () {
            document.getElementById('mobileMenu').style.display = 'none';
        }, 300); // Match the transition duration
    });
});

// Scrollposition speichern und wiederherstellen
document.addEventListener("DOMContentLoaded", function (event) {
    var baseUrl = window.location.href.split('?')[0];
    var previousBaseUrl = localStorage.getItem('previousBaseUrl');

    if (previousBaseUrl === baseUrl) {
        var scrollpos = localStorage.getItem('scrollpos');
        if (scrollpos) {
            window.scrollTo(0, parseInt(scrollpos, 10));
        }
    } else {
        localStorage.removeItem('scrollpos');
    }

    localStorage.setItem('previousBaseUrl', baseUrl);
});

window.onbeforeunload = function (e) {
    localStorage.setItem('scrollpos', window.scrollY);
};

//Farben der Portal Einstellungen
function syncPrimaryColor() {
    var colorPicker = document.getElementById('primary_color');
    var colorHex = document.getElementById('primary_color_hex');
    colorHex.value = colorPicker.value;
}

function syncPrimaryHex() {
    var colorPicker = document.getElementById('primary_color');
    var colorHex = document.getElementById('primary_color_hex');
    colorPicker.value = colorHex.value;
}

function syncSecondaryColor() {
    var colorPicker = document.getElementById('secondary_color');
    var colorHex = document.getElementById('secondary_color_hex');
    colorHex.value = colorPicker.value;
}

function syncSecondaryHex() {
    var colorPicker = document.getElementById('secondary_color');
    var colorHex = document.getElementById('secondary_color_hex');
    colorPicker.value = colorHex.value;
}