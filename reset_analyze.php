<?php
// Définir le type de contenu comme JSON
header('Content-Type: application/json');

// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
    
    // Paramètre pour choisir l'action
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    
    // Résultats à retourner
    $results = [];
    
    if ($action === 'reset') {
        // 1. Vider la table erreurs_eleves
        $stmt = $pdo->exec("DELETE FROM erreurs_eleves");
        $results['delete_erreurs'] = "Table erreurs_eleves vidée";
        
        // 2. Réinitialiser les compteurs dans mots_ref
        $stmt = $pdo->exec("UPDATE mots_ref SET nbErrRad = 0, nbErrDes = 0, nbErrRadEtDes = 0");
        $results['reset_compteurs'] = "Compteurs réinitialisés dans mots_ref";
        
    } elseif ($action === 'analyze') {
        // Récupérer toutes les réponses
        $stmt = $pdo->query("SELECT id_reponse FROM reponses_eleves ORDER BY id_reponse");
        $reponses = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $results['reponses_count'] = count($reponses);
        $results['analyses'] = [];
        
        // Analyser chaque réponse
        foreach ($reponses as $id_reponse) {
            // Récupérer les données nécessaires pour analyser la réponse
            $stmt_reponse = $pdo->prepare("
                SELECT re.id_dictee, re.saisie, d.type_dictee
                FROM reponses_eleves re
                JOIN dictees d ON re.id_dictee = d.id_dictee
                WHERE re.id_reponse = ?
            ");
            $stmt_reponse->execute([$id_reponse]);
            $reponse_data = $stmt_reponse->fetch(PDO::FETCH_ASSOC);
            
            if (!$reponse_data) {
                $results['analyses'][$id_reponse] = "Erreur: Réponse introuvable";
                continue;
            }
            
            // Ignorer les types de dictées non supportés
            if ($reponse_data['type_dictee'] !== 'mots') {
                $results['analyses'][$id_reponse] = "Ignoré: Type de dictée non supporté";
                continue;
            }
            
            // Créer l'URL pour l'API d'analyse
            $analyse_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/analyser_reponse.php?id_reponse_eleve=' . $id_reponse;
            
            // Appeler l'API d'analyse
            $ch = curl_init($analyse_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $result = curl_exec($ch);
            
            if (curl_errno($ch)) {
                $results['analyses'][$id_reponse] = "Erreur cURL: " . curl_error($ch);
            } else {
                $resultData = json_decode($result, true);
                $results['analyses'][$id_reponse] = $resultData;
            }
            
            curl_close($ch);
        }
        
    } else {
        // Instructions d'utilisation
        $results['usage'] = "Utilisation: reset_analyze.php?action=reset|analyze";
        $results['actions'] = [
            'reset' => "Réinitialise les tables d'erreurs et les compteurs",
            'analyze' => "Analyse toutes les réponses existantes"
        ];
    }
    
    // Retourner les résultats
    echo json_encode($results, JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    // Gestion des erreurs de base de données
    http_response_code(500);
    echo json_encode([
        "error" => "Erreur de base de données: " . $e->getMessage(),
        "trace" => $e->getTraceAsString()
    ]);
} catch (Exception $e) {
    // Gestion des autres erreurs
    http_response_code(500);
    echo json_encode([
        "error" => "Erreur: " . $e->getMessage(),
        "trace" => $e->getTraceAsString()
    ]);
}
?>