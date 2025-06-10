<?php
session_start(); // Doit être la première chose dans le script
require_once 'config_helper.php';

// Configuration de la base de données
$host = 'localhost';
$dbname = 'test';
$username = 'freezbee';
$password = 'free';

$pdo = null;
$erreur_db = null;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // echo "<p style='color: green;'>Connexion à la base de données réussie pour gestionnaire!</p>"; // Message de débogage
} catch (PDOException $e) {
    $erreur_db = 'Erreur de connexion : ' . $e->getMessage();
    // echo "<p style='color: red;'>$erreur_db</p>"; // Message de débogage
}

// Initialisation de la variable d'erreur
$erreur = '';

// Traitement du formulaire de connexion pour le gestionnaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifiant_saisi = $_POST['identifiant-gestionnaire'] ?? '';
    $mdp_saisi = $_POST['mot-de-passe-gestionnaire'] ?? '';

    if ($pdo) { // Assurez-vous que la connexion PDO est établie
        try {
            // Préparation de la requête SQL pour récupérer l'utilisateur 'gestionnaire'
            $sql = "SELECT identifiant, mot_de_passe FROM Connexion WHERE identifiant = :identifiant AND role = 'gestionnaire'";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':identifiant', $identifiant_saisi, PDO::PARAM_STR);
            $stmt->execute();

            $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($utilisateur) {
                // echo "<p style='color: blue;'>Utilisateur gestionnaire trouvé dans la base de données.</p>"; // Message de débogage
                // Un utilisateur avec cet identifiant et le rôle 'gestionnaire' a été trouvé
                // Vérifier le mot de passe
                if ($mdp_saisi === $utilisateur['mot_de_passe']) { // Rappel: utiliser password_verify() pour la sécurité réelle
                    $_SESSION['gestionnaire'] = true;
                    // echo "<p style='color: green;'>Connexion gestionnaire réussie!</p>"; // Message de débogage
                } else {
                    $erreur = "Identifiants incorrects.";
                    // echo "<p style='color: red;'>Mot de passe gestionnaire incorrect.</p>"; // Message de débogage
                }
            } else {
                // Aucun utilisateur trouvé avec cet identifiant ou rôle incorrect
                $erreur = "Identifiants incorrects.";
                // echo "<p style='color: red;'>Utilisateur gestionnaire non trouvé ou rôle incorrect.</p>"; // Message de débogage
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

// Déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: gestionnaire.php");
    exit();
}

// Récupération des données si connecté
$statistiques_capteurs = [];

if (isset($_SESSION['gestionnaire']) && $_SESSION['gestionnaire'] === true && $pdo) { // Assurez-vous que la connexion est établie
    try {
        // Construire la requête dynamiquement basée sur la configuration
        // Assurez-vous que les fonctions buildDynamicWhereCondition et getDynamicBindValues sont définies dans config_helper.php
        $whereCondition = buildDynamicWhereCondition('c');
        $bindValues = getDynamicBindValues();
        
        // Requête pour les statistiques des capteurs configurés
        $sql = "
            SELECT 
                c.nom_capteur, 
                c.type, 
                c.unite, 
                c.nom_salle,
                ROUND(AVG(m.valeur), 2) AS moyenne,
                MIN(m.valeur) AS minimum,
                MAX(m.valeur) AS maximum,
                COUNT(m.valeur) AS nombre_mesures
            FROM Capteur c
            JOIN Mesure m ON c.nom_capteur = m.nom_capteur
            WHERE $whereCondition
            GROUP BY c.nom_capteur, c.type, c.unite, c.nom_salle
            ORDER BY c.nom_salle ASC, c.nom_capteur ASC
        ";
        
        $stmt_stats = $pdo->prepare($sql);
        $stmt_stats->execute($bindValues);
        $statistiques_capteurs = $stmt_stats->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $erreur_db = "Erreur lors de la récupération des données : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestion - Capteurs & Salles</title>
  <link rel="stylesheet" href="./styles/style.css">
</head>
<body>
<header>
    <nav>
        <ul>
            <li><a href="index.html">Accueil</a></li>
            <li><a href="administration.php">Administration</a></li>
            <li><a href="gestionnaire.php" class="active">Gestion</a></li>
            <li><a href="consultation.php">Consultation</a></li>
            <li><a href="gestion-projet.html">Gestion de projet</a></li>
        </ul>
    </nav>
</header>
<main>
<?php if (!isset($_SESSION['gestionnaire']) || $_SESSION['gestionnaire'] !== true): ?>
    <section class="connexion-gestionnaire">
        <h2>Connexion Gestionnaire</h2>
        <form method="post" class="form-connexion">
            <div class="champ-formulaire">
                <label for="identifiant-gestionnaire">Identifiant :</label>
                <input type="text" id="identifiant-gestionnaire" name="identifiant-gestionnaire" required>
            </div>
            <div class="champ-formulaire">
                <label for="mot-de-passe-gestionnaire">Mot de passe :</label>
                <input type="password" id="mot-de-passe-gestionnaire" name="mot-de-passe-gestionnaire" required>
            </div>
            <button type="submit">Se connecter</button>
            <?php if (isset($erreur) && $erreur != ''): // Afficher l'erreur seulement si elle est définie et non vide ?>
                <p class="alert"><?= htmlspecialchars($erreur) ?></p>
            <?php endif; ?>
            <?php if (isset($erreur_db)): // Afficher l'erreur de connexion à la BDD ?>
                <p class="alert"><?= htmlspecialchars($erreur_db) ?></p>
            <?php endif; ?>
        </form>
    </section>
<?php else: ?>
    <section class="panel-gestion">
        <h2>Panneau de gestion</h2>
        <a href="gestionnaire.php?logout=true" class="btn-deconnexion">Se déconnecter</a>
        
        <?php if (isset($erreur_db)): ?>
            <div class="alert-error"><?= htmlspecialchars($erreur_db) ?></div>
        <?php endif; ?>        
        <div class="statistiques-salles">
            <h3>Statistiques des capteurs configurés</h3>
            <div class="table-container">
                <?php if (!empty($statistiques_capteurs)): ?>
                    <table class="liste-capteurs">
                        <thead>
                            <tr>
                                <th>Capteur</th>
                                <th>Type</th>
                                <th>Salle</th>
                                <th>Moyenne</th>
                                <th>Minimum</th>
                                <th>Maximum</th>
                                <th>Unité</th>
                                <th>Nb mesures</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($statistiques_capteurs as $stat): ?>
                                <tr>
                                    <td><?= htmlspecialchars($stat['nom_capteur']) ?></td>
                                    <td><?= htmlspecialchars($stat['type']) ?></td>
                                    <td><?= htmlspecialchars($stat['nom_salle']) ?></td>
                                    <td><?= htmlspecialchars($stat['moyenne']) ?></td>
                                    <td><?= htmlspecialchars($stat['minimum']) ?></td>
                                    <td><?= htmlspecialchars($stat['maximum']) ?></td>
                                    <td><?= htmlspecialchars($stat['unite']) ?></td>
                                    <td><?= htmlspecialchars($stat['nombre_mesures']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">
                        <?php if (function_exists('hasConfiguration') && hasConfiguration()): // Vérifier si la fonction existe avant de l'appeler ?>
                            <p>Aucune statistique disponible pour les capteurs configurés.</p>
                        <?php else: ?>
                            <p>Aucune configuration définie. Veuillez configurer les capteurs dans la section Administration.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>
</main>
<footer>
    <p>IUT Blagnac - BUT R&T - 2025</p>
</footer>
</body>
</html>

