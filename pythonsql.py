import psycopg2
from psycopg2 import Error
import datetime
import serial
import time
from twilio.rest import Client



print(r"""
  _________                      __ ___     __        
 /   _____/ _____ _____ ________/  |\_ |__ |__| ____  
 \_____  \ /     \\__  \\_  __ \   __\ __ \|  |/    \ 
 /        \  Y Y  \/ __ \|  | \/|  | | \_\ \  |   |  \
/_______  /__|_|  (____  /__|   |__| |___  /__|___|  /
        \/      \/     \/                \/        \/ 
""")

print("🗑️  Smart Bin Terminal - Initializing...\n")


# Connexion à PostgreSQL
def connect_to_postgresql():
    try:
        connection = psycopg2.connect(
            host="192.168.183.98",
            database="smartbin",
            user="smartbin_user",
            password="secure_password",
            port="5432"
        )
        cursor = connection.cursor()
        print("✅ Connexion PostgreSQL établie.")
        return connection, cursor
    except (Exception, Error) as error:
        print(f"❌ Erreur de connexion PostgreSQL : {error}")
        return None, None

# Fermeture de la connexion
def close_connection(connection, cursor):
    if cursor:
        cursor.close()
    if connection:
        connection.close()
        print("🔒 Connexion PostgreSQL fermée.")

# Insertion dans la table history
def add_row_to_history(bin_id, bin_level, bin_date, bin_temp, bin_humidity):
    conn, cur = connect_to_postgresql()
    if conn and cur:
        try:
            cur.execute("""
                INSERT INTO history (id, level, date, temperature, humidity)
                VALUES (%s, %s, %s, %s, %s)
            """, (bin_id, bin_level*100, bin_date, bin_temp, bin_humidity))
            conn.commit()
            print(f"✅ Données insérées : id={bin_id}, niveau={bin_level*100}, temp={bin_temp}, humid={bin_humidity}")
        except Exception as e:
            print(f"❌ Erreur d'insertion : {e}")
        finally:
            close_connection(conn, cur)

def read_from_serial(port='/dev/ttyUSB0', baudrate=115200):
    taillemax = 200
    try:
        ser = serial.Serial(port, baudrate, timeout=2)
        print(f"📡 Port série {port} ouvert.\n")
        buffer = []
        while True:
            try:
                line = ser.readline().decode(errors='ignore').strip()
                if not line:
                    continue
                print(f"📥 Reçu: {line}")

                # On ne garde que les lignes utiles
                if line.startswith("id") or line.startswith("temp"):
                    buffer.append(line)

                # On attend un couple id/temp (puisque Etat n'est plus toujours là)
                while len(buffer) >= 2:
                    if buffer[0].startswith("id") and buffer[1].startswith("temp"):
                        try:
                            line1 = buffer.pop(0)
                            line2 = buffer.pop(0)

                            # Extraction des données
                            bin_id = int(line1.split(';')[0].split(':')[1].strip())
                            level = float(line1.split(';')[1].split(':')[1].replace('cm','').strip())
                            level = level / taillemax

                            temp = float(line2.split(';')[0].split(':')[1].strip())
                            hum = float(line2.split(';')[1].split(':')[1].strip())
                            date = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")

                            print(f"✅ Données extraites : id={bin_id}, niveau={level}, temp={temp}, hum={hum}, date={date}")
                            add_row_to_history(bin_id, level, date, temp, hum)
                        except Exception as e:
                            print(f"❌ Erreur de parsing couple : {e}")
                            buffer = []
                    else:
                        buffer.pop(0)
                time.sleep(0.1)
            except Exception as e:
                print(f"❌ Erreur de lecture/parsing : {e}")
                continue
    except serial.SerialException as e:
        print(f"❌ Impossible d'ouvrir le port série : {e}")




def send_sms_notification():
    # Configuration de Twilio
    # Remplacez par vos identifiants Twilio
    account_sid = 'votre_account_sid'
    auth_token = 'votre_auth_token'

    client = Client(account_sid, auth_token)

    message = client.messages.create(
        body="⚠️ SmartBin : Température > 50°C détectée !",
        from_='+1234567890',  # Numéro Twilio
        to='+33612345678'     # Numéro cible
    )

    print("Message envoyé :", message.sid)


# Lancer le script
if __name__ == "__main__":
    read_from_serial()




