<?php
/**
 * Fichier helper pour la configuration des salles et capteurs
 * À inclure dans gestionnaire.php et consultation.php
 * 
 * Usage:
 * require_once 'config_helper.php';
 * $config = getConfiguration();
 * $whereCondition = buildDynamicWhereCondition();
 * $bindValues = getDynamicBindValues();
 */

function getConfiguration() {
    $config = [];
    if (file_exists('config.json')) {
        $config = json_decode(file_get_contents('config.json'), true);
    }
    
    // Valeurs par défaut si aucune configuration n'existe
    if (empty($config) || !isset($config['pairs'])) {
        $config = [
            'pairs' => [
                ['salle' => 'B101', 'capteur' => 'temperature'],
                ['salle' => 'B110', 'capteur' => 'illumination'],
                ['salle' => 'E104', 'capteur' => 'co2'],
                ['salle' => 'E105', 'capteur' => 'humidity']
            ]
        ];
    }
    
    return $config;
}

function getConfiguredPairs() {
    $config = getConfiguration();
    return $config['pairs'];
}

/**
 * Construit la condition WHERE dynamique pour les requêtes SQL
 * @param string $tableAlias - Alias de la table (ex: 'c' pour Capteur)
 * @return string - Condition WHERE SQL
 */
function buildDynamicWhereCondition($tableAlias = 'c') {
    $pairs = getConfiguredPairs();
    
    if (empty($pairs)) {
        return '1=0'; // Aucune donnée si pas de configuration
    }
    
    $conditions = [];
    foreach ($pairs as $pair) {
        if (!empty($pair['salle']) && !empty($pair['capteur'])) {
            $conditions[] = "({$tableAlias}.nom_salle = ? AND {$tableAlias}.type = ?)";
        }
    }
    
    if (empty($conditions)) {
        return '1=0'; // Aucune donnée si pas de paires valides
    }
    
    return '(' . implode(' OR ', $conditions) . ')';
}

/**
 * Retourne les valeurs à binder pour la requête préparée
 * @return array - Tableau des valeurs pour les placeholders
 */
function getDynamicBindValues() {
    $pairs = getConfiguredPairs();
    $values = [];
    
    foreach ($pairs as $pair) {
        if (!empty($pair['salle']) && !empty($pair['capteur'])) {
            $values[] = $pair['salle'];
            $values[] = $pair['capteur'];
        }
    }
    
    return $values;
}

/**
 * Retourne une description textuelle de la configuration
 * @return string - Description de la configuration
 */
function getConfigurationDescription() {
    $pairs = getConfiguredPairs();
    $descriptions = [];
    
    foreach ($pairs as $index => $pair) {
        if (!empty($pair['salle']) && !empty($pair['capteur'])) {
            $descriptions[] = "Salle {$pair['salle']} - Capteur {$pair['capteur']}";
        }
    }
    
    return empty($descriptions) ? 'Aucune configuration définie' : implode(', ', $descriptions);
}

/**
 * Vérifie si une configuration existe
 * @return bool - True si au moins une paire est configurée
 */
function hasConfiguration() {
    $pairs = getConfiguredPairs();
    
    foreach ($pairs as $pair) {
        if (!empty($pair['salle']) && !empty($pair['capteur'])) {
            return true;
        }
    }
    
    return false;
}
?>