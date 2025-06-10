<?php
require_once 'config_helper.php';

// Connexion à la base de données
$host = 'localhost';
$dbname = 'test';
$user = 'freezbee';
$pass = 'free';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

// Construire la requête dynamiquement basée sur la configuration
$whereCondition = buildDynamicWhereCondition('c');
$bindValues = getDynamicBindValues();

// Récupérer la dernière mesure pour chaque capteur configuré
$sql = "
    SELECT c.nom_capteur, c.type, c.unite, c.nom_salle, m.valeur, m.date, m.horaire
    FROM Capteur c
    JOIN (
        SELECT nom_capteur, MAX(CONCAT(date, ' ', horaire)) AS max_datetime
        FROM Mesure
        GROUP BY nom_capteur
    ) AS latest
    ON c.nom_capteur = latest.nom_capteur
    JOIN Mesure m
    ON m.nom_capteur = latest.nom_capteur
    AND CONCAT(m.date, ' ', m.horaire) = latest.max_datetime
    WHERE $whereCondition
    ORDER BY c.nom_salle ASC, c.type ASC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindValues);
    $mesures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mesures = [];
    $error_message = "Erreur lors de la récupération des mesures : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Consultation - Dernières mesures</title>
  <link rel="stylesheet" href="./styles/style.css">
</head>
<body>

<header>
  <nav>
    <ul>
      <li><a href="index.html">Accueil</a></li>
      <li><a href="administration.php">Administration</a></li>
      <li><a href="gestionnaire.php">Gestion</a></li>
      <li><a href="consultation.php" class="active">Consultation</a></li>
      <li><a href="gestion-projet.html">Gestion de projet</a></li>
    </ul>
  </nav>
</header>

<main>
  <section class="dernieres-mesures">
    <h2>Dernières mesures des capteurs</h2>        
    <div class="liste-capteurs">
      <?php if (count($mesures) > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Capteur</th>
              <th>Type</th>
              <th>Unité</th>
              <th>Salle</th>
              <th>Valeur</th>
              <th>Date</th>
              <th>Heure</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($mesures as $m): ?>
              <tr>
                <td><?= htmlspecialchars($m['nom_capteur']) ?></td>
                <td><?= htmlspecialchars($m['type']) ?></td>
                <td><?= htmlspecialchars($m['unite']) ?></td>
                <td><?= htmlspecialchars($m['nom_salle']) ?></td>
                <td><?= htmlspecialchars($m['valeur']) ?></td>
                <td><?= htmlspecialchars($m['date']) ?></td>
                <td><?= htmlspecialchars($m['horaire']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="no-data">
          <?php if (hasConfiguration()): ?>
            <p>Aucune mesure disponible pour les capteurs configurés.</p>
          <?php else: ?>
            <p>Aucune configuration définie. Veuillez configurer les capteurs dans la section Administration.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<footer>
  <p>IUT Blagnac - BUT R&T - 2025</p>
</footer>

</body>
</html>
