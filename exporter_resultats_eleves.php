<?php
// Définir les en-têtes pour forcer le téléchargement du fichier CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=resultats_eleves_' . date('Y-m-d') . '.csv');

// Créer le pointeur de fichier pour la sortie
$output = fopen('php://output', 'w');

// Définir l'encodage UTF-8 BOM pour Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Définir les en-têtes des colonnes
fputcsv($output, [
    'Élève',
    'Niveau',
    'Dictée',
    'Erreurs Radical',
    'Erreurs Désinence',
    'Erreurs Radical et Désinence',
    'Autres Erreurs',
    'Total Erreurs'
], ';'); // Utiliser ; comme séparateur pour la compatibilité avec Excel français

try {
    // Connexion à la base de données
    $host = getenv('DB_HOST');
    $db   = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    
    // Récupération et validation des paramètres de filtre
    $id_dictee = isset($_GET['id_dictee']) ? (int)$_GET['id_dictee'] : 0;
    $niveau_eleve = isset($_GET['niveau_eleve']) ? $_GET['niveau_eleve'] : '';
    
    // Construction de la requête SQL de base (similaire à recuperer_resultats_eleves.php)
    $sql = "
        SELECT 
            CONCAT(e.prenom, ' ', e.nom) AS nom_complet_eleve,
            e.niveau AS niveau,
            d.nom_dictee,
            COUNT(CASE WHEN ee.type_erreur = 'orthographique_radical' THEN 1 END) AS nb_err_radical,
            COUNT(CASE WHEN ee.type_erreur = 'orthographique_desinence' THEN 1 END) AS nb_err_desinence,
            COUNT(CASE WHEN ee.type_erreur = 'orthographique_radical_et_desinence' THEN 1 END) AS nb_err_rad_des,
            COUNT(CASE WHEN ee.type_erreur = 'orthographique_mot_incorrect_complexe' OR
                         ee.type_erreur = 'lexical_omission' OR 
                         ee.type_erreur = 'lexical_ajout' THEN 1 END) AS nb_err_orth_complexe,
            COUNT(ee.id_erreur) AS total_erreurs
        FROM 
            reponses_eleves re
        JOIN 
            eleves e ON re.id_eleve = e.id_eleve
        JOIN 
            dictees d ON re.id_dictee = d.id_dictee
        LEFT JOIN 
            erreurs_eleves ee ON re.id_reponse = ee.id_reponse_eleve
    ";
    
    // Ajout des conditions WHERE en fonction des filtres
    $where_conditions = [];
    $params = [];
    
    if ($id_dictee > 0) {
        $where_conditions[] = "re.id_dictee = :id_dictee";
        $params[':id_dictee'] = $id_dictee;
    }
    
    if (!empty($niveau_eleve)) {
        $where_conditions[] = "e.niveau = :niveau_eleve";
        $params[':niveau_eleve'] = $niveau_eleve;
    }
    
    // Ajout de la clause WHERE si des conditions existent
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    // Ajout du GROUP BY et ORDER BY
    $sql .= " GROUP BY re.id_reponse, e.prenom, e.nom, e.niveau, d.nom_dictee";
    $sql .= " ORDER BY e.nom ASC, e.prenom ASC";
    
    // Préparation et exécution de la requête
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    // Écrire les résultats dans le CSV
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['nom_complet_eleve'],
            $row['niveau'],
            $row['nom_dictee'],
            $row['nb_err_radical'] ?? 0,
            $row['nb_err_desinence'] ?? 0,
            $row['nb_err_rad_des'] ?? 0,
            $row['nb_err_orth_complexe'] ?? 0,
            $row['total_erreurs'] ?? 0
        ], ';');
    }
    
} catch (Exception $e) {
    // En cas d'erreur, écrire une ligne d'erreur dans le CSV
    fputcsv($output, ["Erreur lors de l'exportation: " . $e->getMessage()], ';');
}

// Fermer le pointeur de fichier
fclose($output);
?>