<?php
// Include Redis library (using Composer's autoload)
require 'vendor/autoload.php';

class NoscriptChat {
    private $redis;

    public $name; // Store the username
    public $message; // Store the user's message

    // Constructor to connect to Redis
    public function __construct() {
        $this->redis = new Predis\Client();
    }

    // Insert a new chat message into the Redis queue
    public function insertMessage() {
        if (isset($this->name) && isset($this->message)) {
            // Push the message to a Redis list (acting as the queue)
            $this->redis->rpush('chat_queue', $this->name . ':' . $this->message);
        }
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

        // Infinite loop to keep streaming chat
        while (1) {
            // Pop the next message from the Redis queue
            $message = $this->redis->lpop('chat_queue'); // Retrieve and remove the first message

            if ($message) {
                list($name, $text) = explode(':', $message, 2);
                echo "<div style='float:left;width:270px;'>$name: " . htmlspecialchars($text, ENT_QUOTES) . "<br /><br /></div>";
            }

            // Flush the output to the browser
            flush();

            // Wait for 0.25 seconds before checking again
            usleep(250000);
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

            // Start streaming chat
            $noscript->streamChat();
        } else {
            echo "<a href='?request=stream&start=go'>start</a>";
        }
    } elseif ($request == "messenger") {
        @session_start();
        if (@$_POST["message"] != null) {
            // If a new message is posted, insert it into the Redis queue
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
