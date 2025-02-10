<?php

namespace Fawaz\App;

use Fawaz\App\Chat;
use Fawaz\App\ChatParticipants;
use Fawaz\App\ChatMessages;
use Fawaz\Database\ChatMapper;
use Psr\Log\LoggerInterface;
use Ratchet\Client\Connector;
use React\EventLoop\Factory as EventLoopFactory;

class ChatService
{
    protected ?string $currentUserId = null;
    private FileUploader $fileUploader;

    public function __construct(protected LoggerInterface $logger, protected ChatMapper $chatMapper)
    {
        $this->fileUploader = new FileUploader($this->logger);
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
			return $this->respondWithError('Unauthorized');
		}

        if (empty($args)) {
            return $this->respondWithError('Invalid input');
        }

		$this->logger->info('ChatService.createChatWithRecipients started');

		$chatId = $this->generateUUID();
		$creatorId = $this->currentUserId;
		$name = $args['name'] ?? null;
		$image = $args['image'] ?? null;
		$recipients = $args['recipients'] ?? null;
		$maxUsers = 1;
		$public = 1;

		// Validate input parameters
		if (!is_array($recipients) || empty($recipients)) {
			return $this->respondWithError('Invalid input parameters');
		}

		if (count($recipients) < $maxUsers) {
			return $this->respondWithError('No participants found');
		}

		$friends = $this->getFriends();

		// Check if $friends is an array and has follow
		if (!is_array($friends) || empty($friends)) {
			return $this->respondWithError('No friends found or an error occurred in fetching friends');
		}

		$friendIds = array_column($friends, 'uid');

		foreach ($recipients as $recipientId) {
			if (!in_array($recipientId, $friendIds)) {
				return $this->respondWithError('RECIPIENT IS NOT A FRIEND');
			}
		}

		$recipientId = null;

		if (count($recipients) === $maxUsers) {
			$public = 0;
			$name = null;
			$image = null;

			$recipientId = $recipients[0];
			if ($this->chatMapper->getPrivateChatBetweenUsers($creatorId, $recipientId)) {
				return $this->respondWithError('already chat exists with this userId.');
			}
		}

		$recipientId = null;

		try {
			if ($image !== null && $image !== '' && count($recipients) > $maxUsers) {
				$mediaPaths = $this->fileUploader->handleFileUpload($image, 'image', $chatId, true);
				if ($mediaPaths !== null) {
					$image = $mediaPaths;
				}
			}
		} catch (\Exception $e) {
			$this->logger->error('Failed to upload media files', ['exception' => $e]);
			return $this->respondWithError('An error occurred while uploading the media files');
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
				'ResponseCode' => 'Chat and participants created successfully',
				'affectedRows' => ['chatid' => $chatId],
			];
		} catch (\Exception $e) {
			$this->logger->error('Failed to create chat and participants', ['args' => $args, 'exception' => $e]);
			return $this->respondWithError('Failed to create chat and participants');
		}
	}

    public function updateChat(array $args): array
    {
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
		}

        if (empty($args)) {
            return $this->respondWithError('Invalid input');
        }

        $this->logger->info('ChatService.updateChat started');

        // Validate required fields
        $requiredFields = ['chatid'];
        foreach ($requiredFields as $field) {
            if (!isset($args[$field]) || empty($args[$field])) {
                $this->logger->warning("$field is required", ['args' => $args]);
				return $this->respondWithError('$field is required.');
            }
        }

        $chatId = $args['chatid'] ?? null;
        $name = $args['name'] ?? null;
        $image = $args['image'] ?? null;

        // Validate args parameters
        if ($chatId === null) {
			return $this->respondWithError('Invalid input parameters');
        }

		if (empty($name) && empty($image)) {
			return $this->respondWithError('Could not find mandatory name & image');
		}

        if (!self::isValidUUID($chatId)) {
			return $this->respondWithError('Invalid uuid input');
        }

        if (!$this->chatMapper->isCreator($chatId, $this->currentUserId)) {
			return $this->respondWithError('Unauthorized: You can only update your own chats');
        }

		if ($this->chatMapper->isPrivate($chatId)) {
			return $this->respondWithError('Not Allowed: Only public chats can be updated');
		}

        try {
            if (!empty($image)) {
                $mediaPaths = $this->fileUploader->handleFileUpload($image, 'image', $chatId, true);
                if ($mediaPaths !== null) {
                    $image = $mediaPaths;
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to upload media files', ['exception' => $e]);
			return $this->respondWithError('An error occurred while uploading the media files');
        }

        try {
            $chat = $this->chatMapper->loadById($chatId);
            if (!$chat) {
				return $this->respondWithError('Invalid chatId');
            }

            $chat->update([
                'name' => $name,
                'image' => $image,
            ]);


            $this->chatMapper->update($chat);

            $this->logger->info('Chat updated successfully', ['chatid' => $chatId]);
            return [
                'status' => 'success',
				'ResponseCode' => 'Successfully updated chat',
                'affectedRows' => $chat->getArrayCopy()
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to update chat', ['args' => $args, 'exception' => $e]);
			return $this->respondWithError('Failed to update chat');
        }
    }

    public function deleteChat(string $id): array
    {
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
		}

        if (empty($id)) {
            return $this->respondWithError('Invalid input');
        }

        if (!self::isValidUUID($id)) {
			return $this->respondWithError('Invalid uuid input');
        }

        $this->logger->info('ChatService.deleteChat started');

        $chats = $this->chatMapper->loadById($id);
        if (!$chats) {
			return $this->respondWithError('Invalid chatId ' . $id);
        }

        $chat = $chats->getArrayCopy();

        if ($chat['creatorid'] !== $this->currentUserId && !$this->chatMapper->isCreator($id, $this->currentUserId)) {
			return $this->respondWithError('Unauthorized: You can only delete your own chats');
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
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete chat', ['id' => $id, 'error' => $e->getMessage()]);
			return $this->respondWithError('Failed to delete chatId');
        }

		return $this->respondWithError('Failed to delete chatId');
    }

    public function addParticipants(array $args): array
    {
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
		}

        if (empty($args)) {
            return $this->respondWithError('Invalid input');
        }

        $this->logger->info('ChatService.addParticipants started');

        $chatId = $args['chatid'] ?? null;
        $participants = $args['recipients'] ?? null;

        // Validate input parameters
        if ($chatId === null || !is_array($participants) || empty($participants)) {
			return $this->respondWithError('Invalid input parameters');
        }

        if (!self::isValidUUID($chatId)) {
			return $this->respondWithError('Invalid uuid input');
        }

        if (!$this->chatMapper->isCreator($chatId, $this->currentUserId)) {
			return $this->respondWithError('Unauthorized: You can only add to your own chats');
        }

		if ($this->chatMapper->isPrivate($chatId)) {
			return $this->respondWithError('Not Allowed: Only public chats can add participants');
		}

        $chat = $this->chatMapper->loadById($chatId);
        if (!$chat) {
			return $this->respondWithError('Invalid chatId');
        }

        try {
            foreach ($participants as $participantId) {

                if (!self::isValidUUID($participantId)) {
                    continue;
                }

                $participantData = [
                    'chatid' => $chatId,
                    'userid' => $participantId,
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
                'ResponseCode' => 'Participants added successfully',
                'affectedRows' => $participants,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to add participants', ['chatId' => $chatId, 'exception' => $e]);
			return $this->respondWithError('Failed to add participants');
        }
    }

    public function removeParticipants(array $args): array
    {
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
		}

        if (empty($args)) {
            return $this->respondWithError('Invalid input');
        }

        $this->logger->info('ChatService.removeParticipants started');

        $chatId = $args['chatid'] ?? null;
        $participants = $args['recipients'] ?? null;

        // Validate input parameters
        if ($chatId === null || !is_array($participants) || empty($participants)) {
			return $this->respondWithError('Invalid input parameters');
        }

        if (!self::isValidUUID($chatId)) {
			return $this->respondWithError('Invalid uuid input');
        }

        if (!$this->chatMapper->isCreator($chatId, $this->currentUserId)) {
			return $this->respondWithError('Unauthorized: You can only remove in your own chats');
        }

		if ($this->chatMapper->isPrivate($chatId)) {
			return $this->respondWithError('Not Allowed: Only public chats can remove participants');
		}

        $chat = $this->chatMapper->loadById($chatId);
        if (!$chat) {
			return $this->respondWithError('Invalid chatId');
        }

        try {
            foreach ($participants as $participantId) {

                if (!self::isValidUUID($participantId)) {
                    continue;
                }

                $this->chatMapper->deleteParticipant($chatId, $participantId);
            }

            $this->logger->info('Participants removed successfully', ['chatId' => $chatId]);
            return [
                'status' => 'success',
                'ResponseCode' => 'Participants removed successfully',
                'affectedRows' => $participants,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to remove participants', ['chatId' => $chatId, 'exception' => $e]);
			return $this->respondWithError('Failed to remove participants');
        }
    }

    public function readChatMessages(?array $args = []): array
    {
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
		}

        if (empty($args)) {
            return $this->respondWithError('Invalid input');
        }

        $this->logger->info('ChatService.readChatMessages started');

        $results = $this->chatMapper->getChatMessages($args);

        return [
            'status' => 'success',
			'ResponseCode' => 'Getting Messages successfully',
            'affectedRows' => $results,
        ];
    }

    public function addMessage(string $chatId, string $content): array
    {
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
		}

        if (empty($chatId) || empty($content)) {
			return $this->respondWithError('Could not find mandatory input');
        }

        if (!self::isValidUUID($chatId)) {
			return $this->respondWithError('Invalid uuid input');
        }

        $this->logger->info('ChatService.addMessage started');

        $chat = $this->chatMapper->loadById($chatId);

        if (!$chat) {
			return $this->respondWithError('Invalid chatId');
        }

        try {
            $messageData = [
                'messid' => 0,
                'chatid' => $chatId,
                'userid' => $this->currentUserId,
                'content' => $content,
                'createdat' => (new \DateTime())->format('Y-m-d H:i:s.u'),
            ];
            $message = new ChatMessages($messageData);
            $result = $this->chatMapper->insertMess($message);

            if ($result['status'] === 'error') {
                return $result;
            }

            $this->logger->info('Message added successfully', ['chatId' => $chatId, 'content' => $content]);
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to add message', ['chatId' => $chatId, 'exception' => $e]);
			return $this->respondWithError('Failed to add message');
        }
    }

    public function removeMessage(string $chatId, int $messageId): array
    {
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
		}

        if (empty($chatId) || empty($messageId)) {
			return $this->respondWithError('Could not find mandatory input');
        }

        if (!self::isValidUUID($chatId)) {
			return $this->respondWithError('Invalid uuid input');
        }

        $this->logger->info('ChatService.removeMessage started');

        $chat = $this->chatMapper->loadById($chatId);

        if (!$chat) {
			return $this->respondWithError('Invalid chatId');
        }

        $message = $this->chatMapper->loadMessageById($messageId);

        if ($message === false) {
			return $this->respondWithError('Invalid messageId');
        }

        if ($message['userid'] !== $this->currentUserId && !$this->chatMapper->isCreator($chatId, $this->currentUserId)) {
			return $this->respondWithError('Unauthorized: You can only remove your own messages or in your own chats.');
        }

        try {
            $this->chatMapper->deleteMessage($chatId, $messageId);

            $this->logger->info('Message removed successfully', ['chatId' => $chatId, 'messageId' => $messageId]);
            return [
                'status' => 'success',
				'ResponseCode' => 'Message removed successfully',
                'affectedRows' => $message,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to remove message', ['chatId' => $chatId, 'exception' => $e]);
			return $this->respondWithError('Failed to remove message');
        }
    }

    public function getFriends(): array|null
    {
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
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
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
		}

		$chatId = $args['chatid'] ?? null;

        if (!self::isValidUUID($chatId)) {
			return $this->respondWithError('MissingChatId');
        }

		$this->logger->info('ChatService.loadChatById started');

		$results = $this->chatMapper->loadChatById($args, $this->currentUserId);

		$this->logger->info("Response received from ChatMapper.loadChatById", [
			'ResponseCode' => $results['ResponseCode'],
		]);

		return $results; // Pass the structured response directly
	}

    public function findChatser(?array $args = []): array|false
    {
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
		}

        $this->logger->info('ChatService.findChatser started');

        $results = $this->chatMapper->findChatser($args, $this->currentUserId);
        $this->logger->info('ChatService.findChatser successfully', ['currentUserId' => $this->currentUserId]);

        return $results;
    }

    public function setChatMessages(string $chatId, string $content): array
    {
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
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
			return $this->respondWithError('Unauthorized');
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
			'ResponseCode' => 'Getting Messages successfully',
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
            }, function (\Exception $e) {
                $this->logger->error("WebSocket connection error", ['exception' => $e->getMessage()]);
            });
        $loop->run();
    }
}
