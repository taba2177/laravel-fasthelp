<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbot Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
        <h1 class="text-2xl font-bold mb-6 text-center">Chatbot Setup</h1>

        @if (session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif

        <form action="{{ url('/chatbot/setup') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="mb-4">
                <label for="whatsapp_chat_file" class="block text-gray-700 text-sm font-bold mb-2">Upload WhatsApp Chat History (.txt):</label>
                <input type="file" name="whatsapp_chat_file" id="whatsapp_chat_file" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>

            <div class="mb-6">
                <label for="database_tables" class="block text-gray-700 text-sm font-bold mb-2">Database Tables (comma-separated, e.g., users,products):</label>
                <input type="text" name="database_tables" id="database_tables" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="e.g., users, products">
                <p class="text-gray-600 text-xs italic mt-1">Leave empty to extract all string/text columns from all tables.</p>
            </div>

            <div class="flex items-center justify-between">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Setup Chatbot
                </button>
            </div>
        </form>
    </div>
</body>
</html>
