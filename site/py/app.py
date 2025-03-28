from flask import Flask, jsonify, request  # Add `request` to handle POST data
from flask_cors import CORS
import psycopg2
from cryptography.fernet import Fernet
import json  # Import json to handle history serialization

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

# Endpoint: Add a new bin
@app.route('/bins', methods=['POST'])
def add_bin():
    data = request.get_json()  # Get JSON data from the request
    if not data:
        return jsonify({"error": "Invalid input"}), 400

    # Extract data from the request
    address = data.get("address")
    lat = data.get("lat")
    lng = data.get("lng")
    trash_level = data.get("trash_level", 0)  # Default to 0 if not provided
    history = data.get("history", {})  # Default to an empty dictionary

    if not address or lat is None or lng is None:
        return jsonify({"error": "Missing required fields"}), 400

    conn = get_db_connection()
    cur = conn.cursor()
    try:
        # Insert the new bin into the database
        cur.execute(
            "INSERT INTO bins (address, lat, lng, trash_level, history) VALUES (%s, %s, %s, %s, %s) RETURNING id;",
            (address, lat, lng, trash_level, json.dumps(history))
        )
        bin_id = cur.fetchone()[0]  # Get the ID of the newly inserted bin
        conn.commit()
        return jsonify({"message": "Bin added successfully", "id": bin_id}), 201
    except Exception as e:
        conn.rollback()
        return jsonify({"error": str(e)}), 500
    finally:
        cur.close()
        conn.close()

# Run Flask app
if __name__ == '__main__':
    app.run(debug=True, port=5000)
