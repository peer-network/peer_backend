<?php

declare(strict_types=1);

namespace Fawaz\Services\Notifications;

use AndroidApiService;
use AndroidPayloadStructure;
use Fawaz\App\Models\UserDeviceToken;
use Fawaz\Database\Interfaces\NotificationsMapper;
use Fawaz\Services\Notifications\Helpers\UserNotificationContent;
use Fawaz\Services\Notifications\Interface\NotificationInitiator;
use Fawaz\Services\Notifications\Interface\NotificationPayload;
use Fawaz\Services\Notifications\Interface\NotificationReceiver;
use Fawaz\Services\Notifications\Interface\NotificationStrategy;
use PDO;
use Fawaz\Utils\PeerLoggerInterface;
use IosApiService;

class NotificationMapperImpl implements NotificationsMapper
{
    private NotificationPayload $notificationPayload;

    public function __construct(protected PeerLoggerInterface $logger, protected PDO $db)
    {
    }

    public function notify(NotificationStrategy $notificationStrategy, NotificationInitiator $notificationInititor,  NotificationReceiver $notificationReceivers): bool
    {
        $this->logger->debug("NotificationMapperImpl.notify started");

        // Prepare Content to be sent
        // $message = $this->prepareContent($notificationStrategy, $notificationInititor);

        $this->notificationPayload = $this->prepareContent($notificationStrategy, $notificationInititor);
        
        // Prepare receivers
        $receivers = $notificationReceivers->receiver();

        $allReceivers = UserDeviceToken::query()->whereIn('userid', array_values($receivers))->all();

        // Check if iOS, Android or WEB
        if(!empty($receivers) && is_array($receivers)){
            $this->sendNotification($notificationStrategy, $notificationInititor, $receivers);
        }

        // // Send Push in application

        return true;
    }

    // ProfileReplaceable

    private function prepareContent(NotificationStrategy $notificationStrategy, NotificationInitiator $notificationInititor): NotificationPayload
    {
       $userNotificationContent = new UserNotificationContent( $notificationStrategy, $notificationInititor);

       return $userNotificationContent;
    }


    /**
     * sendNotification
     */
    private function sendNotification(NotificationStrategy $notificationStrategy, NotificationInitiator $notificationInititor, array $receivers): bool
    {
        foreach($receivers as $key => $receiver){
            if($receiver->platform == 'android'){
                AndroidApiService::sendNotification($this->notificationPayload, new UserDeviceToken((array)$receiver));
            }else if($receiver->platform == 'ios'){
                IosApiService::sendNotification($this->notificationPayload, new UserDeviceToken((array)$receiver));
            }
        }

        return true;
    }
    
}
