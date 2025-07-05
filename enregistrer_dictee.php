<?php

try {
    // 1. Connexion à la base de données avec PDO
    $pdo = new PDO("mysql:host=localhost;dbname=duzhenko;charset=utf8", 'duzhenko', 'nikita!');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Récupération des données POST
    $nom_dictee = $_POST['nom_dictee'] ?? '';
    $type_dictee = $_POST['type_dictee'] ?? '';
    $contenu_dictee = $_POST['contenu_dictee'] ?? '';
    $mots = $_POST['mot_reference'] ?? [];
    $categories = $_POST['categorie'] ?? [];
    $lemmes = $_POST['lemme'] ?? [];
    $radicaux = $_POST['radical'] ?? [];
    $desinences = $_POST['desinence'] ?? [];

    // 3. Validation
    if (empty($nom_dictee) || empty($type_dictee)) {
        http_response_code(400);
        echo "Nom de dictée ou type de dictée manquant";
        exit;
    }

    // Vérifier que type_dictee est valide
    if (!in_array($type_dictee, ['mots', 'phrases', 'texte'])) {
        http_response_code(400);
        echo "Type de dictée invalide";
        exit;
    }

    // Validation spécifique selon le type de dictée
    if ($type_dictee === 'mots' && empty($mots)) {
        http_response_code(400);
        echo "Liste de mots requise pour une dictée de type 'mots'";
        exit;
    }

    if (($type_dictee === 'phrases' || $type_dictee === 'texte') && empty($contenu_dictee)) {
        http_response_code(400);
        echo "Contenu requis pour une dictée de type 'phrases' ou 'texte'";
        exit;
    }

    // 4. Préparation du contenu à insérer
    $contenu_val = ($type_dictee === 'mots') ? null : $contenu_dictee;

    // 5. Insertion de la dictée
    $stmt_dictee = $pdo->prepare("INSERT INTO dictees (nom_dictee, type_dictee, contenu) VALUES (?, ?, ?)");
    $stmt_dictee->execute([$nom_dictee, $type_dictee, $contenu_val]);
    $id_dictee = $pdo->lastInsertId();

    // 6. Traitement des mots de référence (si fournis)
    if (is_array($mots) && !empty($mots)) {
        // Préparation des requêtes
        $stmt_ref = $pdo->prepare("
            INSERT INTO mots_ref (mot, categorie, lemme, radical, desinence)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt_link = $pdo->prepare("
            INSERT INTO dictee_mots (id_dictee, id_mot)
            VALUES (?, ?)
        ");

        // Insertion des mots et des liens
        for ($i = 0; $i < count($mots); $i++) {
            $mot       = $mots[$i];
            $categorie = $categories[$i] ?? null;
            $lemme     = $lemmes[$i] ?? null;
            $radical   = $radicaux[$i] ?? null;
            $desinence = $desinences[$i] ?? null;

            // Insère le mot
            $stmt_ref->execute([$mot, $categorie, $lemme, $radical, $desinence]);
            $id_mot = $pdo->lastInsertId();

            // Lien vers la dictée
            $stmt_link->execute([$id_dictee, $id_mot]);
        }
    }

    echo "Succès : dictée " . ($type_dictee === 'mots' ? "et mots liés " : "") . "enregistrés.";

} catch (PDOException $e) {
    http_response_code(500);
    echo "Erreur : " . $e->getMessage();
}
?>
