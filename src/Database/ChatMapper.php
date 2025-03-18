<?php
namespace Fawaz\Database;

use PDO;
use Fawaz\App\Chat;
use Fawaz\App\ChatParticipants;
use Fawaz\App\ChatParticipantInfo;
use Fawaz\App\ChatMessages;
use Fawaz\App\NewsFeed;
use Psr\Log\LoggerInterface;

class ChatMapper
{
    public function __construct(protected LoggerInterface $logger, protected PDO $db)
    {
    }

    public function isSameUser(string $userid, string $currentUserId): bool
    {
        return $userid === $currentUserId;
    }

    public function fetchAll(int $offset, int $limit): array
    {
        $this->logger->info("ChatMapper.fetchAll started");

        $sql = "SELECT * FROM chats WHERE ispublic >= 0 ORDER BY chatid ASC LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $results = array_map(fn($row) => new Chat($row), $stmt->fetchAll(PDO::FETCH_ASSOC));

            $this->logger->info(
                $results ? "Fetched chats successfully" : "No chats found",
                ['count' => count($results)]
            );

            return $results;
        } catch (\PDOException $e) {
            $this->logger->error("Error fetching chats from database", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    public function isCreator(string $chatid, string $currentUserId): bool
    {
        $this->logger->info("ChatMapper.isCreator started");

        $sql = "SELECT COUNT(*) FROM chats WHERE chatid = :chatid AND creatorid = :currentUserId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['chatid' => $chatid, 'currentUserId' => $currentUserId]);

        return (bool) $stmt->fetchColumn();
    }

    public function isPrivate(string $chatid): bool
    {
        $this->logger->info("ChatMapper.isPrivate started");

        $sql = "SELECT COUNT(*) FROM chats WHERE chatid = :chatid AND ispublic = 0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['chatid' => $chatid]);

        return (bool) $stmt->fetchColumn();
    }

    public function fetchFriends(string $userid): array
    {
        $this->logger->info("ChatMapper.fetchFriends started");

        $sql = "SELECT u.uid, u.username, u.updatedat, u.biography, u.img 
                FROM follows f1 
                INNER JOIN follows f2 ON f1.followedid = f2.followerid 
                INNER JOIN users u ON f1.followedid = u.uid 
                WHERE f1.followerid = :userid 
                AND f2.followedid = :userid";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['userid' => $userid]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPrivateChatBetweenUsers(string $userId1, string $userId2): bool
    {
        $this->logger->info("ChatMapper.getPrivateChatBetweenUsers started");

        $sql = "SELECT chatid FROM chats
                WHERE ispublic = 0 
                AND chatid IN (
                    SELECT chatid FROM chatparticipants WHERE userid IN (:userId1, :userId2)
                    GROUP BY chatid
                    HAVING COUNT(DISTINCT userid) = 2
                ) LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['userId1' => $userId1, 'userId2' => $userId2]);

        return (bool) $stmt->fetchColumn();
    }

    public function loadById(string $id): Chat|false
    {
        $this->logger->info("ChatMapper.loadById started");

        $sql = "SELECT * FROM chats WHERE chatid = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data !== false) {
            return new Chat($data);
        }

        $this->logger->warning("No chat found with id", ['id' => $id]);
        return false;
    }

    public function loadChatById(?array $args = [], string $currentUserId): Chat|array
    {
        $this->logger->info("ChatMapper.loadChatById started");

        $chatId = $args['chatid'] ?? null;

        try {
            // Check if chat exists
            $chatExistsSql = "SELECT chatid FROM chats WHERE chatid = :chatid";
            $chatExistsStmt = $this->db->prepare($chatExistsSql);
            $chatExistsStmt->execute(['chatid' => $chatId]);
            $chatExists = $chatExistsStmt->fetch(PDO::FETCH_ASSOC);

            if (!$chatExists) {
                $this->logger->warning("Chat ID not found", ['chatid' => $chatId]);
                return [
                    'status' => 'error',
                    'ResponseCode' => 'Chat ID not found',
                ];
            }

            // Check if user is a participant
            $isParticipantSql = "
                SELECT EXISTS(
                    SELECT 1
                    FROM chatparticipants 
                    WHERE chatid = :chatid AND userid = :currentUserId
                ) AS isParticipant;
            ";
            $isParticipantStmt = $this->db->prepare($isParticipantSql);
            $isParticipantStmt->execute([
                'chatid' => $chatId,
                'currentUserId' => $currentUserId,
            ]);
            $isParticipant = (bool)$isParticipantStmt->fetchColumn();

            if (!$isParticipant) {
                $this->logger->warning("User is not allowed to access chat", [
                    'chatid' => $chatId,
                    'currentUserId' => $currentUserId,
                ]);
                return [
                    'status' => 'error',
                    'ResponseCode' => 'AccessDenied',
                ];
            }

            // Load chat details
            $sql = "
                SELECT 
                    chatid, 
                    creatorid, 
                    name, 
                    image, 
                    ispublic, 
                    createdat, 
                    updatedat
                FROM chats 
                WHERE chatid = :chatid;
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['chatid' => $chatId]);

            $chatRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$chatRow) {
                $this->logger->warning("No chat details found for chatid", ['chatid' => $chatId]);
                return [
                    'status' => 'error',
                    'ResponseCode' => 'ChatNotFound',
                ];
            }

            // Fetch messages with pagination or using last_seen_message_id
            $messageLimit = min(max((int)($args['messageLimit'] ?? 10), 1), 20);
            $messageOffset = isset($args['messageOffset']) ? max((int)($args['messageOffset']), 0) : null;

            // If messageOffset is provided, use OFFSET instead of lastSeenMessageId logic
            if ($messageOffset !== null) {
                $chatMessagesSql = "
                    SELECT 
                        messid, 
                        chatid, 
                        userid, 
                        content, 
                        createdat 
                    FROM chatmessages 
                    WHERE chatid = :chatid 
                    ORDER BY createdat DESC
                    LIMIT :limit OFFSET :offset;
                ";
                $messageStmt = $this->db->prepare($chatMessagesSql);
                $messageStmt->bindValue('chatid', $chatId);
                $messageStmt->bindValue('limit', $messageLimit, PDO::PARAM_INT);
                $messageStmt->bindValue('offset', $messageOffset, PDO::PARAM_INT);
            } else {
                // If messageOffset is not set, fallback to last_seen_message_id logic
                $lastSeenSql = "
                    SELECT last_seen_message_id 
                    FROM user_chat_status 
                    WHERE userid = :userid AND chatid = :chatid
                ";
                $lastSeenStmt = $this->db->prepare($lastSeenSql);
                $lastSeenStmt->execute([
                    'userid' => $currentUserId,
                    'chatid' => $chatId,
                ]);
                $lastSeenMessageId = (int) $lastSeenStmt->fetchColumn();

                $chatMessagesSql = "
                    SELECT 
                        messid, 
                        chatid, 
                        userid, 
                        content, 
                        createdat 
                    FROM chatmessages 
                    WHERE chatid = :chatid 
                    AND messid > :lastSeenMessageId
                    ORDER BY createdat DESC
                    LIMIT :limit;
                ";
                $messageStmt = $this->db->prepare($chatMessagesSql);
                $messageStmt->bindValue('chatid', $chatId);
                $messageStmt->bindValue('lastSeenMessageId', $lastSeenMessageId, PDO::PARAM_INT);
                $messageStmt->bindValue('limit', $messageLimit, PDO::PARAM_INT);
            }

            $messageStmt->execute();
            $chatMessages = $messageStmt->fetchAll(PDO::FETCH_ASSOC);

            if ($chatMessages) {
                $lastMessage = $chatMessages[0] ?? null;
                if ($lastMessage) {
                    $this->updateLastSeenMessage($currentUserId, $chatId, $lastMessage['messid']);
                }
            }

            // Fetch participants
            $chatParticipantsSql = "
                SELECT 
                    p.userid, 
                    u.username, 
                    u.img, 
                    u.slug, 
                    p.hasaccess 
                FROM chatparticipants p 
                JOIN users u ON p.userid = u.uid 
                WHERE p.chatid = :chatid 
                ORDER BY p.createdat DESC;
            ";
            $participantStmt = $this->db->prepare($chatParticipantsSql);
            $participantStmt->execute(['chatid' => $chatId]);
            $chatParticipants = $participantStmt->fetchAll(PDO::FETCH_ASSOC);

            // Return the simplified response
            return [
                'status' => 'success',
                'ResponseCode' => 'Chat fetched successfullyd',
                'data' => new Chat([
                    'chatid' => $chatRow['chatid'],
                    'creatorid' => $chatRow['creatorid'],
                    'name' => $chatRow['name'],
                    'image' => $chatRow['image'],
                    'ispublic' => (bool)$chatRow['ispublic'],
                    'createdat' => $chatRow['createdat'],
                    'updatedat' => $chatRow['updatedat'],
                    'chatmessages' => $chatMessages, // Messages with pagination
                    'chatparticipants' => $chatParticipants, // Participants
                ]),
            ];

        } catch (PDOException $e) {
            $this->logger->error("Database error occurred in loadChatById", [
                'error' => $e->getMessage(),
                'chatid' => $chatId,
                'currentUserId' => $currentUserId,
            ]);
            return [
                'status' => 'error',
                'ResponseCode' => 'DatabaseError',
            ];
        }
    }

    public function getChatMessages(array $args): array
    {
        $this->logger->info("ChatMapper.getChatMessages started");

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);
        $chatId = $args['chatid'] ?? null;

        try {
            $sql = "SELECT * FROM chatmessages WHERE chatid = :chatid ORDER BY createdat ASC LIMIT :limit OFFSET :offset";

            $params['limit'] = $limit;
            $params['offset'] = $offset;
            $params['chatid'] = $chatId;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            //$results = array_map(fn($row) => new ChatMessages($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
            //return $results ?: [];
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());
            return [];
        } catch (Exception $e) {
            error_log('General error: ' . $e->getMessage());
            return [];
        }
    }

    public function insert(Chat $chat): Chat
    {
        $this->logger->info("ChatMapper.insert started");

        $data = $chat->getArrayCopy();
        $updatedAt = (new \DateTime())->format('Y-m-d H:i:s.u'); 

        $query = "INSERT INTO chats (chatid, creatorid, image, name, ispublic, createdat, updatedat) 
                  VALUES (:chatid, :creatorid, :image, :name, :ispublic, :createdat, :updatedat)";

        try {
            $stmt = $this->db->prepare($query);

            // Explicitly bind each value
            $stmt->bindValue(':chatid', $data['chatid'], \PDO::PARAM_STR);
            $stmt->bindValue(':creatorid', $data['creatorid'], \PDO::PARAM_STR);
            $stmt->bindValue(':image', $data['image'], \PDO::PARAM_STR);
            $stmt->bindValue(':name', $data['name'], \PDO::PARAM_STR);
            $stmt->bindValue(':ispublic', $data['ispublic'], \PDO::PARAM_INT);
            $stmt->bindValue(':createdat', $updatedAt, \PDO::PARAM_STR); 
            $stmt->bindValue(':updatedat', $updatedAt, \PDO::PARAM_STR); 

            $stmt->execute();

            $this->logger->info("Inserted new chat into database", ['chat' => $data]);

            return new Chat(array_merge($data, ['updatedat' => $updatedAt]));
        } catch (\PDOException $e) {
            $this->logger->error("Failed to insert new chat into database", [
                'chat' => $data,
                'exception' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to insert chat: " . $e->getMessage());
        }
    }

    public function insertFeed(NewsFeed $feed): NewsFeed
    {
        $this->logger->info("ChatMapper.insertFeed started");

        $data = $feed->getArrayCopy();
        $createdAt = $data['createdat'];
        $updatedAt = (new \DateTime())->format('Y-m-d H:i:s.u');

        $query = "INSERT INTO newsfeed (feedid, creatorid, image, name, createdat, updatedat) 
                  VALUES (:feedid, :creatorid, :image, :name, :createdat, :updatedat)";

        try {
            $stmt = $this->db->prepare($query);

            // Explicitly bind each value
            $stmt->bindValue(':feedid', $data['feedid'], \PDO::PARAM_STR);
            $stmt->bindValue(':creatorid', $data['creatorid'], \PDO::PARAM_STR);
            $stmt->bindValue(':image', $data['image'], \PDO::PARAM_STR);
            $stmt->bindValue(':name', $data['name'], \PDO::PARAM_STR);
            $stmt->bindValue(':createdat', $createdAt, \PDO::PARAM_STR); 
            $stmt->bindValue(':updatedat', $updatedAt, \PDO::PARAM_STR); 

            $stmt->execute();

            $this->logger->info("Inserted new feed into database", ['feed' => $data]);

            // Return the NewsFeed object with the updated timestamp
            return new NewsFeed(array_merge($data, ['updatedat' => $updatedAt]));
        } catch (\PDOException $e) {
            $this->logger->error("Failed to insert feed into database", [
                'feed' => $data,
                'exception' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to insert feed: " . $e->getMessage());
        }
    }

    public function insertPart(ChatParticipants $participant): array
    {
        $this->logger->info("ChatMapper.insertPart started");

        $data = $participant->getArrayCopy();

        try {
            $userid = $data['userid'];

            // Check if the user exists
            $userExistsQuery = "SELECT COUNT(*) FROM users WHERE uid = :userid";
            $stmt = $this->db->prepare($userExistsQuery);
            $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmt->execute();
            $userExists = (bool) $stmt->fetchColumn();

            if (!$userExists) {
                $this->logger->warning("User does not exist in users", ['userid' => $userid]);
                return [
                    'status' => 'error',
                    'ResponseCode' => 'User does not exist in users'
                ];
            }

            // Check if the participant already exists
            $participantExistsQuery = "SELECT COUNT(*) FROM chatparticipants WHERE chatid = :chatid AND userid = :userid";
            $stmt = $this->db->prepare($participantExistsQuery);
            $stmt->bindValue(':chatid', $data['chatid'], \PDO::PARAM_STR);
            $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmt->execute();
            $participantExists = (bool) $stmt->fetchColumn();

            if ($participantExists) {
                $this->logger->warning("Participant already exists", ['userid' => $userid]);
                return [
                    'status' => 'error',
                    'ResponseCode' => 'Participant already exists'
                ];
            }

            // Insert the new participant
            $query = "INSERT INTO chatparticipants (chatid, userid, hasaccess, createdat) 
                      VALUES (:chatid, :userid, :hasaccess, :createdat)";
            $stmt = $this->db->prepare($query);

            // Bind values explicitly
            $stmt->bindValue(':chatid', $data['chatid'], \PDO::PARAM_STR);
            $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmt->bindValue(':hasaccess', $data['hasaccess'], \PDO::PARAM_INT);
            $stmt->bindValue(':createdat', $data['createdat'], \PDO::PARAM_STR);

            $stmt->execute();

            $this->logger->info("Inserted new participant into database", ['participant' => $data]);

            return [
                'status' => 'success',
                'ResponseCode' => 'Participant successfully inserted.',
                'affectedRows' => new ChatParticipants($data)
            ];
        } catch (\Exception $e) {
            $this->logger->error("Error inserting participant", ['exception' => $e->getMessage()]);
            return [
                'status' => 'error',
                'ResponseCode' => $e->getMessage()
            ];
        }
    }

    public function insertMess(ChatMessages $chatmessage): array
    {
        $this->logger->info("ChatMapper.insertMess started");

        $data = $chatmessage->getArrayCopy();

        try {
            $userid = $data['userid'];

            // Check if the user exists
            $userExistsQuery = "SELECT COUNT(*) FROM users WHERE uid = :userid";
            $stmt = $this->db->prepare($userExistsQuery);
            $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmt->execute();
            $userExists = (bool) $stmt->fetchColumn();

            if (!$userExists) {
                $this->logger->warning("User did not exist in users", ['userid' => $userid]);
                return [
                    'status' => 'error',
                    'ResponseCode' => 'User does not exist in users'
                ];
            }

            // Check if the user is a participant in the chat
            $participantExistsQuery = "SELECT COUNT(*) FROM chatparticipants WHERE chatid = :chatid AND userid = :userid";
            $stmt = $this->db->prepare($participantExistsQuery);
            $stmt->bindValue(':chatid', $data['chatid'], \PDO::PARAM_STR);
            $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmt->execute();
            $participantExists = (bool) $stmt->fetchColumn();

            if (!$participantExists) {
                $this->logger->warning("User is not a participant of the chat", ['userid' => $userid]);
                return [
                    'status' => 'error',
                    'ResponseCode' => 'You are not allowed to write messages'
                ];
            }

            // Insert the new chat message
            $query = "INSERT INTO chatmessages (chatid, userid, content, createdat) 
                      VALUES (:chatid, :userid, :content, :createdat)";
            $stmt = $this->db->prepare($query);

            // Bind each value explicitly
            $stmt->bindValue(':chatid', $data['chatid'], \PDO::PARAM_STR);
            $stmt->bindValue(':userid', $data['userid'], \PDO::PARAM_STR);
            $stmt->bindValue(':content', $data['content'], \PDO::PARAM_STR);
            $stmt->bindValue(':createdat', $data['createdat'], \PDO::PARAM_STR);

            $stmt->execute();

            // Get the last inserted ID
            $data['messid'] = $lastInsertedId = (int)$this->db->lastInsertId();

            $this->logger->info("Inserted new chat message into database", [
                'chatmessage' => $data,
                'lastInsertedId' => $lastInsertedId
            ]);

            // Update the last seen message
            $this->updateLastSeenMessage($userid, $data['chatid'], $lastInsertedId);

            return [
                'status' => 'success',
                'ResponseCode' => 'Message successfully inserted.',
                'affectedRows' => [$data]
            ];
        } catch (\Exception $e) {
            $this->logger->error("Error inserting message", [
                'exception' => $e->getMessage()
            ]);
            return [
                'status' => 'error',
                'ResponseCode' => $e->getMessage()
            ];
        }
    }

    public function update(Chat $chat): Chat
    {
        $this->logger->info("ChatMapper.update started");

        $data = $chat->getArrayCopy();
        $query = "UPDATE chats
                  SET image = :image,
                      name = :name,
                      creatorid = :creatorid,
                      ispublic = :ispublic,
                      createdat = :createdat,
                      updatedat = :updatedat
                  WHERE chatid = :chatid";

        try {
            $stmt = $this->db->prepare($query);

            // Explicitly bind each value
            $stmt->bindValue(':image', $data['image'], \PDO::PARAM_STR);
            $stmt->bindValue(':name', $data['name'], \PDO::PARAM_STR);
            $stmt->bindValue(':creatorid', $data['creatorid'], \PDO::PARAM_STR);
            $stmt->bindValue(':ispublic', $data['ispublic'], \PDO::PARAM_INT);
            $stmt->bindValue(':createdat', $data['createdat'], \PDO::PARAM_STR); 
            $stmt->bindValue(':updatedat', $data['updatedat'], \PDO::PARAM_STR); 
            $stmt->bindValue(':chatid', $data['chatid'], \PDO::PARAM_STR);

            $stmt->execute();

            $this->logger->info("Updated chat in database", ['chat' => $data]);

            return new Chat($data);
        } catch (\PDOException $e) {
            $this->logger->error("Error updating chat in database", [
                'chat' => $data,
                'exception' => $e->getMessage()
            ]);

            throw new \RuntimeException("Failed to update chat: " . $e->getMessage());
        }
    }

    public function delete(string $id): bool
    {
        $this->logger->info("ChatMapper.delete started");

        $query = "DELETE FROM chats WHERE chatid = :id";

        try {
            $stmt = $this->db->prepare($query);

            // Explicitly bind the `id` parameter
            $stmt->bindValue(':id', $id, \PDO::PARAM_STR);

            $stmt->execute();

            $deleted = (bool)$stmt->rowCount();
            if ($deleted) {
                $this->logger->info("Deleted chat from database", ['id' => $id]);
            } else {
                $this->logger->warning("No chat found to delete in database for id", ['id' => $id]);
            }

            return $deleted;
        } catch (\PDOException $e) {
            $this->logger->error("Error deleting chat from database", [
                'id' => $id,
                'exception' => $e->getMessage()
            ]);

            throw new \RuntimeException("Failed to delete chat: " . $e->getMessage());
        }
    }

    public function deleteParticipant(string $chatid, string $participantId): bool
    {
        $this->logger->info("ChatMapper.deleteParticipant started");

        $query = "DELETE FROM chatparticipants WHERE chatid = :chatid AND userid = :participantId";

        try {
            $stmt = $this->db->prepare($query);

            // Explicitly bind the parameters
            $stmt->bindValue(':chatid', $chatid, \PDO::PARAM_STR);
            $stmt->bindValue(':participantId', $participantId, \PDO::PARAM_STR);

            $stmt->execute();

            $deleted = (bool)$stmt->rowCount();
            if ($deleted) {
                $this->logger->info("Deleted participant from chat", ['chatid' => $chatid, 'participantId' => $participantId]);
            } else {
                $this->logger->warning("No participant found to delete from chat", ['chatid' => $chatid, 'participantId' => $participantId]);
            }

            return $deleted;
        } catch (\PDOException $e) {
            $this->logger->error("Error deleting participant from chat", [
                'chatid' => $chatid,
                'participantId' => $participantId,
                'exception' => $e->getMessage()
            ]);

            throw new \RuntimeException("Failed to delete participant from chat: " . $e->getMessage());
        }
    }

    public function deleteMessage(string $chatid, int $messid): bool
    {
        $this->logger->info("ChatMapper.deleteMessage started");

        $query = "DELETE FROM chatmessages WHERE chatid = :chatid AND messid = :messid";

        try {
            $stmt = $this->db->prepare($query);

            // Explicitly bind the parameters
            $stmt->bindValue(':chatid', $chatid, \PDO::PARAM_STR);
            $stmt->bindValue(':messid', $messid, \PDO::PARAM_INT);

            $stmt->execute();

            $deleted = (bool)$stmt->rowCount();
            if ($deleted) {
                $this->logger->info("Deleted message from chat", ['chatid' => $chatid, 'messid' => $messid]);
            } else {
                $this->logger->warning("No message found to delete from chat", ['chatid' => $chatid, 'messid' => $messid]);
            }

            return $deleted;
        } catch (\PDOException $e) {
            $this->logger->error("Error deleting message from chat", [
                'chatid' => $chatid,
                'messid' => $messid,
                'exception' => $e->getMessage()
            ]);

            throw new \RuntimeException("Failed to delete message from chat: " . $e->getMessage());
        }
    }

    public function findChatser(?array $args = [], string $currentUserId): array
    {
        $this->logger->info("ChatMapper.findChatser started");

        // Pagination arguments for chats
        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);
        $from = $args['from'] ?? null;
        $to = $args['to'] ?? null;
        $sortBy = $args['sortBy'] ?? null;

        $whereClauses = ["c.ispublic >= 0"];
        $orderBy = match ($sortBy) {
            'newest' => "c.createdat DESC",
            default => "c.createdat ASC",
        };

        if ($from !== null) {
            $whereClauses[] = "c.createdat >= :from";
        }
        if ($to !== null) {
            $whereClauses[] = "c.createdat <= :to";
        }

        $sql = sprintf(
            "SELECT 
                c.chatid, 
                c.creatorid, 
                c.name, 
                c.image, 
                c.ispublic, 
                c.createdat, 
                c.updatedat
            FROM chats c
            JOIN chatparticipants p ON c.chatid = p.chatid AND p.userid = :currentUserId
            WHERE %s
            ORDER BY %s
            LIMIT :limit OFFSET :offset",
            implode(" AND ", $whereClauses),
            $orderBy
        );

        try {
            $stmt = $this->db->prepare($sql);

            // Bind values
            $stmt->bindValue(':currentUserId', $currentUserId, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            if ($from !== null) {
                $stmt->bindValue(':from', $from, PDO::PARAM_STR);
            }
            if ($to !== null) {
                $stmt->bindValue(':to', $to, PDO::PARAM_STR);
            }

            $stmt->execute();

            $chats = [];
            while ($chatRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Fetch all messages with pagination for the current chatid
                $messageOffset = max((int)($args['messageOffset'] ?? 0), 0);
                $messageLimit = min(max((int)($args['messageLimit'] ?? 10), 1), 20);

                $chatMessagesSql = "
                    SELECT 
                        messid, 
                        chatid, 
                        userid, 
                        content, 
                        createdat 
                    FROM 
                        chatmessages 
                    WHERE 
                        chatid = :chatid 
                    ORDER BY 
                        createdat ASC
                    LIMIT :limit OFFSET :offset";

                $messageStmt = $this->db->prepare($chatMessagesSql);
                $messageStmt->bindValue(':chatid', $chatRow['chatid'], PDO::PARAM_STR);
                $messageStmt->bindValue(':limit', $messageLimit, PDO::PARAM_INT);
                $messageStmt->bindValue(':offset', $messageOffset, PDO::PARAM_INT);
                $messageStmt->execute();
                $chatMessages = $messageStmt->fetchAll(PDO::FETCH_ASSOC);

                // Fetch all participants for the current chatid
                $chatParticipantsSql = "
                    SELECT 
                        p.userid, 
                        u.username, 
                        u.img, 
                        u.slug, 
                        p.hasaccess 
                    FROM 
                        chatparticipants p 
                    JOIN 
                        users u ON p.userid = u.uid 
                    WHERE 
                        p.chatid = :chatid 
                    ORDER BY 
                        p.createdat DESC";

                $participantStmt = $this->db->prepare($chatParticipantsSql);
                $participantStmt->bindValue(':chatid', $chatRow['chatid'], PDO::PARAM_STR);
                $participantStmt->execute();
                $chatParticipants = $participantStmt->fetchAll(PDO::FETCH_ASSOC);

                // Build the chat object
                $chats[] = new Chat([
                    'chatid' => $chatRow['chatid'],
                    'creatorid' => $chatRow['creatorid'],
                    'name' => $chatRow['name'],
                    'image' => $chatRow['image'],
                    'ispublic' => (bool)$chatRow['ispublic'],
                    'createdat' => $chatRow['createdat'],
                    'updatedat' => $chatRow['updatedat'],
                    'chatmessages' => $chatMessages, // Add messages with pagination
                    'chatparticipants' => $chatParticipants, // Add participants
                ]);
            }

            $this->logger->info(
                $chats ? "Fetched chats with messages and participants from database" : "No chats found in database",
                ['count' => count($chats)]
            );

            return $chats;
        } catch (PDOException $e) {
            $this->logger->error("Database error occurred in findChatser", [
                'error' => $e->getMessage(),
                'sql' => $sql
            ]);
            return [];
        }
    }

    public function fetchMessagesWithPagination(string $chatid, string $user_id, int $limit = 20, int $offset = 0): array
    {
        $this->logger->info("ChatMapper.fetchMessagesWithPagination started");

        $sql = "SELECT messid, content, userid, chatid, createdat 
                FROM chatmessages 
                WHERE chatid = :chatid 
                ORDER BY createdat DESC 
                LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($sql);

            // Bind parameters explicitly
            $stmt->bindValue(':chatid', $chatid, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();

            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = new ChatMessages($row);
            }

            if ($results) {
                $this->logger->info("Fetched messages for chat from database", ['count' => count($results)]);

                // Update the last seen message for the user
                $lastMessage = $results[0] ?? null;
                if ($lastMessage) {
                    $this->updateLastSeenMessage($user_id, $chatid, $lastMessage->getMessId());
                }
            } else {
                $this->logger->warning("No messages found for chat in database", ['chatid' => $chatid]);
            }

            return $results;
        } catch (PDOException $e) {
            $this->logger->error("Database error occurred while fetching messages with pagination", [
                'chatid' => $chatid,
                'exception' => $e->getMessage()
            ]);

            return [];
        }
    }

    public function updateLastSeenMessage(string $user_id, string $chat_id, int $last_seen_message_id): void
    {
        $this->logger->info("ChatMapper.updateLastSeenMessage started");

        $query = "
            INSERT INTO user_chat_status (userid, chatid, last_seen_message_id)
            VALUES (:user_id, :chat_id, :last_seen_message_id)
            ON CONFLICT (userid, chatid)
            DO UPDATE SET last_seen_message_id = EXCLUDED.last_seen_message_id";

        try {
            $stmt = $this->db->prepare($query);

            // Bind parameters explicitly
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_STR);
            $stmt->bindValue(':chat_id', $chat_id, PDO::PARAM_STR);
            $stmt->bindValue(':last_seen_message_id', $last_seen_message_id, PDO::PARAM_INT);

            $stmt->execute();

            $this->logger->info("Updated last seen message for user in chat", [
                'user_id' => $user_id,
                'chat_id' => $chat_id,
                'last_seen_message_id' => $last_seen_message_id
            ]);
        } catch (PDOException $e) {
            $this->logger->error("Failed to update last seen message", [
                'user_id' => $user_id,
                'chat_id' => $chat_id,
                'last_seen_message_id' => $last_seen_message_id,
                'exception' => $e->getMessage()
            ]);

            throw new \RuntimeException("Failed to update last seen message: " . $e->getMessage());
        }
    }

    public function getUnseenMessages(string $user_id, string $chat_id): array|false
    {
        $this->logger->info("ChatMapper.getUnseenMessages started");

        $query = "
            SELECT * FROM chatmessages 
            WHERE chatid = :chat_id 
              AND messid > (
                  SELECT COALESCE(last_seen_message_id, 0)
                  FROM user_chat_status 
                  WHERE chatid = :chat_id 
                  AND userid = :user_id
              )";

        try {
            $stmt = $this->db->prepare($query);

            // Bind parameters explicitly
            $stmt->bindValue(':chat_id', $chat_id, PDO::PARAM_STR);
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_STR);

            $stmt->execute();

            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($messages)) {
                $this->logger->info("Fetched unseen messages from chat", [
                    'user_id' => $user_id,
                    'chat_id' => $chat_id,
                    'message_count' => count($messages),
                ]);
                return $messages;
            } else {
                $this->logger->info("No unseen messages found for chat", [
                    'user_id' => $user_id,
                    'chat_id' => $chat_id,
                ]);
                return false;
            }
        } catch (PDOException $e) {
            $this->logger->error("Failed to fetch unseen messages from chat", [
                'user_id' => $user_id,
                'chat_id' => $chat_id,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
