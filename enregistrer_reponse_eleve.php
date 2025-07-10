<?php
// Définir le type de contenu comme JSON
header('Content-Type: application/json');

// Activer l'affichage des erreurs (À commenter en production)
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



    // Vérifier si 'eleves' est défini
    if (!isset($_POST['eleves'])) {
        throw new Exception("Aucune donnée reçue.");
    }

    // Journaliser les données reçues pour débogage
    error_log("Données reçues: " . $_POST['eleves']);

    $eleves = json_decode($_POST['eleves'], true);

    if (!is_array($eleves) || count($eleves) === 0) {
        throw new Exception("Le format des données est invalide.");
    }

    // Préparer les requêtes
    $stmt_check_eleve = $pdo->prepare("
        SELECT id_eleve FROM eleves WHERE nom = ? AND prenom = ? AND niveau = ?
    ");
    
    $stmt_insert_eleve = $pdo->prepare("
        INSERT INTO eleves (nom, prenom, niveau) 
        VALUES (?, ?, ?)
    ");
    
    $stmt_insert_reponse = $pdo->prepare("
        INSERT INTO reponses_eleves (id_eleve, id_dictee, saisie) 
        VALUES (?, ?, ?)
    ");

    $reponses_ids = []; // Pour stocker les IDs des réponses insérées

    foreach ($eleves as $eleve) {
        // Vérification plus détaillée des champs
        if (!isset($eleve['nom_eleve']) || !isset($eleve['prenom_eleve']) || 
            !isset($eleve['niveau_eleve']) || !isset($eleve['id_dictee']) || 
            !isset($eleve['saisie_eleve'])) {
            throw new Exception("Données incomplètes pour un élève.");
        }

        // Vérification des valeurs vides
        if (empty($eleve['nom_eleve']) || empty($eleve['prenom_eleve']) || 
            empty($eleve['id_dictee']) || empty($eleve['saisie_eleve'])) {
            throw new Exception("Un ou plusieurs champs obligatoires sont vides.");
        }

        // Nettoyer les données
        $nom = trim($eleve['nom_eleve']);
        $prenom = trim($eleve['prenom_eleve']);
        $niveau = trim($eleve['niveau_eleve']);
        $id_dictee = (int)$eleve['id_dictee'];
        
        // Normaliser la saisie (s'assurer que les mots sont séparés par des ;)
        $saisie = trim($eleve['saisie_eleve']);
        $saisie = str_replace(',', ';', $saisie); // Convertir les virgules en points-virgules

        // Vérifier si l'élève existe déjà
        $stmt_check_eleve->execute([$nom, $prenom, $niveau]);
        $eleve_existant = $stmt_check_eleve->fetch(PDO::FETCH_ASSOC);
        
        if ($eleve_existant) {
            $id_eleve = $eleve_existant['id_eleve'];
        } else {
            // Insérer le nouvel élève
            $stmt_insert_eleve->execute([$nom, $prenom, $niveau]);
            $id_eleve = $pdo->lastInsertId();
        }

        // Journaliser pour débogage
        error_log("Élève ID: $id_eleve, Dictée ID: $id_dictee, Saisie: $saisie");

        // Insérer la réponse
        $stmt_insert_reponse->execute([$id_eleve, $id_dictee, $saisie]);
        $reponse_id = $pdo->lastInsertId();
        $reponses_ids[] = $reponse_id;
    }

    // Analyser automatiquement les réponses
    $erreurs_analysees = 0;
    foreach ($reponses_ids as $id_reponse) {
        // Journaliser pour débogage
        error_log("Analyse de la réponse ID: $id_reponse");
        
        // Appeler le script d'analyse pour chaque réponse
        $analyse_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/analyser_reponse.php?id_reponse_eleve=' . $id_reponse;
        
        // Options pour cURL - ajouter timeouts et suivre les redirections
        $ch = curl_init($analyse_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); 
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        
        $result = curl_exec($ch);
        
        if (curl_errno($ch)) {
            error_log("Erreur cURL lors de l'analyse: " . curl_error($ch));
        } else {
            error_log("Résultat de l'analyse: " . $result);
        }
        
        curl_close($ch);
        
        if ($result) {
            $resultData = json_decode($result, true);
            if (isset($resultData['erreurs_enregistrees'])) {
                $erreurs_analysees += $resultData['erreurs_enregistrees'];
            }
        }
    }

    echo json_encode([
        'success' => "Les réponses des élèves ont été enregistrées et analysées avec succès.",
        'nb_reponses' => count($reponses_ids),
        'nb_erreurs_analysees' => $erreurs_analysees
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur PDO dans enregistrer_reponse_eleve.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => "Erreur base de données: " . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Erreur dans enregistrer_reponse_eleve.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>