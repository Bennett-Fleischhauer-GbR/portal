<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

function handleStep2($conn) {
    $successMessages = [];
    
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Eingabewerte aus dem Formular abfangen
        $email = $_POST['email'];
        $username = $_POST['username'];
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $city = $_POST['city'];
        $password = $_POST['password'];

        try {
            // Namen kombinieren
            $full_name = $first_name . ' ' . $last_name;
            // Passwort verschlüsseln
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $role = "admin";
            $box_order = "box1,box2,box3"; // Wert für die Spalte 'box_order'
            
            $sql = "INSERT INTO users (email, username, first_name, last_name, full_name, city, password, role, box_order) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("sssssssss", $email, $username, $first_name, $last_name, $full_name, $city, $hashed_password, $role, $box_order);
                $stmt->execute();
                $stmt->close();

                // Erfolgsmeldung hinzufügen
                $successMessages[] = "Admin user created successfully.";
                return ['status' => 'success', 'messages' => $successMessages];

            } else {
                // Bei einem Fehler die Exception werfen
                throw new Exception("Error: " . $conn->error);
            }
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    return '';
}
?>
