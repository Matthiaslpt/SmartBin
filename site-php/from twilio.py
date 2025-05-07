from twilio.rest import Client
import os
from dotenv import load_dotenv

# Charger les variables depuis .env
load_dotenv()

def send_sms_twilio(message):
  account_sid = os.environ.get('TWILIO_ACCOUNT_SID')
  auth_token = os.environ.get('TWILIO_AUTH_TOKEN')
  print()
  client = Client(account_sid, auth_token)
  message = client.messages.create(
    body=message,
    from_='+13024070916',
    to='+33786970551')
  return message.sid

send_sms_twilio('test')