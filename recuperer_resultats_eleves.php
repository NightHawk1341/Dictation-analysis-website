<?php
// Définir le type de contenu comme JSON
header('Content-Type: application/json');

// Activer l'affichage des erreurs (À commenter en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // Connexion à la base de données
    $pdo = new PDO("mysql:host=localhost;dbname=duzhenko;charset=utf8", 'duzhenko', 'nikita!');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Récupération et validation des paramètres de filtre
    $id_dictee = isset($_GET['id_dictee']) && !empty($_GET['id_dictee']) ? (int)$_GET['id_dictee'] : null;
    $niveau_eleve = isset($_GET['niveau_eleve']) && !empty($_GET['niveau_eleve']) ? $_GET['niveau_eleve'] : null;
    
    // Journaliser les paramètres reçus pour débogage
    error_log("Paramètres reçus: id_dictee=" . ($id_dictee ?? 'null') . ", niveau_eleve=" . ($niveau_eleve ?? 'null'));
    
    // Construction de la requête SQL
    // Approche directe: on compte les erreurs dans erreurs_eleves par type
    $sql = "
        SELECT 
            re.id_reponse,
            e.id_eleve,
            e.nom,
            e.prenom,
            CONCAT(e.prenom, ' ', e.nom) AS nom_complet_eleve,
            e.niveau,
            d.id_dictee,
            d.nom_dictee,
            (
                SELECT COUNT(*) 
                FROM erreurs_eleves ee 
                WHERE ee.id_reponse_eleve = re.id_reponse 
                AND ee.type_erreur = 'orthographique_radical'
            ) AS nb_err_radical,
            (
                SELECT COUNT(*) 
                FROM erreurs_eleves ee 
                WHERE ee.id_reponse_eleve = re.id_reponse 
                AND ee.type_erreur = 'orthographique_desinence'
            ) AS nb_err_desinence,
            (
                SELECT COUNT(*) 
                FROM erreurs_eleves ee 
                WHERE ee.id_reponse_eleve = re.id_reponse 
                AND ee.type_erreur = 'orthographique_radical_et_desinence'
            ) AS nb_err_rad_des,
            (
                SELECT COUNT(*) 
                FROM erreurs_eleves ee 
                WHERE ee.id_reponse_eleve = re.id_reponse 
                AND (
                    ee.type_erreur = 'orthographique_mot_incorrect_complexe' OR
                    ee.type_erreur = 'lexical_omission' OR 
                    ee.type_erreur = 'lexical_ajout'
                )
            ) AS nb_err_orth_complexe,
            (
                SELECT COUNT(*) 
                FROM erreurs_eleves ee 
                WHERE ee.id_reponse_eleve = re.id_reponse
            ) AS total_erreurs,
            re.saisie
        FROM 
            reponses_eleves re
        JOIN 
            eleves e ON re.id_eleve = e.id_eleve
        JOIN 
            dictees d ON re.id_dictee = d.id_dictee
    ";
    
    // Ajout des conditions WHERE en fonction des filtres
    $where_conditions = [];
    $params = [];
    
    if ($id_dictee !== null) {
        $where_conditions[] = "re.id_dictee = :id_dictee";
        $params[':id_dictee'] = $id_dictee;
    }
    
    if ($niveau_eleve !== null) {
        $where_conditions[] = "e.niveau = :niveau_eleve";
        $params[':niveau_eleve'] = $niveau_eleve;
    }
    
    // Ajout de la clause WHERE si des conditions existent
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    // Ajout du ORDER BY
    $sql .= " ORDER BY e.nom ASC, e.prenom ASC";
    
    // Journaliser la requête SQL pour débogage
    error_log("Requête SQL: " . $sql);
    error_log("Paramètres: " . json_encode($params));
    
    // Préparation et exécution de la requête
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    // Récupération de tous les résultats
    $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Journaliser le nombre de résultats
    error_log("Nombre de résultats: " . count($resultats));
    
    // Si aucun résultat, retourner un message spécifique
    if (count($resultats) === 0) {
        echo json_encode([
            "message" => "Aucun résultat trouvé pour les critères spécifiés.",
            "resultats" => []
        ]);
        exit;
    }
    
    // Retour de la réponse JSON
    echo json_encode($resultats);
    
} catch (PDOException $e) {
    // Gestion des erreurs de base de données
    error_log("Erreur SQL: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Erreur de base de données lors de la récupération des résultats: " . $e->getMessage()]);
} catch (Exception $e) {
    // Gestion des autres erreurs
    error_log("Erreur: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Erreur lors de la récupération des résultats: " . $e->getMessage()]);
}
?>