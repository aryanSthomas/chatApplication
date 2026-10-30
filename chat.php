
<?php
// Database configuration
$host = "172.16.50.33";
$user = "user";
$pass = "test@123";
$database = "web";

// Connect to MySQL
$db = new mysqli($host, $user, $pass, $database);

if ($db->connect_error) {
    http_response_code(500);
    exit("Database connection error.");
}

$db->set_charset("utf8mb4");

// Create table if it does not exist
$createTable = "CREATE TABLE IF NOT EXISTS chat_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender VARCHAR(30) NOT NULL,
    message TEXT NOT NULL,
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (!$db->query($createTable)) {
    http_response_code(500);
    exit("Table creation failed.");
}

// Handle new messages
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    header("Content-Type: application/json");

    $name = trim($_POST["sender"] ?? "");
    $msg = trim($_POST["message"] ?? "");

    if (empty($name) || empty($msg)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Name and message are required."
        ]);
        exit;
    }

    if (strlen($name) > 30 || strlen($msg) > 2000) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Input exceeds the allowed length."
        ]);
        exit;
    }

    $insertQuery = "INSERT INTO chat_messages (sender, message)
                    VALUES (?, ?)";

    $statement = $db->prepare($insertQuery);
    $statement->bind_param("ss", $name, $msg);

    if ($statement->execute()) {
        echo json_encode(["success" => true]);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Unable to send message."
        ]);
    }

    $statement->close();
    $db->close();
    exit;
}

// Retrieve chat history
if (isset($_GET["fetch"])) {

    header("Content-Type: application/json; charset=UTF-8");

    $fetchQuery = "SELECT sender, message, reg_date
                   FROM chat_messages
                   ORDER BY id DESC
                   LIMIT 200";

    $result = $db->query($fetchQuery);
    $chatData = [];

    if ($result) {
        while ($record = $result->fetch_assoc()) {
            $chatData[] = $record;
        }
    }

    echo json_encode(
        array_reverse($chatData),
        JSON_UNESCAPED_UNICODE
    );

    $db->close();
    exit;
}

$db->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Simple Chat Application</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #eef2ff;
        }

        .container {
            max-width: 500px;
            width: 100%;
            margin: 30px auto;
            padding: 20px;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 4px 15px #00000015;
        }

        h2 {
            color: #2563eb;
            text-align: center;
            margin-bottom: 5px;
        }

        .subtitle {
            color: #777;
            text-align: center;
            margin-bottom: 20px;
        }

        input, button {
            padding: 12px;
            font-size: 15px;
            border-radius: 8px;
        }

        input {
            width: 100%;
            border: 1px solid #ddd;
            outline: none;
        }

        input:focus {
            border-color: #2563eb;
        }

        #sender {
            margin-bottom: 12px;
        }

        #chatbox {
            height: 350px;
            overflow-y: auto;
            padding: 12px;
            margin-bottom: 12px;
            background: #f8fafc;
            border: 1px solid #eee;
            border-radius: 8px;
        }

        .chat-message {
            background: #dbeafe;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 8px;
            overflow-wrap: anywhere;
        }

        .chat-message strong {
            color: #1d4ed8;
        }

        .chat-message p {
            margin: 6px 0;
            white-space: pre-wrap;
        }

        .chat-message small {
            color: #666;
            font-size: 11px;
        }

        .message-row {
            display: flex;
            gap: 8px;
        }

        .message-row input {
            flex: 1;
            min-width: 0;
        }

        button {
            background: #2563eb;
            color: white;
            border: none;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        #status {
            text-align: center;
            color: #666;
            font-size: 12px;
            margin-top: 12px;
        }

        @media (max-width: 400px) {
            body {
                padding: 10px;
            }

            .container {
                padding: 14px;
                margin: 10px auto;
            }

            #chatbox {
                height: 55vh;
            }

            .message-row {
                flex-wrap: wrap;
            }

            .message-row button {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <h2>💬 Simple Chat</h2>
    <p class="subtitle">Chat with your friends</p>

    <input
        type="text"
        id="sender"
        placeholder="Enter your name"
        maxlength="30"
        autocomplete="nickname"
        required
    >

    <div id="chatbox">Loading messages...</div>

    <form id="chat-form" class="message-row">

        <input
            type="text"
            id="message"
            placeholder="Type a message..."
            maxlength="2000"
            autocomplete="off"
            required
        >

        <button type="submit" id="send-button">Send</button>

    </form>

    <div id="status">Connecting to chat...</div>

</div>

<script>
    const chatArea = document.getElementById("chatbox");
    const userName = document.getElementById("sender");
    const userMessage = document.getElementById("message");
    const chatForm = document.getElementById("chat-form");
    const connectionStatus = document.getElementById("status");
    const submitButton = document.getElementById("send-button");

    // Display messages from MySQL
    async function fetchChat() {

        try {
            const response = await fetch("chat.php?fetch=1", {
                cache: "no-store"
            });

            if (!response.ok) {
                throw new Error("Failed to retrieve messages.");
            }

            const messages = await response.json();

            const shouldScroll =
                chatArea.scrollHeight -
                chatArea.scrollTop -
                chatArea.clientHeight < 50;

            const content = document.createDocumentFragment();

            messages.forEach((entry) => {

                const messageBox = document.createElement("div");
                messageBox.className = "chat-message";

                const senderName = document.createElement("strong");
                senderName.textContent = entry.sender;

                const messageText = document.createElement("p");
                messageText.textContent = entry.message;

                const dateText = document.createElement("small");
                dateText.textContent = entry.reg_date;

                messageBox.append(senderName, messageText, dateText);
                content.appendChild(messageBox);
            });

            chatArea.replaceChildren(content);

            if (shouldScroll) {
                chatArea.scrollTop = chatArea.scrollHeight;
            }

            connectionStatus.textContent = "Connected to chat";

        } catch (error) {
            connectionStatus.textContent =
                "Connection failed. Retrying...";
        }
    }

    // Submit a new chat message
    chatForm.addEventListener("submit", async (event) => {

        event.preventDefault();

        const nameValue = userName.value.trim();
        const messageValue = userMessage.value.trim();

        if (!nameValue || !messageValue) {
            alert("Please enter your name and message.");
            return;
        }

        submitButton.disabled = true;

        try {
            const response = await fetch("chat.php", {
                method: "POST",
                headers: {
                    "Content-Type":
                        "application/x-www-form-urlencoded"
                },
                body: new URLSearchParams({
                    sender: nameValue,
                    message: messageValue
                })
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(
                    result.error || "Unable to send message."
                );
            }

            userMessage.value = "";

            await fetchChat();
            userMessage.focus();

        } catch (error) {
            alert(error.message);
        } finally {
            submitButton.disabled = false;
        }
    });

    // Load messages when the page opens
    fetchChat();

    // Refresh chat every two seconds
    setInterval(fetchChat, 2000);
</script>

</body>
</html>
