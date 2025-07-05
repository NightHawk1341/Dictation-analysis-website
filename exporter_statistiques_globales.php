<?php
// Définir les en-têtes pour forcer le téléchargement du fichier CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=statistiques_globales_' . date('Y-m-d') . '.csv');

// Créer le pointeur de fichier pour la sortie
$output = fopen('php://output', 'w');

// Définir l'encodage UTF-8 BOM pour Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Définir les en-têtes des colonnes
fputcsv($output, [
    'Mot',
    'Dictée',
    'Erreurs Radical',
    'Erreurs Désinence',
    'Erreurs Radical et Désinence',
    'Total Erreurs',
    'Pourcentage d\'erreurs'
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
    
    // Construction de la requête SQL pour obtenir les statistiques par mot
    $sql = "
        SELECT 
            mr.mot,
            d.nom_dictee,
            mr.nbErrRad AS compteur_err_rad,
            mr.nbErrDes AS compteur_err_des,
            mr.nbErrRadEtDes AS compteur_err_rad_des,
            (
                SELECT COUNT(re2.id_reponse) 
                FROM reponses_eleves re2
                JOIN eleves e2 ON re2.id_eleve = e2.id_eleve
                WHERE re2.id_dictee = d.id_dictee
                " . (!empty($niveau_eleve) ? " AND e2.niveau = :niveau_eleve_count" : "") . "
            ) AS total_reponses
        FROM 
            mots_ref mr
        JOIN 
            dictee_mots dm ON mr.id_mot = dm.id_mot
        JOIN 
            dictees d ON dm.id_dictee = d.id_dictee
    ";
    
    // Ajout des conditions WHERE en fonction des filtres
    $where_conditions = [];
    $params = [];
    
    if ($id_dictee > 0) {
        $where_conditions[] = "d.id_dictee = :id_dictee";
        $params[':id_dictee'] = $id_dictee;
    }
    
    if (!empty($niveau_eleve)) {
        $params[':niveau_eleve_count'] = $niveau_eleve;
    }
    
    // Ajout de la clause WHERE si des conditions existent
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    // Ajout du GROUP BY et ORDER BY
    $sql .= " GROUP BY mr.id_mot, mr.mot, d.nom_dictee, mr.nbErrRad, mr.nbErrDes, mr.nbErrRadEtDes";
    $sql .= " ORDER BY (mr.nbErrRad + mr.nbErrDes + mr.nbErrRadEtDes) DESC, mr.mot ASC";
    
    // Préparation et exécution de la requête
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    // Écrire les résultats dans le CSV
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $compteur_err_rad = $row['compteur_err_rad'] ?? 0;
        $compteur_err_des = $row['compteur_err_des'] ?? 0;
        $compteur_err_rad_des = $row['compteur_err_rad_des'] ?? 0;
        $total_erreurs = $compteur_err_rad + $compteur_err_des + $compteur_err_rad_des;
        
        // Calculer le pourcentage d'erreurs
        $pourcentage = 0;
        if (isset($row['total_reponses']) && $row['total_reponses'] > 0) {
            $pourcentage = min(($total_erreurs / $row['total_reponses']) * 100, 100);
        }
        
        fputcsv($output, [
            $row['mot'],
            $row['nom_dictee'],
            $compteur_err_rad,
            $compteur_err_des,
            $compteur_err_rad_des,
            $total_erreurs,
            number_format($pourcentage, 1, ',', ' ') . '%'
        ], ';');
    }
    
} catch (Exception $e) {
    // En cas d'erreur, écrire une ligne d'erreur dans le CSV
    fputcsv($output, ["Erreur lors de l'exportation: " . $e->getMessage()], ';');
}

// Fermer le pointeur de fichier
fclose($output);
?>