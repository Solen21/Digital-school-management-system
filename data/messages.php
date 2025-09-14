<?php
session_start();

// 1. Security Check: User must be logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$page_title = 'My Messages';
$user_id = $_SESSION['user_id'];

// --- Handle POST request to send a message ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_message'])) {
    $receiver_id = $_POST['receiver_id'];
    $subject = $_POST['subject'];
    $content = $_POST['content'];
    $parent_id = !empty($_POST['parent_message_id']) ? $_POST['parent_message_id'] : null;

    if (!empty($receiver_id) && !empty($subject) && !empty($content)) {
        $sql = "INSERT INTO messages (parent_message_id, sender_id, receiver_id, subject, content) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iiiss", $parent_id, $user_id, $receiver_id, $subject, $content);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        // Redirect to the same conversation
        header("Location: messages.php?conversation_id=" . ($parent_id ?? mysqli_insert_id($conn)));
        exit();
    }
}

// --- Fetch Data for Display ---

// Get all conversations for the current user
// A "conversation" is a group of messages with the same parent_message_id or a root message
$sql_conversations = "
    SELECT 
        m.parent_message_id,
        m.message_id,
        m.subject,
        m.created_at,
        (CASE WHEN m.sender_id = ? THEN r.username ELSE s.username END) as other_user,
        (SELECT COUNT(*) FROM messages sub WHERE (sub.parent_message_id = m.message_id OR sub.message_id = m.message_id) AND sub.receiver_id = ? AND sub.is_read = 0) as unread_count
    FROM messages m
    JOIN users s ON m.sender_id = s.user_id
    JOIN users r ON m.receiver_id = r.user_id
    WHERE (m.sender_id = ? OR m.receiver_id = ?) AND m.parent_message_id IS NULL
    ORDER BY m.created_at DESC
";
$stmt_conv = mysqli_prepare($conn, $sql_conversations);
mysqli_stmt_bind_param($stmt_conv, "iiii", $user_id, $user_id, $user_id, $user_id);
mysqli_stmt_execute($stmt_conv);
$conversations = mysqli_fetch_all(mysqli_stmt_get_result($stmt_conv), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_conv);

$selected_conversation_id = $_GET['conversation_id'] ?? null;
$messages = [];
$current_conversation = null;

if ($selected_conversation_id) {
    // Fetch all messages in the selected conversation thread
    $sql_messages = "
        SELECT m.*, u.username as sender_username, u.role as sender_role
        FROM messages m
        JOIN users u ON m.sender_id = u.user_id
        WHERE (m.message_id = ? OR m.parent_message_id = ?) AND (m.sender_id = ? OR m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ";
    $stmt_msg = mysqli_prepare($conn, $sql_messages);
    mysqli_stmt_bind_param($stmt_msg, "iiii", $selected_conversation_id, $selected_conversation_id, $user_id, $user_id);
    mysqli_stmt_execute($stmt_msg);
    $messages = mysqli_fetch_all(mysqli_stmt_get_result($stmt_msg), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_msg);

    if (!empty($messages)) {
        $current_conversation = $messages[0]; // The first message is the root
        // Mark messages in this thread as read
        $sql_mark_read = "UPDATE messages SET is_read = 1 WHERE (parent_message_id = ? OR message_id = ?) AND receiver_id = ?";
        $stmt_read = mysqli_prepare($conn, $sql_mark_read);
        mysqli_stmt_bind_param($stmt_read, "iii", $selected_conversation_id, $selected_conversation_id, $user_id);
        mysqli_stmt_execute($stmt_read);
        mysqli_stmt_close($stmt_read);
    }
}

include 'header.php';
?>
<style>
    .messaging-container {
        display: grid;
        grid-template-columns: 320px 1fr;
        height: calc(100vh - 120px); /* Full height minus nav and some padding */
        gap: 1rem;
    }
    .conversation-list, .message-view {
        background-color: var(--white);
        border: 1px solid var(--medium-gray);
        border-radius: var(--border-radius-lg);
        display: flex;
        flex-direction: column;
    }
    .conversation-list .list-group-item {
        border-left: 4px solid transparent;
    }
    .conversation-list .list-group-item.active {
        border-left-color: var(--primary-color);
        background-color: var(--primary-color-light);
    }
    .message-view-header {
        padding: 1rem;
        border-bottom: 1px solid var(--medium-gray);
    }
    .message-thread {
        flex-grow: 1;
        overflow-y: auto;
        padding: 1rem;
    }
    .message-bubble {
        max-width: 70%;
        padding: 0.75rem 1rem;
        border-radius: 1rem;
        margin-bottom: 1rem;
    }
    .message-bubble.sent {
        background-color: var(--primary-color);
        color: white;
        margin-left: auto;
        border-bottom-right-radius: 0.25rem;
    }
    .message-bubble.received {
        background-color: var(--light-gray);
        margin-right: auto;
        border-bottom-left-radius: 0.25rem;
    }
    .message-bubble .meta {
        font-size: 0.75rem;
        opacity: 0.8;
        margin-top: 0.5rem;
    }
    .message-reply-form {
        padding: 1rem;
        border-top: 1px solid var(--medium-gray);
        background-color: var(--light-gray);
    }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0">Messages</h1>
        <a href="compose_message.php" class="btn btn-primary"><i class="bi bi-pencil-square me-2"></i>New Message</a>
    </div>

    <div class="messaging-container">
        <!-- Conversation List -->
        <div class="conversation-list">
            <div class="p-3 border-bottom"><strong>Conversations</strong></div>
            <div class="list-group list-group-flush" style="overflow-y: auto;">
                <?php if (empty($conversations)): ?>
                    <div class="p-3 text-muted">No conversations yet.</div>
                <?php else: ?>
                    <?php foreach ($conversations as $convo): ?>
                        <a href="messages.php?conversation_id=<?php echo $convo['message_id']; ?>" 
                           class="list-group-item list-group-item-action <?php echo ($selected_conversation_id == $convo['message_id']) ? 'active' : ''; ?>">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($convo['other_user']); ?></h6>
                                <small class="text-muted"><?php echo date('M j', strtotime($convo['created_at'])); ?></small>
                            </div>
                            <p class="mb-1 text-truncate"><?php echo htmlspecialchars($convo['subject']); ?></p>
                            <?php if ($convo['unread_count'] > 0): ?>
                                <span class="badge bg-danger rounded-pill">New</span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Message View -->
        <div class="message-view">
            <?php if (!$selected_conversation_id || empty($messages)): ?>
                <div class="d-flex flex-column justify-content-center align-items-center h-100 text-muted">
                    <i class="bi bi-chat-square-dots-fill" style="font-size: 4rem;"></i>
                    <p class="mt-3">Select a conversation to view messages.</p>
                </div>
            <?php else: ?>
                <!-- Header -->
                <div class="message-view-header">
                    <h5><?php echo htmlspecialchars($current_conversation['subject']); ?></h5>
                    <?php
                        $other_user_id = ($current_conversation['sender_id'] == $user_id) ? $current_conversation['receiver_id'] : $current_conversation['sender_id'];
                        $other_user_name = ($current_conversation['sender_id'] == $user_id) ? $messages[0]['sender_username'] : $messages[0]['sender_username'];
                    ?>
                    <p class="text-muted mb-0">Conversation with 
                        <?php
                            $other_user_id = ($current_conversation['sender_id'] == $user_id) ? $current_conversation['receiver_id'] : $current_conversation['sender_id'];
                            $other_user_name = '';
                            foreach($messages as $msg) {
                                if($msg['sender_id'] != $user_id) {
                                    $other_user_name = $msg['sender_username'];
                                    break;
                                }
                            }
                             if (empty($other_user_name)) {
                                $other_user_name = $messages[0]['sender_username'];
                            }
                            echo htmlspecialchars($other_user_name);
                        ?>
                    </p>
                </div>

                <!-- Message Thread -->
                <div class="message-thread">
                    <?php foreach ($messages as $msg): ?>
                        <?php $bubble_class = ($msg['sender_id'] == $user_id) ? 'sent' : 'received'; ?>
                        <div class="message-bubble <?php echo $bubble_class; ?>">
                            <div><?php echo nl2br(htmlspecialchars($msg['content'])); ?></div>
                            <div class="meta text-end">
                                <?php if ($bubble_class == 'sent') echo 'You'; else echo htmlspecialchars($msg['sender_username']); ?>
                                - <?php echo date('M j, g:i a', strtotime($msg['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Reply Form -->
                <div class="message-reply-form">
                    <form action="messages.php" method="POST">
                        <input type="hidden" name="send_message" value="1">
                        <input type="hidden" name="parent_message_id" value="<?php echo $current_conversation['message_id']; ?>">
                        <input type="hidden" name="receiver_id" value="<?php echo $other_user_id; ?>">
                        <input type="hidden" name="subject" value="<?php echo htmlspecialchars($current_conversation['subject']); ?>">
                        <div class="input-group">
                            <textarea name="content" class="form-control" placeholder="Type your reply..." rows="2" required></textarea>
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-send-fill"></i> Send
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

```

### 3. New "Compose Message" Page (`compose_message.php`)

This page provides a dedicated form for starting a new conversation.

```diff