<?php

declare(strict_types=1);

namespace Fawaz\Services\Notifications;

use Fawaz\App\Models\UserDeviceToken;
use Fawaz\Services\Notifications\NotificationApiServices\AndroidApiService;
use Fawaz\App\Services\Notifications\NotificationApiServices\IosApiService;
use Fawaz\Database\Interfaces\NotificationsMapper;
use Fawaz\Services\Notifications\Helpers\UserNotificationContent;
use Fawaz\Services\Notifications\Interface\NotificationInitiator;
use Fawaz\Services\Notifications\Interface\NotificationPayload;
use Fawaz\Services\Notifications\Interface\NotificationReceiver;
use Fawaz\Services\Notifications\Interface\NotificationStrategy;
use PDO;
use Fawaz\Utils\PeerLoggerInterface;

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

        $allReceivers = UserDeviceToken::query()->where('userid', '34b9e1cd-6845-4456-bbf3-d9c8c5356dac')->first();

        // Check if iOS, Android or WEB
        $this->sendNotification($notificationStrategy, $notificationInititor, $allReceivers);

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

        // foreach($receivers as $key => $receiver){
            if($receivers['platform'] == 'ANDROID'){
                AndroidApiService::sendNotification($this->notificationPayload, new UserDeviceToken((array)$receivers));
            }else if($receivers['platform'] == 'IOS'){
                IosApiService::sendNotification($this->notificationPayload, new UserDeviceToken((array)$receivers));
            }
        // }

        return true;
    }
    
}
