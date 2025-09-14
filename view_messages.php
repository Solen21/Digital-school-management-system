<?php
session_start();

// 1. Security Check: Ensure the user is a teacher.
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';
require_once 'functions.php';

$teacher_user_id = $_SESSION['user_id'];
$messages = [];
$error_message = '';
$success_message = '';

// --- Handle POST request to send a reply ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_reply'])) {
    $recipient_user_id = $_POST['recipient_user_id'];
    $subject = $_POST['subject'];
    $body = $_POST['body'];

    if (empty($recipient_user_id) || empty($subject) || empty($body)) {
        $error_message = "An error occurred. Could not send reply.";
    } else {
        try {
            $sql_insert_reply = "INSERT INTO messages (sender_user_id, recipient_user_id, subject, body, is_read) VALUES (?, ?, ?, ?, 0)";
            $stmt_insert = mysqli_prepare($conn, $sql_insert_reply);
            mysqli_stmt_bind_param($stmt_insert, "iiss", $teacher_user_id, $recipient_user_id, $subject, $body);
            mysqli_stmt_execute($stmt_insert);
            mysqli_stmt_close($stmt_insert);

            create_notification($conn, $recipient_user_id, "You have a new message from " . $_SESSION['username'], 'view_messages.php');
            $success_message = "Your reply has been sent successfully.";
        } catch (Exception $e) {
            $error_message = "Database error: Could not send reply.";
        }
    }
}

// 2. Fetch all messages for this teacher.
// We join on users and then guardians to get the sender's name.
$sql = "
    SELECT 
        m.message_id, m.subject, m.body, m.created_at, m.is_read, m.sender_user_id,
        g.name as sender_name
    FROM messages m
    JOIN users u ON m.sender_user_id = u.user_id
    JOIN guardians g ON u.user_id = g.user_id
    WHERE m.recipient_user_id = ?
    ORDER BY m.created_at DESC
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $messages[] = $row;
}
mysqli_stmt_close($stmt);

// 3. Mark all unread messages as read now that the teacher is viewing them.
$sql_mark_read = "UPDATE messages SET is_read = 1 WHERE recipient_user_id = ? AND is_read = 0";
$stmt_mark_read = mysqli_prepare($conn, $sql_mark_read);
mysqli_stmt_bind_param($stmt_mark_read, "i", $teacher_user_id);
mysqli_stmt_execute($stmt_mark_read);
mysqli_stmt_close($stmt_mark_read);

mysqli_close($conn);
$page_title = 'My Messages';
include 'header.php';
?>
<style>
    .accordion-button.unread {
        font-weight: bold;
    }
    .accordion-button.unread::after {
        background-image: var(--bs-accordion-btn-icon);
    }
    .message-body {
        white-space: pre-wrap;
        line-height: 1.6;
    }
</style>
<div class="container">
    <h1>My Messages</h1>
    <p class="lead">Messages from guardians regarding their children in your classes.</p>

    <?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>

    <?php if (empty($messages)): ?>
        <div class="alert alert-info">You have no messages.</div>
    <?php else: ?>
        <div class="accordion" id="messagesAccordion">
            <?php foreach ($messages as $index => $msg): ?>
            <div class="accordion-item">
                <h2 class="accordion-header" id="heading-<?php echo $msg['message_id']; ?>">
                    <button class="accordion-button <?php if (!$msg['is_read']) echo 'unread'; ?> <?php if ($index > 0) echo 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $msg['message_id']; ?>" aria-expanded="<?php echo $index == 0 ? 'true' : 'false'; ?>" aria-controls="collapse-<?php echo $msg['message_id']; ?>">
                        <div class="d-flex justify-content-between w-100">
                            <span>
                                <span class="fw-bold me-2"><?php echo htmlspecialchars($msg['sender_name']); ?>:</span>
                                <?php echo htmlspecialchars($msg['subject']); ?>
                            </span>
                            <span class="text-muted small me-3"><?php echo date('M j, Y, g:i a', strtotime($msg['created_at'])); ?></span>
                        </div>
                    </button>
                </h2>
                <div id="collapse-<?php echo $msg['message_id']; ?>" class="accordion-collapse collapse <?php if ($index == 0) echo 'show'; ?>" aria-labelledby="heading-<?php echo $msg['message_id']; ?>" data-bs-parent="#messagesAccordion">
                    <div class="accordion-body message-body">
                        <?php echo htmlspecialchars($msg['body']); ?>
                        <hr>
                        <button type="button" class="btn btn-sm btn-primary reply-btn" 
                                data-bs-toggle="modal" 
                                data-bs-target="#replyModal"
                                data-recipient-name="<?php echo htmlspecialchars($msg['sender_name']); ?>"
                                data-recipient-id="<?php echo $msg['sender_user_id']; ?>"
                                data-subject="<?php echo htmlspecialchars($msg['subject']); ?>">
                            <i class="bi bi-reply-fill me-1"></i> Reply
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Reply Modal -->
<div class="modal fade" id="replyModal" tabindex="-1" aria-labelledby="replyModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="replyModalLabel">Send Reply</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="view_messages.php" method="POST">
        <div class="modal-body">
            <input type="hidden" name="send_reply" value="1">
            <input type="hidden" name="recipient_user_id" id="modal_recipient_id">
            <div class="mb-3">
                <label class="form-label">To:</label>
                <input type="text" class="form-control" id="modal_recipient_name" readonly>
            </div>
            <div class="mb-3">
                <label for="modal_subject" class="form-label">Subject</label>
                <input type="text" class="form-control" id="modal_subject" name="subject" required>
            </div>
            <div class="mb-3">
                <label for="modal_body" class="form-label">Message</label>
                <textarea class="form-control" id="modal_body" name="body" rows="6" required></textarea>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Send Reply</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const replyModal = document.getElementById('replyModal');
    replyModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        document.getElementById('modal_recipient_name').value = button.getAttribute('data-recipient-name');
        document.getElementById('modal_recipient_id').value = button.getAttribute('data-recipient-id');
        document.getElementById('modal_subject').value = 'Re: ' + button.getAttribute('data-subject');
        document.getElementById('modal_body').focus();
    });
});
</script>
<?php include 'footer.php'; ?>