<?php
// Set content type to JSON
header('Content-Type: application/json');

try {
    // Database connection
    $host = getenv('DB_HOST');
    $db   = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass);
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