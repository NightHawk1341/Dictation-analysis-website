<?php
// Définir le type de contenu comme JSON
header('Content-Type: application/json');

try {
    // Connexion à la base de données
    $pdo = new PDO("mysql:host=localhost;dbname=duzhenko;charset=utf8", 'duzhenko', 'nikita!');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Récupération et validation des paramètres de filtre
    $id_dictee = isset($_GET['id_dictee']) ? (int)$_GET['id_dictee'] : 0;
    $niveau_eleve = isset($_GET['niveau_eleve']) ? $_GET['niveau_eleve'] : '';
    
    // Journaliser les paramètres reçus pour débogage
    error_log("Paramètres reçus: id_dictee=$id_dictee, niveau_eleve=$niveau_eleve");
    
    // Construction de la requête SQL pour obtenir les statistiques par mot
    $sql = "
        SELECT 
            mr.id_mot,
            mr.mot,
            d.id_dictee,
            d.nom_dictee,
            mr.nbErrRad AS compteur_err_rad,
            mr.nbErrDes AS compteur_err_des,
            mr.nbErrRadEtDes AS compteur_err_rad_des,
            (mr.nbErrRad + mr.nbErrDes + mr.nbErrRadEtDes) AS total_erreurs,
            (
                SELECT COUNT(DISTINCT re2.id_reponse) 
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
    $sql .= " GROUP BY mr.id_mot, mr.mot, d.id_dictee, d.nom_dictee, mr.nbErrRad, mr.nbErrDes, mr.nbErrRadEtDes";
    $sql .= " ORDER BY total_erreurs DESC, mr.mot ASC";
    
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
    $statistiques = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer le pourcentage d'erreurs correct si total_reponses est disponible
    foreach ($statistiques as &$stat) {
        if (isset($stat['total_reponses']) && $stat['total_reponses'] > 0) {
            // Utiliser les compteurs stockés dans la table mots_ref pour le calcul
            $total_erreurs_stockees = $stat['compteur_err_rad'] + $stat['compteur_err_des'] + $stat['compteur_err_rad_des'];
            // Limiter le pourcentage à 100% maximum
            $stat['pourcentage_erreurs'] = min(($total_erreurs_stockees / $stat['total_reponses']) * 100, 100);
        } else {
            $stat['pourcentage_erreurs'] = 0;
        }
    }
    
    // Journaliser le nombre de résultats
    error_log("Nombre de statistiques: " . count($statistiques));
    
    // Retour de la réponse JSON
    echo json_encode($statistiques);
    
} catch (PDOException $e) {
    // Gestion des erreurs de base de données
    error_log("Erreur SQL: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Erreur de base de données lors de la récupération des statistiques: " . $e->getMessage()]);
} catch (Exception $e) {
    // Gestion des autres erreurs
    error_log("Erreur: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Erreur lors de la récupération des statistiques: " . $e->getMessage()]);
}
?>