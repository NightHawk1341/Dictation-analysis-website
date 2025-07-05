/**
 * script_reference.js
 * Script pour la gestion de la page de création des dictées de référence
 */

$(document).ready(function() {
    console.log("Script de création des dictées chargé");
    
    // Variables globales
    let compteurMots = 1;
    let dicteeConfirmee = false;
    let motsConfirmes = [];
    
    // Initialiser l'interface
    $("#bloc_reference").hide();
    
    // Confirmer le nom de la dictée
    $("#confirmer_nom_dictee").on("click", function() {
        const nom = $("#nom_dictee").val().trim();
        const type = $("#type_dictee").val();
        
        if (!nom) {
            alert("Veuillez saisir un nom pour la dictée.");
            return;
        }
        
        // Vérifier si le nom contient des caractères spéciaux
        if (!/^[a-zA-Z0-9\u00C0-\u017Fàâäæáãåāèéêëęėēîïíīįìôöòóœøōõùûüūúÿçćčñń\s-]+$/.test(nom)) {
            const confirmer = confirm("Le nom de la dictée contient des caractères spéciaux. Cela pourrait poser des problèmes. Voulez-vous continuer ?");
            if (!confirmer) return;
        }
        
        // Désactiver les champs et le bouton
        $("#nom_dictee, #type_dictee").prop("disabled", true);
        $(this).prop("disabled", true);
        
        // Afficher le bloc de référence
        $("#bloc_reference").slideDown();
    });
    
    // Ajouter un nouveau mot
    $("#ajouter_mot").on("click", function() {
        if (dicteeConfirmee) return;
        
        compteurMots++;
        const nouvelleLigne = `
            <div class="ligne_mot">
                <label>Mot <span class="compteur_mot">${compteurMots}</span>
                    <input type="text" name="mot_reference[]" class="mot_reference">
                </label>
                <div class="info_mot_supplementaire" style="display: none;">
                    <label>Catégorie: <input type="text" name="categorie[]" placeholder="nom, verbe, etc."></label>
                    <label>Lemme: <input type="text" name="lemme[]" placeholder="forme canonique"></label>
                    <label>Radical: <input type="text" name="radical[]"></label>
                    <label>Désinence: <input type="text" name="desinence[]"></label>
                </div>
                <button type="button" class="afficher_plus">+</button>
                <button type="button" class="confirmer_mot">✓</button>
                <button type="button" class="supprimer_mot">✕</button>
            </div>
        `;
        
        $("#conteneur_mots_reference").append(nouvelleLigne);
    });
    
    // Afficher les informations supplémentaires du mot
    $(document).on("click", ".afficher_plus", function() {
        const infoSupp = $(this).siblings(".info_mot_supplementaire");
        infoSupp.slideToggle();
        
        // Changer le texte du bouton
        $(this).text(infoSupp.is(":visible") ? "-" : "+");
    });
    
    // Confirmer un mot
    $(document).on("click", ".confirmer_mot", function() {
        const ligne = $(this).closest(".ligne_mot");
        const mot = ligne.find(".mot_reference").val().trim();
        
        if (!mot) {
            alert("Veuillez saisir un mot avant de confirmer.");
            return;
        }
        
        // Récupérer les informations supplémentaires
        const categorie = ligne.find("input[name='categorie[]']").val() || "";
        const lemme = ligne.find("input[name='lemme[]']").val() || "";
        let radical = ligne.find("input[name='radical[]']").val() || "";
        let desinence = ligne.find("input[name='desinence[]']").val() || "";
        
        // Si radical ou désinence non renseignés, proposer une division automatique
        if (!radical || !desinence) {
            // Division automatique : 2/3 radical, 1/3 désinence
            const separationIndex = Math.ceil(mot.length * 2 / 3);
            
            if (!radical) {
                radical = mot.substring(0, separationIndex);
                ligne.find("input[name='radical[]']").val(radical);
            }
            
            if (!desinence) {
                desinence = mot.substring(separationIndex);
                ligne.find("input[name='desinence[]']").val(desinence);
            }
            
            // Afficher les informations supplémentaires pour montrer la décomposition
            ligne.find(".info_mot_supplementaire").slideDown();
            ligne.find(".afficher_plus").text("-");
        }
        
        // Stocker les données du mot
        motsConfirmes.push({
            mot: mot,
            categorie: categorie,
            lemme: lemme,
            radical: radical,
            desinence: desinence,
            index: ligne.find(".compteur_mot").text() - 1 // Pour garder l'ordre
        });
        
        // Trier les mots par index pour maintenir l'ordre
        motsConfirmes.sort((a, b) => a.index - b.index);
        
        // Marquer visuellement comme confirmé
        ligne.addClass("confirme");
        ligne.find("input").prop("disabled", true);
        $(this).prop("disabled", true);
        
        // Mettre à jour l'aperçu
        mettreAJourApercu();
    });
    
    // Supprimer un mot
    $(document).on("click", ".supprimer_mot", function() {
        if (dicteeConfirmee) return;
        
        const ligne = $(this).closest(".ligne_mot");
        const indexMot = ligne.find(".compteur_mot").text() - 1;
        
        // Si le mot était confirmé, le retirer de la liste
        if (ligne.hasClass("confirme")) {
            motsConfirmes = motsConfirmes.filter(mot => mot.index != indexMot);
            mettreAJourApercu();
        }
        
        // Supprimer la ligne
        ligne.remove();
        
        // Mettre à jour les numéros
        $(".compteur_mot").each(function(index) {
            $(this).text(index + 1);
        });
        
        // Mettre à jour le compteur global
        compteurMots = $(".ligne_mot").length;
    });
    
    // Confirmer la dictée complète
    $("#confirmer_dictee").on("click", function() {
        if (motsConfirmes.length === 0) {
            alert("Veuillez confirmer au moins un mot avant de soumettre la dictée.");
            return;
        }
        
        const nomDictee = $("#nom_dictee").val().trim();
        const typeDictee = $("#type_dictee").val();
        
        if (!nomDictee) {
            alert("Erreur: Nom de dictée manquant.");
            return;
        }
        
        // Vérification finale des mots
        let motsValides = true;
        motsConfirmes.forEach(function(mot, index) {
            if (!mot.radical || !mot.desinence) {
                alert(`Le mot "${mot.mot}" (n°${index+1}) n'a pas de radical ou de désinence définis.`);
                motsValides = false;
            }
        });
        
        if (!motsValides) return;
        
        // Désactiver le bouton pour éviter les soumissions multiples
        $(this).prop("disabled", true);
        dicteeConfirmee = true;
        
        // Préparer les données pour l'envoi
        const donneesEnvoi = {
            nom_dictee: nomDictee,
            type_dictee: typeDictee,
            contenu: "",
            mot_reference: [],
            categorie: [],
            lemme: [],
            radical: [],
            desinence: []
        };
        
        // Ajouter chaque mot confirmé
        motsConfirmes.forEach(function(mot) {
            donneesEnvoi.mot_reference.push(mot.mot);
            donneesEnvoi.categorie.push(mot.categorie);
            donneesEnvoi.lemme.push(mot.lemme);
            donneesEnvoi.radical.push(mot.radical);
            donneesEnvoi.desinence.push(mot.desinence);
        });
        
        // Afficher un message de chargement
        $("#liste_mots_apercu").html("<p>Enregistrement en cours...</p>");
        
        // Envoyer au serveur
        $.ajax({
            url: "enregistrer_dictee.php",
            type: "POST",
            data: donneesEnvoi,
            success: function(response) {
                alert("Dictée enregistrée avec succès!");
                
                // Rediriger vers la page de saisie
                window.location.href = "page_saisie.html";
            },
            error: function(xhr, status, error) {
                alert("Erreur lors de l'enregistrement: " + (xhr.responseText || error));
                
                // Réactiver le bouton
                $("#confirmer_dictee").prop("disabled", false);
                dicteeConfirmee = false;
            }
        });
    });
    
    // Réinitialiser le formulaire
    $("#reinitialiser").on("click", function() {
        if (confirm("Êtes-vous sûr de vouloir tout réinitialiser ? Tous les mots saisis seront perdus.")) {
            location.reload();
        }
    });
    
    // À propos
    $("#a_propos").on("click", function(e) {
        e.preventDefault();
        alert("Application d'analyse de dictées\nDéveloppée pour le Master 1 IdL - 2024-2025");
    });
    
    /**
     * Met à jour l'aperçu de la dictée
     */
    function mettreAJourApercu() {
        const conteneur = $("#liste_mots_apercu");
        
        if (motsConfirmes.length === 0) {
            conteneur.html("<p>Aucun mot confirmé</p>");
            return;
        }
        
        let html = "";
        
        motsConfirmes.forEach(function(mot, index) {
            html += `
                <div class="mot_apercu">
                    <strong>${index + 1}. ${mot.mot}</strong>
                    ${mot.categorie ? `<br>Catégorie: ${mot.categorie}` : ""}
                    ${mot.lemme ? `<br>Lemme: ${mot.lemme}` : ""}
                    <br>Radical: <span class="radical">${mot.radical}</span>
                    <br>Désinence: <span class="desinence">${mot.desinence}</span>
                </div>
            `;
            
            if (index < motsConfirmes.length - 1) {
                html += "<hr>";
            }
        });
        
        conteneur.html(html);
    }
});