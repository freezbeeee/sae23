<?php
session_start(); // Doit être la première chose dans le script

// Configuration de la base de données
$host = 'localhost';
$dbname = 'test';
$username = 'freezbee';
$password = 'free';

$pdo = null;
$db_error = null;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // echo "<p style='color: green;'>Connexion à la base de données réussie!</p>"; // Message de débogage
} catch(PDOException $e) {
    $db_error = "Erreur de connexion à la base de données : " . $e->getMessage();
    // echo "<p style='color: red;'>$db_error</p>"; // Message de débogage
}

// Initialisation de la variable d'erreur
$erreur = '';

// Traitement du formulaire de connexion pour l'administrateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['identifiant'])) {
    $identifiant_saisi = $_POST['identifiant'] ?? '';
    $mdp_saisi = $_POST['mot-de-passe'] ?? '';

    if ($pdo) { // Assurez-vous que la connexion PDO est établie
        try {
            // Préparation de la requête SQL pour récupérer l'utilisateur 'admin'
            $sql = "SELECT identifiant, mot_de_passe FROM Connexion WHERE identifiant = :identifiant AND role = 'admin'";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':identifiant', $identifiant_saisi, PDO::PARAM_STR);
            $stmt->execute();

            $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($utilisateur) {
                // echo "<p style='color: blue;'>Utilisateur trouvé dans la base de données.</p>"; // Message de débogage
                // Un utilisateur avec cet identifiant et le rôle 'admin' a été trouvé
                // Vérifier le mot de passe
                if ($mdp_saisi === $utilisateur['mot_de_passe']) { // Rappel: utiliser password_verify() pour la sécurité réelle
                    $_SESSION['connecte'] = true;
                    // echo "<p style='color: green;'>Connexion administrateur réussie!</p>"; // Message de débogage
                } else {
                    $erreur = "Identifiants incorrects.";
                    // echo "<p style='color: red;'>Mot de passe incorrect.</p>"; // Message de débogage
                }
            } else {
                // Aucun utilisateur trouvé avec cet identifiant ou rôle incorrect
                $erreur = "Identifiants incorrects.";
                // echo "<p style='color: red;'>Utilisateur non trouvé ou rôle incorrect.</p>"; // Message de débogage
            }
        } catch (PDOException $e) {
            $erreur = "Erreur lors de la vérification des identifiants : " . $e->getMessage();
            // echo "<p style='color: red;'>$erreur</p>"; // Message de débogage
        }
    } else {
        $erreur = "Erreur de base de données : impossible de vérifier les identifiants.";
        // echo "<p style='color: red;'>$erreur</p>"; // Message de débogage
    }
}

// Traitement du formulaire de configuration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salle1']) && isset($_SESSION['connecte'])) {
    $config = [
        'pairs' => [],
        'salles' => [],
        'capteurs' => []
    ];
    
    // Récupérer toutes les salles et capteurs depuis la base de données pour les sauvegarder
    if (!isset($db_error) && $pdo) { // S'assurer que la connexion est établie
        try {
            // Récupérer les salles depuis la base de données
            $stmt_salles = $pdo->query("SELECT DISTINCT nom_salle FROM Salle ORDER BY nom_salle");
            $config['salles'] = $stmt_salles->fetchAll(PDO::FETCH_COLUMN);

            // Récupérer les types de capteurs depuis la base de données
            $stmt_capteurs = $pdo->query("SELECT DISTINCT type FROM Capteur ORDER BY type");
            $config['capteurs'] = $stmt_capteurs->fetchAll(PDO::FETCH_COLUMN);
        } catch(PDOException $e) {
            $db_error = "Erreur lors de la récupération des données : " . $e->getMessage();
        }
    }
    
    // Créer les paires salle-capteur
    for ($i = 1; $i <= 4; $i++) {
        $salle = $_POST["salle$i"] ?? '';
        $capteur = $_POST["capteur$i"] ?? '';
        
        if (!empty($salle) && !empty($capteur)) {
            $config['pairs'][] = [
                'salle' => $salle,
                'capteur' => $capteur
            ];
        }
    }
    
    // Sauvegarder la configuration dans un fichier
    try {
        // Vérifier si le répertoire est writable
        if (!is_writable('.')) {
            throw new Exception("Le répertoire n'est pas accessible en écriture.");
        }
        
        $result = file_put_contents('config.json', json_encode($config, JSON_PRETTY_PRINT));
        if ($result === false) {
            throw new Exception("Impossible d'écrire dans le fichier config.json.");
        }
        
        $message_config = "Configuration sauvegardée avec succès !";
    } catch (Exception $e) {
        $message_config = "Erreur lors de la sauvegarde : " . $e->getMessage();
        // Log l'erreur pour debug
        error_log("Erreur sauvegarde config: " . $e->getMessage());
    }
}

// Déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: administration.php");
    exit();
}

// Charger la configuration existante
$config = [];
if (file_exists('config.json')) {
    $config = json_decode(file_get_contents('config.json'), true);
}

// Récupérer les données de la base si connecté et pas d'erreur DB
$salles = [];
$capteurs = [];
if (isset($_SESSION['connecte']) && !isset($db_error) && $pdo) { // S'assurer que la connexion est établie
    try {
        // Vérifier d'abord si on a les données dans le fichier config
        if (isset($config['salles']) && isset($config['capteurs']) && 
            !empty($config['salles']) && !empty($config['capteurs'])) {
            $salles = $config['salles'];
            $capteurs = $config['capteurs'];
        } else {
            // Sinon récupérer depuis la base de données
            $stmt_salles = $pdo->query("SELECT DISTINCT nom_salle FROM Salle ORDER BY nom_salle");
            $salles = $stmt_salles->fetchAll(PDO::FETCH_COLUMN);

            $stmt_capteurs = $pdo->query("SELECT DISTINCT type FROM Capteur ORDER BY type");
            $capteurs = $stmt_capteurs->fetchAll(PDO::FETCH_COLUMN);
        }
    } catch(PDOException $e) {
        $db_error = "Erreur lors de la récupération des données : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Gestion</title>
    <link rel="stylesheet" href="./styles/style.css">
</head>
<body>
<header>
    <nav>
        <ul>
            <li><a href="index.html">Accueil</a></li>
            <li><a href="administration.php" class="active">Administration</a></li>
            <li><a href="gestionnaire.php">Gestion</a></li>
            <li><a href="consultation.php">Consultation</a></li>
            <li><a href="gestion-projet.html">Gestion de projet</a></li>
        </ul>
    </nav>
</header>
<main>
<?php if (!isset($_SESSION['connecte']) || $_SESSION['connecte'] !== true): ?>
    <section class="connexion-admin">
        <h2>Connexion Administrateur</h2>
        <form method="post" class="form-connexion">
            <div class="champ-formulaire">
                <label for="identifiant">Identifiant :</label>
                <input type="text" id="identifiant" name="identifiant" required>
            </div>
            <div class="champ-formulaire">
                <label for="mot-de-passe">Mot de passe :</label>
                <input type="password" id="mot-de-passe" name="mot-de-passe" required>
            </div>
            <button type="submit">Se connecter</button>
            <?php if (isset($erreur) && $erreur != ''): // Afficher l'erreur seulement si elle est définie et non vide ?>
                <p class="alert"><?= htmlspecialchars($erreur) ?></p>
            <?php endif; ?>
             <?php if (isset($db_error)): // Afficher l'erreur de connexion à la BDD ?>
                <p class="alert"><?= htmlspecialchars($db_error) ?></p>
            <?php endif; ?>
        </form>
    </section>
<?php else: ?>
    <section class="panel-admin">
        <h2>Panneau d'administration</h2>
        <a href="administration.php?logout=true" class="btn-deconnexion">Se déconnecter</a>
        
        <?php if (isset($db_error)): ?>
            <div class="message-error">
                <?= htmlspecialchars($db_error) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($message_config)): ?>
            <div class="message-success">
                <?= htmlspecialchars($message_config) ?>
            </div>
        <?php endif; ?>
        
        <div class="section-configuration">
            <h3>Configuration des Capteurs</h3>           
            
            <?php if (!isset($db_error)): ?>
                <form method="POST">
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <div class="form-group">
                            <h4>Configuration <?= $i ?></h4>
                            <div class="form-pair">
                                <div class="form-field">
                                    <label for="salle<?= $i ?>">Salle :</label>
                                    <select name="salle<?= $i ?>" id="salle<?= $i ?>">
                                        <option value="">-- Sélectionnez une salle --</option>
                                        <?php foreach ($salles as $salle): ?>
                                            <option value="<?= htmlspecialchars($salle) ?>" 
                                                <?= (isset($config['pairs'][$i-1]) && $config['pairs'][$i-1]['salle'] === $salle) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($salle) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label for="capteur<?= $i ?>">Type de capteur :</label>
                                    <select name="capteur<?= $i ?>" id="capteur<?= $i ?>">
                                        <option value="">-- Sélectionnez un type --</option>
                                        <?php foreach ($capteurs as $capteur): ?>
                                            <option value="<?= htmlspecialchars($capteur) ?>" 
                                                <?= (isset($config['pairs'][$i-1]) && $config['pairs'][$i-1]['capteur'] === $capteur) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($capteur) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                    
                    <button type="submit" class="btn-config">Sauvegarder la Configuration</button>
                </form>
                
                <?php if (!empty($config['pairs'])): ?>
                    <div class="preview-config">
                        <h4>Configuration Actuelle</h4>
                        <p><strong>Paires salle-capteur configurées :</strong></p>
                        <ul>
                            <?php foreach ($config['pairs'] as $index => $pair): ?>
                                <li>Configuration <?= $index + 1 ?> : Salle <?= htmlspecialchars($pair['salle']) ?> - Capteur <?= htmlspecialchars($pair['capteur']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p>Impossible de charger la configuration des capteurs en raison d'un problème de base de données.</p>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
</main>
<footer>
    <p>IUT Blagnac - BUT R&T - 2025</p>
</footer>
</body>
</html>

