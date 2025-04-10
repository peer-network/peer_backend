<?php

namespace Fawaz\App;

use Fawaz\App\Chat;
use Fawaz\App\ChatParticipants;
use Fawaz\App\ChatMessages;
use Fawaz\Database\ChatMapper;
use Fawaz\Services\Base64FileHandler;
use Psr\Log\LoggerInterface;
use Ratchet\Client\Connector;
use React\EventLoop\Factory as EventLoopFactory;

class ChatService
{
    protected ?string $currentUserId = null;
    private Base64FileHandler $base64filehandler;

    public function __construct(protected LoggerInterface $logger, protected ChatMapper $chatMapper)
    {
        $this->base64filehandler = new Base64FileHandler();
    }

    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    private function generateUUID(): string
    {
        return \sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            \mt_rand(0, 0xffff), \mt_rand(0, 0xffff),
            \mt_rand(0, 0xffff),
            \mt_rand(0, 0x0fff) | 0x4000,
            \mt_rand(0, 0x3fff) | 0x8000,
            \mt_rand(0, 0xffff), \mt_rand(0, 0xffff), \mt_rand(0, 0xffff)
        );
    }

    public static function isValidUUID(string $uuid): bool
    {
        return preg_match('/^\{?[a-fA-F0-9]{8}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{12}\}?$/', $uuid) === 1;
    }

    private function respondWithError(string $message): array
    {
        return ['status' => 'error', 'ResponseCode' => $message];
    }

    private function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->warning('Unauthorized access attempt');
            return false;
        }
        return true;
    }

    public function createChatWithRecipients(array $args): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (empty($args)) {
            return $this->respondWithError(30103);
        }

        $this->logger->info('ChatService.createChatWithRecipients started');

        $chatId = $this->generateUUID();
        if (empty($chatId)) {
            $this->logger->critical('Failed to generate chat ID');
            return $this->respondWithError(41808);
        }

        $creatorId = $this->currentUserId;
        $name = $args['name'] ?? null;
        $image = $args['image'] ?? null;
        $recipients = $args['recipients'] ?? null;
        $maxUsers = 1;
        $public = 1;

        if (!is_array($recipients) || empty($recipients)) {
            return $this->respondWithError(30103);
        }

        if (count($recipients) < $maxUsers) {
            return $this->respondWithError(31808);
        }

        $friends = $this->getFriends();

        if (!is_array($friends) || empty($friends)) {
            return $this->respondWithError(21101);
        }

        $friendIds = array_column($friends, 'uid');

        foreach ($recipients as $recipientId) {
            if (!in_array($recipientId, $friendIds)) {
                return $this->respondWithError(31101);
            }
        }

        $recipientId = null;

        if (count($recipients) === $maxUsers) {
            $public = 0;
            $name = null;
            $image = null;

            $recipientId = $recipients[0];
            if ($this->chatMapper->getPrivateChatBetweenUsers($creatorId, $recipientId)) {
                return $this->respondWithError(21805);
            }
        }

        $recipientId = null;

        try {
            if ($image !== null && $image !== '' && count($recipients) > $maxUsers) {

                if (!empty($image)) {
                    $mediaPath = $this->base64filehandler->handleFileUpload($image, 'image', $chatId, 'chat');
                    $this->logger->info('PostService.createPost mediaPath', ['mediaPath' => $mediaPath]);

                    if ($mediaPath === '') {
                        return $this->respondWithError(40304);
                    }

                    if (isset($mediaPath['path'])) {
                        $image = $mediaPath['path'];
                    } else {
                        return $this->respondWithError(31006);
                    }

                } else {
                    return $this->respondWithError(31007); 
                }

            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to upload media files', ['exception' => $e]);
            return $this->respondWithError(40304);
        }

        try {
            // Create the chat
            $chatData = [
                'chatid' => $chatId,
                'creatorid' => $creatorId,
                'name' => $name,
                'image' => $image,
                'ispublic' => $public,
                'createdat' => (new \DateTime())->format('Y-m-d H:i:s.u'),
                'updatedat' => (new \DateTime())->format('Y-m-d H:i:s.u'),
            ];
            $chat = new Chat($chatData);
            $this->chatMapper->insert($chat);

            if (count($recipients) > $maxUsers) {
                // Create the newsfeed
                $newsfeed = [
                    'feedid' => $chatId,
                    'creatorid' => $creatorId,
                    'name' => $name,
                    'image' => $image,
                    'createdat' => (new \DateTime())->format('Y-m-d H:i:s.u'),
                    'updatedat' => (new \DateTime())->format('Y-m-d H:i:s.u'),
                ];
                $chat = new NewsFeed($newsfeed);
                $this->chatMapper->insertFeed($chat);
            }

            // Add participants to the chatparticipant
            foreach ($recipients as $recipientId) {
                if (!self::isValidUUID($recipientId)) {
                    continue;
                }

                $participantData = [
                    'chatid' => $chatId,
                    'userid' => $recipientId,
                    'hasaccess' => 0,
                    'createdat' => (new \DateTime())->format('Y-m-d H:i:s.u'),
                ];
                $participant = new ChatParticipants($participantData);
                $this->chatMapper->insertPart($participant);
                $this->logger->info('ChatParticipants started');
            }

            $recipientId = null;

            // Add creator as a chatparticipant
            $creatorData = [
                'chatid' => $chatId,
                'userid' => $creatorId,
                'hasaccess' => 10,
                'createdat' => (new \DateTime())->format('Y-m-d H:i:s.u'),
            ];
            $creatorParticipant = new ChatParticipants($creatorData);
            $this->chatMapper->insertPart($creatorParticipant);

            $this->logger->info('Chat and participants created successfully', ['chatId' => $chatId]);
            return [
                'status' => 'success',
                'ResponseCode' => 11804,
                'affectedRows' => ['chatid' => $chatId],
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create chat and participants', ['exception' => $e]);
            return $this->respondWithError(41802);
        }
    }

    public function updateChat(array $args): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (empty($args)) {
            return $this->respondWithError(30103);
        }

        $this->logger->info('ChatService.updateChat started');

        // Validate required fields
        $requiredFields = ['chatid'];
        foreach ($requiredFields as $field) {
            if (!isset($args[$field]) || empty($args[$field])) {
                $this->logger->warning("$field is required", ['args' => $args]);
                return $this->respondWithError(30101);
            }
        }

        $chatId = $args['chatid'] ?? null;
        $name = $args['name'] ?? null;
        $image = $args['image'] ?? null;

        // Validate args parameters
        if ($chatId === null) {
            return $this->respondWithError(30103);
        }

        if (empty($name) && empty($image)) {
            return $this->respondWithError(31809);
        }

        if (!self::isValidUUID($chatId)) {
            return $this->respondWithError(20201);
        }

        if (!$this->chatMapper->isCreator($chatId, $this->currentUserId)) {
            return $this->respondWithError(31802);
        }

        if ($this->chatMapper->isPrivate($chatId)) {
            return $this->respondWithError(31803);
        }

        try {
            if (!empty($image)) {
                $chatImage = $chatId . '-' . uniqid();
                $mediaPath = $this->base64filehandler->handleFileUpload($image, 'image', $chatImage);
                $this->logger->info('mediaPath', ['mediaPath' => $mediaPath]);
                if ($mediaPath !== null) {
                    $image = $mediaPath['path'];
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to upload media files', ['exception' => $e]);
            return $this->respondWithError(40304);
        }

        try {
            $chat = $this->chatMapper->loadById($chatId);
            if (!$chat) {
                return $this->respondWithError(20218);
            }

            $chat->setName($name);
            $chat->setImage($image);

            $this->chatMapper->update($chat);

            $this->logger->info('Chat updated successfully', ['chatid' => $chatId]);
            return [
                'status' => 'success',
                'ResponseCode' => 11805,
                'affectedRows' => $chat->getArrayCopy()
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Failed to update chat', ['args' => $args, 'exception' => $e]);
            return $this->respondWithError(41803);
        }
    }

    public function deleteChat(string $id): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (empty($id)) {
            return $this->respondWithError(30103);
        }

        if (!self::isValidUUID($id)) {
            return $this->respondWithError(20201);
        }

        $this->logger->info('ChatService.deleteChat started');

        $chats = $this->chatMapper->loadById($id);
        if (!$chats) {
            return $this->respondWithError(20218 . $id);
        }

        $chat = $chats->getArrayCopy();

        if ($chat['creatorid'] !== $this->currentUserId && !$this->chatMapper->isCreator($id, $this->currentUserId)) {
            return $this->respondWithError(31804);
        }

        try {
            $chatId = $this->chatMapper->delete($id);

            if ($chatId) {
                $this->logger->info('Chat deleted successfully', ['chatId' => $chatId]);
                return [
                    'status' => 'success',
                    'ResponseCode' => 'Chat deleted successfully'
                ];
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to delete chat', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->respondWithError(41809);
        }

        return $this->respondWithError(41809);
    }

    public function addParticipants(array $args): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (empty($args)) {
            return $this->respondWithError(30103);
        }

        $this->logger->info('ChatService.addParticipants started');

        $chatId = $args['chatid'] ?? null;
        $participants = $args['recipients'] ?? null;

        // Validate input parameters
        if ($chatId === null || !is_array($participants) || empty($participants)) {
            return $this->respondWithError(30103);
        }

        if (!self::isValidUUID($chatId)) {
            return $this->respondWithError(20201);
        }

        if (!$this->chatMapper->isCreator($chatId, $this->currentUserId)) {
            return $this->respondWithError(31804);
        }

        if ($this->chatMapper->isPrivate($chatId)) {
            return $this->respondWithError(31805);
        }

        $friends = $this->getFriends();

        // Check if $friends is an array and has follow
        if (!is_array($friends) || empty($friends)) {
            return $this->respondWithError(21101);
        }

        $friendIds = array_column($friends, 'uid');

        foreach ($participants as $recipientId) {
            if (!in_array($recipientId, $friendIds)) {
                return $this->respondWithError(31101);
            }
        }

        $chat = $this->chatMapper->loadById($chatId);
        if (!$chat) {
            return $this->respondWithError(20218);
        }

        try {
            foreach ($participants as $participantId) {

                if (!self::isValidUUID($participantId)) {
                    continue;
                }

                $participantData = [
                    'chatid' => $chatId,
                    'userid' => $participantId,
                    'hasaccess' => 0,
                    'createdat' => (new \DateTime())->format('Y-m-d H:i:s.u'),
                ];
                $participant = new ChatParticipants($participantData);
                $response = $this->chatMapper->insertPart($participant);

                if ($response['status'] === 'error') {
                    return $response;
                }
            }

            $this->logger->info('Participants added successfully', ['chatId' => $chatId]);
            return [
                'status' => 'success',
                'ResponseCode' => 11802,
                'affectedRows' => $participants,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Failed to add participants', ['chatId' => $chatId, 'exception' => $e]);
            return $this->respondWithError(41804);
        }
    }

    public function removeParticipants(array $args): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (empty($args)) {
            return $this->respondWithError(30103);
        }

        $this->logger->info('ChatService.removeParticipants started');

        $chatId = $args['chatid'] ?? null;
        $participants = $args['recipients'] ?? null;

        // Validate input parameters
        if ($chatId === null || !is_array($participants) || empty($participants)) {
            return $this->respondWithError(30103);
        }

        if (!self::isValidUUID($chatId)) {
            return $this->respondWithError(20201);
        }

        if (!$this->chatMapper->isCreator($chatId, $this->currentUserId)) {
            return $this->respondWithError(31806);
        }

        if ($this->chatMapper->isPrivate($chatId)) {
            return $this->respondWithError(31807);
        }

        $chat = $this->chatMapper->loadById($chatId);
        if (!$chat) {
            return $this->respondWithError(20218);
        }

        try {
            foreach ($participants as $participantId) {

                if (!self::isValidUUID($participantId)) {
					return $this->respondWithError(20201);
                }

				if (!$this->chatMapper->isParticipantExist($chatId, $participantId)) {
					return $this->respondWithError(31810);
				}

                $this->chatMapper->deleteParticipant($chatId, $participantId);
            }

            $this->logger->info('Participants removed successfully', ['chatId' => $chatId]);
            return [
                'status' => 'success',
                'ResponseCode' => 11806,
                'affectedRows' => $participants,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Failed to remove participants', ['chatId' => $chatId, 'exception' => $e]);
            return $this->respondWithError(41805);
        }
    }

    public function readChatMessages(?array $args = []): array
    {

        $this->logger->info('ChatService.readChatMessages started');

        $results = $this->chatMapper->getChatMessages($args);

        return $results;
    }

    public function addMessage(string $chatId, string $content): array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->warning('Unauthorized access attempt in addMessage', ['chatId' => $chatId]);
            return $this->respondWithError(60501);
        }

        if (empty($chatId) || empty($content)) {
            return $this->respondWithError(30102);
        }

        if (strlen($content) < 1 || strlen($content) > 500) {
            return $this->respondWithError(20252);
        }

        if (!self::isValidUUID($chatId)) {
            return $this->respondWithError(20201);
        }

        $this->logger->info('ChatService.addMessage started', ['chatId' => $chatId]);

        $chat = $this->chatMapper->loadById($chatId);
        
        if (is_array($chat) && isset($chat['status']) && $chat['status'] === 'error') {
            return $chat; // Immediately return if chat is invalid
        }

        if ($chat->getIsPublic() === 9) {
            return $this->respondWithError(41809);
        }

        try {
            $messageData = [
                'messid' => 0,
                'chatid'    => $chatId,
                'userid'    => $this->currentUserId,
                'content'   => $content,
                'createdat' => (new \DateTime())->format('Y-m-d H:i:s.u'),
            ];

            $message = new ChatMessages($messageData);
            $result = $this->chatMapper->insertMess($message);

            if ($result['status'] === 'error') {
                $this->logger->error('Failed to insert message', ['chatId' => $chatId, 'error' => $result]);
                return $result;
            }

            $this->logger->info('Message added successfully', ['chatId' => $chatId, 'content' => $content]);
            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to add message', ['chatId' => $chatId, 'exception' => $e->getMessage()]);
            return $this->respondWithError(41806);
        }
    }

    public function removeMessage(string $chatId, int $messageId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (empty($chatId) || empty($messageId)) {
            return $this->respondWithError(30102);
        }

        if (!self::isValidUUID($chatId)) {
            return $this->respondWithError(20201);
        }

        $this->logger->info('ChatService.removeMessage started');

        $chat = $this->chatMapper->loadById($chatId);

        if (!$chat) {
            return $this->respondWithError(20218);
        }

        $message = $this->chatMapper->loadMessageById($messageId);

        if ($message === false) {
            return $this->respondWithError('Invalid messageId');
        }

        if ($message['userid'] !== $this->currentUserId && !$this->chatMapper->isCreator($chatId, $this->currentUserId)) {
            return $this->respondWithError(31806);
        }

        try {
            $this->chatMapper->deleteMessage($chatId, $messageId);

            $this->logger->info('Message removed successfully', ['chatId' => $chatId, 'messageId' => $messageId]);
            return [
                'status' => 'success',
                'ResponseCode' => 'Message removed successfully',
                'affectedRows' => $message,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Failed to remove message', ['chatId' => $chatId, 'exception' => $e]);
            return $this->respondWithError('Failed to remove message');
        }
    }

    public function getFriends(): array|null
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $this->logger->info('ChatService.getFriends started');
        $users = $this->chatMapper->fetchFriends($this->currentUserId);

        if ($users) {
            return $users;
        } 

        return null;
    }

    public function loadChatById(?array $args = []): Chat|array
    {
        try {
            if (!$this->checkAuthentication()) {
                throw new ValidationException('Unauthorized');
            }

            $chatId = $args['chatid'] ?? null;

            if (!self::isValidUUID($chatId)) {
                throw new ValidationException('MissingChatId');
            }

            $this->logger->info('ChatService.loadChatById started');

            $result = $this->chatMapper->loadChatById($this->currentUserId, $args);

            if ($result['status'] !== 'success') {
                throw new ValidationException($result['ResponseCode']);
            }

            $chatData = $result['data'];

            return [
                'status' => 'success',
                'ResponseCode' => 'Chat fetched successfullyd',
                'data' => new Chat([
                    'chatid' => $chatData['chat']['chatid'],
                    'creatorid' => $chatData['chat']['creatorid'],
                    'name' => $chatData['chat']['name'],
                    'image' => $chatData['chat']['image'],
                    'ispublic' => (bool) $chatData['chat']['ispublic'],
                    'createdat' => $chatData['chat']['createdat'],
                    'updatedat' => $chatData['chat']['updatedat'],
                    'chatmessages' => $chatData['messages'],
                    'chatparticipants' => $chatData['participants'],
                ]),
            ];
        } catch (ValidationException $e) {
            $this->logger->warning("Validation error in loadChatById", ['error' => $e->getMessage()]);
            return $this->respondWithError(40301);
        } catch (\Throwable $e) {
            $this->logger->error("Unexpected error in loadChatById", ['error' => $e->getMessage()]);
            return $this->respondWithError(40301);
        }
    }

    public function findChatser(?array $args = []): array|false
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $this->logger->info('ChatService.findChatser started');

        $results = $this->chatMapper->findChatser($this->currentUserId, $args);
        $this->logger->info('ChatService.findChatser successfully', ['currentUserId' => $this->currentUserId]);

        return $results;
    }

    public function setChatMessages(string $chatId, string $content): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $messageData = [
            'type' => 'message',
            'chatId' => $chatId,
            'content' => $content,
            'senderId' => $this->currentUserId,
            'createdAt' => (new \DateTime())->format('Y-m-d H:i:s.u'),
        ];

        $result = $this->chatMapper->insertMess(new ChatMessages($messageData));
        if ($result['status'] === 'success') {
            $this->sendToWebSocket($messageData);
        }

        return $result;
    }

    public function getChatMessages(string $chatId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $results = $this->chatMapper->getChatMessages($chatId);
        $requestData = [
            'type' => 'getMessages',
            'chatId' => $chatId,
            'requesterId' => $this->currentUserId,
        ];

        $this->sendToWebSocket($requestData);

        return [
            'status' => 'success',
            'ResponseCode' => 11807,
            'affectedRows' => $results,
        ];
    }

    private function sendToWebSocket(array $data): void
    {
        $loop = EventLoopFactory::create();
        $connector = new Connector($loop);
        $connector('ws://127.0.0.1:8080')
            ->then(function ($connection) use ($data) {
                $connection->send(json_encode($data));
                $connection->close();
            }, function (\Throwable $e) {
                $this->logger->error("WebSocket connection error", ['exception' => $e->getMessage()]);
            });
        $loop->run();
    }
}
