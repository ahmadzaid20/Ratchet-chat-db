<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        #messages {
            width: 90%;
            height: 300px;
            overflow-y: scroll;
            padding: 10px;
            border: 1px solid #ccc;
        }
        .message {
            padding: 10px;
            margin: 5px;
            border-radius: 10px;
            max-width: 70%;
            word-wrap: break-word;
        }
        .message.you {
            background-color: #d4edda;
            text-align: left;
            margin-left: 0;
            border-radius: 10px 10px 10px 0px;
        }
        .message.other {
            background-color: #f1f1f1;
            text-align: right;
            margin-left: auto;
            border-radius: 10px 0px 10px 10px;
        }
        .message .meta {
            font-size: 0.8em;
            color: #555;
        }
        .message .meta strong {
            font-weight: bold;
        }
        #messageInput, #usernameInput, #roomInput {
            width: calc(100% - 120px);
            padding: 10px;
            margin: 10px auto;
            margin-right: 10px;
            border: 1px solid #ccc;
        }
        #usernameInput, #roomInput {
            width: calc(100% - 120px);
        }
        #sendBtn, #saveUsernameBtn {
            padding: 10px 20px;
            border: none;
            background-color: #007bff;
            color: white;
            cursor: pointer;
        }
        #sendBtn:hover, #saveUsernameBtn:hover {
            background-color: #0056b3;
        }
        .date-divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 20px 0;
            position: relative;
        }
        .date-divider span {
            background-color: #f1f1f1;
            padding: 0 10px;
            color: #555;
            font-size: 0.9em;
            font-weight: bold;
        }
        .date-divider::before {
            content: '';
            flex: 1;
            border-bottom: 1px solid #ccc;
            margin-right: 10px;
        }
        .date-divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #ccc;
            margin-left: 10px;
        }
        #typingIndicator {
            font-size: 0.9em;
            color: #555;
            margin: 10px auto;
            text-align: left;
            width: 90%;
            height: 0.9em;
            visibility: hidden;
        }
        #users {
            font-size: 1em;
            margin: 10px auto;
            width: 90%;
            list-style: none;
            padding-left: 0;
            display: flex;
            flex-direction: row;
            gap: 8px;
        }
        #users li {
            margin: 5px 0;
        }
        #users li::after {
            content: ', ';
        }

        #users li:last-child::after {
            content: ''; /* Remove comma from last element */
        }

    </style>
</head>
<body>
    <h1>Chat</h1>
    <input type="text" id="usernameInput" placeholder="Enter your name">
    <input type="text" id="roomInput" placeholder="Enter room name">
    <button id="saveUsernameBtn">Save Username</button>
    <ul id="users"></ul> <!-- User List -->
    <div id="messages" data-last-date=""></div>
    <div id="typingIndicator"></div>
    <input type="text" id="messageInput" placeholder="Enter your message" disabled>
    <button id="sendBtn" disabled>Send</button>

    <script>
        let username = null;
        let room = null;
        let socket = null;
        let typingTimeout;
        const TYPING_DELAY = 3000;

        document.getElementById('saveUsernameBtn').addEventListener('click', function () {
            const usernameInput = document.getElementById('usernameInput').value;
            const roomInput = document.getElementById('roomInput').value;
            if (usernameInput.trim() !== "" && roomInput.trim() !== "") {
                username = usernameInput;
                room = roomInput;
                alert(`Username saved as ${username} in room ${room}`);

                socket = new WebSocket(`ws://192.168.0.6:8080?username=${encodeURIComponent(username)}&room=${encodeURIComponent(room)}`);

                socket.addEventListener('open', function (event) {
                    console.log(`Connected to the WebSocket server as ${username} in room ${room}`);
                    socket.send(JSON.stringify({ action: 'checkMessages', room: room }));
                });

                socket.addEventListener('message', function (event) {
                    const messagesDiv = document.getElementById('messages');
                    const typingIndicator = document.getElementById('typingIndicator');
                    const usersList = document.getElementById('users');
                    const receivedData = JSON.parse(event.data);

                    let typingUsers = [];

                    if (receivedData.action === 'typing') {
                        typingUsers.push(receivedData.username);
                        updateTypingIndicator();
                    } else if (receivedData.action === 'stopTyping') {
                        typingUsers = typingUsers.filter(user => user !== receivedData.username);
                        updateTypingIndicator();
                    } else if (receivedData.action === 'updateUsers') {
                        // Show users with separators
                        usersList.textContent = receivedData.users.join(', ');
                    } else {
                        const newMessage = document.createElement('div');
                        const timestamp = new Date(receivedData.timestamp);
                        const timeString = timestamp.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                        const dateString = timestamp.toLocaleDateString();

                        const lastMessageDate = messagesDiv.getAttribute('data-last-date');
                        if (lastMessageDate !== dateString) {
                            const dateDivider = document.createElement('div');
                            dateDivider.classList.add('date-divider');
                            dateDivider.innerHTML = `<span>${dateString}</span>`;
                            messagesDiv.appendChild(dateDivider);
                            messagesDiv.setAttribute('data-last-date', dateString);
                        }

                        const displayName = receivedData.username === username ? 'You' : receivedData.username;

                        newMessage.classList.add('message', receivedData.username === username ? 'you' : 'other');
                        newMessage.innerHTML = `<div class="meta"><strong>${displayName}</strong> : ${timeString}</div>${receivedData.message}`;
                        messagesDiv.appendChild(newMessage);
                        messagesDiv.scrollTop = messagesDiv.scrollHeight;
                    }

                    function updateTypingIndicator() {
                        if (typingUsers.length > 0) {
                            typingIndicator.textContent = typingUsers.join(', ') + ' is typing...';
                            typingIndicator.style.visibility = 'visible';
                        } else {
                            typingIndicator.textContent = '';
                            typingIndicator.style.visibility = 'hidden';
                        }
                    }
                });

                document.getElementById('messageInput').disabled = false;
                document.getElementById('sendBtn').disabled = false;
            } else {
                alert("Please enter a valid username and room name.");
            }
        });

        document.getElementById('messageInput').addEventListener('input', function () {
            if (socket && socket.readyState === WebSocket.OPEN) {
                socket.send(JSON.stringify({
                    username: username,
                    room: room,
                    action: 'typing'
                }));
                
                clearTimeout(typingTimeout);
                typingTimeout = setTimeout(function() {
                    socket.send(JSON.stringify({
                        username: username,
                        room: room,
                        action: 'stopTyping'
                    }));
                }, TYPING_DELAY);
            }
        });

        document.getElementById('sendBtn').addEventListener('click', function () {
            const messageInput = document.getElementById('messageInput').value;

            if (messageInput.trim() === "") {
                return;
            }

            const timestamp = new Date().toISOString();

            const messageData = {
                username: username,
                room: room,
                message: messageInput,
                timestamp: timestamp,
                action: 'sendMessage'
            };

            socket.send(JSON.stringify(messageData));

            const messagesDiv = document.getElementById('messages');
            const typingIndicator = document.getElementById('typingIndicator');
            const newMessage = document.createElement('div');
            const timeString = new Date(timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            newMessage.classList.add('message', 'you');
            newMessage.innerHTML = `<div class="meta"><strong>You</strong> : ${timeString}</div>${messageInput}`;
            messagesDiv.appendChild(newMessage);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;

            typingIndicator.textContent = '';
            document.getElementById('messageInput').value = '';
        });

    </script>
</body>
</html>
