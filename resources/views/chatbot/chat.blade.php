<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbot</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        #chat-box {
            height: 400px;
            overflow-y: scroll;
            border: 1px solid #e2e8f0;
            padding: 1rem;
            border-radius: 0.5rem;
            background-color: #f7fafc;
        }
        .message {
            margin-bottom: 0.5rem;
        }
        .user-message {
            text-align: right;
            color: #3182ce;
        }
        .bot-message {
            text-align: left;
            color: #2d3748;
        }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-lg">
        <h1 class="text-2xl font-bold mb-6 text-center">Chat with Bot</h1>

        <div id="chat-box" class="mb-4">
            <!-- Chat messages will appear here -->
        </div>

        <form id="chat-form" class="flex">
            <input type="text" id="user-input" class="flex-grow shadow appearance-none border rounded-l py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Type your message...">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-r focus:outline-none focus:shadow-outline">
                Send
            </button>
        </form>
    </div>

    <script>
        document.getElementById('chat-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const userInput = document.getElementById('user-input');
            const message = userInput.value.trim();
            userInput.value = '';

            if (message) {
                addMessage(message, 'user-message');

                try {
                    const response = await fetch('/api/chat', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}' // Laravel CSRF token
                        },
                        body: JSON.stringify({ query: message })
                    });

                    const data = await response.json();
                    if (data.response && data.response.text) {
                        addMessage(data.response.text, 'bot-message');
                    } else if (data.error) {
                        addMessage('Error: ' + data.error, 'bot-message');
                    } else {
                        addMessage('Unexpected response from bot.', 'bot-message');
                    }
                } catch (error) {
                    console.error('Error sending message:', error);
                    addMessage('Could not connect to the chatbot.', 'bot-message');
                }
            }
        });

        function addMessage(text, type) {
            const chatBox = document.getElementById('chat-box');
            const messageDiv = document.createElement('div');
            messageDiv.classList.add('message', type);
            messageDiv.textContent = text;
            chatBox.appendChild(messageDiv);
            chatBox.scrollTop = chatBox.scrollHeight; // Scroll to bottom
        }
    </script>
</body>
</html>
