<?php

declare(strict_types=1);

namespace Fawaz\Services\Notifications;

use Fawaz\App\Models\UserDeviceToken;
use Fawaz\Services\Notifications\NotificationApiServices\AndroidApiService;
use Fawaz\Services\Notifications\NotificationApiServices\IosApiService;
use Fawaz\Database\Interfaces\NotificationsMapper;
use Fawaz\Services\Notifications\Enums\NotificationAction;
use Fawaz\Services\Notifications\Helpers\NotificationContentStructure;
use Fawaz\Services\Notifications\Interface\NotificationInitiator;
use Fawaz\Services\Notifications\Interface\NotificationPayload;
use Fawaz\Services\Notifications\Interface\NotificationReceiver;
use Fawaz\Services\Notifications\Interface\NotificationStrategy;
use Fawaz\Services\Notifications\NotificationApiServices\AndroidNotificationSender;
use Fawaz\Services\Notifications\NotificationApiServices\IosNotificationSender;
use Fawaz\Services\Notifications\NotificationApiServices\NotificationSenderResolver;
use Fawaz\Services\Notifications\Strategies\NotificationStrategyRegistry;
use Fawaz\Utils\PeerLoggerInterface;

class NotificationMapperImpl implements NotificationsMapper
{
    public function __construct(protected PeerLoggerInterface $logger)
    {
    }

    private function notify(NotificationStrategy $notificationStrategy, NotificationInitiator $notificationInititor, NotificationReceiver $notificationReceivers): bool
    {
        $this->logger->debug("NotificationMapperImpl.notify started");


        // Prepare receivers
        $receivers = $notificationReceivers->receiver();

        $allReceivers = UserDeviceToken::query()
                                        // ->select('user_device_tokens.*', 'users.username')
                                        ->whereIn('userid', $receivers)
                                        ->join('users', 'users.uid', '=', 'user_device_tokens.userid', 'LEFT')
                                        ->orderBy('user_device_tokens.createdat', 'desc')
                                        ->all();

        $senderResolver = new NotificationSenderResolver(
            new AndroidNotificationSender(new AndroidApiService($this->logger)),
            new IosNotificationSender(new IosApiService($this->logger))
        );
        // Send Push in application
        $this->sendNotification($notificationStrategy, $notificationInititor, $allReceivers, $senderResolver);

        return true;
    }

    public function notifyByType(NotificationAction $action, array $payload, NotificationInitiator $notificationInititor, NotificationReceiver $notificationReceivers): bool
    {
        $registry = new NotificationStrategyRegistry();
        $notificationStrategy = $registry->create($action, $payload);

        return $this->notify($notificationStrategy, $notificationInititor, $notificationReceivers);
    }

    // ProfileReplaceable

    private function prepareContent(NotificationStrategy $notificationStrategy, NotificationInitiator $notificationInititor, UserDeviceToken $receiverObj): NotificationPayload
    {
        $notificationStrategy = $this->contentReplacer($notificationStrategy, $notificationInititor, $receiverObj);
        $notificationContent = new NotificationContentStructure($notificationStrategy, $notificationInititor);

        return $notificationContent;
    }


    /**
     * sendNotification
     */
    private function sendNotification(NotificationStrategy $notificationStrategy, NotificationInitiator $notificationInititor, array $receivers, NotificationSenderResolver $senderResolver): bool
    {
        foreach ($receivers as $key => $receiver) {
            $receiverObj = new UserDeviceToken($receiver);
            $notificationPayload = $this->prepareContent($notificationStrategy, $notificationInititor, $receiverObj);

            $sender = $senderResolver->resolve($receiverObj);
            try {
                $sender->send($notificationPayload, $receiverObj);
            } catch (\Exception $exception) {
                $this->logger->error('Failed to send notification', [
                    'error' => $exception->getMessage(),
                ]);
                throw $exception;
            }
        }

        return true;
    }

    private function contentReplacer(NotificationStrategy $notificationStrategy, NotificationInitiator $notificationInitiator, UserDeviceToken $receiverObj): NotificationStrategy
    {
        $bodyTemplate = $notificationStrategy->bodyContent(); // must exist in your class

        // replace initiator
        if (!empty($notificationInitiator) && str_contains($bodyTemplate, 'initiator.username')) {
            $initiator = $notificationInitiator->initiatorUserObj()->getName();

            if (empty($initiator)) {
                return $notificationStrategy;
            }
            $username = $initiator;

            // Replace placeholder with initiator.username
            $bodyContent = str_replace('{{initiator.username}}', $username, $bodyTemplate);

            $notificationStrategy->setBodyContent($bodyContent);

        }

        $receiverArray = $receiverObj->getArrayCopy();
        // replace receiver
        if (!empty($receiverArray) && str_contains($bodyTemplate, 'receiver.username')) {

            if (empty($receiverArray)) {
                return $notificationStrategy;
            }

            $username = $receiverArray['userObj']['username'] ?? '';

            // Replace placeholder with receiver.username
            $bodyContent = str_replace('{{receiver.username}}', $username, $bodyTemplate);

            $notificationStrategy->setBodyContent($bodyContent);
        }

        return $notificationStrategy;
    }

}
