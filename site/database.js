document.addEventListener("DOMContentLoaded", function() {
    // Charger les données des poubelles depuis le fichier JSON
    fetch('poubelles_data.json')
        .then(response => response.json())
        .then(data => {
            // Créer un tableau HTML pour afficher les données
            if (data.poubelles && data.poubelles.length > 0) {
                let tableHTML = '<div class="table-container"><table><thead><tr>';
                
                // Créer les en-têtes de colonnes
                const columns = Object.keys(data.poubelles[0]);
                columns.forEach(column => {
                    tableHTML += `<th>${column}</th>`;
                });
                tableHTML += '</tr></thead><tbody>';
                
                // Ajouter les lignes de données
                data.poubelles.forEach(poubelle => {
                    tableHTML += '<tr>';
                    columns.forEach(column => {
                        tableHTML += `<td>${poubelle[column] !== null ? poubelle[column] : '-'}</td>`;
                    });
                    tableHTML += '</tr>';
                });
                
                tableHTML += '</tbody></table></div>';
                
                // Afficher le tableau
                document.getElementById('database-table').innerHTML = tableHTML;
            } else {
                document.getElementById('database-table').innerHTML = 
                    '<p>Aucune donnée disponible.</p>';
            }
        })
        .catch(error => {
            console.error("Erreur lors du chargement des données PostgreSQL:", error);
            document.getElementById('database-table').innerHTML = 
                '<p>Impossible de charger les données de la base PostgreSQL.</p>';
        });
});