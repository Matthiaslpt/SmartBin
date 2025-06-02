import psycopg2
from psycopg2 import Error
import datetime
import serial
import time
from twilio.rest import Client

# -------------------- Introduction --------------------

print(r"""
  _________                      __ ___     __        
 /   _____/ _____ _____ ________/  |\_ |__ |__| ____  
 \_____  \ /     \\__  \\_  __ \   __\ __ \|  |/    \ 
 /        \  Y Y  \/ __ \|  | \/|  | | \_\ \  |   |  \
/_______  /__|_|  (____  /__|   |__| |___  /__|___|  /
        \/      \/     \/                \/        \/ 
""")
print("🗑️  Smart Bin Terminal - Initializing...\n")


# -------------------- PostgreSQL --------------------

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


def close_connection(connection, cursor):
    if cursor:
        cursor.close()
    if connection:
        connection.close()
        print("🔒 Connexion PostgreSQL fermée.")


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

            # Envoi d'un SMS si température critique
            if bin_temp >= 50:
                send_sms_notification(bin_id, bin_temp, bin_date)

        except Exception as e:
            print(f"❌ Erreur d'insertion : {e}")
        finally:
            close_connection(conn, cur)


# -------------------- Envoi SMS Twilio --------------------

def send_sms_notification(bin_id, temperature, date):
    try:
      #Les SID/TOKEN sont à rajouter à la main
        account_sid = ''
        auth_token = ''
        messaging_service_sid = ''
        to_number = '+33769048181'

        client = Client(account_sid, auth_token)

        message = client.messages.create(
            messaging_service_sid=messaging_service_sid,
            body=f"⚠️ Alerte SmartBin\nID: {bin_id} | Température: {temperature}°C\nDate: {date}",
            to=to_number
        )

        print("📲 SMS envoyé :", message.sid)

    except Exception as e:
        print("❌ Échec de l'envoi du SMS :", e)

# -------------------- Lecture Port Série --------------------

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

                if line.startswith("id") or line.startswith("temp"):
                    buffer.append(line)

                while len(buffer) >= 2:
                    if buffer[0].startswith("id") and buffer[1].startswith("temp"):
                        try:
                            line1 = buffer.pop(0)
                            line2 = buffer.pop(0)

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


# -------------------- Exécution principale --------------------

if __name__ == "__main__":
    print("🔄 Démarrage de la lecture du port série...")
    read_from_serial(port='/dev/ttyUSB0', baudrate=115200)
    print("🔚 Fin de la lecture du port série.")
