<?php
// Real-time chat application without using Javascript
// This version uses file-based storage for chat messages.

class NoscriptChat {
    const CHAT_FILE = 'chat_messages.txt'; // File where chat messages will be stored.

    public $name; // Store the username
    public $message; // Store the user's message

    // Fetch the last message position from the stored file
    private function getStartPosition() {
        if (file_exists(self::CHAT_FILE)) {
            // Read all lines from the file
            $messages = file(self::CHAT_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            return count($messages) ? count($messages) - 1 : 0; // Return the number of messages or 0 if no messages exist
        }
        return 0;
    }

    // Get and display chat messages starting from a specific position
    private function getChatMessages($position) {
        if (file_exists(self::CHAT_FILE)) {
            // Read all lines from the file
            $messages = file(self::CHAT_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            // Get messages from the specified position onward
            $newMessages = array_slice($messages, $position);

            // Display each message
            foreach ($newMessages as $msg) {
                list($name, $message) = explode(':', $msg, 2); // Split the message into name and message parts
                echo "<div style='float:left;width:270px;'>$name: " . htmlspecialchars($message, ENT_QUOTES) . "<br /><br /></div>";
            }

            return count($messages); // Return the total number of messages
        }
        return $position; // If file doesn't exist, return the initial position
    }

    // Stream chat messages to the browser
    public function streamChat() {
        // Set environment variables to disable compression
        @apache_setenv('no-gzip', 1);
        @ini_set('zlib.output_compression', 0);
        @ini_set('implicit_flush', 1);

        // Flush all output buffers
        for ($i = 0; $i < ob_get_level(); $i++) {
            ob_end_flush();
        }
        ob_implicit_flush(1);

        // Get the starting position for chat messages
        $position = $this->getStartPosition();

        // Infinite loop to keep streaming chat
        while (1) {
            // Fetch new chat messages from the last position
            $position = $this->getChatMessages($position);
            // Flush the output to the browser
            flush();
            // Wait for 0.25 seconds before checking again
            usleep(250000);
        }
    }

    // Insert a new chat message into the file
    public function insertMessage() {
        if (isset($this->name) && isset($this->message)) {
            // Format the message as "name: message"
            $message = $this->name . ":" . $this->message . "\n";
            // Append the message to the chat file
            file_put_contents(self::CHAT_FILE, $message, FILE_APPEND);
        }
    }
}

// Start of page script
header("Content-type: text/html; charset=ASCII");
$request = @$_GET["request"];
$name = @$_POST["name"];
$noscript = new NoscriptChat; // Create a new NoscriptChat instance

// Check if there is a request parameter in the URL
if ($request) {
    if ($request == "stream") {
        if (isset($_GET['start'])) {
            set_time_limit(0); // Set script execution time to unlimited

            // Blank div to support some old browsers like Internet Explorer
            echo "<div style='visibility:hidden;'>" . str_repeat(" ", 2010) . "</div>";
            // Start streaming chat
            $noscript->streamChat();
        }else {
            echo "<a href='?request=stream&start=go'>start</a>";
        }
    } elseif ($request == "messenger") {
        @session_start();
        if (@$_POST["message"] != null) {
            // If a new message is posted, insert it into the chat
            $noscript->name = $_SESSION["noscript_name"];
            $noscript->message = $_POST["message"];
            $noscript->insertMessage();
        }
        // Display the chat input form
        echo "<form action='' method='post'>" . $_SESSION["noscript_name"] . ": <input type='text' name='message' maxlength='200' /><input type='submit' value='Send'></form>";
    }
} elseif ($name) {
    @session_start();
    // Store the user's name in the session
    $_SESSION["noscript_name"] = $name;
    // Display the chat interface with two iframes (one for streaming chat, one for sending messages)
    echo "<div style='margin:auto;width:300px;height:500px;'>
            <iframe src='?request=stream' frameborder='0' scrolling='yes' style='float:left;width:300px;height:450px;'></iframe>
            <iframe src='?request=messenger' frameborder='0' scrolling='no' style='float:left;width:300px;height:50px;'></iframe>
          </div>";
} else {
    // Display the form to ask for the username
    echo "<form action='' method='post'>Username: <input type='text' name='name' maxlength='15' /><input type='submit' value='Join'></form>";
}
?>
