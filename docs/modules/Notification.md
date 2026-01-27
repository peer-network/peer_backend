### How to add a new notification

1. **Create the notification class**

   * Copy an existing notification class (e.g. `PostLikeNotification`) and rename it to your new notification (e.g. `PostCommentNotification`).

2. **Define title and body texts**

   * Update the notification **title** and **body** in your new class.
   * Add the corresponding constants to:
     `src/config/constants/ConstantsNotification.php`

3. **Trigger the notification from the right action**

   * Implement the logic where the event happens (the “trigger”/queue).
   * Example: if it should fire when a post is liked, add it in:
     `src/App/PostInfoService.php` → `likePost()`

4. ### **Make Sure to restart Worker on Server after any changes related to notifications**

**Tip:** Reuse existing notifications as a reference to keep naming, constants, and triggering consistent.



### Deployment Steps
1. Install OS package to use redis

```
sudo apt update
sudo apt install -y redis-server
sudo service redis-server start
redis-cli ping
```

2. Enable and configure Redis in PHP & Restart server
```
sudo phpenmod redis
```

3. Configure Redis env variables (in `.env`)

```
REDIS_SCHEME=tcp
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=0
REDIS_PASSWORD=
NOTIFICATIONS_QUEUE=notifications_queue
NOTIFICATIONS_QUEUE_BLOCK_TIMEOUT=5
NOTIFICATIONS_WORKER_MAX_RUNTIME=0
```

4. Set Notification server worker on server. use file `docs\notifications-worker.service`.
Please set `WorkingDirectory` and `ExecStart` as per OS setup.

5. Restart Worker after any changes to the worker script, messages or configuration.


### Redis & Worker Health Checks

```
redis-cli ping
```


Worker status:

```
systemctl status notifications-worker
```

`NOTIFICATIONS_WORKER_MAX_RUNTIME=0` means no auto-exit. Set a number of seconds to let systemd restart periodically.


### Queue Payload Example

```
{
  "action": "POST_LIKE",
  "payload": {"contentId": "<post-id>"},
  "initiator": {
    "class": "Fawaz\\Services\\Notifications\\InitiatorReceiver\\UserInitiator",
    "id": "<user-id>"
  },
  "receivers": ["<user-id>"]
}
```
