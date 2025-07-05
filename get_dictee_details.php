<?php
// Définir le type de contenu comme JSON
header('Content-Type: application/json');

try {
    // Connexion à la base de données
    $pdo = new PDO("mysql:host=localhost;dbname=duzhenko;charset=utf8", 'duzhenko', 'nikita!');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Récupérer et valider l'ID de la dictée
    $id_dictee = isset($_GET['id_dictee']) ? (int)$_GET['id_dictee'] : 0;
    
    if ($id_dictee <= 0) {
        http_response_code(400);
        echo json_encode(["error" => "ID de dictée invalide ou manquant"]);
        exit;
    }
    
    // Requête pour récupérer les détails de la dictée
    $stmt = $pdo->prepare("
        SELECT id_dictee, nom_dictee, type_dictee, contenu 
        FROM dictees 
        WHERE id_dictee = ?
    ");
    $stmt->execute([$id_dictee]);
    
    // Récupérer le résultat
    $dictee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dictee) {
        http_response_code(404);
        echo json_encode(["error" => "Dictée non trouvée"]);
        exit;
    }
    
    // Si c'est une dictée de mots, récupérer les mots associés
    if ($dictee['type_dictee'] === 'mots') {
        $stmt_mots = $pdo->prepare("
            SELECT mr.id_mot, mr.mot, mr.categorie, mr.lemme, mr.radical, mr.desinence
            FROM mots_ref mr
            JOIN dictee_mots dm ON mr.id_mot = dm.id_mot
            WHERE dm.id_dictee = ?
            ORDER BY dm.id_dict_mots ASC
        ");
        $stmt_mots->execute([$id_dictee]);
        $mots = $stmt_mots->fetchAll(PDO::FETCH_ASSOC);
        
        // Ajouter les mots aux détails de la dictée
        $dictee['mots'] = $mots;
    }
    
    // Journaliser pour le débogage (à enlever en production)
    error_log("Détails dictée ID $id_dictee: " . json_encode($dictee));
    
    // Retourner les détails de la dictée
    echo json_encode($dictee);
    
} catch (PDOException $e) {
    // Gestion des erreurs de base de données
    error_log("Erreur PDO dans get_dictee_details.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Erreur de base de données: " . $e->getMessage()]);
} catch (Exception $e) {
    // Gestion des autres erreurs
    error_log("Erreur dans get_dictee_details.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Erreur: " . $e->getMessage()]);
}
?>