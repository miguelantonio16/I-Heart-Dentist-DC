import mysql.connector
from googleapiclient.discovery import build
from googleapiclient.errors import HttpError
from email.mime.text import MIMEText
import base64
from datetime import datetime, timedelta
from authenticate import main  # Import the authentication function from authenticate.py
from email.mime.text import MIMEText
import schedule
import time


# Function to query the database for patients with upcoming appointments (using schedule table)
def get_upcoming_patients():
    try:
        # Connect to the MySQL database
        db_connection = mysql.connector.connect(
            host="127.0.0.1",
            user="root",  # Your MySQL username
            password="",  # Your MySQL password
            database="edoc"  # Your database name
        )
        
        cursor = db_connection.cursor(dictionary=True)

        # Query to find patients with appointments in the next 24 hours, using the schedule table for time
        cursor.execute("""
            SELECT p.pemail, p.pname, a.appoid, a.appodate, s.scheduledate, s.scheduletime, d.docname
            FROM appointment a
            JOIN patient p ON a.pid = p.pid
            JOIN schedule s ON a.scheduleid = s.scheduleid
            JOIN doctor d ON s.docid = d.docid
            WHERE s.scheduledate BETWEEN CURDATE() AND CURDATE() + INTERVAL 1 DAY;
        """)

        patients = cursor.fetchall()

        cursor.close()
        db_connection.close()

        return patients

    except mysql.connector.Error as err:
        print(f"Error: {err}")
        return []

# Function to send email to patients
def send_email_to_patients(patients):
    try:
        # Get credentials from authenticate.py
        creds = main()  # Ensure you're authenticated

        # Build the Gmail API service
        service = build('gmail', 'v1', credentials=creds)

        # Loop through each patient and send an email
        for patient in patients:
            patient_email = patient["pemail"]
            patient_name = patient["pname"]
            schedule_date = patient["scheduledate"]
            dentist_name = patient["docname"]
            
            # Convert timedelta to hours and minutes (assuming it's a timedelta object)
            schedule_time = (datetime.min + patient["scheduletime"]).time()  # Convert timedelta to time
            schedule_time_str = schedule_time.strftime('%I:%M %p')  # Format as AM/PM time

            # Create the email content
            subject = f"Reminder: Your Appointment on {schedule_date.strftime('%Y-%m-%d')}"
            body = f"""
            <html>
            <body>
                <p>Greetings <strong>{patient_name}</strong>,</p>
                <p>This is to remind you of your upcoming appointment. Please find the details below:</p>
                <p><strong>Appointment Date:</strong> {schedule_date.strftime('%Y-%m-%d')}</p>
                <p><strong>Time:</strong> {schedule_time_str}</p>
                <p>Thank you for choosing Songco Dental Clinic.</p>
                <p>Warm regards, <br/> <strong>Dr. {dentist_name}</strong></p>
            </body>
            </html>
            """            
            message = MIMEText(body, "html")
            message['to'] = patient_email
            message['from'] = 'songcodent@gmail.com'  
            message['subject'] = 'Songco Dental Clinic - Appointment Reminder'

            raw_message = base64.urlsafe_b64encode(message.as_bytes()).decode()

            send_message = service.users().messages().send(userId='me', body={'raw': raw_message}).execute()
            print(f"Message sent to {patient_email} with appointment on {schedule_date} at {schedule_time_str}!")

    except HttpError as error:
        print(f'An error occurred: {error}')



patients = get_upcoming_patients()
send_email_to_patients(patients)

def job():
    patients = get_upcoming_patients()
    send_email_to_patients(patients)

schedule.every().day.at("08:00").do(job)

while True:
    schedule.run_pending()
    time.sleep(1)