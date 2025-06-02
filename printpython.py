import random
import time

def generate_fake_serial_data():
    # Valeurs simulées
    bin_id = random.randint(1, 3)
    distance = round(random.uniform(5.0, 80.0), 1)  # en cm
    temp = round(random.uniform(20.0, 60.0), 1)     # en °C
    hum = round(random.uniform(30.0, 70.0), 1)      # en %

    # Format simulé brut, comme reçu par un port série
    line_id = f"id:{bin_id};dist:{distance}cm"
    line_temp = f"temp:{temp};hum:{hum}"
    
    return [line_id, line_temp, line_temp]  # simulate deux lignes temp

if __name__ == "__main__":
    while True:
        for line in generate_fake_serial_data():
            print(line)
            time.sleep(1)  # délai entre les messages pour l'effet "réel"
