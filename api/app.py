from flask import Flask, jsonify
import subprocess

app = Flask(__name__)

@app.route('/run-email-reminder', methods=['GET'])
def run_email_reminder():
    try:
        # Run the send_email.py script
        subprocess.run(['python', 'send_email.py'], check=True)
        return jsonify({"message": "Email reminders sent successfully!"})
    except subprocess.CalledProcessError as e:
        return jsonify({"error": str(e)}), 500

if __name__ == '__main__':
    app.run(debug=True, port=5000)  # Use any available port
