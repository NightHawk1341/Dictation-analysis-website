<?php
// Set content type to JSON
header('Content-Type: application/json');

try {
    // Database connection
    $pdo = new PDO("mysql:host=localhost;dbname=duzhenko;charset=utf8", 'duzhenko', 'nikita!');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Query to get all dictations
    $stmt = $pdo->prepare("SELECT id_dictee, nom_dictee, type_dictee FROM dictees ORDER BY nom_dictee ASC");
    $stmt->execute();
    
    // Fetch all results
    $dictees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return JSON response
    echo json_encode($dictees);
    
} catch (PDOException $e) {
    // Handle database errors
    http_response_code(500);
    echo json_encode(["error" => "Erreur de base de données lors de la récupération des dictées: " . $e->getMessage()]);
} catch (Exception $e) {
    // Handle other errors
    http_response_code(500);
    echo json_encode(["error" => "Erreur lors de la récupération des dictées: " . $e->getMessage()]);
}
?>