import psycopg2

DB_HOST = '192.168.35.89'
DB_NAME = 'smartbin'
DB_USER = 'your_pg_user'
DB_PASSWORD = 'your_pg_password'
DB_PORT = '5432'  # Port par défaut pour PostgreSQL

# Connexion à la base de données
try:
    conn = psycopg2.connect(
        host=DB_HOST,
        dbname=DB_NAME,
        user=DB_USER,
        password=DB_PASSWORD,
        port=DB_PORT
    )
    print("Connexion réussie !")

    # Créer un curseur pour exécuter les requêtes
    cursor = conn.cursor()

    # Exemple de requête SELECT
    cursor.execute("SELECT * FROM bins;")
    rows = cursor.fetchall()
    for row in rows:
        print(row)
    



 
except Exception as e:
    print(f"Une erreur est survenue : {e}")