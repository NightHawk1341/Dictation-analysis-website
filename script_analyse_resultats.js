/**
 * script_analyse_resultats.js
 * Script pour la gestion de la page d'analyse des résultats
 */

$(document).ready(function() {
    console.log("Script d'analyse des résultats chargé");
    
    // ===== Initialisation =====
    chargerFiltreDictees();
    
    // ===== Gestion des onglets =====
    $(".onglet").on("click", function() {
        // Retirer la classe active de tous les onglets
        $(".onglet").removeClass("active");
        
        // Ajouter la classe active à l'onglet cliqué
        $(this).addClass("active");
        
        // Masquer tous les contenus d'onglets
        $(".contenu_onglet").hide();
        
        // Afficher le contenu correspondant à l'onglet cliqué
        const cible = $(this).data("target");
        $("#" + cible).show();
    });
    
    // ===== Gestion des filtres =====
    $("#bouton_appliquer_filtres").on("click", function() {
        console.log("Application des filtres");
        
        // Récupérer l'onglet actif
        const ongletActif = $(".onglet.active").data("target");
        
        // Charger les données correspondantes
        if (ongletActif === "resultats_eleves") {
            chargerResultatsEleves();
        } else if (ongletActif === "statistiques_globales") {
            chargerStatistiquesGlobales();
        }
    });
    
    // ===== Export CSV =====
    $("#bouton_exporter_csv").on("click", function() {
        console.log("Exportation CSV");
        
        // Récupérer l'onglet actif
        const ongletActif = $(".onglet.active").data("target");
        
        // Exporter les données correspondantes
        if (ongletActif === "resultats_eleves") {
            exporterCSV("resultats_eleves");
        } else if (ongletActif === "statistiques_globales") {
            exporterCSV("statistiques_globales");
        }
    });
    
    // ===== À propos =====
    $("#a_propos").on("click", function(e) {
        e.preventDefault();
        alert("Application d'analyse de dictées\nDéveloppée pour le Master 1 IdL - 2024-2025");
    });
    
    // ===== Fonctions =====
    
    /* Charge la liste des dictées pour le filtre */
    function chargerFiltreDictees() {
        console.log("Chargement des dictées pour le filtre");
        
        const selectDictee = $("#filtre_dictee");
        
        // Afficher un message de chargement
        selectDictee.empty().append('<option value="">Chargement...</option>');
        
        // Requête AJAX pour récupérer les dictées
        $.ajax({
            url: 'lister_dictees.php',
            type: 'GET',
            dataType: 'json',
            success: function(dictees) {
                // Vider et réinitialiser la liste
                selectDictee.empty().append('<option value="">Toutes les dictées</option>');
                
                // Ajouter les dictées à la liste
                dictees.forEach(function(dictee) {
                    selectDictee.append(`<option value="${dictee.id_dictee}">${dictee.nom_dictee}</option>`);
                });
            },
            error: function(xhr, status, error) {
                console.error("Erreur lors du chargement des dictées:", error);
                selectDictee.empty().append('<option value="">Erreur de chargement</option>');
                
                // Afficher un message d'erreur
                alert("Erreur lors du chargement des dictées: " + error);
            }
        });
    }
    
    /* Charge les résultats des élèves selon les filtres */
    function chargerResultatsEleves() {
        console.log("Chargement des résultats des élèves");
        
        // Récupérer les valeurs des filtres
        const id_dictee = $("#filtre_dictee").val();
        const niveau = $("#filtre_niveau").val();
        
        // Afficher un message de chargement
        $("#tbody_resultats").empty();
        $("#message_resultats").text("Chargement des résultats...").show();
        
        // Préparation des données pour la requête
        let donneesRequete = {};
        
        // Ajouter uniquement les filtres non vides
        if (id_dictee) {
            donneesRequete.id_dictee = id_dictee;
        }
        
        if (niveau && niveau !== "") {
            donneesRequete.niveau_eleve = niveau;
        }
        
        // Afficher les données de la requête pour le débogage
        console.log("Données de la requête:", donneesRequete);
        
        // Requête AJAX pour récupérer les résultats
        $.ajax({
            url: 'recuperer_resultats_eleves.php',
            type: 'GET',
            data: donneesRequete,
            dataType: 'json',
            success: function(resultats) {
                console.log("Résultats reçus:", resultats);
                
                $("#tbody_resultats").empty();
                
                // Vérifier s'il y a des résultats
                if (resultats.length === 0) {
                    $("#message_resultats").text("Aucun résultat ne correspond aux filtres sélectionnés.").show();
                    return;
                }
                
                // Masquer le message
                $("#message_resultats").hide();
                
                // Afficher les résultats
                resultats.forEach(function(resultat) {
                    const ligne = `
                        <tr>
                            <td>${resultat.nom_complet_eleve || resultat.prenom + ' ' + resultat.nom}</td>
                            <td>${resultat.niveau || '—'}</td>
                            <td>${resultat.nom_dictee || '—'}</td>
                            <td>${resultat.nb_err_radical || 0}</td>
                            <td>${resultat.nb_err_desinence || 0}</td>
                            <td>${resultat.nb_err_rad_des || 0}</td>
                            <td>${resultat.nb_err_orth_complexe || 0}</td>
                            <td>${resultat.total_erreurs || 0}</td>
                        </tr>
                    `;
                    
                    $("#tbody_resultats").append(ligne);
                });
            },
            error: function(xhr, status, error) {
                console.error("Erreur lors du chargement des résultats:", error);
                console.error("Statut de la réponse:", xhr.status);
                console.error("Texte de la réponse:", xhr.responseText);
                
                $("#message_resultats").text("Une erreur est survenue lors du chargement des résultats.").show();
                
                // Essayer d'afficher l'erreur détaillée si disponible
                try {
                    const reponseErreur = JSON.parse(xhr.responseText);
                    if (reponseErreur.error) {
                        console.error("Détail de l'erreur:", reponseErreur.error);
                        $("#message_resultats").text("Erreur: " + reponseErreur.error).show();
                    }
                } catch (e) {
                    console.error("Impossible de parser la réponse JSON:", e);
                }
            }
        });
    }
    
    /* Charge les statistiques globales selon les filtres */
    function chargerStatistiquesGlobales() {
        console.log("Chargement des statistiques globales");
        
        // Récupérer les valeurs des filtres
        const id_dictee = $("#filtre_dictee").val();
        const niveau = $("#filtre_niveau").val();
        
        // Vérifier si une dictée est sélectionnée
        if (!id_dictee) {
            $("#message_statistiques").text("Veuillez sélectionner une dictée pour afficher les statistiques globales.").show();
            $("#tbody_statistiques").empty();
            return;
        }
        
        // Afficher un message de chargement
        $("#tbody_statistiques").empty();
        $("#message_statistiques").text("Chargement des statistiques...").show();
        
        // Préparation des données pour la requête
        let donneesRequete = {
            id_dictee: id_dictee
        };
        
        // Ajouter le niveau si fourni
        if (niveau && niveau !== "") {
            donneesRequete.niveau_eleve = niveau;
        }
        
        // Afficher les données de la requête pour le débogage
        console.log("Données de la requête:", donneesRequete);
        
        // Requête AJAX pour récupérer les statistiques
        $.ajax({
            url: 'recuperer_statistiques_globales.php',
            type: 'GET',
            data: donneesRequete,
            dataType: 'json',
            success: function(statistiques) {
                console.log("Statistiques reçues:", statistiques);
                
                $("#tbody_statistiques").empty();
                
                // Vérifier s'il y a des statistiques
                if (statistiques.length === 0) {
                    $("#message_statistiques").text("Aucune statistique ne correspond aux filtres sélectionnés.").show();
                    return;
                }
                
                // Masquer le message
                $("#message_statistiques").hide();
                
                // Afficher les statistiques
                statistiques.forEach(function(stat) {
                    const pourcentage = Math.min(parseFloat(stat.pourcentage_erreurs || 0), 100).toFixed(1);
                    
                    // Utiliser les compteurs stockés dans la table mots_ref quand disponibles
                    const errRad = stat.compteur_err_rad !== undefined ? stat.compteur_err_rad : (stat.nb_err_radical || 0);
                    const errDes = stat.compteur_err_des !== undefined ? stat.compteur_err_des : (stat.nb_err_desinence || 0);
                    const errRadDes = stat.compteur_err_rad_des !== undefined ? stat.compteur_err_rad_des : (stat.nb_err_rad_des || 0);
                    const totalErreurs = parseInt(errRad) + parseInt(errDes) + parseInt(errRadDes);
                    
                    const ligne = `
                        <tr>
                            <td>${stat.mot || '—'}</td>
                            <td>${stat.nom_dictee || '—'}</td>
                            <td>${errRad}</td>
                            <td>${errDes}</td>
                            <td>${errRadDes}</td>
                            <td>${totalErreurs}</td>
                            <td>${pourcentage}%</td>
                        </tr>
                    `;
                    
                    $("#tbody_statistiques").append(ligne);
                });
            },
            error: function(xhr, status, error) {
                console.error("Erreur lors du chargement des statistiques:", error);
                console.error("Statut de la réponse:", xhr.status);
                console.error("Texte de la réponse:", xhr.responseText);
                
                $("#message_statistiques").text("Une erreur est survenue lors du chargement des statistiques.").show();
                
                // Essayer d'afficher l'erreur détaillée si disponible
                try {
                    const reponseErreur = JSON.parse(xhr.responseText);
                    if (reponseErreur.error) {
                        console.error("Détail de l'erreur:", reponseErreur.error);
                        $("#message_statistiques").text("Erreur: " + reponseErreur.error).show();
                    }
                } catch (e) {
                    console.error("Impossible de parser la réponse JSON:", e);
                }
            }
        });
    }
    
    /**
     * Exporte les données au format CSV
     * @param {string} type - Type de données à exporter (resultats_eleves ou statistiques_globales)
     */
    function exporterCSV(type) {
        console.log(`Exportation CSV du type: ${type}`);
        
        // Récupérer les valeurs des filtres
        const id_dictee = $("#filtre_dictee").val();
        const niveau = $("#filtre_niveau").val();
        
        // Pour les statistiques globales, une dictée doit être sélectionnée
        if (type === 'statistiques_globales' && !id_dictee) {
            alert("Veuillez sélectionner une dictée pour exporter les statistiques globales.");
            return;
        }
        
        // Construire l'URL avec les paramètres
        let url = 'exporter_' + type + '.php?';
        
        // Ajouter uniquement les filtres non vides
        if (id_dictee) {
            url += 'id_dictee=' + encodeURIComponent(id_dictee) + '&';
        }
        
        if (niveau && niveau !== "") {
            url += 'niveau_eleve=' + encodeURIComponent(niveau);
        }
        
        // Supprimer le dernier & ou ? si présent
        url = url.replace(/[&?]$/, '');
        
        console.log("URL d'exportation:", url);
        
        // Rediriger vers l'URL pour télécharger le fichier
        window.location.href = url;
    }
    
    // Initialiser la page en chargeant les résultats pour les valeurs par défaut
    // Simuler un clic sur le bouton "Appliquer les filtres" au chargement de la page
    setTimeout(function() {
        $("#bouton_appliquer_filtres").click();
    }, 500);
});