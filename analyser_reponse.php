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

    // Récupérer et valider l'entrée
    $id_reponse_eleve = isset($_GET['id_reponse_eleve']) ? (int)$_GET['id_reponse_eleve'] : 0;
    
    if ($id_reponse_eleve <= 0) {
        http_response_code(400);
        echo json_encode(["error" => "ID de réponse élève invalide ou manquant"]);
        exit;
    }

    // Journaliser pour débogage
    error_log("Analyse de la réponse ID: $id_reponse_eleve");

    // Récupérer la réponse de l'élève
    $stmt_reponse = $pdo->prepare("SELECT id_dictee, id_eleve, saisie FROM reponses_eleves WHERE id_reponse = ?");
    $stmt_reponse->execute([$id_reponse_eleve]);
    $reponse_data = $stmt_reponse->fetch(PDO::FETCH_ASSOC);
    
    if (!$reponse_data) {
        http_response_code(404);
        echo json_encode(["error" => "Réponse élève non trouvée"]);
        exit;
    }
    
    $id_dictee = $reponse_data['id_dictee'];
    $id_eleve = $reponse_data['id_eleve'];
    $saisie_eleve = $reponse_data['saisie'];

    // Journaliser pour débogage
    error_log("ID dictée: $id_dictee, ID élève: $id_eleve, Saisie: $saisie_eleve");

    // Récupérer les informations de l'élève pour le log
    $stmt_eleve = $pdo->prepare("SELECT nom, prenom FROM eleves WHERE id_eleve = ?");
    $stmt_eleve->execute([$id_eleve]);
    $eleve_data = $stmt_eleve->fetch(PDO::FETCH_ASSOC);
    if ($eleve_data) {
        error_log("Élève: " . $eleve_data['prenom'] . " " . $eleve_data['nom']);
    }

    // Récupérer le type de dictée
    $stmt_dictee = $pdo->prepare("SELECT nom_dictee, type_dictee FROM dictees WHERE id_dictee = ?");
    $stmt_dictee->execute([$id_dictee]);
    $dictee_data = $stmt_dictee->fetch(PDO::FETCH_ASSOC);
    
    if (!$dictee_data) {
        http_response_code(404);
        echo json_encode(["error" => "Dictée de référence non trouvée"]);
        exit;
    }
    
    $type_dictee = $dictee_data['type_dictee'];
    $nom_dictee = $dictee_data['nom_dictee'];
    
    error_log("Dictée: $nom_dictee, Type: $type_dictee");
    
    // Vérifier si le type de dictée est 'mots'
    if ($type_dictee !== 'mots') {
        http_response_code(501);
        echo json_encode(["message" => "L'analyse pour ce type de dictée n'est pas encore implémentée."]);
        exit;
    }
    
    // Récupérer les mots de référence (incluant radical et désinence)
    $stmt_ref_words = $pdo->prepare("
        SELECT mr.id_mot, mr.mot, mr.radical, mr.desinence 
        FROM mots_ref mr
        JOIN dictee_mots dm ON mr.id_mot = dm.id_mot
        WHERE dm.id_dictee = ?
        ORDER BY dm.id_dict_mots ASC
    ");
    $stmt_ref_words->execute([$id_dictee]);
    $reference_words_db = $stmt_ref_words->fetchAll(PDO::FETCH_ASSOC);
    
    // Journaliser pour débogage
    error_log("Nombre de mots de référence: " . count($reference_words_db));
    error_log("Mots de référence: " . json_encode($reference_words_db));
    
    // Traiter les mots de l'élève - accepter à la fois ',' et ';' comme séparateurs
    $saisie_eleve = str_replace(',', ';', $saisie_eleve); // Normaliser les séparateurs
    $student_words_original = preg_split('/\s*;\s*/', trim($saisie_eleve), -1, PREG_SPLIT_NO_EMPTY);
    $student_words_processed = [];
    
    foreach ($student_words_original as $word) {
        $student_words_processed[] = mb_strtolower(trim($word, ".,;:!?()[]{}'\""), 'UTF-8');
    }
    
    // Journaliser pour débogage
    error_log("Mots de l'élève (original): " . json_encode($student_words_original));
    error_log("Mots de l'élève (traités): " . json_encode($student_words_processed));
    
    // Comparer les mots et enregistrer les erreurs
    $errors_to_log = [];
    $max_words = max(count($student_words_processed), count($reference_words_db));
    
    for ($i = 0; $i < $max_words; $i++) {
        $current_student_word_processed = isset($student_words_processed[$i]) ? $student_words_processed[$i] : null;
        $current_student_word_original = isset($student_words_original[$i]) ? $student_words_original[$i] : null;
        $current_ref_word_data = isset($reference_words_db[$i]) ? $reference_words_db[$i] : null;
        
        if ($current_ref_word_data && $current_student_word_processed) {
            // Les deux mots (référence et élève) existent - vérifier l'exactitude
            $processed_ref_mot_full = mb_strtolower(trim($current_ref_word_data['mot'], ".,;:!?()[]{}'\""), 'UTF-8');
            
            error_log("Comparaison: '{$current_student_word_processed}' vs '{$processed_ref_mot_full}'");
            
            if ($current_student_word_processed !== $processed_ref_mot_full) {
                // Le mot est incorrect - analyser le radical/désinence
                $ref_radical = mb_strtolower($current_ref_word_data['radical'] ?? '', 'UTF-8');
                $ref_desinence = mb_strtolower($current_ref_word_data['desinence'] ?? '', 'UTF-8');
                
                $error_in_radical = false;
                $error_in_desinence = false;
                $type_erreur_detail = '';

                // Heuristique pour diviser le mot de l'élève basée sur les longueurs du radical/désinence de référence
                $student_radical_part = '';
                $student_desinence_part = '';

                if (!empty($ref_radical)) {
                    $radical_length = mb_strlen($ref_radical);
                    
                    // S'assurer que le mot de l'élève est au moins aussi long que la partie du radical à extraire
                    if (mb_strlen($current_student_word_processed) >= $radical_length) {
                        $student_radical_part = mb_substr($current_student_word_processed, 0, $radical_length);
                    } else {
                        $student_radical_part = $current_student_word_processed;
                    }
                    
                    if ($student_radical_part !== $ref_radical) {
                        $error_in_radical = true;
                    }
                }
                
                if (!empty($ref_desinence)) {
                    // Obtenir la partie désinence du mot de l'élève
                    if (!empty($ref_radical) && mb_strlen($current_student_word_processed) > mb_strlen($ref_radical)) {
                        $student_desinence_part = mb_substr($current_student_word_processed, mb_strlen($ref_radical));
                    } else if (empty($ref_radical) && !empty($current_student_word_processed)) { 
                        // Si le radical de référence est vide, considérer tout le mot comme désinence
                        $student_desinence_part = $current_student_word_processed;
                    } else {
                        // Si le mot de l'élève est trop court, il n'y a pas de désinence
                        $student_desinence_part = '';
                    }

                    if ($student_desinence_part !== $ref_desinence) {
                        $error_in_desinence = true;
                    }
                } else if (empty($ref_desinence) && mb_strlen($current_student_word_processed) > mb_strlen($ref_radical)) {
                    // La référence n'a pas de désinence, mais le mot de l'élève a des caractères supplémentaires
                    $error_in_desinence = true;
                }

                error_log("Analyse radical: '{$student_radical_part}' vs '{$ref_radical}', Erreur: " . ($error_in_radical ? 'Oui' : 'Non'));
                error_log("Analyse désinence: '{$student_desinence_part}' vs '{$ref_desinence}', Erreur: " . ($error_in_desinence ? 'Oui' : 'Non'));

                if ($error_in_radical && $error_in_desinence) {
                    $type_erreur_detail = 'orthographique_radical_et_desinence';
                    
                    // Incrémenter le compteur pour ce type d'erreur dans mots_ref
                    $stmt_update = $pdo->prepare("UPDATE mots_ref SET nbErrRadEtDes = nbErrRadEtDes + 1 WHERE id_mot = ?");
                    $stmt_update->execute([$current_ref_word_data['id_mot']]);
                } elseif ($error_in_radical) {
                    $type_erreur_detail = 'orthographique_radical';
                    
                    // Incrémenter le compteur pour ce type d'erreur dans mots_ref
                    $stmt_update = $pdo->prepare("UPDATE mots_ref SET nbErrRad = nbErrRad + 1 WHERE id_mot = ?");
                    $stmt_update->execute([$current_ref_word_data['id_mot']]);
                } elseif ($error_in_desinence) {
                    $type_erreur_detail = 'orthographique_desinence';
                    
                    // Incrémenter le compteur pour ce type d'erreur dans mots_ref
                    $stmt_update = $pdo->prepare("UPDATE mots_ref SET nbErrDes = nbErrDes + 1 WHERE id_mot = ?");
                    $stmt_update->execute([$current_ref_word_data['id_mot']]);
                } else {
                    // Si aucune erreur spécifique n'est trouvée malgré une non-correspondance du mot complet
                    $type_erreur_detail = 'orthographique_mot_incorrect_complexe';
                }
                
                $errors_to_log[] = [
                    'id_reponse_eleve' => $id_reponse_eleve,
                    'id_mot_ref' => $current_ref_word_data['id_mot'],
                    'mot_saisi' => $current_student_word_original,
                    'mot_attendu' => $current_ref_word_data['mot'],
                    'type_erreur' => $type_erreur_detail
                ];
            }
        } elseif ($current_ref_word_data && !$current_student_word_processed) {
            // Le mot de référence existe mais pas le mot de l'élève - omission
            $errors_to_log[] = [
                'id_reponse_eleve' => $id_reponse_eleve,
                'id_mot_ref' => $current_ref_word_data['id_mot'],
                'mot_saisi' => null,
                'mot_attendu' => $current_ref_word_data['mot'],
                'type_erreur' => 'lexical_omission'
            ];
        } elseif (!$current_ref_word_data && $current_student_word_processed) {
            // Le mot de l'élève existe mais pas le mot de référence - ajout
            $errors_to_log[] = [
                'id_reponse_eleve' => $id_reponse_eleve,
                'id_mot_ref' => null,
                'mot_saisi' => $current_student_word_original,
                'mot_attendu' => null,
                'type_erreur' => 'lexical_ajout'
            ];
        }
    }
    
    // Journaliser pour débogage
    error_log("Nombre d'erreurs détectées: " . count($errors_to_log));
    error_log("Erreurs: " . json_encode($errors_to_log));
    
    // Insertion par lots des erreurs
    $error_count = 0;
    
    if (!empty($errors_to_log)) {
        // Supprimer d'abord les erreurs existantes pour cette réponse d'élève
        $stmt_delete = $pdo->prepare("DELETE FROM erreurs_eleves WHERE id_reponse_eleve = ?");
        $stmt_delete->execute([$id_reponse_eleve]);
        
        // Vérifier la structure de la table erreurs_eleves
        $stmt_columns = $pdo->query("SHOW COLUMNS FROM erreurs_eleves");
        $columns = array_map(function($row) { return $row['Field']; }, $stmt_columns->fetchAll(PDO::FETCH_ASSOC));
        
        // Adapter la requête d'insertion en fonction des colonnes disponibles
        $insert_columns = ['id_reponse_eleve', 'id_mot_ref', 'mot_saisi', 'mot_attendu', 'type_erreur'];
        $insert_placeholders = ['?', '?', '?', '?', '?'];
        
        $stmt_insert_error = $pdo->prepare("
            INSERT INTO erreurs_eleves 
            (" . implode(', ', $insert_columns) . ") 
            VALUES (" . implode(', ', $insert_placeholders) . ")
        ");
        
        foreach ($errors_to_log as $error) {
            $params = [
                $error['id_reponse_eleve'],
                $error['id_mot_ref'],
                $error['mot_saisi'],
                $error['mot_attendu'],
                $error['type_erreur']
            ];
            
            $stmt_insert_error->execute($params);
            $error_count++;
        }
    }
    
    // Retourner une réponse de succès
    http_response_code(200);
    echo json_encode([
        "success" => "Analyse terminée.",
        "erreurs_enregistrees" => $error_count,
        "id_eleve" => $id_eleve,
        "id_dictee" => $id_dictee,
        "details" => [
            "mots_reference" => count($reference_words_db),
            "mots_eleve" => count($student_words_processed)
        ]
    ]);
    
} catch (PDOException $e) {
    // Erreur de base de données
    error_log("Erreur SQL dans analyser_reponse.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Erreur de base de données lors de l'analyse: " . $e->getMessage()]);
} catch (Exception $e) {
    // Autres erreurs
    error_log("Erreur dans analyser_reponse.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Erreur lors de l'analyse: " . $e->getMessage()]);
}
?>