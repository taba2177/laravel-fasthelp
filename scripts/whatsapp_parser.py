

import re
import sqlite3
import os
from datetime import datetime

def parse_whatsapp_chat(file_path):
    """
    Parses a WhatsApp chat export file and extracts messages.
    Assumes the format: "[DD/MM/YYYY, HH:MM:SS] Sender: Message"
    """
    messages = []
    with open(file_path, 'r', encoding='utf-8') as f:
        for line in f:
            match = re.match(r"[(\d{2}/\d{2}/\d{4}), (\d{2}:\d{2}:\d{2})] ([^:]+): (.*)", line)
            if match:
                date_str, time_str, sender, message = match.groups()
                # Convert date and time to a datetime object
                try:
                    timestamp_str = f"{date_str} {time_str}"
                    timestamp = datetime.strptime(timestamp_str, "%d/%m/%Y %H:%M:%S")
                    messages.append({
                        "sender": sender.strip(),
                        "message": message.strip(),
                        "timestamp": timestamp
                    })
                except ValueError:
                    # Handle cases where timestamp might be malformed
                    print(f"Skipping malformed timestamp: {timestamp_str}")
                    continue
            else:
                # Handle multi-line messages by appending to the last message
                if messages:
                    messages[-1]["message"] += "\n" + line.strip()
    return messages

def insert_messages_to_db(messages, db_path):
    """
    Inserts parsed WhatsApp messages into the SQLite database.
    """
    conn = None
    try:
        conn = sqlite3.connect(db_path)
        cursor = conn.cursor()

        for msg in messages:
            # Check if a message with the same sender, message, and timestamp already exists
            # This is a simple deduplication. For production, consider a more robust unique identifier.
            cursor.execute(
                "SELECT id FROM whatsapp_messages WHERE sender = ? AND message = ? AND timestamp = ?",
                (msg["sender"], msg["message"], msg["timestamp"].strftime("%Y-%m-%d %H:%M:%S"))
            )
            existing_message = cursor.fetchone()

            if not existing_message:
                cursor.execute(
                    "INSERT INTO whatsapp_messages (sender, message, timestamp, created_at, updated_at) VALUES (?, ?, ?, ?, ?)",
                    (msg["sender"], msg["message"], msg["timestamp"].strftime("%Y-%m-%d %H:%M:%S"),
                     datetime.now().strftime("%Y-%m-%d %H:%M:%S"), datetime.now().strftime("%Y-%m-%d %H:%M:%S"))
                )
            else:
                print(f"Skipping duplicate message: {msg['message']}")

        conn.commit()
        print(f"Successfully inserted {len(messages)} messages into the database.")
    except sqlite3.Error as e:
        print(f"Database error: {e}")
    finally:
        if conn:
            conn.close()

if __name__ == "__main__":
    # This script expects the chat file path as a command-line argument
    import sys
    if len(sys.argv) < 2:
        print("Usage: python whatsapp_parser.py <path_to_whatsapp_chat_file>")
        sys.exit(1)

    chat_file_path = sys.argv[1]
    
    # Determine the absolute path to the SQLite database
    # Assumes the script is run from the project root or a known subdirectory
    current_dir = os.path.dirname(os.path.abspath(__file__))
    project_root = os.path.abspath(os.path.join(current_dir, os.pardir))
    db_path = os.path.join(project_root, 'database', 'database.sqlite')

    if not os.path.exists(chat_file_path):
        print(f"Error: Chat file not found at {chat_file_path}")
        sys.exit(1)

    if not os.path.exists(db_path):
        print(f"Error: Database file not found at {db_path}. Please ensure migrations have been run.")
        sys.exit(1)

    print(f"Parsing chat file: {chat_file_path}")
    messages = parse_whatsapp_chat(chat_file_path)
    print(f"Found {len(messages)} messages.")

    if messages:
        print(f"Inserting {len(messages)} messages into database: {db_path}")
        insert_messages_to_db(messages, db_path)
    else:
        print("No messages to insert.")

