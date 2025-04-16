from cryptography.fernet import Fernet


# Charger la clé
with open("secret.key", "rb") as key_file:
    key = key_file.read()
cipher = Fernet(key)

""" # Chiffrer le mot de passe
mot_de_passe = "postgres".encode()
mot_de_passe_chiffre = cipher.encrypt(mot_de_passe)

# Sauvegarder dans un fichier
with open("passwords.txt", "wb") as f:
    f.write(mot_de_passe_chiffre) """

# Lire et déchiffrer plus tard
with open("mdp_chiffre.txt", "rb") as f:
    mot_de_passe_chiffre = f.read()
mot_de_passe_dechiffre = cipher.decrypt(mot_de_passe_chiffre).decode()

print(f"Mot de passe retrouvé : {mot_de_passe_dechiffre}")
