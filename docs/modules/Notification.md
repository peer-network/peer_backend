### How to add a new notification

1. **Create the notification class**

   * Copy an existing notification class (e.g. `PostLikeNotification`) and rename it to your new notification (e.g. `PostCommentNotification`).

2. **Define title and body texts**

   * Update the notification **title** and **body** in your new class.
   * Add the corresponding constants to:
     `src/config/constants/ConstantsNotification.php`

3. **Trigger the notification from the right action**

   * Implement the logic where the event happens (the “trigger”).
   * Example: if it should fire when a post is liked, add it in:
     `src/App/PostInfoService.php` → `likePost()`

**Tip:** Reuse existing notifications as a reference to keep naming, constants, and triggering consistent.
