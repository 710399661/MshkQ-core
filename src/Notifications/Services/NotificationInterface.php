<?php


namespace MshkQ\Notifications\Services;


use MshkQ\Notifications\Messages\SimpleMessage;

interface NotificationInterface
{
    public function setNotification(SimpleMessage $notification);
}
