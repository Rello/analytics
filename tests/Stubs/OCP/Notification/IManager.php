<?php
namespace OCP\Notification;

interface IManager {
    public function createNotification();
    public function markProcessed(INotification $notification);
    public function notify(INotification $notification);
}
