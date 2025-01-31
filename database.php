<?php
// Database connection parameters
define('DB_HOST', 'localhost'); // Database host
define('DB_USER', 'your_db_user'); // Database username
define('DB_PASS', 'your_db_password'); // Database password
define('DB_NAME', 'your_db_name'); // Database name

// Create a new PDO connection
class NoscriptChat {
    private $conn;
    private $name;
    private $message;

    // Database table for storing messages
    const CHAT_TABLE = 'chat_messages';

    public function __construct() {
        // Create a connection to the MySQL database using MySQLi
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }

    // Fetch the last message position from the database
    private function getStartPosition() {
        $result = $this->conn->query("SELECT COUNT(*) FROM " . self::CHAT_TABLE);
        if ($result) {
            $row = $result->fetch_row();
            return (int) $row[0]; // Return the number of messages in the table
        }
        return 0;
    }

    // Get and display chat messages starting from a specific position
    private function getChatMessages($position) {
        $query = "SELECT name, message FROM " . self::CHAT_TABLE . " ORDER BY created_at ASC LIMIT $position, 10";
        $result = $this->conn->query($query);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $name = htmlspecialchars($row['name'], ENT_QUOTES);
                $message = htmlspecialchars($row['message'], ENT_QUOTES);
                echo "<div style='float:left;width:270px;'>$name: $message<br /><br /></div>";
            }
        }
        return $position + 10; // Move forward by 10 messages
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

    // Insert a new chat message into the database
    public function insertMessage() {
        if (isset($this->name) && isset($this->message)) {
            // Prepare the query to insert a message
            $stmt = $this->conn->prepare("INSERT INTO " . self::CHAT_TABLE . " (name, message) VALUES (?, ?)");
            $stmt->bind_param("ss", $this->name, $this->message);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Start of page script
header("Content-type: text/html; charset=ASCII");
$request = @$_GET["request"];
$name = @$_POST["name"];
$noscript = new NoscriptChat(); // Create a new NoscriptChat instance

// Check if there is a request parameter in the URL
if ($request) {
    if ($request == "stream") {
        if (isset($_GET['start'])) {
            set_time_limit(0); // Set script execution time to unlimited

            // Blank div to support some old browsers like Internet Explorer
            echo "<div style='visibility:hidden;'>" . str_repeat(" ", 2010) . "</div>";
            // Start streaming chat
            $noscript->streamChat();
        } else {
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

<!-- CREATE TABLE `chat_messages` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
); -->