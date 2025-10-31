<?php
// Ensure we have session + data
if (!isset($_SESSION)) { session_start(); }
?>
<link rel="stylesheet" href="../styles/theme.css">
<link rel="stylesheet" href="../styles/index.css">
<link rel="stylesheet" href="../styles/typewriter.css">


<div class="friends-page" style="padding: 20px; font-family: sans-serif;">

    <h1>Your Friends</h1>

    <?php if (count($friends) === 0): ?>
        <p>You have no friends yet 😢</p>
    <?php else: ?>
        <ul>
            <?php foreach ($friends as $f): ?>
                <li>
                    <?= htmlspecialchars($f['first_name'] . " " . $f['last_name']) ?>
                    (<?= htmlspecialchars($f['email']) ?>)
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <hr><br>

    <h1>Add New Friends</h1>

    <?php if (count($non_friends) === 0): ?>
        <p>No available users to add 🎉</p>
    <?php else: ?>
        <ul>
            <?php foreach ($non_friends as $nf): ?>
                <li>
                    <?= htmlspecialchars($nf['first_name'] . " " . $nf['last_name']) ?>
                    (<?= htmlspecialchars($nf['email']) ?>)
                    <form action="index.php?action=add_friend" method="post" style="display:inline;">
                        <input type="hidden" name="friend_id" value="<?= $nf['user_id'] ?>">
                        <button type="submit">Add Friend</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
