<?php

namespace Fawaz\Database;

use PDO;
use Fawaz\App\Chat;
use Fawaz\App\ChatParticipants;
use Fawaz\App\ChatMessages;
use Fawaz\App\NewsFeed;
use Fawaz\App\Status;
use Fawaz\App\User;
use Fawaz\Utils\PeerLoggerInterface;

class ChatMapper
{
    const STATUS_DELETED = 6;

    public function __construct(protected PeerLoggerInterface $logger, protected PDO $db)
    {
    }

    public function isSameUser(string $userid, string $currentUserId): bool
    {
        return $userid === $currentUserId;
    }

    private function respondWithError(int $message): array
    {
        return ['status' => 'error', 'ResponseCode' => $message];
    }

    protected function createSuccessResponse(int $message, array|object $data = [], bool $countEnabled = true, ?string $countKey = null): array 
    {
        $response = [
            'status' => 'success',
            'ResponseCode' => $message,
            'affectedRows' => $data,
        ];

        if ($countEnabled && is_array($data)) {
            if ($countKey !== null && isset($data[$countKey]) && is_array($data[$countKey])) {
                $response['counter'] = count($data[$countKey]);
            } else {
                $response['counter'] = count($data);
            }
        }

        return $response;
    }

    public function isCreator(string $chatid, string $currentUserId): bool
    {
        $this->logger->debug("ChatMapper.isCreator started", [
            'chatid' => $chatid,
            'currentUserId' => $currentUserId
        ]);

        try {
            $sql = "SELECT COUNT(*) FROM chats WHERE chatid = :chatid AND creatorid = :currentUserId";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':chatid', $chatid, \PDO::PARAM_STR);
            $stmt->bindValue(':currentUserId', $currentUserId, \PDO::PARAM_STR);
            $stmt->execute();

            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            $this->logger->error("Database error: " . $e->getMessage(), [
                'chatid' => $chatid,
                'currentUserId' => $currentUserId
            ]);
            return false;
        }
    }

    public function isParticipantExist(string $chatid, string $currentUserId): bool
    {
        $this->logger->debug("ChatMapper.isParticipantExist started", [
            'chatid' => $chatid,
            'currentUserId' => $currentUserId
        ]);

        try {
            $sql = "SELECT COUNT(*) FROM chatparticipants WHERE chatid = :chatid AND userid = :currentUserId";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':chatid', $chatid, \PDO::PARAM_STR);
            $stmt->bindValue(':currentUserId', $currentUserId, \PDO::PARAM_STR);
            $stmt->execute();

            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            $this->logger->error("Database error: " . $e->getMessage(), [
                'chatid' => $chatid,
                'currentUserId' => $currentUserId
            ]);
            return false;
        }
    }

    public function isPrivate(string $chatid): bool
    {
        $this->logger->debug("ChatMapper.isPrivate started", ['chatid' => $chatid]);

        try {
            $sql = "SELECT COUNT(*) FROM chats WHERE chatid = :chatid AND ispublic = 0";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':chatid', $chatid, \PDO::PARAM_STR);
            $stmt->execute();

            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            $this->logger->error("Database error: " . $e->getMessage(), ['chatid' => $chatid]);
            return false;
        }
    }

    public function fetchFriends(string $userid): array
    {
        $this->logger->debug("ChatMapper.fetchFriends started", ['userid' => $userid]);

        try {
            $sql = "SELECT u.uid, u.username, u.slug, u.updatedat, u.biography, u.img 
                    FROM follows f1 
                    INNER JOIN follows f2 ON f1.followedid = f2.followerid 
                    INNER JOIN users u ON f1.followedid = u.uid 
                    WHERE f1.followerid = :userid 
                    AND f2.followedid = :userid
                    AND u.status != :status";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmt->bindValue(':status', Status::DELETED, \PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $this->logger->error("Database error in fetchFriends: " . $e->getMessage(), ['userid' => $userid]);
            return [];
        }
    }

    public function getPrivateChatBetweenUsers(string $userId1, string $userId2): bool
    {
        $this->logger->debug("ChatMapper.getPrivateChatBetweenUsers started", [
            'userId1' => $userId1,
            'userId2' => $userId2
        ]);

        try {
            $sql = "SELECT chatid FROM chats
                    WHERE ispublic = 0 
                    AND chatid IN (
                        SELECT chatid FROM chatparticipants 
                        WHERE userid IN (:userId1, :userId2)
                        GROUP BY chatid
                        HAVING COUNT(DISTINCT userid) = 2
                    ) 
                    LIMIT 1;";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':userId1', $userId1, \PDO::PARAM_STR);
            $stmt->bindValue(':userId2', $userId2, \PDO::PARAM_STR);
            $stmt->execute();

            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            $this->logger->error("Database error: " . $e->getMessage(), [
                'userId1' => $userId1,
                'userId2' => $userId2
            ]);
            return false;
        }
    }

    public function loadById(string $id): Chat|array
    {
        $this->logger->debug("ChatMapper.loadById started", ['id' => $id]);

        try {
            $sql = "SELECT * FROM chats WHERE chatid = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $id, \PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($data) {
                return new Chat($data);
            }

            $this->logger->warning("No chat found with id", ['id' => $id]);
            return $this->createSuccessResponse(21802);
        } catch (\Throwable $e) {  
            $this->logger->error("Database error: " . $e->getMessage(), ['id' => $id]);
            return $this->respondWithError(40302);
        }
    }

    public function loadChatById(string $currentUserId, ?array $args = []): array
    {
        $this->logger->debug("ChatMapper.loadChatById started");

        $chatId = $args['chatid'] ?? null;

        try {
            $chatExistsSql = "SELECT chatid FROM chats WHERE chatid = :chatid";
            $chatExistsStmt = $this->db->prepare($chatExistsSql);
            $chatExistsStmt->execute(['chatid' => $chatId]);
            $chatExists = $chatExistsStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$chatExists) {
                $this->logger->warning("Chat ID not found", ['chatid' => $chatId]);
                return $this->createSuccessResponse(21802);
            }

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
                return $this->respondWithError(31801);
            }

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

            $chatRow = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$chatRow) {
                $this->logger->warning("No chat details found for chatid", ['chatid' => $chatId]);
                return $this->createSuccessResponse(21802);
            }

            $messageLimit = min(max((int)($args['messageLimit'] ?? 10), 1), 20);
            $messageOffset = isset($args['messageOffset']) ? max((int)($args['messageOffset']), 0) : null;

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
                $messageStmt->bindValue('limit', $messageLimit, \PDO::PARAM_INT);
                $messageStmt->bindValue('offset', $messageOffset, \PDO::PARAM_INT);
            } else {
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
                $messageStmt->bindValue('lastSeenMessageId', $lastSeenMessageId, \PDO::PARAM_INT);
                $messageStmt->bindValue('limit', $messageLimit, \PDO::PARAM_INT);
            }

            $messageStmt->execute();
            $chatMessages = $messageStmt->fetchAll(\PDO::FETCH_ASSOC);

            if ($chatMessages) {
                $lastMessage = $chatMessages[0] ?? null;
                if ($lastMessage) {
                    $this->updateLastSeenMessage($currentUserId, $chatId, $lastMessage['messid']);
                }
            }

            $chatParticipantsSql = "
                SELECT 
                    p.userid, 
                    u.username, 
                    u.slug,
                    u.status,
                    u.img, 
                    p.hasaccess 
                FROM chatparticipants p 
                JOIN users u ON p.userid = u.uid 
                WHERE p.chatid = :chatid 
                ORDER BY p.createdat DESC;
            ";
            $participantStmt = $this->db->prepare($chatParticipantsSql);
            $participantStmt->execute(['chatid' => $chatId]);
            $chatParticipants = $participantStmt->fetchAll(\PDO::FETCH_ASSOC);


            $chatParticipantObj = [];
            foreach($chatParticipants as $key => $prt){
                $userObj = [
                        'uid' => $prt['userid'],
                        'status' => $prt['status'],
                        'username' => $prt['username'],
                        'slug' => $prt['slug'],
                        'img' => $prt['img'],
                        'hasaccess' => $prt['hasaccess'],
                    ];
                $userObj = (new User($userObj, [], false))->getArrayCopy();

                $chatParticipantObj[$key] = $userObj;
                $chatParticipantObj[$key]['userid'] = $userObj['uid'];
                $chatParticipantObj[$key]['hasaccess'] = $prt['hasaccess'];
            }   

            return [
                'status' => 'success',
                'ResponseCode' => 11810,
                'data' => [
                    'chat' => $chatRow,
                    'messages' => $chatMessages,
                    'participants' => $chatParticipants,
                ],
            ];

        } catch (\Throwable $e) {
            $this->logger->error("Database error occurred in loadChatById", [
                'error' => $e->getMessage(),
            ]);
            return $this->respondWithError(40302);
        }
    }

    public function getChatMessages(array $args): array
    {
        $this->logger->debug("ChatMapper.getChatMessages started");

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
            //$results = array_map(fn($row) => new ChatMessages($row), $stmt->fetchAll(\PDO::FETCH_ASSOC));
            //return $results ?: [];
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->logger->error("General error in getChatMessages", [
                'error' => $e->getMessage(),
            ]);
            return $this->respondWithError(40301);
        }
    }

    public function insert(Chat $chat): Chat
    {
        $this->logger->debug("ChatMapper.insert started");

        $data = $chat->getArrayCopy();

        $query = "INSERT INTO chats (chatid, creatorid, image, name, ispublic, createdat, updatedat) 
                  VALUES (:chatid, :creatorid, :image, :name, :ispublic, :createdat, :updatedat)";

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':chatid', $data['chatid'], \PDO::PARAM_STR);
            $stmt->bindValue(':creatorid', $data['creatorid'], \PDO::PARAM_STR);
            $stmt->bindValue(':image', $data['image'], \PDO::PARAM_STR);
            $stmt->bindValue(':name', $data['name'], \PDO::PARAM_STR);
            $stmt->bindValue(':ispublic', $data['ispublic'], \PDO::PARAM_INT);
            $stmt->bindValue(':createdat', $data['createdat'], \PDO::PARAM_STR); 
            $stmt->bindValue(':updatedat', $data['updatedat'], \PDO::PARAM_STR); 

            $stmt->execute();

            $this->logger->info("Inserted new chat into database", ['chat' => $data]);

            return new Chat($data);
        } catch (\Throwable $e) {
            $this->logger->error("Failed to insert new chat into database", [
                'exception' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to insert chat: " . $e->getMessage());
        }
    }

    public function insertFeed(NewsFeed $feed): NewsFeed
    {
        $this->logger->debug("ChatMapper.insertFeed started");

        $data = $feed->getArrayCopy();

        $query = "INSERT INTO newsfeed (feedid, creatorid, image, name, createdat, updatedat) 
                  VALUES (:feedid, :creatorid, :image, :name, :createdat, :updatedat)";

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':feedid', $data['feedid'], \PDO::PARAM_STR);
            $stmt->bindValue(':creatorid', $data['creatorid'], \PDO::PARAM_STR);
            $stmt->bindValue(':image', $data['image'], \PDO::PARAM_STR);
            $stmt->bindValue(':name', $data['name'], \PDO::PARAM_STR);
            $stmt->bindValue(':createdat', $data['createdat'], \PDO::PARAM_STR); 
            $stmt->bindValue(':updatedat', $data['updatedat'], \PDO::PARAM_STR); 

            $stmt->execute();

            $this->logger->info("Inserted new feed into database", ['feed' => $data]);

            return new NewsFeed($data);
        } catch (\Throwable $e) {
            $this->logger->error("Failed to insert feed into database", [
                'exception' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to insert feed: " . $e->getMessage());
        }
    }

    public function insertPart(ChatParticipants $participant): array
    {
        $this->logger->debug("ChatMapper.insertPart started");

        $data = $participant->getArrayCopy();

        try {
            $userid = $data['userid'];

            $userExistsQuery = "SELECT COUNT(*) FROM users WHERE uid = :userid AND status != :status";
            $stmt = $this->db->prepare($userExistsQuery);
            $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmt->bindValue(':status', Status::DELETED, \PDO::PARAM_INT);
            $stmt->execute();
            $userExists = (bool) $stmt->fetchColumn();

            if (!$userExists) {
                $this->logger->warning("User does not exist in users", ['userid' => $userid]);
                return $this->createSuccessResponse(21001);
            }

            $participantExistsQuery = "SELECT COUNT(*) FROM chatparticipants WHERE chatid = :chatid AND userid = :userid";
            $stmt = $this->db->prepare($participantExistsQuery);
            $stmt->bindValue(':chatid', $data['chatid'], \PDO::PARAM_STR);
            $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmt->execute();
            $participantExists = (bool) $stmt->fetchColumn();

            if ($participantExists) {
                $this->logger->warning("Participant already exists", ['userid' => $userid]);
                return $this->respondWithError(31813);
            }

            $query = "INSERT INTO chatparticipants (chatid, userid, hasaccess, createdat) 
                      VALUES (:chatid, :userid, :hasaccess, :createdat)";
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':chatid', $data['chatid'], \PDO::PARAM_STR);
            $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmt->bindValue(':hasaccess', $data['hasaccess'], \PDO::PARAM_INT);
            $stmt->bindValue(':createdat', $data['createdat'], \PDO::PARAM_STR);

            $stmt->execute();

            $this->logger->info("Inserted new participant into database", ['participant' => $data]);

            return [
                'status' => 'success',
                'ResponseCode' => 11802,
                'affectedRows' => new ChatParticipants($data)
            ];
        } catch (\Throwable $e) {
            $this->logger->error("Error inserting participant", ['exception' => $e->getMessage()]);
            return $this->respondWithError(41804);
        }
    }

    public function insertMess(ChatMessages $chatmessage): array
    {
        $this->logger->debug("ChatMapper.insertMess started");

        $data = $chatmessage->getArrayCopy();

        try {
            $userid = $data['userid'];

            $userExistsQuery = "SELECT COUNT(*) FROM users WHERE uid = :userid AND status != :status";
            $stmt = $this->db->prepare($userExistsQuery);
            $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmt->bindValue(':status', Status::DELETED, \PDO::PARAM_INT);
            $stmt->execute();
            $userExists = (bool) $stmt->fetchColumn();

            if (!$userExists) {
                $this->logger->warning("User did not exist in users", ['userid' => $userid]);
                return $this->createSuccessResponse(21001);
            }

            $participantExistsQuery = "SELECT COUNT(*) FROM chatparticipants WHERE chatid = :chatid AND userid = :userid";
            $stmt = $this->db->prepare($participantExistsQuery);
            $stmt->bindValue(':chatid', $data['chatid'], \PDO::PARAM_STR);
            $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmt->execute();
            $participantExists = (bool) $stmt->fetchColumn();

            if (!$participantExists) {
                $this->logger->warning("User is not a participant of the chat", ['userid' => $userid]);
                return $this->respondWithError(31814);
            }

            $query = "INSERT INTO chatmessages (chatid, userid, content, createdat) 
                      VALUES (:chatid, :userid, :content, :createdat)";
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':chatid', $data['chatid'], \PDO::PARAM_STR);
            $stmt->bindValue(':userid', $data['userid'], \PDO::PARAM_STR);
            $stmt->bindValue(':content', $data['content'], \PDO::PARAM_STR);
            $stmt->bindValue(':createdat', $data['createdat'], \PDO::PARAM_STR);

            $stmt->execute();

            $data['messid'] = $lastInsertedId = (int)$this->db->lastInsertId();

            $this->logger->info("Inserted new chat message into database", [
                'chatmessage' => $data,
                'lastInsertedId' => $lastInsertedId
            ]);

            $this->updateLastSeenMessage($userid, $data['chatid'], $lastInsertedId);

            return [
                'status' => 'success',
                'ResponseCode' => 11803,
                'affectedRows' => [$data]
            ];
        } catch (\Throwable $e) {
            $this->logger->error("Error inserting message", [
                'exception' => $e->getMessage()
            ]);
            return $this->respondWithError(41801);
        }
    }

    public function update(Chat $chat): Chat
    {
        $this->logger->debug("ChatMapper.update started");

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
        } catch (\Throwable $e) {
            $this->logger->error("Error updating chat in database", [
                'exception' => $e->getMessage()
            ]);

            throw new \RuntimeException("Failed to update chat: " . $e->getMessage());
        }
    }

    public function delete(string $id): bool
    {
        $this->logger->debug("ChatMapper.delete started");

        $query = "DELETE FROM chats WHERE chatid = :id";

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':id', $id, \PDO::PARAM_STR);

            $stmt->execute();

            $deleted = (bool)$stmt->rowCount();
            if ($deleted) {
                $this->logger->info("Deleted chat from database", ['id' => $id]);
            } else {
                $this->logger->warning("No chat found to delete in database for id", ['id' => $id]);
            }

            return $deleted;
        } catch (\Throwable $e) {
            $this->logger->error("Error deleting chat from database", [
                'exception' => $e->getMessage()
            ]);
            
            throw new \RuntimeException("Failed to delete chat: " . $e->getMessage());
        }
    }

    public function deleteParticipant(string $chatid, string $participantId): bool
    {
        $this->logger->debug("ChatMapper.deleteParticipant started");

        $query = "DELETE FROM chatparticipants WHERE chatid = :chatid AND userid = :participantId";

        try {
            $stmt = $this->db->prepare($query);

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
        } catch (\Throwable $e) {
            $this->logger->error("Error deleting participant from chat", [
                'exception' => $e->getMessage()
            ]);

            throw new \RuntimeException("Failed to delete participant from chat: " . $e->getMessage());
        }
    }

    public function deleteMessage(string $chatid, int $messid): bool
    {
        $this->logger->debug("ChatMapper.deleteMessage started");

        $query = "DELETE FROM chatmessages WHERE chatid = :chatid AND messid = :messid";

        try {
            $stmt = $this->db->prepare($query);

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
        } catch (\Throwable $e) {
            $this->logger->error("Error deleting message from chat", [
                'exception' => $e->getMessage()
            ]);

            throw new \RuntimeException("Failed to delete message from chat: " . $e->getMessage());
        }
    }

    public function findChatser(string $currentUserId, ?array $args = []): array
    {
        $this->logger->debug("ChatMapper.findChatser started");

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

            $stmt->bindValue(':currentUserId', $currentUserId, \PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            if ($from !== null) {
                $stmt->bindValue(':from', $from, \PDO::PARAM_STR);
            }
            if ($to !== null) {
                $stmt->bindValue(':to', $to, \PDO::PARAM_STR);
            }

            $stmt->execute();

            $chats = [];
            while ($chatRow = $stmt->fetch(\PDO::FETCH_ASSOC)) {
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
                $messageStmt->bindValue(':chatid', $chatRow['chatid'], \PDO::PARAM_STR);
                $messageStmt->bindValue(':limit', $messageLimit, \PDO::PARAM_INT);
                $messageStmt->bindValue(':offset', $messageOffset, \PDO::PARAM_INT);
                $messageStmt->execute();
                $chatMessages = $messageStmt->fetchAll(\PDO::FETCH_ASSOC);

                $chatParticipantsSql = "
                    SELECT 
                        u.uid, 
                        u.username, 
                        u.status,
						u.slug,
                        u.img, 
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
                $participantStmt->bindValue(':chatid', $chatRow['chatid'], \PDO::PARAM_STR);
                $participantStmt->execute();
                $chatParticipants = $participantStmt->fetchAll(\PDO::FETCH_ASSOC);

                $chatParticipantObj = [];
                foreach ($chatParticipants as $key => $chatPrtcpt) {
                    $userObj = (new User($chatPrtcpt, [], false))->getArrayCopy();
                    $chatParticipantObj[$key] = $userObj;
                    $chatParticipantObj[$key]['userid'] = $userObj['uid'];
                    $chatParticipantObj[$key]['hasaccess'] = $chatPrtcpt['hasaccess'];
                }
                $chats[] = new Chat([
                    'chatid' => $chatRow['chatid'],
                    'creatorid' => $chatRow['creatorid'],
                    'name' => $chatRow['name'],
                    'image' => $chatRow['image'],
                    'ispublic' => (bool)$chatRow['ispublic'],
                    'createdat' => $chatRow['createdat'],
                    'updatedat' => $chatRow['updatedat'],
                    'chatmessages' => $chatMessages, 
                    'chatparticipants' => $chatParticipantObj, 
                ]);
            }

            $this->logger->info(
                $chats ? "Fetched chats with messages and participants from database" : "No chats found in database",
                ['count' => count($chats)]
            );

            return $chats;
        } catch (\Throwable $e) {
            $this->logger->error("Error occurred in findChatser", [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function updateLastSeenMessage(string $user_id, string $chat_id, int $last_seen_message_id): void
    {
        $this->logger->debug("ChatMapper.updateLastSeenMessage started");

        $query = "
            INSERT INTO user_chat_status (userid, chatid, last_seen_message_id)
            VALUES (:user_id, :chat_id, :last_seen_message_id)
            ON CONFLICT (userid, chatid)
            DO UPDATE SET last_seen_message_id = EXCLUDED.last_seen_message_id";

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':user_id', $user_id, \PDO::PARAM_STR);
            $stmt->bindValue(':chat_id', $chat_id, \PDO::PARAM_STR);
            $stmt->bindValue(':last_seen_message_id', $last_seen_message_id, \PDO::PARAM_INT);

            $stmt->execute();

            $this->logger->info("Updated last seen message for user in chat", [
                'user_id' => $user_id,
                'chat_id' => $chat_id,
                'last_seen_message_id' => $last_seen_message_id
            ]);
        } catch (\Throwable $e) {
            $this->logger->error("Failed to update last seen message", [
                'exception' => $e->getMessage()
            ]);

            throw new \RuntimeException("Failed to update last seen message: " . $e->getMessage());
        }
    }

    public function getUnseenMessages(string $user_id, string $chat_id): array|false
    {
        $this->logger->debug("ChatMapper.getUnseenMessages started");

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

            $stmt->bindValue(':chat_id', $chat_id, \PDO::PARAM_STR);
            $stmt->bindValue(':user_id', $user_id, \PDO::PARAM_STR);

            $stmt->execute();

            $messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

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
        } catch (\Throwable $e) {
            $this->logger->error("Failed to fetch unseen messages from chat", [
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function loadMessageById(int $id): array|false
    {
        $this->logger->debug("ChatMapper.loadMessageById started", ['id' => $id]);

        try {
            $sql = "SELECT * FROM chatmessages WHERE messid = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($data) {
                return $data;
            }

            $this->logger->warning("No chat message found with id", ['id' => $id]);
            return false;
        } catch (\Throwable $e) {  
            $this->logger->error("Database error: " . $e->getMessage(), ['id' => $id]);
            return false;
        }
    }
}
