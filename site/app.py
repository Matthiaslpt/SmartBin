from flask import Flask, jsonify
from flask_cors import CORS
import psycopg2
from cryptography.fernet import Fernet

app = Flask(__name__)
CORS(app)  # Allows JavaScript to call API

# Charger la clé
with open("secret.key", "rb") as key_file:
    key = key_file.read()
cipher = Fernet(key)
# Lire et déchiffrer
with open("passwords.txt", "rb") as f:
    psswd_c = f.read()
psswd_d = cipher.decrypt(psswd_c).decode()


# Database connection function
def get_db_connection():
    return psycopg2.connect(
        dbname="smartbin",
        user="your_pg_user",
        password=psswd_d,
        host="localhost",
        port="5432"
    )

# Endpoint: Get all bins
@app.route('/bins', methods=['GET'])
def get_bins():
    conn = get_db_connection()
    cur = conn.cursor()
    cur.execute("SELECT id, address, lat, lng, trash_level FROM bins;")
    bins = [{"id": row[0], "address": row[1], "lat": row[2], "lng": row[3], "trash_level": row[4]} for row in cur.fetchall()]
    cur.close()
    conn.close()
    return jsonify(bins)

# Endpoint: Get bin by ID
@app.route('/bins/<int:bin_id>', methods=['GET'])
def get_bin(bin_id):
    conn = get_db_connection()
    cur = conn.cursor()
    cur.execute("SELECT * FROM bins WHERE id = %s;", (bin_id,))
    row = cur.fetchone()
    cur.close()
    conn.close()
    if row:
        return jsonify({"id": row[0], "address": row[1], "lat": row[2], "lng": row[3], "trash_level": row[4], "history": row[5]})
    return jsonify({"error": "Bin not found"}), 404

# Run Flask app
if __name__ == '__main__':
    app.run(debug=True, port=5000)
