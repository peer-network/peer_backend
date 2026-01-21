<?php

interface PayloadStructure
{
    /**
     * Structure should be compatible with iOS, Android and WEB notification services
     * 
     * @return void
     */
    public function payload(NotificationPayload $contentType): array;
}