/**
 * script_saisie_eleve.js
 * Script pour la gestion de la page de saisie des réponses d'élèves
 */

$(document).ready(function() {
    console.log("Script de saisie des réponses d'élèves chargé");
    
    // Variables globales
    let dicteeSelectionnee = null;
    let elevesConfirmes = [];
    
    // Initialisation
    chargerDictees();
    
    // Événement de changement de dictée
    $("#selection_dictee").on("change", function() {
        const idDictee = $(this).val();
        
        if (idDictee) {
            // Récupérer les détails de la dictée
            $.ajax({
                url: 'get_dictee_details.php',
                type: 'GET',
                data: { id_dictee: idDictee },
                dataType: 'json',
                success: function(details) {
                    dicteeSelectionnee = details;
                    
                    // Afficher les informations de la dictée
                    let infoHTML = `<div class="info_dictee_box">`;
                    infoHTML += `<h4>${details.nom_dictee}</h4>`;
                    infoHTML += `<p>Type: ${details.type_dictee}</p>`;
                    
                    if (details.type_dictee === 'mots') {
                        infoHTML += `<p class="instructions">Instructions: Saisissez les mots séparés par des point-virgules (;)</p>`;
                        // Afficher les mots attendus si disponibles
                        if (details.mots && details.mots.length > 0) {
                            infoHTML += `<p>Nombre de mots attendus: ${details.mots.length}</p>`;
                        }
                    }
                    
                    infoHTML += `</div>`;
                    
                    $("#info_dictee").html(infoHTML);
                },
                error: function(xhr, status, error) {
                    console.error("Erreur lors de la récupération des détails de la dictée:", error);
                    $("#info_dictee").html(`<p class="erreur">Erreur lors de la récupération des détails de la dictée.</p>`);
                }
            });
        } else {
            // Réinitialiser les informations si aucune dictée n'est sélectionnée
            dicteeSelectionnee = null;
            $("#info_dictee").empty();
        }
    });
    
    // Ajouter un élève
    $("#ajouter_eleve").on("click", function() {
        if (!dicteeSelectionnee) {
            alert("Veuillez d'abord sélectionner une dictée.");
            return;
        }
        
        // Cloner le bloc élève modèle (premier bloc)
        const blocModel = $(".bloc_eleve").first().clone();
        
        // Réinitialiser les champs du bloc cloné
        blocModel.find("input, textarea").val("");
        blocModel.removeClass("confirme");
        blocModel.find(".confirmer_eleve").prop("disabled", false);
        
        // Ajouter le bloc cloné au conteneur
        $("#conteneur_eleves").append(blocModel);
    });
    
    // Confirmer un élève (délégation d'événement pour les éléments dynamiques)
    $(document).on("click", ".confirmer_eleve", function() {
        const blocEleve = $(this).closest(".bloc_eleve");
        
        // Récupérer et valider les données
        const nom = blocEleve.find(".nom_eleve").val().trim();
        const prenom = blocEleve.find(".prenom_eleve").val().trim();
        const niveau = blocEleve.find(".niveau_eleve").val();
        let reponse = blocEleve.find(".reponse_eleve").val().trim();
        
        if (!nom || !prenom || !niveau || !reponse) {
            alert("Veuillez remplir tous les champs pour cet élève.");
            return;
        }
        
        if (!dicteeSelectionnee) {
            alert("Veuillez d'abord sélectionner une dictée.");
            return;
        }
        
        // Pour les dictées de mots, formater correctement la saisie
        if (dicteeSelectionnee.type_dictee === 'mots') {
            // Normaliser les séparateurs (accepter virgules ou points-virgules)
            reponse = reponse.replace(/,/g, ';');
            
            // Nettoyer les espaces autour des séparateurs
            reponse = reponse.split(';').map(mot => mot.trim()).join(';');
            
            // Mettre à jour la valeur dans le champ
            blocEleve.find(".reponse_eleve").val(reponse);
        }
        
        // Stocker les données de l'élève
        const donneesEleve = {
            nom_eleve: nom,
            prenom_eleve: prenom,
            niveau_eleve: niveau,
            id_dictee: dicteeSelectionnee.id_dictee,
            saisie_eleve: reponse
        };
        
        // Vérification des données pour debug
        console.log("Données élève à confirmer:", donneesEleve);
        
        // S'assurer que tous les champs requis sont définis et non vides
        if (!donneesEleve.nom_eleve || !donneesEleve.prenom_eleve || 
            !donneesEleve.niveau_eleve || !donneesEleve.id_dictee || 
            !donneesEleve.saisie_eleve) {
            console.error("Données incomplètes pour l'élève:", donneesEleve);
            alert("Données incomplètes. Veuillez vérifier tous les champs.");
            return;
        }
        
        // Ajouter aux élèves confirmés ou mettre à jour si déjà existant
        const indexExistant = elevesConfirmes.findIndex(e => 
            e.nom_eleve === nom && e.prenom_eleve === prenom
        );
        
        if (indexExistant >= 0) {
            elevesConfirmes[indexExistant] = donneesEleve;
        } else {
            elevesConfirmes.push(donneesEleve);
        }
        
        // Marquer le bloc comme confirmé
        blocEleve.addClass("confirme");
        blocEleve.find("input, textarea, select").prop("disabled", true);
        $(this).prop("disabled", true);
        
        // Afficher un message
        afficherMessage("Élève confirmé. Vous pouvez ajouter d'autres élèves ou valider toutes les saisies.", "succes");
    });
    
    // Supprimer un élève
    $(document).on("click", ".supprimer_eleve", function() {
        const blocEleve = $(this).closest(".bloc_eleve");
        
        // Récupérer les informations de l'élève avant manipulation
        const nom = blocEleve.find(".nom_eleve").val().trim();
        const prenom = blocEleve.find(".prenom_eleve").val().trim();
        
        // Ne pas supprimer s'il n'y a qu'un seul bloc
        if ($("#conteneur_eleves .bloc_eleve").length <= 1) {
            // Plutôt vider les champs
            blocEleve.find("input, textarea, select").val("").prop("disabled", false);
            blocEleve.removeClass("confirme");
            blocEleve.find(".confirmer_eleve").prop("disabled", false);
            
            // Supprimer des élèves confirmés si le bloc était confirmé
            if (blocEleve.hasClass("confirme")) {
                elevesConfirmes = elevesConfirmes.filter(e => 
                    !(e.nom_eleve === nom && e.prenom_eleve === prenom)
                );
            }
            
            return;
        }
        
        // Sinon, supprimer le bloc
        // Supprimer des élèves confirmés
        elevesConfirmes = elevesConfirmes.filter(e => 
            !(e.nom_eleve === nom && e.prenom_eleve === prenom)
        );
        
        // Supprimer le bloc du DOM
        blocEleve.remove();
    });
    
    // Validation finale
    $("#valider_saisies").on("click", function() {
        if (elevesConfirmes.length === 0) {
            alert("Veuillez confirmer au moins un élève avant de valider.");
            return;
        }
        
        // Vérification des données avant envoi
        console.log("Données à envoyer:", elevesConfirmes);
        
        // Vérification supplémentaire des données
        let donneeValides = true;
        elevesConfirmes.forEach(function(eleve, index) {
            if (!eleve.nom_eleve || !eleve.prenom_eleve || 
                !eleve.niveau_eleve || !eleve.id_dictee || 
                !eleve.saisie_eleve) {
                console.error("Données incomplètes pour l'élève à l'index", index, ":", eleve);
                donneeValides = false;
            }
        });
        
        if (!donneeValides) {
            alert("Certaines données d'élèves sont incomplètes. Veuillez vérifier tous les champs.");
            return;
        }
        
        // Désactiver le bouton pour éviter les doubles soumissions
        $(this).prop("disabled", true);
        afficherMessage("Envoi en cours...", "info");
        
        // Préparer les données pour l'envoi
        const donneesEnvoi = {
            eleves: JSON.stringify(elevesConfirmes)
        };
        
        // Envoyer les données au serveur
        $.ajax({
            url: 'enregistrer_reponse_eleve.php',
            type: 'POST',
            data: donneesEnvoi,
            dataType: 'json',
            success: function(reponse) {
                // Réactiver le bouton
                $("#valider_saisies").prop("disabled", false);
                
                if (reponse.success) {
                    let message = reponse.success;
                    if (reponse.nb_erreurs_analysees !== undefined) {
                        message += " " + reponse.nb_erreurs_analysees + " erreurs ont été analysées.";
                    }
                    afficherMessage(message, "succes");
                    
                    // Réinitialiser le formulaire
                    resetForm();
                } else {
                    afficherMessage("Erreur: Les données ont été envoyées mais une erreur est survenue.", "erreur");
                }
            },
            error: function(xhr, status, error) {
                // Réactiver le bouton
                $("#valider_saisies").prop("disabled", false);
                
                // Traiter l'erreur
                let messageErreur = "Une erreur est survenue lors de l'envoi des données.";
                
                try {
                    const reponseErreur = JSON.parse(xhr.responseText);
                    if (reponseErreur.error) {
                        messageErreur = reponseErreur.error;
                    }
                } catch (e) {
                    console.error("Erreur lors de l'analyse de la réponse d'erreur:", e);
                }
                
                afficherMessage(messageErreur, "erreur");
            }
        });
    });
    
    // À propos
    $("#a_propos").on("click", function(e) {
        e.preventDefault();
        alert("Application d'analyse de dictées\nDéveloppée pour le Master 1 IdL - 2024-2025");
    });
    
    // Fonctions utilitaires
    
    /**
     * Charge la liste des dictées depuis le serveur
     */
    function chargerDictees() {
        // Récupérer l'élément select
        const selectDictee = $("#selection_dictee");
        
        // Afficher un message de chargement
        selectDictee.empty().append('<option value="">Chargement des dictées...</option>');
        
        // Requête AJAX pour récupérer les dictées
        $.ajax({
            url: 'lister_dictees.php',
            type: 'GET',
            dataType: 'json',
            success: function(dictees) {
                // Vider et réinitialiser la liste
                selectDictee.empty().append('<option value="">Choisir une dictée</option>');
                
                // Ajouter les dictées à la liste
                dictees.forEach(function(dictee) {
                    selectDictee.append(`<option value="${dictee.id_dictee}">${dictee.nom_dictee} (${dictee.type_dictee})</option>`);
                });
            },
            error: function(xhr, status, error) {
                console.error("Erreur lors du chargement des dictées:", error);
                selectDictee.empty().append('<option value="">Erreur de chargement</option>');
                afficherMessage("Impossible de charger la liste des dictées.", "erreur");
            }
        });
    }
    
    /**
     * Affiche un message dans la zone de message
     * @param {string} message - Le message à afficher
     * @param {string} type - Le type de message (info, succes, erreur)
     */
    function afficherMessage(message, type) {
        // Créer la classe CSS en fonction du type
        let classe = "";
        switch (type) {
            case "succes":
                classe = "message_succes";
                break;
            case "erreur":
                classe = "message_erreur";
                break;
            case "info":
            default:
                classe = "message_info";
        }
        
        // Afficher le message
        $("#message_zone").removeClass("message_succes message_erreur message_info")
                          .addClass(classe)
                          .text(message)
                          .show();
        
        // Faire défiler jusqu'au message
        $('html, body').animate({
            scrollTop: $("#message_zone").offset().top
        }, 500);
    }
    
    /**
     * Réinitialise le formulaire
     */
    function resetForm() {
        // Réinitialiser la sélection de dictée
        $("#selection_dictee").val("");
        $("#info_dictee").empty();
        dicteeSelectionnee = null;
        
        // Réinitialiser les élèves
        elevesConfirmes = [];
        
        // Ne garder que le premier bloc élève et le vider
        const premierBloc = $(".bloc_eleve").first();
        $("#conteneur_eleves .bloc_eleve:not(:first)").remove();
        
        premierBloc.find("input, textarea, select").val("").prop("disabled", false);
        premierBloc.removeClass("confirme");
        premierBloc.find(".confirmer_eleve").prop("disabled", false);
    }
});