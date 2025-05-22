<?php
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Nettoyage et validation des données reçues
    $nom = filter_input(INPUT_POST, 'nom', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'mail', FILTER_VALIDATE_EMAIL);
    $mot_de_passe = $_POST['mpd'] ?? '';
    $nationalite = filter_input(INPUT_POST, 'nat', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $genre = filter_input(INPUT_POST, 'sexe', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $type_connexion = filter_input(INPUT_POST, 'condition', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if (!$email || empty($mot_de_passe)) {
        die("❌ Email invalide ou mot de passe vide.");
    }

    // Hachage sécurisé du mot de passe
    $mot_de_passe_hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);

    // Fonction d'envoi de mail via SMTP
    function sendEmail($to, $subject, $body) {
        $smtpHost = 'smtp.gmail.com';
        $smtpPort = 587;
        $smtpUser = 'amirmahamat470@gmail.com';
        $smtpPass = 'ntfk nzqx belc ofox'; // ⚠️ À remplacer par une variable d'environnement

        $fromName = "Mon site";
        $fromEmail = $smtpUser;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $socket = stream_socket_client("tcp://$smtpHost:$smtpPort", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            throw new Exception("Erreur de connexion SMTP : $errstr ($errno)");
        }

        function getResponse($socket) {
            $response = '';
            while ($line = fgets($socket, 515)) {
                $response .= $line;
                if (substr($line, 3, 1) === ' ') break;
            }
            return $response;
        }

        function sendCommand($socket, $command) {
            fwrite($socket, $command . "\r\n");
            return getResponse($socket);
        }

        getResponse($socket);
        sendCommand($socket, "EHLO localhost");
        sendCommand($socket, "STARTTLS");
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        sendCommand($socket, "EHLO localhost");
        sendCommand($socket, "AUTH LOGIN");
        sendCommand($socket, base64_encode($smtpUser));
        sendCommand($socket, base64_encode($smtpPass));
        sendCommand($socket, "MAIL FROM:<$fromEmail>");
        sendCommand($socket, "RCPT TO:<$to>");
        sendCommand($socket, "DATA");

        $headers = "From: $fromName <$fromEmail>\r\n";
        $headers .= "Reply-To: $fromEmail\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        $message = "$headers\r\nSubject: $subject\r\n\r\n$body";

        fwrite($socket, $message . "\r\n.\r\n");
        getResponse($socket);

        sendCommand($socket, "QUIT");
        fclose($socket);
    }

    try {
        // Connexion à la base de données
        $conn = new PDO("mysql:host=sql101.infinityfree.com;dbname=if0_38992685_mabase;charset=utf8mb4", "if0_38992685", "2MLZlo4ewcQN");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Vérification de l'existence de l'email
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = :email");
        $stmt_check->execute([':email' => $email]);
        $exists = $stmt_check->fetchColumn();

        if ($exists > 0) {
            $subject = "Adresse email déjà enregistrée sur le site";
            $message = "Bonjour,\n\nCette adresse email est déjà utilisée pour un compte MON MAGASIN.\nSi vous avez perdu votre mot de passe, utilisez l'option de réinitialisation.\n\nMON MAGASIN.";
            sendEmail($email, $subject, $message);
            die("❌ Email déjà utilisé. Un message a été envoyé à cette adresse.");
        }

        // Insertion dans la table utilisateurs
        $stmt = $conn->prepare("
            INSERT INTO utilisateurs (nom_prenom, email, mot_de_passe, sexe, nationalite, accepte_conditions)
            VALUES (:nom, :email, :mot_de_passe, :genre, :nationalite, :type_connexion)
        ");
        $stmt->execute([
            ':nom' => $nom,
            ':email' => $email,
            ':mot_de_passe' => $mot_de_passe_hash,
            ':genre' => $genre,
            ':nationalite' => $nationalite,
            ':type_connexion' => $type_connexion === 'on' ? 1 : 0
        ]);

        // Envoi de l'email de bienvenue
        $subject = "Bienvenue sur Mon site";
        $message = "Bonjour $nom,\n\nMerci de vous être inscrit sur notre site ! Nous sommes heureux de vous accueillir.\n\nRendez-vous sur notre site pour compléter votre profil.\n\nÀ très vite,\nAMIR Mahamat.";
        sendEmail($email, $subject, $message);

        echo "✅ Inscription réussie ! Un email de confirmation vous a été envoyé.";
    } catch (PDOException $e) {
        die("❌ Erreur de base de données : " . $e->getMessage());
    } catch (Exception $e) {
        die("❌ Erreur générale : " . $e->getMessage());
    }
}
?>
